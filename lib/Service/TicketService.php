<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Service;

use OCA\ConsultasLegales\Db\Comment;
use OCA\ConsultasLegales\Db\CommentMapper;
use OCA\ConsultasLegales\Db\HistoryEntry;
use OCA\ConsultasLegales\Db\HistoryEntryMapper;
use OCA\ConsultasLegales\Db\Ticket;
use OCA\ConsultasLegales\Db\TicketData;
use OCA\ConsultasLegales\Db\TicketDataMapper;
use OCA\ConsultasLegales\Db\TicketMapper;
use OCA\ConsultasLegales\Db\Urgency;
use OCA\ConsultasLegales\Db\UrgencyMapper;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;

class TicketService {
	public function __construct(
		private readonly IDBConnection $db,
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
		private readonly ProvinceCatalogService $provinceCatalogService,
		private readonly CatalogService $catalogService,
		private readonly TicketMapper $ticketMapper,
		private readonly CommentMapper $commentMapper,
		private readonly AttachmentService $attachmentService,
		private readonly HistoryEntryMapper $historyMapper,
		private readonly TicketDataMapper $ticketDataMapper,
		private readonly UrgencyMapper $urgencyMapper,
		private readonly TicketNumberService $ticketNumberService,
		private readonly AssignmentService $assignmentService,
		private readonly PersonalConfigService $personalConfigService,
		private readonly PermissionService $permissionService,
		private readonly NotificationService $notificationService,
		private readonly TaskSyncService $taskSyncService,
		private readonly RichTextSanitizer $richTextSanitizer,
		private readonly RoleService $roleService,
	) {
	}

	public function list(string $uid, array $criteria = [], bool $supportScope = false): array {
		$tickets = [];
		foreach ($this->ticketMapper->findAllOrdered('updated_at', 'DESC') as $row) {
			if ($supportScope) {
				if (!$this->permissionService->canReadTicket($uid, $row)) {
					continue;
				}
			} elseif ($row->getCreatorUid() !== $uid) {
				continue;
			}

			$tickets[] = $this->serializeTicket($uid, $row);
		}

		return array_values(array_filter($tickets, fn (array $ticket) => $this->matchesCriteria($uid, $ticket, $criteria)));
	}

	public function show(string $uid, int $id): array {
		$ticket = $this->ticketMapper->find($id);
		$this->permissionService->assertCanReadTicket($uid, $ticket);
		return $this->serializeTicket($uid, $ticket, true);
	}

	public function create(string $uid, array $payload): array {
		$now = time();
		$roles = $this->roleService->getEffectiveRoles($uid);
		$isSupportActor = in_array(RoleService::SUPPORT, $roles, true) || in_array(RoleService::ADMIN, $roles, true);
		$province = $this->resolveProvinceSelection($payload, !$isSupportActor);
		$manualAssignment = [
			'assignedUserUid' => $this->normalizeOptionalString($payload['assignedUserUid'] ?? null),
			'assignedGroupId' => $this->normalizeOptionalString($payload['assignedGroupId'] ?? null),
		];
		$hasManualAssignment = $manualAssignment['assignedUserUid'] !== null || $manualAssignment['assignedGroupId'] !== null;
		$assignment = ($isSupportActor && $hasManualAssignment)
			? $manualAssignment
			: $this->assignmentService->resolveForType(isset($payload['typeId']) ? (int) $payload['typeId'] : null, $province);
		if ($isSupportActor && $hasManualAssignment) {
			$this->assertAssignmentPayloadAllowed($uid, ['assignedGroupId' => $assignment['assignedGroupId']], null);
		}
		$this->assertAssignmentConsistency($assignment['assignedUserUid'], $assignment['assignedGroupId']);
		$initialStatus = $this->resolveAssignmentAwareStatus('nuevo', $assignment['assignedUserUid'], $assignment['assignedGroupId']);

		$ticket = new Ticket();
		$ticket->setNumber($this->ticketNumberService->nextNumber($now));
		$ticket->setCreatorUid($uid);
		$ticket->setCreatedAt($now);
		$ticket->setUpdatedAt($now);
		$ticket->setStatusUpdatedAt($now);
		$ticket->setStatus($initialStatus);
		$ticket->setUrgencyId(isset($payload['urgencyId']) ? (int) $payload['urgencyId'] : $this->resolveDefaultUrgencyId());
		$ticket->setTypeId(isset($payload['typeId']) ? (int) $payload['typeId'] : null);
		$ticket->setTitle((string) ($payload['title'] ?? ''));
		$ticket->setUserDescription($this->richTextSanitizer->sanitize((string) ($payload['userDescription'] ?? '')));
		$ticket->setSupportDescription('');
		$ticket->setAssignedUserUid($assignment['assignedUserUid']);
		$ticket->setAssignedGroupId($assignment['assignedGroupId']);
		$ticket->setProvince($province);
		$ticket->setCity((string) (($payload['personalData']['city'] ?? '') ?: ''));
		$ticket->setMetadata(['communicationChannel' => $payload['communicationChannel'] ?? 'nextcloud_mail']);

		$ticket = $this->ticketMapper->insert($ticket);
		$this->savePersonalData($ticket->getId(), $payload['personalData'] ?? []);
		$this->rememberProvinceForUser($uid, $province);
		$this->addHistory($ticket->getId(), $uid, $isSupportActor ? RoleService::SUPPORT : RoleService::USER, 'ticket_created', 'publico', ['status' => $initialStatus]);
		$this->taskSyncService->syncTicket($ticket);
		$this->notificationService->emit('ticket_created', $ticket);

		return $this->serializeTicket($uid, $ticket, true);
	}

	public function update(string $uid, int $id, array $payload): array {
		$ticket = $this->ticketMapper->find($id);
		$this->permissionService->assertCanManageTicket($uid, $ticket);
		if ($this->catalogService->isClosedStatus((string) $ticket->getStatus())) {
			throw new \RuntimeException('El ticket está cerrado y debe reabrirse antes de modificarlo.', 409);
		}

		$previousStatus = (string) $ticket->getStatus();
		$previousTicketStatusWasClosed = $this->catalogService->isClosedStatus($previousStatus);
		$previousAssignedUserUid = $ticket->getAssignedUserUid();
		$previousAssignedGroupId = $ticket->getAssignedGroupId();
		$closeReason = $this->normalizeClosureReason($payload['closeReason'] ?? null);
		$this->assertAssignmentPayloadAllowed($uid, $payload, $previousAssignedGroupId);
		$statusChanged = false;
		$assignmentChanged = false;
		foreach (['title', 'status', 'urgencyId', 'assignedUserUid', 'assignedGroupId', 'supportDescription'] as $field) {
			if (!array_key_exists($field, $payload)) {
				continue;
			}

			$value = $payload[$field];
			if ($field === 'title') {
				$value = trim((string) $value);
				if ($value === '') {
					throw new \InvalidArgumentException('El título del ticket no puede estar vacío.');
				}
				$payload[$field] = $value;
			}
			if ($field === 'supportDescription') {
				$value = $this->richTextSanitizer->sanitize(is_string($value) ? $value : '');
				$payload[$field] = $value;
			}
			$getter = 'get' . ucfirst($field);
			$currentValue = method_exists($ticket, $getter) ? $ticket->{$getter}() : null;
			$setter = 'set' . ucfirst($field);
			$ticket->{$setter}($value);
			if ($field === 'status' && $currentValue !== $value) {
				$ticket->setStatusUpdatedAt(time());
				$statusChanged = true;
			}

			if (in_array($field, ['assignedUserUid', 'assignedGroupId'], true) && $currentValue !== $value) {
				$assignmentChanged = true;
			}
		}

		$this->assertAssignmentConsistency($ticket->getAssignedUserUid(), $ticket->getAssignedGroupId());
		$normalizedStatus = $this->resolveAssignmentAwareStatus((string) $ticket->getStatus(), $ticket->getAssignedUserUid(), $ticket->getAssignedGroupId());
		if ($normalizedStatus !== (string) $ticket->getStatus()) {
			$ticket->setStatus($normalizedStatus);
			$ticket->setStatusUpdatedAt(time());
			$statusChanged = true;
			$payload['status'] = $normalizedStatus;
		}

		if ($statusChanged && $this->catalogService->isClosedStatus((string) $ticket->getStatus()) && !$previousTicketStatusWasClosed && $closeReason === '') {
			throw new \InvalidArgumentException('Debes indicar el motivo del cierre.');
		}

		$ticket->setUpdatedAt(time());
		$ticket = $this->ticketMapper->update($ticket);
		$this->addHistory($ticket->getId(), $uid, RoleService::SUPPORT, 'ticket_updated', 'interno', $payload);
		if ($statusChanged && $this->catalogService->isClosedStatus((string) $ticket->getStatus()) && !$previousTicketStatusWasClosed && $closeReason !== '') {
			$this->addClosureReasonComment($ticket, $uid, $closeReason);
		}
		$this->taskSyncService->syncTicket($ticket);

		$eventName = 'ticket_updated';
		if ($assignmentChanged) {
			$eventName = 'ticket_assigned';
		} elseif ($statusChanged) {
			$eventName = in_array((string) $ticket->getStatus(), ['resuelto', 'cerrado'], true)
				? 'ticket_resolved'
				: 'ticket_status_changed';
		}

		$this->notificationService->emit($eventName, $ticket, [], [
			'previousStatus' => $previousStatus,
			'previousAssignedUserUid' => $previousAssignedUserUid,
			'previousAssignedGroupId' => $previousAssignedGroupId,
		]);

		return $this->serializeTicket($uid, $ticket, true);
	}

	private function addClosureReasonComment(Ticket $ticket, string $uid, string $closeReason): void {
		$comment = new Comment();
		$comment->setTicketId((int) $ticket->getId());
		$comment->setAuthorUid($uid);
		$comment->setAuthorRole(RoleService::SUPPORT);
		$comment->setBody($this->plainTextToRichText($closeReason));
		$comment->setVisibility('publico');
		$comment->setCreatedAt(time());

		$comment = $this->commentMapper->insert($comment);
		$this->addHistory((int) $ticket->getId(), $uid, RoleService::SUPPORT, 'comment_added', 'publico', ['commentId' => $comment->getId(), 'isClosureReason' => true]);
	}

	public function addComment(string $uid, int $ticketId, array $payload): array {
		$ticket = $this->ticketMapper->find($ticketId);
		if ($this->catalogService->isClosedStatus((string) $ticket->getStatus())) {
			throw new \RuntimeException('El ticket está cerrado y no admite comentarios hasta reabrirse.', 409);
		}

		$roles = $this->roleService->getEffectiveRoles($uid);
		$isSupport = in_array(RoleService::SUPPORT, $roles, true) || in_array(RoleService::ADMIN, $roles, true);
		if ($isSupport) {
			$this->permissionService->assertCanManageTicket($uid, $ticket);
		} else {
			$this->permissionService->assertCanReadTicket($uid, $ticket);
		}

		$visibility = $isSupport ? (string) ($payload['visibility'] ?? 'interno') : 'publico';
		$body = $this->richTextSanitizer->sanitize((string) ($payload['body'] ?? ''));
		$allowEmpty = filter_var($payload['allowEmpty'] ?? false, FILTER_VALIDATE_BOOLEAN);
		if (!$this->richTextSanitizer->isMeaningful($body) && !$allowEmpty) {
			throw new \InvalidArgumentException('El comentario no puede estar vacío.');
		}

		$comment = new Comment();
		$comment->setTicketId($ticketId);
		$comment->setAuthorUid($uid);
		$comment->setAuthorRole($isSupport ? RoleService::SUPPORT : RoleService::USER);
		$comment->setBody($body);
		$comment->setVisibility($visibility);
		$comment->setCreatedAt(time());

		$comment = $this->commentMapper->insert($comment);

		$now = time();
		$historyPayload = ['commentId' => $comment->getId()];
		$ticket->setUpdatedAt($now);
		if ($ticket->getStatus() === 'en_espera_usuario') {
			$ticket->setStatus('asignado');
			$ticket->setStatusUpdatedAt($now);
			$historyPayload['previousStatus'] = 'en_espera_usuario';
			$historyPayload['nextStatus'] = 'asignado';
		}

		$ticket = $this->ticketMapper->update($ticket);
		$this->taskSyncService->syncTicket($ticket);
		$this->addHistory($ticketId, $uid, $comment->getAuthorRole(), 'comment_added', $visibility, $historyPayload);
		$this->notificationService->emit('ticket_public_reply', $ticket);

		return $comment->jsonSerialize();
	}

	public function reopen(string $uid, int $id): array {
		$ticket = $this->ticketMapper->find($id);
		if (!$this->canReopenTicket($uid, $ticket)) {
			throw new \RuntimeException('Forbidden', 403);
		}

		if (!$this->catalogService->isClosedStatus((string) $ticket->getStatus())) {
			return $this->serializeTicket($uid, $ticket, true);
		}

		$previousStatus = (string) $ticket->getStatus();
		$ticket->setStatus($this->resolveAssignmentAwareStatus('nuevo', $ticket->getAssignedUserUid(), $ticket->getAssignedGroupId()));
		$ticket->setStatusUpdatedAt(time());
		$ticket->setUpdatedAt(time());
		$ticket = $this->ticketMapper->update($ticket);
		$this->addHistory($ticket->getId(), $uid, $this->permissionService->canManageTicket($uid, $ticket) ? RoleService::SUPPORT : RoleService::USER, 'ticket_reopened', 'publico', [
			'previousStatus' => $previousStatus,
			'nextStatus' => (string) $ticket->getStatus(),
		]);
		$this->taskSyncService->syncTicket($ticket);
		$this->notificationService->emit('ticket_status_changed', $ticket, [], ['previousStatus' => $previousStatus]);

		return $this->serializeTicket($uid, $ticket, true);
	}

	public function addAttachment(string $uid, int $ticketId, array $uploadedFile, int $commentId, ?string $sourceUrl = null, ?string $originalName = null): array {
		$ticket = $this->ticketMapper->find($ticketId);
		$this->permissionService->assertCanReadTicket($uid, $ticket);

		$comment = $this->commentMapper->find($commentId);
		if ((int) $comment->getTicketId() !== $ticketId) {
			throw new \InvalidArgumentException('El comentario indicado no pertenece al ticket.');
		}

		$roles = $this->roleService->getEffectiveRoles($uid);
		$actorRole = in_array(RoleService::SUPPORT, $roles, true) || in_array(RoleService::ADMIN, $roles, true)
			? RoleService::SUPPORT
			: RoleService::USER;

		$attachment = ($sourceUrl !== null && trim($sourceUrl) !== '')
			? $this->attachmentService->createFromUrl($ticketId, $uid, $sourceUrl, $originalName, $commentId)
			: $this->attachmentService->create($ticketId, $uid, $uploadedFile, $commentId);
		$this->addHistory($ticketId, $uid, $actorRole, 'attachment_added', (string) $comment->getVisibility(), ['attachmentId' => $attachment['id'], 'commentId' => $commentId]);
		return $attachment;
	}

	private function savePersonalData(int $ticketId, array $rows): void {
		foreach ($rows as $fieldKey => $fieldValue) {
			$item = new TicketData();
			$item->setTicketId($ticketId);
			$item->setFieldKey((string) $fieldKey);
			$item->setFieldLabel(ucfirst((string) $fieldKey));
			$item->setFieldValue((string) $fieldValue);
			$this->ticketDataMapper->insert($item);
		}
	}

	private function addHistory(int $ticketId, string $uid, string $role, string $eventType, string $visibility, array $payload): void {
		$entry = new HistoryEntry();
		$entry->setTicketId($ticketId);
		$entry->setActorUid($uid);
		$entry->setActorRole($role);
		$entry->setEventType($eventType);
		$entry->setVisibility($visibility);
		$entry->setPayload($payload);
		$entry->setCreatedAt(time());
		$this->historyMapper->insert($entry);
	}

	private function resolveDefaultUrgencyId(): ?int {
		foreach ($this->urgencyMapper->findAllOrdered('weight', 'ASC') as $urgency) {
			if ($urgency instanceof Urgency && $urgency->getActive()) {
				return (int) $urgency->getId();
			}
		}

		return null;
	}

	private function normalizeClosureReason(mixed $value): string {
		return trim(is_string($value) ? $value : '');
	}

	private function plainTextToRichText(string $value): string {
		$normalized = preg_replace("/\r\n?|\n/", "\n", trim($value)) ?? trim($value);
		$lines = array_values(array_filter(array_map(static fn (string $line): string => trim($line), explode("\n", $normalized)), static fn (string $line): bool => $line !== ''));
		if ($lines === []) {
			return '<p></p>';
		}

		return implode('', array_map(static fn (string $line): string => '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>', $lines));
	}

	private function resolveProvinceSelection(array $payload, bool $required = true): ?string {
		$rawProvince = is_string($payload['province'] ?? null) ? trim((string) $payload['province']) : '';

		if ($rawProvince !== '') {
			$province = $this->provinceCatalogService->normalize($rawProvince);
			return $province ?? $rawProvince;
		}

		if ($required) {
			throw new \InvalidArgumentException('Debes seleccionar una provincia o Añadir una nueva.');
		}

		return null;
	}

	private function rememberProvinceForUser(string $uid, ?string $province): void {
		if ($uid === '' || $province === null || trim($province) === '') {
			return;
		}

		$current = $this->personalConfigService->getForUser($uid);
		$current['province'] = trim($province);
		$this->personalConfigService->saveForUser($uid, $current);
	}

	private function serializeTicket(string $uid, Ticket $ticket, bool $includeDetail = false): array {
		$data = $ticket->jsonSerialize();
		$isClosedStatus = $this->catalogService->isClosedStatus((string) $ticket->getStatus());
		$comments = $this->commentMapper->findBy('ticket_id', $ticket->getId(), 'created_at', 'ASC');
		$data['canRead'] = $this->permissionService->canReadTicket($uid, $ticket);
		$data['canManage'] = $this->permissionService->canManageTicket($uid, $ticket);
		$data['canComment'] = !$isClosedStatus && $this->permissionService->canCommentOnTicket($uid, $ticket);
		$data['canReopen'] = $isClosedStatus && $this->canReopenTicket($uid, $ticket);
		$data['publicCommentSearchText'] = $this->buildCommentSearchText($uid, $ticket, $comments);
		$attachments = $includeDetail ? $this->attachmentService->listForTicket($ticket->getId()) : [];
		$attachmentsByCommentId = [];
		foreach ($attachments as $attachment) {
			$commentId = isset($attachment['commentId']) ? (int) $attachment['commentId'] : 0;
			if ($commentId <= 0) {
				continue;
			}

			$attachmentsByCommentId[$commentId] ??= [];
			$attachmentsByCommentId[$commentId][] = $attachment;
		}

		$data['attachments'] = $attachments;
		$data['comments'] = $includeDetail ? array_values(array_filter(array_map(function ($row) use ($attachmentsByCommentId) {
			$comment = $row->jsonSerialize();
			$comment['attachments'] = $attachmentsByCommentId[(int) ($comment['id'] ?? 0)] ?? [];
			return $comment;
		}, $comments), fn (array $comment) => $this->permissionService->canSeeComment($uid, $ticket, (string) $comment['visibility']))) : [];
		$data['history'] = $includeDetail ? array_values(array_filter(array_map(fn ($row) => $row->jsonSerialize(), $this->historyMapper->findBy('ticket_id', $ticket->getId(), 'created_at', 'ASC')), fn (array $entry) => $entry['visibility'] === 'publico' || $this->permissionService->canManageTicket($uid, $ticket))) : [];
		$data['personalData'] = $includeDetail ? array_map(fn ($row) => $row->jsonSerialize(), $this->ticketDataMapper->findBy('ticket_id', $ticket->getId(), 'field_key', 'ASC')) : [];
		$data['taskSync'] = $includeDetail ? $this->taskSyncService->getSyncForTicket($ticket->getId()) : null;
		return $data;
	}

	/**
	 * @param Comment[] $comments
	 */
	private function buildCommentSearchText(string $uid, Ticket $ticket, array $comments): string {
		$parts = [];
		foreach ($comments as $comment) {
			if (!$this->permissionService->canSeeComment($uid, $ticket, $comment->getVisibility())) {
				continue;
			}

			$parts[] = $this->richTextSanitizer->toPlainText((string) $comment->getBody());
		}

		return trim(implode(' ', array_filter($parts, static fn (string $value): bool => $value !== '')));
	}

	private function canReopenTicket(string $uid, Ticket $ticket): bool {
		if ($ticket->getCreatorUid() === $uid) {
			return true;
		}

		return $this->permissionService->canManageTicket($uid, $ticket);
	}

	private function matchesCriteria(string $uid, array $ticket, array $criteria): bool {
		$userGroupIds = $this->loadUserGroupIds($uid);
		$negatedCriteria = [];
		if (isset($criteria['negatedCriteria']) && is_array($criteria['negatedCriteria'])) {
			$negatedCriteria = array_fill_keys(array_map('strval', $criteria['negatedCriteria']), true);
		}

		foreach ($criteria as $key => $value) {
			if ($key === 'negatedCriteria') {
				continue;
			}

			if ($value === null || $value === '' || $value === []) {
				continue;
			}

			$matches = true;

			switch ($key) {
				case 'status':
					$statusValues = is_array($value) ? $value : array_map('trim', explode(',', (string) $value))
					;
					$matches = in_array($ticket['status'], $statusValues, true);
					break;
				case 'createdBy':
					$matches = (($ticket['creatorUid'] ?? null) === $value);
					break;
				case 'assignedUser':
					if ($value === '__me__') {
						$matches = (($ticket['assignedUserUid'] ?? null) === $uid);
						break;
					}
					$matches = (($ticket['assignedUserUid'] ?? null) === $value);
					break;
				case 'assignedGroup':
					if ($value === '__my_groups__') {
						$matches = in_array((string) ($ticket['assignedGroupId'] ?? ''), $userGroupIds, true);
						break;
					}
					$matches = (($ticket['assignedGroupId'] ?? null) === $value);
					break;
				case 'unassigned':
					$matches = !($value && (($ticket['assignedUserUid'] ?? null) !== null || ($ticket['assignedGroupId'] ?? null) !== null));
					break;
				case 'city':
					$matches = stripos((string) ($ticket['city'] ?? ''), (string) $value) !== false;
					break;
				case 'province':
					$matches = stripos((string) ($ticket['province'] ?? ''), (string) $value) !== false;
					break;
				case 'typeId':
					$matches = (int) ($ticket['typeId'] ?? 0) === (int) $value;
					break;
				case 'updatedWithinDays':
					$threshold = time() - ((int) $value * 86400);
					$matches = (int) ($ticket['updatedAt'] ?? 0) >= $threshold;
					break;
				case 'createdAtFrom':
					$from = $this->parseDateBoundary((string) $value, false);
					$matches = $from === null || (int) ($ticket['createdAt'] ?? 0) >= $from;
					break;
				case 'createdAtTo':
					$to = $this->parseDateBoundary((string) $value, true);
					$matches = $to === null || (int) ($ticket['createdAt'] ?? 0) <= $to;
					break;
				case 'updatedAtFrom':
					$from = $this->parseDateBoundary((string) $value, false);
					$matches = $from === null || (int) ($ticket['updatedAt'] ?? 0) >= $from;
					break;
				case 'updatedAtTo':
					$to = $this->parseDateBoundary((string) $value, true);
					$matches = $to === null || (int) ($ticket['updatedAt'] ?? 0) <= $to;
					break;
				case 'text':
					$needle = strtolower(trim((string) $value));
					$commentText = implode(' ', array_map(fn ($comment) => $this->richTextSanitizer->toPlainText((string) ($comment->getBody() ?? '')), $this->commentMapper->findBy('ticket_id', (int) ($ticket['id'] ?? 0), 'created_at', 'ASC')));
					$haystack = strtolower(implode(' ', array_filter([
						(string) ($ticket['number'] ?? ''),
						(string) ($ticket['title'] ?? ''),
						$this->richTextSanitizer->toPlainText((string) ($ticket['userDescription'] ?? '')),
						$this->richTextSanitizer->toPlainText((string) ($ticket['supportDescription'] ?? '')),
						(string) ($ticket['status'] ?? ''),
						(string) ($ticket['creatorUid'] ?? ''),
						(string) ($ticket['assignedUserUid'] ?? ''),
						(string) ($ticket['assignedGroupId'] ?? ''),
						(string) ($ticket['province'] ?? ''),
						(string) ($ticket['city'] ?? ''),
						$commentText,
					])));
					$matches = str_contains($haystack, $needle);
					break;
				case 'hasAttachments':
					$matches = !$value || $this->attachmentService->hasForTicket((int) ($ticket['id'] ?? 0));
					break;
			}

			if (isset($negatedCriteria[(string) $key])) {
				$matches = !$matches;
			}

			if (!$matches) {
				return false;
			}
		}

		return true;
	}

	private function loadUserGroupIds(string $uid): array {
		if ($uid === '') {
			return [];
		}

		$user = $this->userManager->get($uid);
		if ($user === null) {
			return [];
		}

		return array_map(static fn ($group) => $group->getGID(), $this->groupManager->getUserGroups($user));
	}

	private function resolveAssignmentAwareStatus(string $currentStatus, ?string $assignedUserUid, ?string $assignedGroupId): string {
		$hasAssignment = ($assignedUserUid !== null && $assignedUserUid !== '') || ($assignedGroupId !== null && $assignedGroupId !== '');
		if ($hasAssignment && $currentStatus === 'nuevo') {
			return 'asignado';
		}

		if (!$hasAssignment && $currentStatus === 'asignado') {
			return 'nuevo';
		}

		return $currentStatus;
	}

	private function parseDateBoundary(string $value, bool $endOfDay): ?int {
		$normalized = trim($value);
		if ($normalized === '') {
			return null;
		}

		$timestamp = strtotime($normalized . ($endOfDay ? ' 23:59:59' : ' 00:00:00'));
		if ($timestamp === false) {
			return null;
		}

		return $timestamp;
	}

	private function normalizeOptionalString(mixed $value): ?string {
		if (!is_string($value)) {
			return null;
		}

		$value = trim($value);
		return $value === '' ? null : $value;
	}

	private function assertAssignmentPayloadAllowed(string $uid, array $payload, ?string $previousAssignedGroupId): void {
		if (!array_key_exists('assignedGroupId', $payload)) {
			return;
		}

		$nextAssignedGroupId = $payload['assignedGroupId'];
		$normalizedNextGroupId = is_string($nextAssignedGroupId) ? trim($nextAssignedGroupId) : null;
		$normalizedNextGroupId = $normalizedNextGroupId === '' ? null : $normalizedNextGroupId;
		$normalizedPreviousGroupId = is_string($previousAssignedGroupId) ? trim($previousAssignedGroupId) : null;
		$normalizedPreviousGroupId = $normalizedPreviousGroupId === '' ? null : $normalizedPreviousGroupId;

		if ($normalizedNextGroupId === $normalizedPreviousGroupId) {
			return;
		}

		if (!$this->permissionService->canAssignGroup($uid, $normalizedNextGroupId)) {
			throw new \RuntimeException('Forbidden', 403);
		}
	}

	private function assertAssignmentConsistency(?string $assignedUserUid, ?string $assignedGroupId): void {
		$normalizedAssignedUserUid = $this->normalizeOptionalString($assignedUserUid);
		$normalizedAssignedGroupId = $this->normalizeOptionalString($assignedGroupId);

		$user = $normalizedAssignedUserUid !== null ? $this->userManager->get($normalizedAssignedUserUid) : null;
		$group = $normalizedAssignedGroupId !== null ? $this->groupManager->get($normalizedAssignedGroupId) : null;

		if ($normalizedAssignedUserUid !== null && $user === null) {
			throw new \InvalidArgumentException('El usuario asignado no es valido.');
		}

		if ($normalizedAssignedGroupId !== null && $group === null) {
			throw new \InvalidArgumentException('El grupo asignado no es valido.');
		}

		if ($user === null || $group === null) {
			return;
		}

		if (!$group->inGroup($user)) {
			throw new \InvalidArgumentException('El usuario asignado no pertenece al grupo indicado.');
		}
	}
}