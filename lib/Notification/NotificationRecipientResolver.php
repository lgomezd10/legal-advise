<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Notification;

use OCA\ConsultasLegales\Db\Ticket;

class NotificationRecipientResolver {
	public function resolveDefaultRecipients(string $eventName, Ticket $ticket, array $context = []): array {
		return match ($eventName) {
			'ticket_assigned' => [$ticket->getAssignedUserUid()],
			'ticket_waiting_for_creator' => [$ticket->getCreatorUid()],
			'ticket_unassigned_created',
			'ticket_group_assigned' => [],
			default => [$ticket->getCreatorUid(), $ticket->getAssignedUserUid()],
		};
	}

	public function resolveRecipientRole(string $uid, Ticket $ticket): string {
		if ($ticket->getCreatorUid() === $uid) {
			return 'creator';
		}

		if ($ticket->getAssignedUserUid() === $uid) {
			return 'assignee';
		}

		return 'watcher';
	}

}