<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Service;

use OCA\Gestion_incidencias\AppInfo\Application;
use OCA\Gestion_incidencias\Db\NotificationPreferenceMapper;
use OCA\Gestion_incidencias\Db\Ticket;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Notification\IManager;

class NotificationService {
	public function __construct(
		private readonly NotificationPreferenceMapper $preferenceMapper,
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
		$all = array_map(static fn ($row) => $row->jsonSerialize(), $this->preferenceMapper->findAllOrdered('scope_id', 'ASC'));

		return array_values(array_filter($all, static function (array $row) use ($uid, $roles): bool {
			return ($row['scopeType'] === 'user' && $row['scopeId'] === $uid)
				|| ($row['scopeType'] === 'profile' && in_array($row['scopeId'], $roles, true));
		}));
	}

	public function updateUserPreferences(string $uid, array $rows): array {
		foreach ($this->preferenceMapper->findByMany(['scope_type' => 'user', 'scope_id' => $uid]) as $existing) {
			$this->preferenceMapper->delete($existing);
		}

		foreach ($rows as $row) {
			$pref = new \OCA\Gestion_incidencias\Db\NotificationPreference();
			$pref->setScopeType('user');
			$pref->setScopeId($uid);
			$pref->setEventName((string) $row['eventName']);
			$pref->setChannel((string) $row['channel']);
			$pref->setEnabled((bool) $row['enabled']);
			$this->preferenceMapper->insert($pref);
		}

		return $this->getPreferencesForUser($uid);
	}

	public function emit(string $eventName, Ticket $ticket, array $extraRecipients = []): void {
		$recipients = array_unique(array_filter(array_merge([$ticket->getCreatorUid(), $ticket->getAssignedUserUid()], $extraRecipients)));
		foreach ($recipients as $uid) {
			$this->sendNextcloud($uid, $eventName, $ticket);
			$this->sendMail($uid, $eventName, $ticket);
		}
	}

	private function sendNextcloud(string $uid, string $eventName, Ticket $ticket): void {
		$notification = $this->notificationManager->createNotification();
		$notification->setApp(Application::APP_ID)
			->setUser($uid)
			->setDateTime(new \DateTime())
			->setObject('ticket', (string) $ticket->getId())
			->setSubject('ticket_update', ['number' => $ticket->getNumber(), 'title' => $ticket->getTitle(), 'event' => $eventName])
			->setLink($this->urlGenerator->linkToRouteAbsolute('legal_advice.page.index') . '#/tickets/' . $ticket->getId());

		$this->notificationManager->notify($notification);
	}

	private function sendMail(string $uid, string $eventName, Ticket $ticket): void {
		$user = $this->userManager->get($uid);
		if ($user === null || !method_exists($user, 'getEMailAddress')) {
			return;
		}

		$email = $user->getEMailAddress();
		if ($email === null || $email === '') {
			return;
		}

		$l = $this->l10nFactory->get(Application::APP_ID);
		$message = $this->mailer->createMessage();
		$message->setTo([$email]);
		$message->setSubject($l->t('Consulta %1$s: %2$s', [$ticket->getNumber(), $ticket->getTitle()]));
		$message->setPlainBody($l->t('Evento: %1$s. Estado actual: %2$s.', [$eventName, $ticket->getStatus()]));
		$this->mailer->send($message);
	}
}