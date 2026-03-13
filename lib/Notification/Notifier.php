<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Notification;

use OCA\Gestion_incidencias\AppInfo\Application;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

class Notifier implements INotifier {
	public function __construct(private readonly IFactory $l10nFactory) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return $this->l10nFactory->get(Application::APP_ID)->t('Consultas Legales');
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		$l = $this->l10nFactory->get(Application::APP_ID, $languageCode);
		$subject = $notification->getSubject() ?: 'ticket_update';
		$params = $notification->getSubjectParameters();

		if ($subject === 'ticket_update') {
			$number = $params['number'] ?? '';
			$title = $params['title'] ?? '';
			$notification->setParsedSubject($l->t('Consulta %1$s actualizada: %2$s', [$number, $title]));
		}

		if ($notification->getParsedMessage() === '') {
			$notification->setParsedMessage($l->t('Hay actividad nueva en una consulta legal asignada o seguida por usted.'));
		}

		return $notification;
	}
}