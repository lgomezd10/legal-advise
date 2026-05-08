<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Notification;

use OCA\ConsultasLegales\Db\Ticket;
use OCA\ConsultasLegales\Notification\GroupNotificationRecipientResolver;
use OCA\ConsultasLegales\Notification\SupportAdminNotificationRecipientResolver;
use OCA\ConsultasLegales\Notification\TicketNotificationPublisher;
use OCA\ConsultasLegales\Service\NotificationService;
use PHPUnit\Framework\TestCase;

class TicketNotificationPublisherTest extends TestCase {
	public function testPublishUpdatedTicketEmitsTicketWaitingForCreatorForEnEsperaUsuario(): void {
		$notificationService = $this->createMock(NotificationService::class);
		$resolver = $this->createMock(GroupNotificationRecipientResolver::class);
		$supportAdminResolver = $this->createMock(SupportAdminNotificationRecipientResolver::class);
		$publisher = new TicketNotificationPublisher($notificationService, $resolver, $supportAdminResolver);

		$ticket = $this->createTicket('en_espera_usuario', 'usuario1', 'soporte1', 'territorial');

		$notificationService->expects(self::once())
			->method('emit')
			->with(
				'ticket_waiting_for_creator',
				$ticket,
				[],
				[
					'previousStatus' => 'asignado',
					'previousAssignedUserUid' => 'soporte1',
					'previousAssignedGroupId' => 'territorial',
				],
			);

		$publisher->publishUpdatedTicket($ticket, 'asignado', 'soporte1', 'territorial', true, false);
	}

	public function testPublishUpdatedTicketEmitsSecondaryGroupNotificationForGroupOnlyAssignment(): void {
		$notificationService = $this->createMock(NotificationService::class);
		$resolver = $this->createMock(GroupNotificationRecipientResolver::class);
		$supportAdminResolver = $this->createMock(SupportAdminNotificationRecipientResolver::class);
		$publisher = new TicketNotificationPublisher($notificationService, $resolver, $supportAdminResolver);

		$ticket = $this->createTicket('nuevo', 'usuario1', null, 'territorial_legal');
		$resolver->expects(self::once())->method('resolve')->with('territorial_legal')->willReturn(['soporte1']);

		$notificationService->expects(self::once())
			->method('emit')
			->willReturnCallback(static function (string $eventName, Ticket $emittedTicket, array $extraRecipients = [], array $context = [], bool $includeDefaultRecipients = true) use ($ticket): void {
				TestCase::assertSame('ticket_group_assigned', $eventName);
				TestCase::assertSame($ticket, $emittedTicket);
				TestCase::assertSame(['soporte1'], $extraRecipients);
				TestCase::assertSame(['previousAssignedGroupId' => 'territorial'], $context);
				TestCase::assertFalse($includeDefaultRecipients);
			});

		$publisher->publishUpdatedTicket($ticket, 'nuevo', null, 'territorial', false, true);
	}

	public function testPublishUpdatedTicketSkipsGenericUpdateNotification(): void {
		$notificationService = $this->createMock(NotificationService::class);
		$resolver = $this->createMock(GroupNotificationRecipientResolver::class);
		$supportAdminResolver = $this->createMock(SupportAdminNotificationRecipientResolver::class);
		$publisher = new TicketNotificationPublisher($notificationService, $resolver, $supportAdminResolver);

		$ticket = $this->createTicket('nuevo', 'usuario1', 'soporte1', 'territorial');

		$notificationService->expects(self::never())->method('emit');

		$publisher->publishUpdatedTicket($ticket, 'nuevo', 'soporte1', 'territorial', false, false);
	}

	public function testPublishUpdatedTicketMarksDirectUserAssignmentAsAssigneeNotification(): void {
		$notificationService = $this->createMock(NotificationService::class);
		$resolver = $this->createMock(GroupNotificationRecipientResolver::class);
		$supportAdminResolver = $this->createMock(SupportAdminNotificationRecipientResolver::class);
		$publisher = new TicketNotificationPublisher($notificationService, $resolver, $supportAdminResolver);

		$ticket = $this->createTicket('asignado', 'usuario1', 'adminqa', 'territorial');

		$notificationService->expects(self::once())
			->method('emit')
			->with(
				'ticket_assigned',
				$ticket,
				[],
				[
					'previousStatus' => 'asignado',
					'previousAssignedUserUid' => 'soporte1',
					'previousAssignedGroupId' => 'territorial',
				],
			);

		$publisher->publishUpdatedTicket($ticket, 'asignado', 'soporte1', 'territorial', false, true);
	}

	public function testPublishUpdatedTicketPrioritizesCreatorNotificationWhenStatusMovesToEnEsperaUsuario(): void {
		$notificationService = $this->createMock(NotificationService::class);
		$resolver = $this->createMock(GroupNotificationRecipientResolver::class);
		$supportAdminResolver = $this->createMock(SupportAdminNotificationRecipientResolver::class);
		$publisher = new TicketNotificationPublisher($notificationService, $resolver, $supportAdminResolver);

		$ticket = $this->createTicket('en_espera_usuario', 'usuario1', 'adminqa', 'territorial');

		$notificationService->expects(self::once())
			->method('emit')
			->with(
				'ticket_waiting_for_creator',
				$ticket,
				[],
				[
					'previousStatus' => 'asignado',
					'previousAssignedUserUid' => 'soporte1',
					'previousAssignedGroupId' => 'territorial',
				],
			);

		$publisher->publishUpdatedTicket($ticket, 'asignado', 'soporte1', 'territorial', true, true);
	}

	public function testPublishCreatedTicketEmitsUnassignedCreatedForSupportAndAdminRecipients(): void {
		$notificationService = $this->createMock(NotificationService::class);
		$resolver = $this->createMock(GroupNotificationRecipientResolver::class);
		$supportAdminResolver = $this->createMock(SupportAdminNotificationRecipientResolver::class);
		$publisher = new TicketNotificationPublisher($notificationService, $resolver, $supportAdminResolver);

		$ticket = $this->createTicket('nuevo', 'usuario1', null, null);
		$supportAdminResolver->expects(self::once())->method('resolve')->willReturn(['soporte1', 'adminqa']);

		$notificationService->expects(self::exactly(2))
			->method('emit')
			->willReturnCallback(static function (string $eventName, Ticket $emittedTicket, array $extraRecipients = [], array $context = [], bool $includeDefaultRecipients = true) use ($ticket): void {
				static $calls = 0;
				$calls++;
				if ($calls === 1) {
					TestCase::assertSame('ticket_created', $eventName);
					TestCase::assertSame($ticket, $emittedTicket);
					TestCase::assertSame([], $extraRecipients);
					TestCase::assertSame([], $context);
					TestCase::assertTrue($includeDefaultRecipients);
					return;
				}

				TestCase::assertSame('ticket_unassigned_created', $eventName);
				TestCase::assertSame($ticket, $emittedTicket);
				TestCase::assertSame(['soporte1', 'adminqa'], $extraRecipients);
				TestCase::assertSame([], $context);
				TestCase::assertFalse($includeDefaultRecipients);
			});

		$publisher->publishCreatedTicket($ticket);

	}

	private function createTicket(string $status, string $creatorUid, ?string $assignedUserUid, ?string $assignedGroupId): Ticket {
		$ticket = new Ticket();
		$ticket->setId(50);
		$ticket->setNumber('2026-000050');
		$ticket->setTitle('Ticket de prueba');
		$ticket->setStatus($status);
		$ticket->setCreatorUid($creatorUid);
		$ticket->setAssignedUserUid($assignedUserUid);
		$ticket->setAssignedGroupId($assignedGroupId);
		return $ticket;
	}
}