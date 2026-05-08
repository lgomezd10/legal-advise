<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Service;

use OCA\ConsultasLegales\Db\NotificationPreference;
use OCA\ConsultasLegales\Db\Ticket;
use OCA\ConsultasLegales\Db\NotificationPreferenceMapper;
use OCA\ConsultasLegales\Notification\NotificationMailBuilder;
use OCA\ConsultasLegales\Notification\NotificationPolicy;
use OCA\ConsultasLegales\Notification\NotificationRecipientResolver;
use OCA\ConsultasLegales\Service\NotificationService;
use OCA\ConsultasLegales\Service\RoleService;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Notification\INotification;
use OCP\Notification\IManager;
use PHPUnit\Framework\TestCase;

class NotificationServiceTest extends TestCase {
	public function testUserPreferencesOnlyExposeEventsConfigurableByUser(): void {
		$rows = [
			$this->createPreference('profile', RoleService::USER, 'ticket_created', NotificationPolicy::CHANNEL_NEXTCLOUD, true),
			$this->createPreference('profile', RoleService::USER, 'ticket_created', NotificationPolicy::CHANNEL_MAIL, true),
			$this->createPreference('profile', RoleService::USER, 'ticket_status_changed', NotificationPolicy::CHANNEL_NEXTCLOUD, true),
			$this->createPreference('profile', RoleService::USER, 'ticket_status_changed', NotificationPolicy::CHANNEL_MAIL, false),
		];

		$mapper = $this->createMock(NotificationPreferenceMapper::class);
		$mapper->method('findAllOrdered')->willReturn($rows);

		$roleService = $this->createMock(RoleService::class);
		$roleService->method('getEffectiveRoles')->with('usuario1')->willReturn([RoleService::USER]);

		$service = $this->createService($mapper, $roleService);

		self::assertSame(
			['ticket_created', 'ticket_waiting_for_creator', 'ticket_resolved'],
			array_column($service->getPreferencesForUser('usuario1'), 'eventName'),
		);
	}

	public function testUserPreferenceSaveOnlyAcceptsConfigurableEventsAndNoMailOnlyMode(): void {
		$rows = [
			$this->createPreference('profile', RoleService::USER, 'ticket_created', NotificationPolicy::CHANNEL_NEXTCLOUD, true),
			$this->createPreference('profile', RoleService::USER, 'ticket_created', NotificationPolicy::CHANNEL_MAIL, true),
			$this->createPreference('profile', RoleService::USER, 'ticket_status_changed', NotificationPolicy::CHANNEL_NEXTCLOUD, true),
			$this->createPreference('profile', RoleService::USER, 'ticket_status_changed', NotificationPolicy::CHANNEL_MAIL, false),
		];
		$inserted = [];

		$mapper = $this->createMock(NotificationPreferenceMapper::class);
		$mapper->method('findAllOrdered')->willReturnCallback(static function () use (&$rows): array {
			return $rows;
		});
		$mapper->method('findByMany')->willReturn([]);
		$mapper->expects(self::exactly(2))
			->method('insert')
			->willReturnCallback(static function (NotificationPreference $entity) use (&$rows, &$inserted): NotificationPreference {
				$rows[] = $entity;
				$inserted[] = $entity;
				return $entity;
			});

		$roleService = $this->createMock(RoleService::class);
		$roleService->method('getEffectiveRoles')->with('usuario1')->willReturn([RoleService::USER]);

		$service = $this->createService($mapper, $roleService);
		$result = $service->updateUserPreferences('usuario1', [
			['eventName' => 'ticket_created', 'deliveryMode' => 'mail'],
			['eventName' => 'ticket_status_changed', 'deliveryMode' => 'both'],
		]);

		self::assertCount(2, $inserted);
		self::assertSame(NotificationPolicy::CHANNEL_NEXTCLOUD, $inserted[0]->getChannel());
		self::assertTrue($inserted[0]->getEnabled());
		self::assertSame(NotificationPolicy::CHANNEL_MAIL, $inserted[1]->getChannel());
		self::assertFalse($inserted[1]->getEnabled());
		self::assertSame('nextcloud', $result[0]['deliveryMode']);
		self::assertSame(['ticket_created', 'ticket_waiting_for_creator', 'ticket_resolved'], array_column($result, 'eventName'));
	}

	public function testSupportPreferenceSaveAllowsNoneDeliveryMode(): void {
		$rows = [
			$this->createPreference('profile', RoleService::SUPPORT, 'ticket_status_changed', NotificationPolicy::CHANNEL_NEXTCLOUD, true),
			$this->createPreference('profile', RoleService::SUPPORT, 'ticket_status_changed', NotificationPolicy::CHANNEL_MAIL, true),
		];
		$inserted = [];

		$mapper = $this->createMock(NotificationPreferenceMapper::class);
		$mapper->method('findAllOrdered')->willReturnCallback(static function () use (&$rows): array {
			return $rows;
		});
		$mapper->method('findByMany')->willReturn([]);
		$mapper->expects(self::exactly(2))
			->method('insert')
			->willReturnCallback(static function (NotificationPreference $entity) use (&$rows, &$inserted): NotificationPreference {
				$rows[] = $entity;
				$inserted[] = $entity;
				return $entity;
			});

		$roleService = $this->createMock(RoleService::class);
		$roleService->method('getEffectiveRoles')->with('soporte1')->willReturn([RoleService::SUPPORT]);

		$service = $this->createService($mapper, $roleService);
		$result = $service->updateUserPreferences('soporte1', [
			['eventName' => 'ticket_status_changed', 'deliveryMode' => 'none'],
		]);
		$eventNames = array_column($result, 'eventName');
		$targetIndex = array_search('ticket_status_changed', $eventNames, true);

		self::assertCount(2, $inserted);
		self::assertFalse($inserted[0]->getEnabled());
		self::assertFalse($inserted[1]->getEnabled());
		self::assertNotFalse($targetIndex);
		self::assertSame('none', $result[$targetIndex]['deliveryMode']);
	}

	public function testTicketAssignedDoesNotNotifyCreatorByDefault(): void {
		$mapper = $this->createMock(NotificationPreferenceMapper::class);
		$mapper->method('findAllOrdered')->willReturn([
			$this->createPreference('profile', RoleService::USER, 'ticket_assigned', NotificationPolicy::CHANNEL_NEXTCLOUD, true),
			$this->createPreference('profile', RoleService::USER, 'ticket_assigned', NotificationPolicy::CHANNEL_MAIL, false),
			$this->createPreference('profile', RoleService::SUPPORT, 'ticket_assigned', NotificationPolicy::CHANNEL_NEXTCLOUD, true),
			$this->createPreference('profile', RoleService::SUPPORT, 'ticket_assigned', NotificationPolicy::CHANNEL_MAIL, false),
		]);

		$roleService = $this->createMock(RoleService::class);
		$roleService->method('getEffectiveRoles')->willReturnMap([
			['usuario1', [RoleService::USER]],
			['soporte1', [RoleService::SUPPORT]],
		]);

		$notifiedUsers = [];
		$manager = $this->createMock(IManager::class);
		$manager->expects(self::once())
			->method('createNotification')
			->willReturnCallback(function () use (&$notifiedUsers) {
				$notification = $this->createMock(INotification::class);
				$notification->method('setApp')->willReturnSelf();
				$notification->method('setDateTime')->willReturnSelf();
				$notification->method('setObject')->willReturnSelf();
				$notification->method('setSubject')->willReturnSelf();
				$notification->method('setLink')->willReturnSelf();
				$notification->method('setUser')->willReturnCallback(function (string $user) use (&$notifiedUsers, $notification) {
					$notifiedUsers[] = $user;
					return $notification;
				});
				return $notification;
			});
		$manager->expects(self::once())->method('notify');

		$service = $this->createService($mapper, $roleService, $manager);
		$ticket = $this->createTicket('asignado', 'usuario1', 'soporte1');

		$service->emit('ticket_assigned', $ticket);

		self::assertSame(['soporte1'], $notifiedUsers);
	}

	public function testSupportPreferencesExposeUnassignedCreatedWithNextcloudDefault(): void {
		$rows = [
			$this->createPreference('profile', RoleService::SUPPORT, 'ticket_unassigned_created', NotificationPolicy::CHANNEL_NEXTCLOUD, true),
			$this->createPreference('profile', RoleService::SUPPORT, 'ticket_unassigned_created', NotificationPolicy::CHANNEL_MAIL, true),
		];

		$mapper = $this->createMock(NotificationPreferenceMapper::class);
		$mapper->method('findAllOrdered')->willReturn($rows);

		$roleService = $this->createMock(RoleService::class);
		$roleService->method('getEffectiveRoles')->with('soporte1')->willReturn([RoleService::SUPPORT]);

		$service = $this->createService($mapper, $roleService);
		$preferences = $service->getPreferencesForUser('soporte1');

		self::assertContains('ticket_unassigned_created', array_column($preferences, 'eventName'));
		self::assertSame('nextcloud', $preferences[array_search('ticket_unassigned_created', array_column($preferences, 'eventName'), true)]['deliveryMode']);
	}

	public function testUnassignedCreatedDefaultOnlySendsNextcloudWhenUserHasNoOverride(): void {
		$mapper = $this->createMock(NotificationPreferenceMapper::class);
		$mapper->method('findAllOrdered')->willReturn([
			$this->createPreference('profile', RoleService::SUPPORT, 'ticket_unassigned_created', NotificationPolicy::CHANNEL_NEXTCLOUD, true),
			$this->createPreference('profile', RoleService::SUPPORT, 'ticket_unassigned_created', NotificationPolicy::CHANNEL_MAIL, true),
		]);

		$roleService = $this->createMock(RoleService::class);
		$roleService->method('getEffectiveRoles')->willReturnMap([
			['soporte1', [RoleService::SUPPORT]],
		]);

		$notifiedUsers = [];
		$manager = $this->createMock(IManager::class);
		$manager->expects(self::once())
			->method('createNotification')
			->willReturnCallback(function () use (&$notifiedUsers) {
				$notification = $this->createMock(INotification::class);
				$notification->method('setApp')->willReturnSelf();
				$notification->method('setDateTime')->willReturnSelf();
				$notification->method('setObject')->willReturnSelf();
				$notification->method('setSubject')->willReturnSelf();
				$notification->method('setLink')->willReturnSelf();
				$notification->method('setUser')->willReturnCallback(function (string $user) use (&$notifiedUsers, $notification) {
					$notifiedUsers[] = $user;
					return $notification;
				});
				return $notification;
			});
		$manager->expects(self::once())->method('notify');

		$mailer = $this->createMock(IMailer::class);
		$mailer->expects(self::never())->method('send');

		$service = $this->createService($mapper, $roleService, $manager, $mailer);
		$ticket = $this->createTicket('nuevo', 'usuario1', null);

		$service->emit('ticket_unassigned_created', $ticket, ['soporte1'], [], false);

		self::assertSame(['soporte1'], $notifiedUsers);
	}

	public function testTicketWaitingForCreatorNotifiesCreatorOnly(): void {
		$mapper = $this->createMock(NotificationPreferenceMapper::class);
		$mapper->method('findAllOrdered')->willReturn([
			$this->createPreference('profile', RoleService::USER, 'ticket_waiting_for_creator', NotificationPolicy::CHANNEL_NEXTCLOUD, true),
			$this->createPreference('profile', RoleService::USER, 'ticket_waiting_for_creator', NotificationPolicy::CHANNEL_MAIL, false),
			$this->createPreference('profile', RoleService::SUPPORT, 'ticket_waiting_for_creator', NotificationPolicy::CHANNEL_NEXTCLOUD, true),
			$this->createPreference('profile', RoleService::SUPPORT, 'ticket_waiting_for_creator', NotificationPolicy::CHANNEL_MAIL, false),
		]);

		$roleService = $this->createMock(RoleService::class);
		$roleService->method('getEffectiveRoles')->willReturnMap([
			['usuario1', [RoleService::USER]],
			['soporte1', [RoleService::SUPPORT]],
		]);

		$notifiedUsers = [];
		$manager = $this->createMock(IManager::class);
		$manager->expects(self::once())
			->method('createNotification')
			->willReturnCallback(function () use (&$notifiedUsers) {
				$notification = $this->createMock(INotification::class);
				$notification->method('setApp')->willReturnSelf();
				$notification->method('setDateTime')->willReturnSelf();
				$notification->method('setObject')->willReturnSelf();
				$notification->method('setSubject')->willReturnSelf();
				$notification->method('setLink')->willReturnSelf();
				$notification->method('setUser')->willReturnCallback(function (string $user) use (&$notifiedUsers, $notification) {
					$notifiedUsers[] = $user;
					return $notification;
				});
				return $notification;
			});
		$manager->expects(self::once())->method('notify');

		$service = $this->createService($mapper, $roleService, $manager);
		$ticket = $this->createTicket('en_espera_usuario', 'usuario1', 'soporte1');

		$service->emit('ticket_waiting_for_creator', $ticket);

		self::assertSame(['usuario1'], $notifiedUsers);
	}

	public function testTicketAssignedDoesNotNotifyCreatorWhenDirectAssignmentEndsWaitingForUser(): void {
		$mapper = $this->createMock(NotificationPreferenceMapper::class);
		$mapper->method('findAllOrdered')->willReturn([
			$this->createPreference('profile', RoleService::USER, 'ticket_assigned', NotificationPolicy::CHANNEL_NEXTCLOUD, true),
			$this->createPreference('profile', RoleService::USER, 'ticket_assigned', NotificationPolicy::CHANNEL_MAIL, false),
			$this->createPreference('profile', RoleService::SUPPORT, 'ticket_assigned', NotificationPolicy::CHANNEL_NEXTCLOUD, true),
			$this->createPreference('profile', RoleService::SUPPORT, 'ticket_assigned', NotificationPolicy::CHANNEL_MAIL, false),
			$this->createPreference('profile', RoleService::ADMIN, 'ticket_assigned', NotificationPolicy::CHANNEL_NEXTCLOUD, true),
			$this->createPreference('profile', RoleService::ADMIN, 'ticket_assigned', NotificationPolicy::CHANNEL_MAIL, false),
		]);

		$roleService = $this->createMock(RoleService::class);
		$roleService->method('getEffectiveRoles')->willReturnMap([
			['usuario1', [RoleService::USER]],
			['adminqa', [RoleService::ADMIN]],
		]);

		$notifiedUsers = [];
		$manager = $this->createMock(IManager::class);
		$manager->expects(self::once())
			->method('createNotification')
			->willReturnCallback(function () use (&$notifiedUsers) {
				$notification = $this->createMock(INotification::class);
				$notification->method('setApp')->willReturnSelf();
				$notification->method('setDateTime')->willReturnSelf();
				$notification->method('setObject')->willReturnSelf();
				$notification->method('setSubject')->willReturnSelf();
				$notification->method('setLink')->willReturnSelf();
				$notification->method('setUser')->willReturnCallback(function (string $user) use (&$notifiedUsers, $notification) {
					$notifiedUsers[] = $user;
					return $notification;
				});
				return $notification;
			});
		$manager->expects(self::once())->method('notify');

		$service = $this->createService($mapper, $roleService, $manager);
		$ticket = $this->createTicket('en_espera_usuario', 'usuario1', 'adminqa');

		$service->emit('ticket_assigned', $ticket);

		self::assertSame(['adminqa'], $notifiedUsers);
	}

	private function createService(NotificationPreferenceMapper $mapper, RoleService $roleService, ?IManager $manager = null, ?IMailer $mailer = null): NotificationService {
		return new NotificationService(
			$mapper,
			new NotificationMailBuilder(),
			new NotificationRecipientResolver(),
			$roleService,
			$this->createMock(IUserManager::class),
			$manager ?? $this->createMock(IManager::class),
			$mailer ?? $this->createMock(IMailer::class),
			$this->createMock(IFactory::class),
			$this->createMock(IURLGenerator::class),
		);
	}

	private function createTicket(string $status, string $creatorUid, ?string $assignedUserUid): Ticket {
		$ticket = new Ticket();
		$ticket->setId(2);
		$ticket->setNumber('2026-000002');
		$ticket->setStatus($status);
		$ticket->setTitle('Territorial y Legal');
		$ticket->setCreatorUid($creatorUid);
		$ticket->setAssignedUserUid($assignedUserUid);
		return $ticket;
	}

	private function createPreference(string $scopeType, string $scopeId, string $eventName, string $channel, bool $enabled): NotificationPreference {
		$entity = new NotificationPreference();
		$entity->setScopeType($scopeType);
		$entity->setScopeId($scopeId);
		$entity->setEventName($eventName);
		$entity->setChannel($channel);
		$entity->setEnabled($enabled);
		return $entity;
	}
}