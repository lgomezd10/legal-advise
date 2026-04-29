<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Notification;

use OCA\ConsultasLegales\Db\Ticket;
use OCA\ConsultasLegales\Notification\NotificationMailBuilder;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

class NotificationMailBuilderTest extends TestCase {
	public function testBuildSubjectUsesAssignedCopyForAssignee(): void {
		$builder = new NotificationMailBuilder();
		$l10n = $this->createPassthroughL10n();
		$ticket = $this->createTicket('asignado');

		self::assertSame(
			'Nueva consulta asignada: 2026-000088: Ticket de prueba',
			$builder->buildSubject($l10n, 'ticket_created', $ticket, 'assignee'),
		);
	}

	public function testBuildBodyUsesStatusLabelAndLink(): void {
		$builder = new NotificationMailBuilder();
		$l10n = $this->createPassthroughL10n();
		$ticket = $this->createTicket('resuelto');

		self::assertSame(
			"La consulta legal ha cambiado de estado a Resuelto.\n\nAbrir ticket: https://example.test/#/soporte/88/completo",
			$builder->buildBody($l10n, 'ticket_status_changed', $ticket, 'assignee', 'https://example.test/#/soporte/88/completo'),
		);
	}

	private function createPassthroughL10n(): IL10N {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static function (string $text, array $parameters = []): string {
			$result = $text;
			foreach ($parameters as $index => $parameter) {
				$result = str_replace('%' . ($index + 1) . '$s', (string) $parameter, $result);
			}

			return $result;
		});

		return $l10n;
	}

	private function createTicket(string $status): Ticket {
		$ticket = new Ticket();
		$ticket->setId(88);
		$ticket->setNumber('2026-000088');
		$ticket->setTitle('Ticket de prueba');
		$ticket->setStatus($status);
		return $ticket;
	}
}