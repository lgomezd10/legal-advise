<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Notification;

use OCA\ConsultasLegales\Db\Ticket;
use OCA\ConsultasLegales\Notification\NotificationRecipientResolver;
use PHPUnit\Framework\TestCase;

class NotificationRecipientResolverTest extends TestCase {
	public function testTicketAssignedOnlyIncludesAssignedUserByDefault(): void {
		$resolver = new NotificationRecipientResolver();
		$assignedTicket = $this->createTicket('asignado', 'usuario1', 'soporte1');
		$waitingTicket = $this->createTicket('en_espera_usuario', 'usuario1', 'soporte1');

		self::assertSame(['soporte1'], $resolver->resolveDefaultRecipients('ticket_assigned', $assignedTicket));
		self::assertSame(['soporte1'], $resolver->resolveDefaultRecipients('ticket_assigned', $waitingTicket));
	}

	public function testTicketWaitingForCreatorOnlyIncludesCreator(): void {
		$resolver = new NotificationRecipientResolver();
		$ticket = $this->createTicket('en_espera_usuario', 'usuario1', 'soporte1');

		self::assertSame(['usuario1'], $resolver->resolveDefaultRecipients('ticket_waiting_for_creator', $ticket));
	}

	public function testTicketGroupAssignedHasNoDefaultRecipients(): void {
		$resolver = new NotificationRecipientResolver();
		$ticket = $this->createTicket('asignado', 'usuario1', null);

		self::assertSame([], $resolver->resolveDefaultRecipients('ticket_group_assigned', $ticket));
		self::assertSame([], $resolver->resolveDefaultRecipients('ticket_unassigned_created', $ticket));
	}

	public function testResolveRecipientRoleDetectsCreatorAssigneeAndWatcher(): void {
		$resolver = new NotificationRecipientResolver();
		$ticket = $this->createTicket('asignado', 'usuario1', 'soporte1');

		self::assertSame('creator', $resolver->resolveRecipientRole('usuario1', $ticket));
		self::assertSame('assignee', $resolver->resolveRecipientRole('soporte1', $ticket));
		self::assertSame('watcher', $resolver->resolveRecipientRole('adminqa', $ticket));
	}

	private function createTicket(string $status, string $creatorUid, ?string $assignedUserUid): Ticket {
		$ticket = new Ticket();
		$ticket->setId(77);
		$ticket->setNumber('2026-000077');
		$ticket->setTitle('Ticket');
		$ticket->setStatus($status);
		$ticket->setCreatorUid($creatorUid);
		$ticket->setAssignedUserUid($assignedUserUid);
		return $ticket;
	}
}