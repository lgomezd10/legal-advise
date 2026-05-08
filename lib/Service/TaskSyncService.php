<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Service;

use OCA\ConsultasLegales\AppInfo\Application;
use OCA\ConsultasLegales\Db\TaskSync;
use OCA\ConsultasLegales\Db\TaskSyncMapper;
use OCA\ConsultasLegales\Db\Ticket;
use OCP\App\IAppManager;
use OCP\Calendar\ICreateFromString;
use OCP\Calendar\IManager as CalendarManager;

class TaskSyncService {
	private const TASK_LIST_URI = 'consultas-legales';
	private const TASK_LIST_DISPLAY_NAME = 'Consultas Legales';

	public function __construct(
		private readonly TaskSyncMapper $taskSyncMapper,
		private readonly CatalogService $catalogService,
		private readonly IAppManager $appManager,
		private readonly CalendarManager $calendarManager,
	) {
	}

	public function getIntegrationStatus(): array {
		$config = $this->catalogService->getTaskConfig();
		return [
			'available' => $this->appManager->isInstalled('tasks'),
			'config' => $config,
		];
	}

	public function getSyncForTicket(int $ticketId): ?array {
		$sync = $this->taskSyncMapper->findOneBy('ticket_id', $ticketId);
		return $sync?->jsonSerialize();
	}

	public function syncTicket(Ticket $ticket): ?array {
		$config = $this->catalogService->getTaskConfig();
		if (!($config['enabled'] ?? false) || !$this->appManager->isInstalled('tasks')) {
			return null;
		}

		$existing = $this->taskSyncMapper->findOneBy('ticket_id', $ticket->getId());

		$assignedUserUid = $ticket->getAssignedUserUid();
		if ($assignedUserUid === null || $assignedUserUid === '') {
			if ($existing instanceof TaskSync) {
				$this->deleteRemoteTask($existing);
			}
			$this->markDetached($ticket->getId());
			return null;
		}

		$calendarSelection = $this->resolveWritableCalendar($assignedUserUid);
		$calendar = $calendarSelection['calendar'] ?? null;
		$calendarUri = $calendarSelection['uri'] ?? null;

		if (!$calendar instanceof ICreateFromString) {
			$this->saveSyncRecord($ticket, $assignedUserUid, null, null, null, 'no_calendar', 'No writable VTODO calendar available');
			return null;
		}

		$objectUid = $existing?->getObjectUid() ?: sprintf('gi-%d-%s', $ticket->getId(), uniqid());
		$objectUri = $existing?->getObjectUri() ?: $objectUid . '.ics';
		$calendarData = $this->buildTodoIcs($ticket, $objectUid);
		$backend = $this->extractCalendarBackend($calendar);
		$calendarId = $this->resolveCalendarId($calendar);

		try {
			if ($existing instanceof TaskSync && $this->shouldReplaceRemoteTask($existing, $assignedUserUid, $calendarUri)) {
				$this->deleteRemoteTask($existing);
			}

			if ($backend !== null && $calendarId !== null && $this->remoteTaskExists($backend, $calendarId, $objectUri)) {
				$backend->updateCalendarObject($calendarId, $objectUri, $calendarData);
			} else {
				$calendar->createFromString($objectUri, $calendarData);
			}

			return $this->saveSyncRecord($ticket, $assignedUserUid, $calendarUri, $objectUri, $objectUid, 'synced', null);
		} catch (\Throwable $e) {
			return $this->saveSyncRecord($ticket, $assignedUserUid, $calendarUri, $objectUri, $objectUid, 'error', $e->getMessage());
		}
	}

	private function shouldReplaceRemoteTask(TaskSync $existing, string $assignedUserUid, ?string $calendarUri): bool {
		$existingAssigneeUid = $existing->getAssigneeUid();
		if ($existingAssigneeUid !== null && $existingAssigneeUid !== '' && $existingAssigneeUid !== $assignedUserUid) {
			return true;
		}

		$existingCalendarUri = $existing->getCalendarUri();
		return $existingCalendarUri !== null && $existingCalendarUri !== '' && $calendarUri !== null && $existingCalendarUri !== $calendarUri;
	}

	/**
	 * @return array{calendar: ICreateFromString|null, uri: string|null}
	 */
	private function resolveWritableCalendar(string $uid, ?string $preferredUri = null, bool $strictPreferred = false): array {
		$principal = 'principals/users/' . $uid;
		$targetUri = $this->resolveTargetCalendarUri($preferredUri, $strictPreferred);
		$target = ['calendar' => null, 'uri' => null];
		$fallback = ['calendar' => null, 'uri' => null];

		foreach ($this->calendarManager->getCalendarsForPrincipal($principal) as $candidate) {
			if (!$candidate instanceof ICreateFromString) {
				continue;
			}

			$uri = method_exists($candidate, 'getUri') ? (string) $candidate->getUri() : null;
			if ($uri === $targetUri) {
				$target = ['calendar' => $candidate, 'uri' => $uri];
			}

			if (!$fallback['calendar'] instanceof ICreateFromString) {
				$fallback = ['calendar' => $candidate, 'uri' => $uri];
			}
		}

		if ($target['calendar'] instanceof ICreateFromString) {
			return $target;
		}

		if ($targetUri === self::TASK_LIST_URI && $this->ensureDedicatedTaskList($principal, $fallback['calendar'])) {
			foreach ($this->calendarManager->getCalendarsForPrincipal($principal, [self::TASK_LIST_URI]) as $candidate) {
				if ($candidate instanceof ICreateFromString) {
					$uri = method_exists($candidate, 'getUri') ? (string) $candidate->getUri() : self::TASK_LIST_URI;
					return ['calendar' => $candidate, 'uri' => $uri];
				}
			}
		}

		return $fallback;
	}

	private function resolveTargetCalendarUri(?string $preferredUri, bool $strictPreferred): string {
		if ($strictPreferred && $preferredUri !== null && $preferredUri !== '') {
			return $preferredUri;
		}

		return self::TASK_LIST_URI;
	}

	private function ensureDedicatedTaskList(string $principalUri, mixed $calendar): bool {
		if (!$calendar instanceof ICreateFromString) {
			return false;
		}

		$backend = $this->extractCalendarBackend($calendar);
		if ($backend === null || !method_exists($backend, 'createCalendar') || !method_exists($backend, 'getCalendarByUri')) {
			return false;
		}

		if (is_array($backend->getCalendarByUri($principalUri, self::TASK_LIST_URI))) {
			return true;
		}

		$backend->createCalendar($principalUri, self::TASK_LIST_URI, [
			'components' => 'VTODO',
			'{DAV:}displayname' => self::TASK_LIST_DISPLAY_NAME,
		]);

		return true;
	}

	private function deleteRemoteTask(TaskSync $sync): void {
		$assigneeUid = $sync->getAssigneeUid();
		$objectUri = $sync->getObjectUri();
		if ($assigneeUid === null || $assigneeUid === '' || $objectUri === null || $objectUri === '') {
			return;
		}

		$calendarSelection = $this->resolveWritableCalendar($assigneeUid, $sync->getCalendarUri(), true);
		$calendar = $calendarSelection['calendar'] ?? null;
		if (!$calendar instanceof ICreateFromString) {
			return;
		}

		$backend = $this->extractCalendarBackend($calendar);
		$calendarId = $this->resolveCalendarId($calendar);
		if ($backend === null || $calendarId === null || !$this->remoteTaskExists($backend, $calendarId, $objectUri)) {
			return;
		}

		$backend->deleteCalendarObject($calendarId, $objectUri);
	}

	private function remoteTaskExists(object $backend, int $calendarId, string $objectUri): bool {
		if (!method_exists($backend, 'getCalendarObject')) {
			return false;
		}

		return is_array($backend->getCalendarObject($calendarId, $objectUri));
	}

	private function resolveCalendarId(object $calendar): ?int {
		if (!method_exists($calendar, 'getKey')) {
			return null;
		}

		$calendarId = (int) $calendar->getKey();
		return $calendarId > 0 ? $calendarId : null;
	}

	private function extractCalendarBackend(object $calendar): ?object {
		$reflection = new \ReflectionObject($calendar);
		while ($reflection !== false) {
			if ($reflection->hasProperty('backend')) {
				$property = $reflection->getProperty('backend');
				$property->setAccessible(true);
				$backend = $property->getValue($calendar);
				if (is_object($backend)
					&& method_exists($backend, 'getCalendarObject')
					&& method_exists($backend, 'updateCalendarObject')
					&& method_exists($backend, 'deleteCalendarObject')) {
					return $backend;
				}
				return null;
			}

			$reflection = $reflection->getParentClass();
		}

		return null;
	}

	private function markDetached(int $ticketId): void {
		$existing = $this->taskSyncMapper->findOneBy('ticket_id', $ticketId);
		if ($existing === null) {
			return;
		}

		$existing->setSyncStatus('detached');
		$existing->setLastSyncedAt(time());
		$this->taskSyncMapper->update($existing);
	}

	private function saveSyncRecord(Ticket $ticket, ?string $assigneeUid, ?string $calendarUri, ?string $objectUri, ?string $objectUid, string $status, ?string $error): array {
		$existing = $this->taskSyncMapper->findOneBy('ticket_id', $ticket->getId()) ?? new TaskSync();
		$existing->setTicketId($ticket->getId());
		$existing->setAssigneeUid($assigneeUid);
		$existing->setCalendarUri($calendarUri);
		$existing->setObjectUri($objectUri);
		$existing->setObjectUid($objectUid);
		$existing->setSyncStatus($status);
		$existing->setLastSyncedAt(time());
		$existing->setLastError($error);
		$existing->setPayload([
			'ticketNumber' => $ticket->getNumber(),
			'ticketStatus' => $ticket->getStatus(),
			'url' => '/apps/' . Application::APP_ID . '/#/tickets/' . $ticket->getId(),
		]);

		if ($existing->getId() === null) {
			$existing = $this->taskSyncMapper->insert($existing);
		} else {
			$existing = $this->taskSyncMapper->update($existing);
		}

		return $existing->jsonSerialize();
	}

	private function buildTodoIcs(Ticket $ticket, string $uid): string {
		$status = in_array($ticket->getStatus(), ['resuelto', 'cerrado'], true) ? 'COMPLETED' : 'NEEDS-ACTION';
		$priority = match ((int) ($ticket->getMetadata()['urgencyWeight'] ?? 2)) {
			4 => 1,
			3 => 3,
			2 => 5,
			default => 7,
		};

		$summary = sprintf('[%s] %s', $ticket->getNumber(), $ticket->getTitle());
		$description = str_replace(["\r", "\n"], ['','\\n'], trim($ticket->getUserDescription() . "\n\n" . 'Ticket: ' . $ticket->getNumber()));

		return implode("\r\n", [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Nextcloud//' . Application::APP_ID . '//ES',
			'BEGIN:VTODO',
			'UID:' . $uid,
			'DTSTAMP:' . gmdate('Ymd\\THis\\Z'),
			'SUMMARY:' . $summary,
			'DESCRIPTION:' . $description,
			'PRIORITY:' . $priority,
			'STATUS:' . $status,
			'PERCENT-COMPLETE:' . ($status === 'COMPLETED' ? '100' : '0'),
			'END:VTODO',
			'END:VCALENDAR',
			'',
		]);
	}
}