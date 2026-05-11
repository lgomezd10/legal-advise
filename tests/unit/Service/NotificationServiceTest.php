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
use OCP\IL10N;
use OCP\IUser;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMessage;
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

	public function testNextcloudNotificationsUseRelativeAppLinks(): void {
		$mapper = $this->createMock(NotificationPreferenceMapper::class);
		$mapper->method('findAllOrdered')->willReturn([
			$this->createPreference('profile', RoleService::USER, 'ticket_waiting_for_creator', NotificationPolicy::CHANNEL_NEXTCLOUD, true),
			$this->createPreference('profile', RoleService::USER, 'ticket_waiting_for_creator', NotificationPolicy::CHANNEL_MAIL, false),
		]);

		$roleService = $this->createMock(RoleService::class);
		$roleService->method('getEffectiveRoles')->with('usuario1')->willReturn([RoleService::USER]);

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->expects(self::once())
			->method('linkToRoute')
			->with('legal_advice.page.index')
			->willReturn('/index.php/apps/legal_advice');
		$urlGenerator->expects(self::never())->method('linkToRouteAbsolute');

		$link = null;
		$manager = $this->createMock(IManager::class);
		$manager->expects(self::once())
			->method('createNotification')
			->willReturnCallback(function () use (&$link) {
				$notification = $this->createMock(INotification::class);
				$notification->method('setApp')->willReturnSelf();
				$notification->method('setDateTime')->willReturnSelf();
				$notification->method('setObject')->willReturnSelf();
				$notification->method('setSubject')->willReturnSelf();
				$notification->method('setUser')->willReturnSelf();
				$notification->method('setLink')->willReturnCallback(function (string $value) use (&$link, $notification) {
					$link = $value;
					return $notification;
				});
				return $notification;
			});
		$manager->expects(self::once())->method('notify');

		$service = $this->createService($mapper, $roleService, $manager, null, null, $urlGenerator);
		$ticket = $this->createTicket('en_espera_usuario', 'usuario1', 'soporte1');

		$service->emit('ticket_waiting_for_creator', $ticket);

		self::assertSame('/index.php/apps/legal_advice#/mis-incidencias/2/completo', $link);
	}

	public function testMailNotificationsUseAbsoluteAppLinks(): void {
		$mapper = $this->createMock(NotificationPreferenceMapper::class);
		$mapper->method('findAllOrdered')->willReturn([
			$this->createPreference('profile', RoleService::SUPPORT, 'ticket_status_changed', NotificationPolicy::CHANNEL_NEXTCLOUD, false),
			$this->createPreference('profile', RoleService::SUPPORT, 'ticket_status_changed', NotificationPolicy::CHANNEL_MAIL, true),
		]);

		$roleService = $this->createMock(RoleService::class);
		$roleService->method('getEffectiveRoles')->with('soporte1')->willReturn([RoleService::SUPPORT]);

		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn('soporte1@example.test');

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->with('soporte1')->willReturn($user);

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->expects(self::once())
			->method('linkToRouteAbsolute')
			->with('legal_advice.page.index')
			->willReturn('https://cloud.example.test/index.php/apps/legal_advice');
		$urlGenerator->expects(self::never())->method('linkToRoute');

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static function (string $text, $parameters = []): string {
			if (!is_array($parameters) || $parameters === []) {
				return $text;
			}

			return vsprintf(str_replace(['%1$s', '%2$s'], ['%s', '%s'], $text), array_values($parameters));
		});

		$l10nFactory = $this->createMock(IFactory::class);
		$l10nFactory->method('get')->with('legal_advice')->willReturn($l10n);

		$message = $this->createMock(IMessage::class);
		$message->method('setTo')->willReturnSelf();
		$message->method('setSubject')->willReturnSelf();
		$plainBody = null;
		$message->method('setPlainBody')->willReturnCallback(function (string $body) use (&$plainBody, $message) {
			$plainBody = $body;
			return $message;
		});

		$mailer = $this->createMock(IMailer::class);
		$mailer->method('createMessage')->willReturn($message);
		$mailer->expects(self::once())->method('send')->with($message);

		$service = $this->createService($mapper, $roleService, null, $mailer, $userManager, $urlGenerator, $l10nFactory);
		$ticket = $this->createTicket('asignado', 'usuario1', 'soporte1');

		$service->emit('ticket_status_changed', $ticket, ['soporte1'], [], false);

		self::assertIsString($plainBody);
		self::assertStringContainsString('https://cloud.example.test/index.php/apps/legal_advice#/soporte/2/completo', $plainBody);
	}

	public function testNotificationDispatchIgnoresMailFailures(): void {
		$mapper = $this->createMock(NotificationPreferenceMapper::class);
		$mapper->method('findAllOrdered')->willReturn([
			$this->createPreference('profile', RoleService::SUPPORT, 'ticket_status_changed', NotificationPolicy::CHANNEL_NEXTCLOUD, true),
			$this->createPreference('profile', RoleService::SUPPORT, 'ticket_status_changed', NotificationPolicy::CHANNEL_MAIL, true),
		]);

		$roleService = $this->createMock(RoleService::class);
		$roleService->method('getEffectiveRoles')->willReturn([RoleService::SUPPORT]);

		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn('soporte1@example.test');

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturn($user);

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRoute')->willReturn('/index.php/apps/legal_advice');
		$urlGenerator->method('linkToRouteAbsolute')->willReturn('https://cloud.example.test/index.php/apps/legal_advice');

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static function (string $text, $parameters = []): string {
			if (!is_array($parameters) || $parameters === []) {
				return $text;
			}

			return vsprintf(str_replace(['%1$s', '%2$s'], ['%s', '%s'], $text), array_values($parameters));
		});

		$l10nFactory = $this->createMock(IFactory::class);
		$l10nFactory->method('get')->willReturn($l10n);

		$manager = $this->createMock(IManager::class);
		$manager->expects(self::once())
			->method('createNotification')
			->willReturnCallback(function () {
				$notification = $this->createMock(INotification::class);
				$notification->method('setApp')->willReturnSelf();
				$notification->method('setDateTime')->willReturnSelf();
				$notification->method('setObject')->willReturnSelf();
				$notification->method('setSubject')->willReturnSelf();
				$notification->method('setLink')->willReturnSelf();
				$notification->method('setUser')->willReturnSelf();
				return $notification;
			});
		$manager->expects(self::once())->method('notify');

		$message = $this->createMock(IMessage::class);
		$message->method('setTo')->willReturnSelf();
		$message->method('setSubject')->willReturnSelf();
		$message->method('setPlainBody')->willReturnSelf();

		$mailer = $this->createMock(IMailer::class);
		$mailer->method('createMessage')->willReturn($message);
		$mailer->expects(self::once())->method('send')->willThrowException(new \RuntimeException('smtp offline'));

		$service = $this->createService($mapper, $roleService, $manager, $mailer, $userManager, $urlGenerator, $l10nFactory);
		$ticket = $this->createTicket('asignado', 'usuario1', 'soporte1');

		$service->emit('ticket_status_changed', $ticket, ['soporte1'], [], false);
		self::addToAssertionCount(1);
	}

	public function testNotificationDispatchIgnoresNextcloudFailuresAndStillSendsMail(): void {
		$mapper = $this->createMock(NotificationPreferenceMapper::class);
		$mapper->method('findAllOrdered')->willReturn([
			$this->createPreference('profile', RoleService::SUPPORT, 'ticket_status_changed', NotificationPolicy::CHANNEL_NEXTCLOUD, true),
			$this->createPreference('profile', RoleService::SUPPORT, 'ticket_status_changed', NotificationPolicy::CHANNEL_MAIL, true),
		]);

		$roleService = $this->createMock(RoleService::class);
		$roleService->method('getEffectiveRoles')->willReturn([RoleService::SUPPORT]);

		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn('soporte1@example.test');

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturn($user);

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRoute')->willReturn('/index.php/apps/legal_advice');
		$urlGenerator->method('linkToRouteAbsolute')->willReturn('https://cloud.example.test/index.php/apps/legal_advice');

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static function (string $text, $parameters = []): string {
			if (!is_array($parameters) || $parameters === []) {
				return $text;
			}

			return vsprintf(str_replace(['%1$s', '%2$s'], ['%s', '%s'], $text), array_values($parameters));
		});

		$l10nFactory = $this->createMock(IFactory::class);
		$l10nFactory->method('get')->willReturn($l10n);

		$manager = $this->createMock(IManager::class);
		$manager->expects(self::once())->method('createNotification')->willThrowException(new \RuntimeException('notify offline'));
		$manager->expects(self::never())->method('notify');

		$message = $this->createMock(IMessage::class);
		$message->method('setTo')->willReturnSelf();
		$message->method('setSubject')->willReturnSelf();
		$message->method('setPlainBody')->willReturnSelf();

		$mailer = $this->createMock(IMailer::class);
		$mailer->method('createMessage')->willReturn($message);
		$mailer->expects(self::once())->method('send')->with($message);

		$service = $this->createService($mapper, $roleService, $manager, $mailer, $userManager, $urlGenerator, $l10nFactory);
		$ticket = $this->createTicket('asignado', 'usuario1', 'soporte1');

		$service->emit('ticket_status_changed', $ticket, ['soporte1'], [], false);
		self::addToAssertionCount(1);
	}

	private function createService(NotificationPreferenceMapper $mapper, RoleService $roleService, ?IManager $manager = null, ?IMailer $mailer = null, ?IUserManager $userManager = null, ?IURLGenerator $urlGenerator = null, ?IFactory $l10nFactory = null): NotificationService {
		return new NotificationService(
			$mapper,
			new NotificationMailBuilder(),
			new NotificationRecipientResolver(),
			$roleService,
			$userManager ?? $this->createMock(IUserManager::class),
			$manager ?? $this->createMock(IManager::class),
			$mailer ?? $this->createMock(IMailer::class),
			$l10nFactory ?? $this->createMock(IFactory::class),
			$urlGenerator ?? $this->createMock(IURLGenerator::class),
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