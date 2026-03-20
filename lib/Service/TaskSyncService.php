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

		$assignedUserUid = $ticket->getAssignedUserUid();
		if ($assignedUserUid === null || $assignedUserUid === '') {
			$this->markDetached($ticket->getId());
			return null;
		}

		$principal = 'principals/users/' . $assignedUserUid;
		$calendar = null;
		$calendarUri = null;
		foreach ($this->calendarManager->getCalendarsForPrincipal($principal) as $candidate) {
			if ($candidate instanceof ICreateFromString) {
				$calendar = $candidate;
				$calendarUri = method_exists($candidate, 'getUri') ? $candidate->getUri() : null;
				break;
			}
		}

		if (!$calendar instanceof ICreateFromString) {
			$this->saveSyncRecord($ticket, $assignedUserUid, null, null, null, 'no_calendar', 'No writable VTODO calendar available');
			return null;
		}

		$existing = $this->taskSyncMapper->findOneBy('ticket_id', $ticket->getId());
		$objectUid = $existing?->getObjectUid() ?: sprintf('gi-%d-%s', $ticket->getId(), uniqid());
		$objectUri = $existing?->getObjectUri() ?: $objectUid . '.ics';

		try {
			$calendar->createFromString($objectUri, $this->buildTodoIcs($ticket, $objectUid));
			return $this->saveSyncRecord($ticket, $assignedUserUid, $calendarUri, $objectUri, $objectUid, 'synced', null);
		} catch (\Throwable $e) {
			return $this->saveSyncRecord($ticket, $assignedUserUid, $calendarUri, $objectUri, $objectUid, 'error', $e->getMessage());
		}
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