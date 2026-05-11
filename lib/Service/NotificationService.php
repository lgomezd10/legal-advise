<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Service;

use OCA\ConsultasLegales\AppInfo\Application;
use OCA\ConsultasLegales\Db\NotificationPreferenceMapper;
use OCA\ConsultasLegales\Db\Ticket;
use OCA\ConsultasLegales\Notification\NotificationMailBuilder;
use OCA\ConsultasLegales\Notification\NotificationPolicy;
use OCA\ConsultasLegales\Notification\NotificationRecipientResolver;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Notification\IManager;

class NotificationService {
	public function __construct(
		private readonly NotificationPreferenceMapper $preferenceMapper,
		private readonly NotificationMailBuilder $notificationMailBuilder,
		private readonly NotificationRecipientResolver $notificationRecipientResolver,
		private readonly RoleService $roleService,
		private readonly IUserManager $userManager,
		private readonly IManager $notificationManager,
		private readonly IMailer $mailer,
		private readonly IFactory $l10nFactory,
		private readonly IURLGenerator $urlGenerator,
	) {
	}

	public function getPreferencesForUser(string $uid): array {
		$roles = $this->roleService->getEffectiveRoles($uid);
		$basePreferences = $this->resolveBaseChannelPreferencesForUser($roles);
		$preferences = $this->resolveChannelPreferencesForUser($uid, $roles, $basePreferences);

		$items = [];
		foreach (NotificationPolicy::getSupportedEventsForRoles($roles) as $eventName) {
			$nextcloudEnabled = $basePreferences[$eventName][NotificationPolicy::CHANNEL_NEXTCLOUD] ?? false;
			$mailEnabled = $basePreferences[$eventName][NotificationPolicy::CHANNEL_MAIL] ?? false;
			if (!NotificationPolicy::isUserConfigurable($nextcloudEnabled, $mailEnabled)) {
				continue;
			}

			$items[] = [
				'scopeId' => $uid,
				'eventName' => $eventName,
				'deliveryMode' => NotificationPolicy::resolvePersonalDeliveryModeForRoles(
					$roles,
					$preferences[$eventName][NotificationPolicy::CHANNEL_NEXTCLOUD] ?? false,
					$preferences[$eventName][NotificationPolicy::CHANNEL_MAIL] ?? false,
				),
			];
		}

		return $items;
	}

	public function updateUserPreferences(string $uid, array $rows): array {
		$roles = $this->roleService->getEffectiveRoles($uid);
		$basePreferences = $this->resolveBaseChannelPreferencesForUser($roles);
		$supportedEvents = array_values(array_filter(
			NotificationPolicy::getSupportedEventsForRoles($roles),
			fn (string $eventName): bool => NotificationPolicy::isUserConfigurable(
				$basePreferences[$eventName][NotificationPolicy::CHANNEL_NEXTCLOUD] ?? false,
				$basePreferences[$eventName][NotificationPolicy::CHANNEL_MAIL] ?? false,
			),
		));
		foreach ($this->preferenceMapper->findByMany(['scope_type' => 'user', 'scope_id' => $uid]) as $existing) {
			$this->preferenceMapper->delete($existing);
		}

		foreach ($rows as $row) {
			$eventName = trim((string) ($row['eventName'] ?? ''));
			if (!in_array($eventName, $supportedEvents, true)) {
				continue;
			}

			$deliveryMode = NotificationPolicy::normalizePersonalDeliveryModeForRoles($roles, $row['deliveryMode'] ?? null);
			$pref = new \OCA\ConsultasLegales\Db\NotificationPreference();
			$pref->setScopeType('user');
			$pref->setScopeId($uid);
			$pref->setEventName($eventName);
			$pref->setChannel(NotificationPolicy::CHANNEL_NEXTCLOUD);
			$pref->setEnabled(in_array($deliveryMode, [NotificationPolicy::DELIVERY_NEXTCLOUD, NotificationPolicy::DELIVERY_BOTH], true));
			$this->preferenceMapper->insert($pref);

			$mailPref = new \OCA\ConsultasLegales\Db\NotificationPreference();
			$mailPref->setScopeType('user');
			$mailPref->setScopeId($uid);
			$mailPref->setEventName($eventName);
			$mailPref->setChannel(NotificationPolicy::CHANNEL_MAIL);
			$mailPref->setEnabled($deliveryMode === NotificationPolicy::DELIVERY_BOTH);
			$this->preferenceMapper->insert($mailPref);
		}

		return $this->getPreferencesForUser($uid);
	}

	public function emit(string $eventName, Ticket $ticket, array $extraRecipients = [], array $context = [], bool $includeDefaultRecipients = true): void {
		$defaultRecipients = $includeDefaultRecipients ? $this->notificationRecipientResolver->resolveDefaultRecipients($eventName, $ticket, $context) : [];
		$recipients = array_unique(array_filter(array_merge($defaultRecipients, $extraRecipients)));
		foreach ($recipients as $uid) {
			$preferences = $this->resolveChannelPreferencesForUser($uid);
			if (($preferences[$eventName][NotificationPolicy::CHANNEL_NEXTCLOUD] ?? false) === true) {
				$this->safeDispatch(function () use ($uid, $eventName, $ticket, $context): void {
					$this->sendNextcloud($uid, $eventName, $ticket, $context);
				});
			}

			if (($preferences[$eventName][NotificationPolicy::CHANNEL_MAIL] ?? false) === true) {
				$this->safeDispatch(function () use ($uid, $eventName, $ticket, $context): void {
					$this->sendMail($uid, $eventName, $ticket, $context);
				});
			}
		}
	}

	private function safeDispatch(callable $dispatch): void {
		try {
			$dispatch();
		} catch (\Throwable) {
		}
	}

	private function sendNextcloud(string $uid, string $eventName, Ticket $ticket, array $context): void {
		$recipientRole = $this->notificationRecipientResolver->resolveRecipientRole($uid, $ticket);
		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($uid)
			->setDateTime(new \DateTime())
			->setObject('ticket', (string) $ticket->getId())
			->setSubject($eventName, array_merge([
				'number' => $ticket->getNumber(),
				'title' => $ticket->getTitle(),
				'status' => $ticket->getStatus(),
				'recipientRole' => $recipientRole,
			], $context))
			->setLink($this->buildNotificationLink($uid, $ticket, $recipientRole, false));

		$this->notificationManager->notify($notification);
	}

	private function sendMail(string $uid, string $eventName, Ticket $ticket, array $context): void {
		$user = $this->userManager->get($uid);
		if ($user === null || !method_exists($user, 'getEMailAddress')) {
			return;
		}

		$email = $user->getEMailAddress();
		if ($email === null || $email === '') {
			return;
		}

		$l = $this->l10nFactory->get(Application::APP_ID);
		$recipientRole = $this->notificationRecipientResolver->resolveRecipientRole($uid, $ticket);
		$link = $this->buildNotificationLink($uid, $ticket, $recipientRole, true);
		$message = $this->mailer->createMessage();
		$message->setTo([$email]);
		$message->setSubject($this->notificationMailBuilder->buildSubject($l, $eventName, $ticket, $recipientRole));
		$message->setPlainBody($this->notificationMailBuilder->buildBody($l, $eventName, $ticket, $recipientRole, $link));
		$this->mailer->send($message);
	}

	private function buildNotificationLink(string $uid, Ticket $ticket, string $recipientRole, bool $absolute): string {
		$baseRoute = '/mis-incidencias/' . $ticket->getId() . '/completo';

		if ($recipientRole === 'assignee') {
			$baseRoute = '/soporte/' . $ticket->getId() . '/completo';
		} elseif ($recipientRole === 'watcher') {
			$roles = $this->roleService->getEffectiveRoles($uid);
			if (in_array(RoleService::SUPPORT, $roles, true) || in_array(RoleService::ADMIN, $roles, true)) {
				$baseRoute = '/soporte/' . $ticket->getId() . '/completo';
			}
		}

		$appRoute = $absolute
			? $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.page.index')
			: $this->urlGenerator->linkToRoute(Application::APP_ID . '.page.index');

		return $appRoute . '#' . $baseRoute;
	}

	private function resolveChannelPreferencesForUser(string $uid, ?array $roles = null, ?array $resolved = null): array {
		$roles ??= $this->roleService->getEffectiveRoles($uid);
		$resolved ??= $this->resolveBaseChannelPreferencesForUser($roles);
		$all = array_map(static fn ($row) => $row->jsonSerialize(), $this->preferenceMapper->findAllOrdered('scope_id', 'ASC'));

		$userRows = array_values(array_filter($all, static function (array $row) use ($uid): bool {
			return ($row['scopeType'] ?? '') === 'user'
				&& ($row['scopeId'] ?? '') === $uid
				&& in_array((string) ($row['eventName'] ?? ''), NotificationPolicy::getSupportedEvents(), true);
		}));

		$userRowsByEvent = [];
		foreach ($userRows as $row) {
			$userRowsByEvent[(string) $row['eventName']][] = $row;
		}

		foreach (NotificationPolicy::getSupportedEvents() as $eventName) {
			$eventRows = $userRowsByEvent[$eventName] ?? [];
			if ($eventRows === []) {
				$resolved[$eventName] = $this->applyDefaultUserDeliveryMode($roles, $eventName, $resolved[$eventName]);
				continue;
			}

			foreach ($eventRows as $row) {
				$channel = (string) ($row['channel'] ?? '');
				if (!isset($resolved[$eventName][$channel])) {
					continue;
				}

				$resolved[$eventName][$channel] = $resolved[$eventName][$channel] && (bool) ($row['enabled'] ?? false);
			}
		}

		return $resolved;
	}

	private function applyDefaultUserDeliveryMode(array $roles, string $eventName, array $channels): array {
		$deliveryMode = NotificationPolicy::resolveDefaultUserDeliveryModeForRoles(
			$roles,
			$eventName,
			(bool) ($channels[NotificationPolicy::CHANNEL_NEXTCLOUD] ?? false),
			(bool) ($channels[NotificationPolicy::CHANNEL_MAIL] ?? false),
		);

		if ($deliveryMode === null) {
			return $channels;
		}

		return [
			NotificationPolicy::CHANNEL_NEXTCLOUD => in_array($deliveryMode, [NotificationPolicy::DELIVERY_NEXTCLOUD, NotificationPolicy::DELIVERY_BOTH], true)
				&& (bool) ($channels[NotificationPolicy::CHANNEL_NEXTCLOUD] ?? false),
			NotificationPolicy::CHANNEL_MAIL => $deliveryMode === NotificationPolicy::DELIVERY_BOTH
				&& (bool) ($channels[NotificationPolicy::CHANNEL_MAIL] ?? false),
		];
	}

	private function resolveBaseChannelPreferencesForUser(array $roles): array {
		$resolved = [];
		$all = array_map(static fn ($row) => $row->jsonSerialize(), $this->preferenceMapper->findAllOrdered('scope_id', 'ASC'));

		foreach (NotificationPolicy::getSupportedEvents() as $eventName) {
			$resolved[$eventName] = [
				NotificationPolicy::CHANNEL_NEXTCLOUD => false,
				NotificationPolicy::CHANNEL_MAIL => false,
			];

			foreach ($roles as $role) {
				$resolved[$eventName][NotificationPolicy::CHANNEL_NEXTCLOUD] = $resolved[$eventName][NotificationPolicy::CHANNEL_NEXTCLOUD]
					|| $this->getBaseChannelEnabledForProfile($all, $role, $eventName, NotificationPolicy::CHANNEL_NEXTCLOUD);
				$resolved[$eventName][NotificationPolicy::CHANNEL_MAIL] = $resolved[$eventName][NotificationPolicy::CHANNEL_MAIL]
					|| $this->getBaseChannelEnabledForProfile($all, $role, $eventName, NotificationPolicy::CHANNEL_MAIL);
			}
		}

		return $resolved;
	}

	private function getBaseChannelEnabledForProfile(array $allRows, string $profile, string $eventName, string $channel): bool {
		foreach ($allRows as $row) {
			if (($row['scopeType'] ?? '') !== 'profile') {
				continue;
			}

			if (($row['scopeId'] ?? '') !== $profile || ($row['eventName'] ?? '') !== $eventName || ($row['channel'] ?? '') !== $channel) {
				continue;
			}

			return (bool) ($row['enabled'] ?? false);
		}

		return NotificationPolicy::getDefaultChannelEnabledForProfile($profile, $eventName, $channel);
	}

}