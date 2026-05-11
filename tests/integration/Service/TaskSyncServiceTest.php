<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Integration\Service;

use OCA\ConsultasLegales\Db\TaskSyncMapper;
use OCA\ConsultasLegales\Db\TaskSync;
use OCA\ConsultasLegales\Db\Ticket;
use OCA\ConsultasLegales\Service\CatalogService;
use OCA\ConsultasLegales\Service\TaskSyncService;
use OCP\App\IAppManager;
use OCP\Calendar\ICreateFromString;
use OCP\Calendar\IManager;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

class TaskSyncServiceTest extends TestCase {
	public function testDoesNothingWhenTasksConfigIsDisabled(): void {
		$mapper = $this->createMock(TaskSyncMapper::class);
		$catalogService = $this->createMock(CatalogService::class);
		$catalogService->method('getTaskConfig')->willReturn(['enabled' => false]);

		$appManager = $this->createMock(IAppManager::class);
		$calendarManager = $this->createMock(IManager::class);

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$service = new TaskSyncService($mapper, $catalogService, $appManager, $calendarManager, $urlGenerator);

		$ticket = new Ticket();
		$ticket->setId(10);
		$ticket->setNumber('2026-000010');
		$ticket->setTitle('Prueba');
		$ticket->setUserDescription('Descripcion');
		$ticket->setStatus('nuevo');
		$ticket->setAssignedUserUid('agent-1');

		self::assertNull($service->syncTicket($ticket));
	}

	public function testIntegrationStatusFallsBackWhenIntegrationDependenciesThrow(): void {
		$mapper = $this->createMock(TaskSyncMapper::class);
		$catalogService = $this->createMock(CatalogService::class);
		$catalogService->method('getTaskConfig')->willThrowException(new \RuntimeException('broken config'));

		$appManager = $this->createMock(IAppManager::class);
		$calendarManager = $this->createMock(IManager::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);

		$service = new TaskSyncService($mapper, $catalogService, $appManager, $calendarManager, $urlGenerator);

		self::assertSame([
			'available' => false,
			'config' => [],
			'degraded' => true,
		], $service->getIntegrationStatus());
	}

	public function testGetSyncForTicketReturnsNullWhenMapperFails(): void {
		$mapper = $this->createMock(TaskSyncMapper::class);
		$mapper->method('findOneBy')->willThrowException(new \RuntimeException('db unavailable'));

		$catalogService = $this->createMock(CatalogService::class);
		$appManager = $this->createMock(IAppManager::class);
		$calendarManager = $this->createMock(IManager::class);
		$urlGenerator = $this->createMock(IURLGenerator::class);

		$service = new TaskSyncService($mapper, $catalogService, $appManager, $calendarManager, $urlGenerator);

		self::assertNull($service->getSyncForTicket(99));
	}

	public function testUpdatesExistingRemoteTaskWhenSyncRecordAlreadyExists(): void {
		$existingSync = new TaskSync();
		$existingSync->setId(9);
		$existingSync->setTicketId(10);
		$existingSync->setAssigneeUid('agent-1');
		$existingSync->setCalendarUri('personal');
		$existingSync->setObjectUri('gi-10-existing.ics');
		$existingSync->setObjectUid('gi-10-existing');

		$mapper = $this->createMock(TaskSyncMapper::class);
		$mapper->method('findOneBy')->with('ticket_id', 10)->willReturn($existingSync);
		$mapper->expects(self::once())
			->method('update')
			->with(self::isInstanceOf(TaskSync::class))
			->willReturnCallback(static fn (TaskSync $sync): TaskSync => $sync);

		$catalogService = $this->createMock(CatalogService::class);
		$catalogService->method('getTaskConfig')->willReturn(['enabled' => true]);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isInstalled')->with('tasks')->willReturn(true);

		$backend = new class {
			public array $existingUris = ['gi-10-existing.ics'];
			public array $updated = [];

			public function getCalendarObject(int $calendarId, string $objectUri): ?array {
				return in_array($objectUri, $this->existingUris, true) ? ['uri' => $objectUri, 'calendarid' => $calendarId] : null;
			}

			public function updateCalendarObject(int $calendarId, string $objectUri, string $calendarData): void {
				$this->updated[] = ['calendarId' => $calendarId, 'objectUri' => $objectUri, 'calendarData' => $calendarData];
			}

			public function deleteCalendarObject(int $calendarId, string $objectUri): void {
			}
		};

		$calendar = new class($backend) implements ICreateFromString {
			private object $backend;

			public function __construct(object $backend) {
				$this->backend = $backend;
			}

			public function getKey(): string {
				return '55';
			}

			public function getUri(): string {
				return 'personal';
			}

			public function getDisplayName(): ?string {
				return 'Personal';
			}

			public function getDisplayColor(): ?string {
				return null;
			}

			public function search(string $pattern, array $searchProperties = [], array $options = [], ?int $limit = null, ?int $offset = null): array {
				return [];
			}

			public function getPermissions(): int {
				return 31;
			}

			public function isDeleted(): bool {
				return false;
			}

			public function createFromString(string $name, string $calendarData): void {
				throw new \RuntimeException('No deberia intentar crear una tarea nueva si ya existe.');
			}
		};

		$calendarManager = $this->createMock(IManager::class);
		$calendarManager->method('getCalendarsForPrincipal')->with('principals/users/agent-1')->willReturn([$calendar]);

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRoute')->with('legal_advice.page.index')->willReturn('/index.php/apps/legal_advice');
		$service = new TaskSyncService($mapper, $catalogService, $appManager, $calendarManager, $urlGenerator);

		$ticket = new Ticket();
		$ticket->setId(10);
		$ticket->setNumber('2026-000010');
		$ticket->setTitle('Prueba');
		$ticket->setUserDescription('Descripcion');
		$ticket->setStatus('asignado');
		$ticket->setAssignedUserUid('agent-1');

		$result = $service->syncTicket($ticket);

		self::assertSame('synced', $result['syncStatus'] ?? null);
		self::assertSame('/index.php/apps/legal_advice#/soporte/10/completo', $result['payload']['url'] ?? null);
		self::assertCount(1, $backend->updated);
		self::assertSame('gi-10-existing.ics', $backend->updated[0]['objectUri']);
	}

	public function testCreatesDedicatedConsultasLegalesListWhenMissing(): void {
		$mapper = $this->createMock(TaskSyncMapper::class);
		$mapper->method('findOneBy')->with('ticket_id', 12)->willReturn(null);
		$mapper->expects(self::once())
			->method('insert')
			->with(self::isInstanceOf(TaskSync::class))
			->willReturnCallback(static function (TaskSync $sync): TaskSync {
				$sync->setId(12);
				return $sync;
			});

		$catalogService = $this->createMock(CatalogService::class);
		$catalogService->method('getTaskConfig')->willReturn(['enabled' => true]);

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isInstalled')->with('tasks')->willReturn(true);

		$backend = new class {
			public array $createdCalendars = [];

			public function getCalendarByUri(string $principalUri, string $calendarUri): ?array {
				foreach ($this->createdCalendars as $calendar) {
					if ($calendar['principalUri'] === $principalUri && $calendar['calendarUri'] === $calendarUri) {
						return ['uri' => $calendarUri];
					}
				}

				return null;
			}

			public function createCalendar(string $principalUri, string $calendarUri, array $properties): int {
				$this->createdCalendars[] = [
					'principalUri' => $principalUri,
					'calendarUri' => $calendarUri,
					'properties' => $properties,
				];
				return 77;
			}

			public function getCalendarObject(int $calendarId, string $objectUri): ?array {
				return null;
			}

			public function updateCalendarObject(int $calendarId, string $objectUri, string $calendarData): void {
			}

			public function deleteCalendarObject(int $calendarId, string $objectUri): void {
			}
		};

		$personalCalendar = new class($backend) implements ICreateFromString {
			private object $backend;

			public function __construct(object $backend) {
				$this->backend = $backend;
			}

			public function getKey(): string {
				return '55';
			}

			public function getUri(): string {
				return 'personal';
			}

			public function getDisplayName(): ?string {
				return 'Personal';
			}

			public function getDisplayColor(): ?string {
				return null;
			}

			public function search(string $pattern, array $searchProperties = [], array $options = [], ?int $limit = null, ?int $offset = null): array {
				return [];
			}

			public function getPermissions(): int {
				return 31;
			}

			public function isDeleted(): bool {
				return false;
			}

			public function createFromString(string $name, string $calendarData): void {
				throw new \RuntimeException('La tarea debe crearse en la lista dedicada, no en Personal.');
			}
		};

		$dedicatedCalendar = new class($backend) implements ICreateFromString {
			private object $backend;
			public array $createdObjects = [];

			public function __construct(object $backend) {
				$this->backend = $backend;
			}

			public function getKey(): string {
				return '77';
			}

			public function getUri(): string {
				return 'consultas-legales';
			}

			public function getDisplayName(): ?string {
				return 'Consultas Legales';
			}

			public function getDisplayColor(): ?string {
				return null;
			}

			public function search(string $pattern, array $searchProperties = [], array $options = [], ?int $limit = null, ?int $offset = null): array {
				return [];
			}

			public function getPermissions(): int {
				return 31;
			}

			public function isDeleted(): bool {
				return false;
			}

			public function createFromString(string $name, string $calendarData): void {
				$this->createdObjects[] = ['name' => $name, 'calendarData' => $calendarData];
			}
		};

		$calendarManager = $this->createMock(IManager::class);
		$calendarManager->expects(self::exactly(2))
			->method('getCalendarsForPrincipal')
			->willReturnOnConsecutiveCalls(
				[$personalCalendar],
				[$dedicatedCalendar],
			);

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRoute')->with('legal_advice.page.index')->willReturn('/index.php/apps/legal_advice');
		$service = new TaskSyncService($mapper, $catalogService, $appManager, $calendarManager, $urlGenerator);

		$ticket = new Ticket();
		$ticket->setId(12);
		$ticket->setNumber('2026-000012');
		$ticket->setTitle('Lista dedicada');
		$ticket->setUserDescription('Debe acabar en Consultas Legales');
		$ticket->setStatus('asignado');
		$ticket->setAssignedUserUid('agent-1');

		$result = $service->syncTicket($ticket);

		self::assertSame('consultas-legales', $result['calendarUri'] ?? null);
		self::assertSame('/index.php/apps/legal_advice#/soporte/12/completo', $result['payload']['url'] ?? null);
		self::assertCount(1, $backend->createdCalendars);
		self::assertSame('Consultas Legales', $backend->createdCalendars[0]['properties']['{DAV:}displayname'] ?? null);
		self::assertCount(1, $dedicatedCalendar->createdObjects);
	}
}