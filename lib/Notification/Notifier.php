<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Notification;

use OCA\ConsultasLegales\AppInfo\Application;
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
		$subject = $notification->getSubject() ?: 'ticket_updated';
		$params = $notification->getSubjectParameters();
		$number = $params['number'] ?? '';
		$title = $params['title'] ?? '';
		$statusLabel = $this->getStatusLabel((string) ($params['status'] ?? ''));
		$recipientRole = (string) ($params['recipientRole'] ?? 'watcher');

		switch ($subject) {
			case 'ticket_created':
				if ($recipientRole === 'assignee') {
					$notification->setParsedSubject($l->t('Consulta %1$s asignada: %2$s', [$number, $title]));
					$notification->setParsedMessage($l->t('Se le ha asignado una nueva consulta legal.'));
					break;
				}

				$notification->setParsedSubject($l->t('Consulta %1$s creada: %2$s', [$number, $title]));
				$notification->setParsedMessage($l->t('Su consulta legal se ha registrado correctamente.'));
				break;

			case 'ticket_unassigned_created':
				$notification->setParsedSubject($l->t('Consulta %1$s sin asignar: %2$s', [$number, $title]));
				$notification->setParsedMessage($l->t('Se ha creado una nueva consulta legal sin asignación.'));
				break;

			case 'ticket_assigned':
				$notification->setParsedSubject($l->t('Consulta %1$s asignada: %2$s', [$number, $title]));
				$notification->setParsedMessage($l->t('Se le ha asignado esta consulta legal.'));
				break;

			case 'ticket_waiting_for_creator':
				$notification->setParsedSubject($l->t('Consulta %1$s pendiente de su respuesta: %2$s', [$number, $title]));
				$notification->setParsedMessage($l->t('Soporte espera su respuesta en esta consulta legal.'));
				break;

			case 'ticket_group_assigned':
				$notification->setParsedSubject($l->t('Consulta %1$s asignada a su grupo: %2$s', [$number, $title]));
				$notification->setParsedMessage($l->t('Se ha asignado una consulta legal a uno de sus grupos.'));
				break;

			case 'ticket_status_changed':
				$notification->setParsedSubject($l->t('Estado actualizado en la consulta %1$s: %2$s', [$number, $title]));
				$notification->setParsedMessage($l->t('La consulta legal ha cambiado de estado a %1$s.', [$statusLabel]));
				break;

			case 'ticket_resolved':
				$notification->setParsedSubject($l->t('Consulta %1$s resuelta: %2$s', [$number, $title]));
				$notification->setParsedMessage($l->t('La consulta legal ha quedado resuelta.'));
				break;

			case 'ticket_public_reply':
				$notification->setParsedSubject($l->t('Nueva respuesta en la consulta %1$s: %2$s', [$number, $title]));
				$notification->setParsedMessage($l->t('Hay un nuevo comentario en la consulta legal.'));
				break;

			case 'ticket_update':
			case 'ticket_updated':
			default:
				$notification->setParsedSubject($l->t('Consulta %1$s actualizada: %2$s', [$number, $title]));
				$notification->setParsedMessage($l->t('Se ha actualizado la informacion de la consulta legal.'));
				break;
		}

		return $notification;
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