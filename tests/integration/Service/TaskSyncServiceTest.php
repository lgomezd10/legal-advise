<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Integration\Service;

use OCA\ConsultasLegales\Db\TaskSyncMapper;
use OCA\ConsultasLegales\Db\Ticket;
use OCA\ConsultasLegales\Service\CatalogService;
use OCA\ConsultasLegales\Service\TaskSyncService;
use OCP\App\IAppManager;
use OCP\Calendar\IManager;
use PHPUnit\Framework\TestCase;

class TaskSyncServiceTest extends TestCase {
	public function testDoesNothingWhenTasksConfigIsDisabled(): void {
		$mapper = $this->createMock(TaskSyncMapper::class);
		$catalogService = $this->createMock(CatalogService::class);
		$catalogService->method('getTaskConfig')->willReturn(['enabled' => false]);

		$appManager = $this->createMock(IAppManager::class);
		$calendarManager = $this->createMock(IManager::class);

		$service = new TaskSyncService($mapper, $catalogService, $appManager, $calendarManager);

		$ticket = new Ticket();
		$ticket->setId(10);
		$ticket->setNumber('2026-000010');
		$ticket->setTitle('Prueba');
		$ticket->setUserDescription('Descripcion');
		$ticket->setStatus('nuevo');
		$ticket->setAssignedUserUid('agent-1');

		self::assertNull($service->syncTicket($ticket));
	}
}