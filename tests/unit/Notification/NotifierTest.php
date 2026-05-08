<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Notification;

use OCA\ConsultasLegales\Notification\Notifier;
use OCP\L10N\IFactory;
use OCP\IL10N;
use OCP\Notification\INotification;
use PHPUnit\Framework\TestCase;

class NotifierTest extends TestCase {
	public function testPrepareFormatsUnassignedCreatedNotification(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static function (string $text, array $parameters = []): string {
			$result = $text;
			foreach ($parameters as $index => $parameter) {
				$result = str_replace('%' . ($index + 1) . '$s', (string) $parameter, $result);
			}

			return $result;
		});

		$l10nFactory = $this->createMock(IFactory::class);
		$l10nFactory->method('get')->willReturn($l10n);

		$notification = $this->createMock(INotification::class);
		$notification->method('getSubject')->willReturn('ticket_unassigned_created');
		$notification->method('getSubjectParameters')->willReturn([
			'number' => '2026-000123',
			'title' => 'Ticket sin asignar',
			'status' => 'nuevo',
			'recipientRole' => 'watcher',
		]);
		$notification->expects(self::once())->method('setParsedSubject')->with('Consulta 2026-000123 sin asignar: Ticket sin asignar')->willReturnSelf();
		$notification->expects(self::once())->method('setParsedMessage')->with('Se ha creado una nueva consulta legal sin asignación.')->willReturnSelf();

		$notifier = new Notifier($l10nFactory);

		self::assertSame($notification, $notifier->prepare($notification, 'es'));
	}
}