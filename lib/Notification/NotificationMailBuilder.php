<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Notification;

use OCA\ConsultasLegales\Db\Ticket;
use OCP\IL10N;

class NotificationMailBuilder {
	public function buildSubject(IL10N $l, string $eventName, Ticket $ticket, string $recipientRole): string {
		return match ($eventName) {
			'ticket_created' => $recipientRole === 'assignee'
				? $l->t('Nueva consulta asignada: %1$s: %2$s', [$ticket->getNumber(), $ticket->getTitle()])
				: $l->t('Consulta %1$s creada: %2$s', [$ticket->getNumber(), $ticket->getTitle()]),
			'ticket_unassigned_created' => $l->t('Consulta %1$s sin asignar: %2$s', [$ticket->getNumber(), $ticket->getTitle()]),
			'ticket_assigned' => $l->t('Consulta %1$s asignada: %2$s', [$ticket->getNumber(), $ticket->getTitle()]),
			'ticket_waiting_for_creator' => $l->t('Consulta %1$s pendiente de su respuesta: %2$s', [$ticket->getNumber(), $ticket->getTitle()]),
			'ticket_group_assigned' => $l->t('Consulta %1$s asignada a su grupo: %2$s', [$ticket->getNumber(), $ticket->getTitle()]),
			'ticket_status_changed' => $l->t('Estado actualizado en la consulta %1$s: %2$s', [$ticket->getNumber(), $ticket->getTitle()]),
			'ticket_resolved' => $l->t('Consulta %1$s resuelta: %2$s', [$ticket->getNumber(), $ticket->getTitle()]),
			'ticket_public_reply' => $l->t('Nueva respuesta en la consulta %1$s: %2$s', [$ticket->getNumber(), $ticket->getTitle()]),
			default => $l->t('Consulta %1$s actualizada: %2$s', [$ticket->getNumber(), $ticket->getTitle()]),
		};
	}

	public function buildBody(IL10N $l, string $eventName, Ticket $ticket, string $recipientRole, string $link): string {
		$statusLabel = $this->getStatusLabel((string) $ticket->getStatus());

		$body = match ($eventName) {
			'ticket_created' => $recipientRole === 'assignee'
				? $l->t('Se le ha asignado una nueva consulta legal.')
				: $l->t('Su consulta legal se ha registrado correctamente.'),
			'ticket_unassigned_created' => $l->t('Se ha creado una nueva consulta legal sin asignación.'),
			'ticket_assigned' => $l->t('Se le ha asignado esta consulta legal.'),
			'ticket_waiting_for_creator' => $l->t('Soporte espera su respuesta en esta consulta legal.'),
			'ticket_group_assigned' => $l->t('Se ha asignado una consulta legal a uno de sus grupos.'),
			'ticket_status_changed' => $l->t('La consulta legal ha cambiado de estado a %1$s.', [$statusLabel]),
			'ticket_resolved' => $l->t('La consulta legal ha quedado resuelta.'),
			'ticket_public_reply' => $l->t('Hay un nuevo comentario en la consulta legal.'),
			default => $l->t('Se ha actualizado la consulta legal.'),
		};

		return $body . "\n\n" . $l->t('Abrir ticket: %1$s', [$link]);
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