<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Notification;

use OCA\ConsultasLegales\Db\Ticket;
use OCA\ConsultasLegales\Service\NotificationService;

class TicketNotificationPublisher {
	public function __construct(
		private readonly NotificationService $notificationService,
		private readonly GroupNotificationRecipientResolver $groupNotificationRecipientResolver,
		private readonly SupportAdminNotificationRecipientResolver $supportAdminNotificationRecipientResolver,
	) {
	}

	public function publishCreatedTicket(Ticket $ticket): void {
		$this->notificationService->emit('ticket_created', $ticket);

		if (($ticket->getAssignedUserUid() === null || $ticket->getAssignedUserUid() === '')
			&& ($ticket->getAssignedGroupId() === null || $ticket->getAssignedGroupId() === '')) {
			$this->notificationService->emit(
				'ticket_unassigned_created',
				$ticket,
				$this->supportAdminNotificationRecipientResolver->resolve(),
				[],
				false,
			);
		}

		if (($ticket->getAssignedUserUid() === null || $ticket->getAssignedUserUid() === '')
			&& $ticket->getAssignedGroupId() !== null
			&& $ticket->getAssignedGroupId() !== '') {
			$this->notificationService->emit(
				'ticket_group_assigned',
				$ticket,
				$this->groupNotificationRecipientResolver->resolve((string) $ticket->getAssignedGroupId()),
				[],
				false,
			);
		}
	}

	public function publishUpdatedTicket(
		Ticket $ticket,
		string $previousStatus,
		?string $previousAssignedUserUid,
		?string $previousAssignedGroupId,
		bool $statusChanged,
		bool $assignmentChanged,
	): void {
		$assignedUserChanged = $ticket->getAssignedUserUid() !== $previousAssignedUserUid;
		$assignedGroupChanged = $ticket->getAssignedGroupId() !== $previousAssignedGroupId;
		$eventName = null;
		$context = [
			'previousStatus' => $previousStatus,
			'previousAssignedUserUid' => $previousAssignedUserUid,
			'previousAssignedGroupId' => $previousAssignedGroupId,
		];

		if ($statusChanged && (string) $ticket->getStatus() === 'en_espera_usuario') {
			$eventName = 'ticket_waiting_for_creator';
		} elseif ($assignmentChanged) {
			if ($assignedUserChanged && $ticket->getAssignedUserUid() !== null && $ticket->getAssignedUserUid() !== '') {
				$eventName = 'ticket_assigned';
			}
		} elseif ($statusChanged) {
			$eventName = in_array((string) $ticket->getStatus(), ['resuelto', 'cerrado'], true)
				? 'ticket_resolved'
				: 'ticket_status_changed';
		}

		if ($eventName !== null) {
			$this->notificationService->emit($eventName, $ticket, [], $context);
		}

		if ($assignedGroupChanged
			&& ($ticket->getAssignedUserUid() === null || $ticket->getAssignedUserUid() === '')
			&& $ticket->getAssignedGroupId() !== null
			&& $ticket->getAssignedGroupId() !== '') {
			$this->notificationService->emit('ticket_group_assigned', $ticket, $this->groupNotificationRecipientResolver->resolve((string) $ticket->getAssignedGroupId()), [
				'previousAssignedGroupId' => $previousAssignedGroupId,
			], false);
		}
	}

	public function publishPublicReply(Ticket $ticket): void {
		$this->notificationService->emit('ticket_public_reply', $ticket);
	}

	public function publishReopenedTicket(Ticket $ticket, string $previousStatus): void {
		$this->notificationService->emit('ticket_status_changed', $ticket, [], ['previousStatus' => $previousStatus]);
	}
}