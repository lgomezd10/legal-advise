<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Service;

use OCA\Gestion_incidencias\Db\Comment;
use OCA\Gestion_incidencias\Db\CommentMapper;
use OCA\Gestion_incidencias\Db\HistoryEntry;
use OCA\Gestion_incidencias\Db\HistoryEntryMapper;
use OCA\Gestion_incidencias\Db\Ticket;
use OCA\Gestion_incidencias\Db\TicketData;
use OCA\Gestion_incidencias\Db\TicketDataMapper;
use OCA\Gestion_incidencias\Db\TicketMapper;
use OCA\Gestion_incidencias\Db\Urgency;
use OCA\Gestion_incidencias\Db\UrgencyMapper;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUserManager;

class TicketService {
	public function __construct(
		private readonly IDBConnection $db,
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
		private readonly ProvinceCatalogService $provinceCatalogService,
		private readonly TicketMapper $ticketMapper,
		private readonly CommentMapper $commentMapper,
		private readonly AttachmentService $attachmentService,
		private readonly HistoryEntryMapper $historyMapper,
		private readonly TicketDataMapper $ticketDataMapper,
		private readonly UrgencyMapper $urgencyMapper,
		private readonly TicketNumberService $ticketNumberService,
		private readonly AssignmentService $assignmentService,
		private readonly PermissionService $permissionService,
		private readonly NotificationService $notificationService,
		private readonly TaskSyncService $taskSyncService,
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
		$province = $this->resolveProvinceSelection($payload);
		$assignment = $this->assignmentService->resolveForType(isset($payload['typeId']) ? (int) $payload['typeId'] : null, $province);

		$ticket = new Ticket();
		$ticket->setNumber($this->ticketNumberService->nextNumber($now));
		$ticket->setCreatorUid($uid);
		$ticket->setCreatedAt($now);
		$ticket->setUpdatedAt($now);
		$ticket->setStatusUpdatedAt($now);
		$ticket->setStatus('nuevo');
		$ticket->setUrgencyId(isset($payload['urgencyId']) ? (int) $payload['urgencyId'] : $this->resolveDefaultUrgencyId());
		$ticket->setTypeId(isset($payload['typeId']) ? (int) $payload['typeId'] : null);
		$ticket->setTitle((string) ($payload['title'] ?? ''));
		$ticket->setUserDescription((string) ($payload['userDescription'] ?? ''));
		$ticket->setSupportDescription('');
		$ticket->setAssignedUserUid($assignment['assignedUserUid']);
		$ticket->setAssignedGroupId($assignment['assignedGroupId']);
		$ticket->setProvince($province);
		$ticket->setCity((string) (($payload['personalData']['city'] ?? '') ?: ''));
		$ticket->setMetadata(['communicationChannel' => $payload['communicationChannel'] ?? 'nextcloud_mail']);

		$ticket = $this->ticketMapper->insert($ticket);
		$this->savePersonalData($ticket->getId(), $payload['personalData'] ?? []);
		$this->addHistory($ticket->getId(), $uid, RoleService::USER, 'ticket_created', 'publico', ['status' => 'nuevo']);
		$this->taskSyncService->syncTicket($ticket);
		$this->notificationService->emit('ticket_created', $ticket);

		return $this->serializeTicket($uid, $ticket, true);
	}

	public function update(string $uid, int $id, array $payload): array {
		$ticket = $this->ticketMapper->find($id);
		$this->permissionService->assertCanManageTicket($uid, $ticket);

		$statusChanged = false;
		foreach (['status', 'urgencyId', 'assignedUserUid', 'assignedGroupId', 'supportDescription'] as $field) {
			if (!array_key_exists($field, $payload)) {
				continue;
			}

			$value = $payload[$field];
			$setter = 'set' . ucfirst($field);
			$ticket->{$setter}($value);
			if ($field === 'status') {
				$ticket->setStatusUpdatedAt(time());
				$statusChanged = true;
			}
		}

		$ticket->setUpdatedAt(time());
		$ticket = $this->ticketMapper->update($ticket);
		$this->addHistory($ticket->getId(), $uid, RoleService::SUPPORT, 'ticket_updated', 'interno', $payload);
		$this->taskSyncService->syncTicket($ticket);
		$this->notificationService->emit($statusChanged ? 'ticket_status_changed' : 'ticket_updated', $ticket);

		return $this->serializeTicket($uid, $ticket, true);
	}

	public function addComment(string $uid, int $ticketId, array $payload): array {
		$ticket = $this->ticketMapper->find($ticketId);
		$this->permissionService->assertCanReadTicket($uid, $ticket);

		$roles = $this->roleService->getEffectiveRoles($uid);
		$isSupport = in_array(RoleService::SUPPORT, $roles, true) || in_array(RoleService::ADMIN, $roles, true);
		$visibility = $isSupport ? (string) ($payload['visibility'] ?? 'interno') : 'publico';
		$body = trim((string) ($payload['body'] ?? ''));
		if ($body === '') {
			throw new \InvalidArgumentException('El comentario no puede estar vacio.');
		}

		$comment = new Comment();
		$comment->setTicketId($ticketId);
		$comment->setAuthorUid($uid);
		$comment->setAuthorRole($isSupport ? RoleService::SUPPORT : RoleService::USER);
		$comment->setBody($body);
		$comment->setVisibility($visibility);
		$comment->setCreatedAt(time());

		$comment = $this->commentMapper->insert($comment);
		$this->addHistory($ticketId, $uid, $comment->getAuthorRole(), 'comment_added', $visibility, ['commentId' => $comment->getId()]);
		$this->notificationService->emit('ticket_public_reply', $ticket);

		return $comment->jsonSerialize();
	}

	public function addAttachment(string $uid, int $ticketId, array $uploadedFile, int $commentId): array {
		$ticket = $this->ticketMapper->find($ticketId);
		$this->permissionService->assertCanReadTicket($uid, $ticket);

		$comment = $this->commentMapper->find($commentId);
		if ((int) $comment->getTicketId() !== $ticketId) {
			throw new \InvalidArgumentException('El comentario indicado no pertenece a la incidencia.');
		}

		$roles = $this->roleService->getEffectiveRoles($uid);
		$actorRole = in_array(RoleService::SUPPORT, $roles, true) || in_array(RoleService::ADMIN, $roles, true)
			? RoleService::SUPPORT
			: RoleService::USER;

		$attachment = $this->attachmentService->create($ticketId, $uid, $uploadedFile, $commentId);
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

	private function resolveProvinceSelection(array $payload): ?string {
		$rawProvince = is_string($payload['province'] ?? null) ? trim((string) $payload['province']) : '';
		$withoutProvince = filter_var($payload['withoutProvince'] ?? false, FILTER_VALIDATE_BOOLEAN);

		if ($rawProvince !== '') {
			$province = $this->provinceCatalogService->normalize($rawProvince);
			if ($province === null) {
				throw new \InvalidArgumentException('La provincia seleccionada no es valida.');
			}

			return $province;
		}

		if ($withoutProvince) {
			return null;
		}

		throw new \InvalidArgumentException('Debes seleccionar una provincia o marcar "Sin provincia".');
	}

	private function serializeTicket(string $uid, Ticket $ticket, bool $includeDetail = false): array {
		$data = $ticket->jsonSerialize();
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
		}, $this->commentMapper->findBy('ticket_id', $ticket->getId(), 'created_at', 'ASC')), fn (array $comment) => $this->permissionService->canSeeComment($uid, $ticket, (string) $comment['visibility']))) : [];
		$data['history'] = $includeDetail ? array_values(array_filter(array_map(fn ($row) => $row->jsonSerialize(), $this->historyMapper->findBy('ticket_id', $ticket->getId(), 'created_at', 'ASC')), fn (array $entry) => $entry['visibility'] === 'publico' || $this->permissionService->canManageTicket($uid, $ticket))) : [];
		$data['personalData'] = $includeDetail ? array_map(fn ($row) => $row->jsonSerialize(), $this->ticketDataMapper->findBy('ticket_id', $ticket->getId(), 'field_key', 'ASC')) : [];
		$data['taskSync'] = $includeDetail ? $this->taskSyncService->getSyncForTicket($ticket->getId()) : null;
		return $data;
	}

	private function matchesCriteria(string $uid, array $ticket, array $criteria): bool {
		$userGroupIds = $this->loadUserGroupIds($uid);
		foreach ($criteria as $key => $value) {
			if ($value === null || $value === '' || $value === []) {
				continue;
			}

			switch ($key) {
				case 'status':
					$statusValues = is_array($value) ? $value : array_map('trim', explode(',', (string) $value))
					;
					if (!in_array($ticket['status'], $statusValues, true)) {
						return false;
					}
					break;
				case 'assignedUser':
					if ($value === '__me__') {
						if (($ticket['assignedUserUid'] ?? null) !== $uid) {
							return false;
						}
						break;
					}
					if (($ticket['assignedUserUid'] ?? null) !== $value) {
						return false;
					}
					break;
				case 'assignedGroup':
					if ($value === '__my_groups__') {
						if (!in_array((string) ($ticket['assignedGroupId'] ?? ''), $userGroupIds, true)) {
							return false;
						}
						break;
					}
					if (($ticket['assignedGroupId'] ?? null) !== $value) {
						return false;
					}
					break;
				case 'unassigned':
					if ($value && (($ticket['assignedUserUid'] ?? null) !== null || ($ticket['assignedGroupId'] ?? null) !== null)) {
						return false;
					}
					break;
				case 'city':
					if (stripos((string) ($ticket['city'] ?? ''), (string) $value) === false) {
						return false;
					}
					break;
				case 'typeId':
					if ((int) ($ticket['typeId'] ?? 0) !== (int) $value) {
						return false;
					}
					break;
				case 'updatedWithinDays':
					$threshold = time() - ((int) $value * 86400);
					if ((int) ($ticket['updatedAt'] ?? 0) < $threshold) {
						return false;
					}
					break;
				case 'text':
					$haystack = strtolower(($ticket['title'] ?? '') . ' ' . ($ticket['userDescription'] ?? '') . ' ' . ($ticket['supportDescription'] ?? ''));
					if (!str_contains($haystack, strtolower((string) $value))) {
						return false;
					}
					break;
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
}