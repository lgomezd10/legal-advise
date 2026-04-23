<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Service;

use OCA\ConsultasLegales\AppInfo\Application;
use OCA\ConsultasLegales\Db\NotificationPreferenceMapper;
use OCA\ConsultasLegales\Db\Ticket;
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
			$pref = new \OCA\ConsultasLegales\Db\NotificationPreference();
			$pref->setScopeType('user');
			$pref->setScopeId($uid);
			$pref->setEventName((string) $row['eventName']);
			$pref->setChannel((string) $row['channel']);
			$pref->setEnabled((bool) $row['enabled']);
			$this->preferenceMapper->insert($pref);
		}

		return $this->getPreferencesForUser($uid);
	}

	public function emit(string $eventName, Ticket $ticket, array $extraRecipients = [], array $context = []): void {
		$recipients = array_unique(array_filter(array_merge([$ticket->getCreatorUid(), $ticket->getAssignedUserUid()], $extraRecipients)));
		foreach ($recipients as $uid) {
			$this->sendNextcloud($uid, $eventName, $ticket, $context);
			$this->sendMail($uid, $eventName, $ticket, $context);
		}
	}

	private function sendNextcloud(string $uid, string $eventName, Ticket $ticket, array $context): void {
		$recipientRole = $this->resolveRecipientRole($uid, $ticket);
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
			->setLink($this->buildNotificationLink($uid, $ticket, $recipientRole));

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
		$recipientRole = $this->resolveRecipientRole($uid, $ticket);
		$message = $this->mailer->createMessage();
		$message->setTo([$email]);
		$message->setSubject($this->buildMailSubject($l, $eventName, $ticket, $recipientRole));
		$message->setPlainBody($this->buildMailBody($l, $eventName, $ticket, $recipientRole, $context));
		$this->mailer->send($message);
	}

	private function resolveRecipientRole(string $uid, Ticket $ticket): string {
		if ($ticket->getCreatorUid() === $uid) {
			return 'creator';
		}

		if ($ticket->getAssignedUserUid() === $uid) {
			return 'assignee';
		}

		return 'watcher';
	}

	private function buildNotificationLink(string $uid, Ticket $ticket, string $recipientRole): string {
		$baseRoute = '/mis-incidencias/' . $ticket->getId() . '/completo';

		if ($recipientRole === 'assignee') {
			$baseRoute = '/soporte/' . $ticket->getId() . '/completo';
		} elseif ($recipientRole === 'watcher') {
			$roles = $this->roleService->getEffectiveRoles($uid);
			if (in_array(RoleService::SUPPORT, $roles, true) || in_array(RoleService::ADMIN, $roles, true)) {
				$baseRoute = '/soporte/' . $ticket->getId() . '/completo';
			}
		}

		return $this->urlGenerator->linkToRouteAbsolute('legal_advice.page.index') . '#' . $baseRoute;
	}

	private function buildMailSubject($l, string $eventName, Ticket $ticket, string $recipientRole): string {
		return match ($eventName) {
			'ticket_created' => $recipientRole === 'assignee'
				? $l->t('Nueva consulta asignada: %1$s', [$ticket->getNumber()])
				: $l->t('Consulta %1$s creada: %2$s', [$ticket->getNumber(), $ticket->getTitle()]),
			'ticket_assigned' => $l->t('Consulta %1$s asignada: %2$s', [$ticket->getNumber(), $ticket->getTitle()]),
			'ticket_status_changed' => $l->t('Estado actualizado en la consulta %1$s: %2$s', [$ticket->getNumber(), $ticket->getTitle()]),
			'ticket_resolved' => $l->t('Consulta %1$s resuelta: %2$s', [$ticket->getNumber(), $ticket->getTitle()]),
			'ticket_public_reply' => $l->t('Nueva respuesta en la consulta %1$s: %2$s', [$ticket->getNumber(), $ticket->getTitle()]),
			default => $l->t('Consulta %1$s actualizada: %2$s', [$ticket->getNumber(), $ticket->getTitle()]),
		};
	}

	private function buildMailBody($l, string $eventName, Ticket $ticket, string $recipientRole, array $context): string {
		$statusLabel = $this->getStatusLabel((string) $ticket->getStatus());

		return match ($eventName) {
			'ticket_created' => $recipientRole === 'assignee'
				? $l->t('Se le ha asignado una nueva consulta legal. Estado actual: %1$s.', [$statusLabel])
				: $l->t('Su consulta legal se ha registrado correctamente. Estado actual: %1$s.', [$statusLabel]),
			'ticket_assigned' => $recipientRole === 'assignee'
				? $l->t('Se le ha asignado esta consulta legal. Estado actual: %1$s.', [$statusLabel])
				: $l->t('La consulta legal ha sido asignada para su gestion. Estado actual: %1$s.', [$statusLabel]),
			'ticket_status_changed' => $l->t('La consulta legal ha cambiado de estado a %1$s.', [$statusLabel]),
			'ticket_resolved' => $l->t('La consulta legal ha quedado resuelta. Estado actual: %1$s.', [$statusLabel]),
			'ticket_public_reply' => $l->t('Hay un nuevo comentario en la consulta legal. Estado actual: %1$s.', [$statusLabel]),
			default => $l->t('Se ha actualizado la consulta legal. Estado actual: %1$s.', [$statusLabel]),
		};
	}

	private function getStatusLabel(string $status): string {
		return match ($status) {
			'nuevo' => 'Nuevo',
			'asignado' => 'Asignado',
			'en_progreso' => 'En progreso',
			'resuelto' => 'Resuelto',
			'cerrado' => 'Cerrado',
			default => $status,
		};
	}
}