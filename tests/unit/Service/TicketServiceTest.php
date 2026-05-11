<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Service;

use OCA\ConsultasLegales\Db\Ticket;
use OCA\ConsultasLegales\Db\Comment;
use OCA\ConsultasLegales\Notification\TicketNotificationPublisher;
use OCA\ConsultasLegales\Service\RichTextSanitizer;
use OCA\ConsultasLegales\Service\RoleService;
use OCA\ConsultasLegales\Service\TicketService;
use OCA\ConsultasLegales\Ticket\TicketStatusPolicy;
use OCP\IGroupManager;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

class TicketServiceTest extends TestCase {
	public function testUpdateDoesNotEmitAssignmentNotificationsWhenOnlyGroupChangesAndThereIsAlreadyAnAssignedUser(): void {
		$ticket = new Ticket();
		$ticket->setId(22);
		$ticket->setNumber('2026-000022');
		$ticket->setCreatorUid('usuario1');
		$ticket->setStatus('asignado');
		$ticket->setTitle('Consulta con soporte');
		$ticket->setAssignedUserUid('soporte1');
		$ticket->setAssignedGroupId('territorial');

		$notificationCalls = [];
		$service = $this->buildTicketServiceForUpdateScenario(
			$ticket,
			['assignedGroupId' => 'territorial_legal'],
			$notificationCalls,
		);

		$service->update('admin', 22, ['assignedGroupId' => 'territorial_legal']);

		self::assertSame([], array_column($notificationCalls, 'eventName'));
	}

	public function testUpdateEmitsGroupNotificationOnlyWhenTicketEndsAssignedToGroupWithoutConcreteUser(): void {
		$ticket = new Ticket();
		$ticket->setId(23);
		$ticket->setNumber('2026-000023');
		$ticket->setCreatorUid('usuario1');
		$ticket->setStatus('nuevo');
		$ticket->setTitle('Consulta solo a grupo');
		$ticket->setAssignedUserUid(null);
		$ticket->setAssignedGroupId('territorial');

		$notificationCalls = [];
		$group = new class {
			public function inGroup(object $user): bool {
				return true;
			}

			public function getUsers(): array {
				return [
					new class {
						public function getUID(): string {
							return 'soporte1';
						}
					},
				];
			}
		};

		$service = $this->buildTicketServiceForUpdateScenario(
			$ticket,
			['assignedGroupId' => 'territorial_legal'],
			$notificationCalls,
			$group,
		);

		$service->update('admin', 23, ['assignedGroupId' => 'territorial_legal']);

		self::assertSame(['ticket_group_assigned'], array_column($notificationCalls, 'eventName'));
		self::assertSame(['soporte1'], $notificationCalls[0]['extraRecipients']);
		self::assertFalse($notificationCalls[0]['includeDefaultRecipients']);
	}

	public function testUpdateEmitsTicketAssignedWhenStatusMovesToEnEsperaUsuario(): void {
		$ticket = new Ticket();
		$ticket->setId(24);
		$ticket->setNumber('2026-000024');
		$ticket->setCreatorUid('usuario1');
		$ticket->setStatus('asignado');
		$ticket->setTitle('Consulta pendiente de usuario');
		$ticket->setAssignedUserUid('soporte1');
		$ticket->setAssignedGroupId('territorial');

		$notificationCalls = [];
		$service = $this->buildTicketServiceForUpdateScenario(
			$ticket,
			['assignedGroupId' => 'territorial'],
			$notificationCalls,
		);

		$service->update('admin', 24, ['status' => 'en_espera_usuario']);

		self::assertSame(['ticket_waiting_for_creator'], array_column($notificationCalls, 'eventName'));
		self::assertSame('asignado', $notificationCalls[0]['context']['previousStatus']);
	}

	public function testUpdateChangesStatusToAsignadoWhenAssignmentChanges(): void {
		$ticket = new Ticket();
		$ticket->setId(25);
		$ticket->setNumber('2026-000025');
		$ticket->setCreatorUid('usuario1');
		$ticket->setStatus('en_espera_usuario');
		$ticket->setTitle('Consulta reasignada');
		$ticket->setAssignedUserUid('soporte1');
		$ticket->setAssignedGroupId('territorial');

		$notificationCalls = [];
		$service = $this->buildTicketServiceForUpdateScenario(
			$ticket,
			['assignedUserUid' => 'soporte2', 'assignedGroupId' => 'territorial'],
			$notificationCalls,
		);

		$result = $service->update('admin', 25, ['assignedUserUid' => 'soporte2']);

		self::assertSame('asignado', $result['status']);
		self::assertSame('soporte2', $result['assignedUserUid']);
		self::assertSame(['ticket_assigned'], array_column($notificationCalls, 'eventName'));
		self::assertSame('en_espera_usuario', $notificationCalls[0]['context']['previousStatus']);
		self::assertSame('soporte1', $notificationCalls[0]['context']['previousAssignedUserUid']);
	}

	public function testUpdatePreservesExplicitStatusWhenAssignmentAlsoChanges(): void {
		$ticket = new Ticket();
		$ticket->setId(26);
		$ticket->setNumber('2026-000026');
		$ticket->setCreatorUid('usuario1');
		$ticket->setStatus('asignado');
		$ticket->setTitle('Consulta pendiente de usuario tras reasignacion');
		$ticket->setAssignedUserUid('soporte1');
		$ticket->setAssignedGroupId('territorial');

		$notificationCalls = [];
		$service = $this->buildTicketServiceForUpdateScenario(
			$ticket,
			['assignedUserUid' => 'soporte2', 'assignedGroupId' => 'territorial'],
			$notificationCalls,
		);

		$result = $service->update('admin', 26, ['assignedUserUid' => 'soporte2', 'status' => 'en_espera_usuario']);

		self::assertSame('en_espera_usuario', $result['status']);
		self::assertSame('soporte2', $result['assignedUserUid']);
		self::assertSame(['ticket_waiting_for_creator'], array_column($notificationCalls, 'eventName'));
		self::assertSame('asignado', $notificationCalls[0]['context']['previousStatus']);
		self::assertSame('soporte1', $notificationCalls[0]['context']['previousAssignedUserUid']);
	}

	public function testBuildPublicCommentSearchTextOnlyUsesPublicComments(): void {
		$sanitizer = $this->createMock(RichTextSanitizer::class);
		$sanitizer->expects(self::exactly(2))
			->method('toPlainText')
			->willReturnCallback(static fn (string $value): string => trim(strip_tags($value)));
		$permissionService = $this->createMock(\OCA\ConsultasLegales\Service\PermissionService::class);
		$permissionService->method('canSeeComment')
			->willReturnCallback(static fn (string $uid, Ticket $ticket, string $visibility): bool => $visibility === 'publico');

		$serviceReflection = new \ReflectionClass(TicketService::class);
		$service = $serviceReflection->newInstanceWithoutConstructor();
		$sanitizerProperty = $serviceReflection->getProperty('richTextSanitizer');
		$sanitizerProperty->setValue($service, $sanitizer);
		$permissionServiceProperty = $serviceReflection->getProperty('permissionService');
		$permissionServiceProperty->setValue($service, $permissionService);

		$ticket = new Ticket();
		$ticket->setId(100);

		$publicCommentA = new Comment();
		$publicCommentA->setBody('<p>primer comentario visible</p>');
		$publicCommentA->setVisibility('publico');

		$internalComment = new Comment();
		$internalComment->setBody('<p>comentario interno oculto</p>');
		$internalComment->setVisibility('interno');

		$publicCommentB = new Comment();
		$publicCommentB->setBody('<p>segundo comentario visible</p>');
		$publicCommentB->setVisibility('publico');

		$invoke = \Closure::bind(
			/**
			 * @param Comment[] $comments
			 */
			function (Ticket $ticket, array $comments): string {
				/** @var TicketService $this */
				return $this->buildCommentSearchText('usuario1', $ticket, $comments);
			},
			$service,
			TicketService::class,
		);

		$result = $invoke($ticket, [$publicCommentA, $internalComment, $publicCommentB]);

		self::assertSame('primer comentario visible segundo comentario visible', $result);
		self::assertStringNotContainsString('interno oculto', $result);
	}

	public function testMatchesCriteriaSupportsCreationAndUpdateDateRanges(): void {
		$serviceReflection = new \ReflectionClass(TicketService::class);
		$service = $serviceReflection->newInstanceWithoutConstructor();

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturn(null);
		$groupManager = $this->createMock(IGroupManager::class);

		$userManagerProperty = $serviceReflection->getProperty('userManager');
		$userManagerProperty->setValue($service, $userManager);
		$groupManagerProperty = $serviceReflection->getProperty('groupManager');
		$groupManagerProperty->setValue($service, $groupManager);

		$invoke = \Closure::bind(
			function (array $ticket, array $criteria): bool {
				/** @var TicketService $this */
				return $this->matchesCriteria('usuario1', $ticket, $criteria);
			},
			$service,
			TicketService::class,
		);

		$ticket = [
			'id' => 100,
			'createdAt' => strtotime('2026-03-15 10:30:00'),
			'updatedAt' => strtotime('2026-03-20 18:45:00'),
			'status' => 'nuevo',
		];

		self::assertTrue($invoke($ticket, ['createdAtFrom' => '2026-03-15']));
		self::assertTrue($invoke($ticket, ['createdAtTo' => '2026-03-15']));
		self::assertTrue($invoke($ticket, ['updatedAtFrom' => '2026-03-20', 'updatedAtTo' => '2026-03-20']));
		self::assertFalse($invoke($ticket, ['createdAtFrom' => '2026-03-16']));
		self::assertFalse($invoke($ticket, ['createdAtTo' => '2026-03-14']));
		self::assertFalse($invoke($ticket, ['updatedAtTo' => '2026-03-19']));
	}

	public function testMatchesCriteriaSupportsNegatedCriteria(): void {
		$serviceReflection = new \ReflectionClass(TicketService::class);
		$service = $serviceReflection->newInstanceWithoutConstructor();

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willReturn(null);
		$groupManager = $this->createMock(IGroupManager::class);

		$userManagerProperty = $serviceReflection->getProperty('userManager');
		$userManagerProperty->setValue($service, $userManager);
		$groupManagerProperty = $serviceReflection->getProperty('groupManager');
		$groupManagerProperty->setValue($service, $groupManager);

		$attachmentService = $this->getMockBuilder(\OCA\ConsultasLegales\Service\AttachmentService::class)
			->disableOriginalConstructor()
			->onlyMethods(['hasForTicket'])
			->getMock();
		$attachmentService->method('hasForTicket')->willReturn(false);
		$attachmentServiceProperty = $serviceReflection->getProperty('attachmentService');
		$attachmentServiceProperty->setValue($service, $attachmentService);

		$commentMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\CommentMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findBy'])
			->getMock();
		$commentMapper->method('findBy')->willReturn([]);
		$commentMapperProperty = $serviceReflection->getProperty('commentMapper');
		$commentMapperProperty->setValue($service, $commentMapper);

		$sanitizer = $this->createMock(RichTextSanitizer::class);
		$sanitizer->method('toPlainText')->willReturnCallback(static fn (string $value): string => trim(strip_tags($value)));
		$sanitizerProperty = $serviceReflection->getProperty('richTextSanitizer');
		$sanitizerProperty->setValue($service, $sanitizer);

		$invoke = \Closure::bind(
			function (array $ticket, array $criteria): bool {
				/** @var TicketService $this */
				return $this->matchesCriteria('usuario1', $ticket, $criteria);
			},
			$service,
			TicketService::class,
		);

		$ticket = [
			'id' => 100,
			'createdAt' => strtotime('2026-03-15 10:30:00'),
			'updatedAt' => strtotime('2026-03-20 18:45:00'),
			'status' => 'nuevo',
			'assignedUserUid' => 'soporte1',
			'assignedGroupId' => 'grupo-soporte',
			'city' => 'Madrid',
			'typeId' => 11,
		];

		self::assertFalse($invoke($ticket, ['status' => ['nuevo'], 'negatedCriteria' => ['status']]));
		self::assertTrue($invoke($ticket, ['status' => ['cerrado'], 'negatedCriteria' => ['status']]));
		self::assertTrue($invoke($ticket, ['unassigned' => true, 'negatedCriteria' => ['unassigned']]));
		self::assertTrue($invoke($ticket, ['city' => 'Barcelona', 'negatedCriteria' => ['city']]));
	}

	public function testSerializeTicketKeepsDetailResponseWhenOptionalSlicesFail(): void {
		$serviceReflection = new \ReflectionClass(TicketService::class);
		$service = $serviceReflection->newInstanceWithoutConstructor();

		$catalogService = $this->getMockBuilder(\OCA\ConsultasLegales\Service\CatalogService::class)
			->disableOriginalConstructor()
			->onlyMethods(['isClosedStatus'])
			->getMock();
		$catalogService->method('isClosedStatus')->willReturn(false);

		$commentMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\CommentMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findBy'])
			->getMock();
		$commentMapper->method('findBy')->willReturn([]);

		$attachmentService = $this->getMockBuilder(\OCA\ConsultasLegales\Service\AttachmentService::class)
			->disableOriginalConstructor()
			->onlyMethods(['listForTicket'])
			->getMock();
		$attachmentService->method('listForTicket')->willThrowException(new \RuntimeException('storage offline'));

		$historyMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\HistoryEntryMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findBy'])
			->getMock();
		$historyMapper->method('findBy')->willReturn([]);

		$ticketDataMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\TicketDataMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findBy'])
			->getMock();
		$ticketDataMapper->method('findBy')->willReturn([]);

		$permissionService = $this->createMock(\OCA\ConsultasLegales\Service\PermissionService::class);
		$permissionService->method('canReadTicket')->willReturn(true);
		$permissionService->method('canManageTicket')->willReturn(true);
		$permissionService->method('canCommentOnTicket')->willReturn(true);
		$permissionService->method('canSeeComment')->willReturn(true);

		$taskSyncService = $this->createMock(\OCA\ConsultasLegales\Service\TaskSyncService::class);
		$taskSyncService->method('getSyncForTicket')->willThrowException(new \RuntimeException('tasks offline'));

		$richTextSanitizer = $this->createMock(RichTextSanitizer::class);

		foreach ([
			'catalogService' => $catalogService,
			'commentMapper' => $commentMapper,
			'attachmentService' => $attachmentService,
			'historyMapper' => $historyMapper,
			'ticketDataMapper' => $ticketDataMapper,
			'permissionService' => $permissionService,
			'taskSyncService' => $taskSyncService,
			'richTextSanitizer' => $richTextSanitizer,
		] as $property => $value) {
			$serviceReflection->getProperty($property)->setValue($service, $value);
		}

		$ticket = new Ticket();
		$ticket->setId(51);
		$ticket->setNumber('2026-000051');
		$ticket->setStatus('asignado');
		$ticket->setTitle('Detalle resiliente');
		$ticket->setCreatorUid('usuario1');

		$invoke = \Closure::bind(
			function (Ticket $ticket): array {
				/** @var TicketService $this */
				return $this->serializeTicket('usuario1', $ticket, true);
			},
			$service,
			TicketService::class,
		);

		$result = $invoke($ticket);

		self::assertSame([], $result['attachments']);
		self::assertSame([], $result['comments']);
		self::assertSame([], $result['history']);
		self::assertSame([], $result['personalData']);
		self::assertNull($result['taskSync']);
		self::assertTrue($result['canRead']);
	}

	public function testAddAttachmentWrapsInfrastructureFailuresAs503(): void {
		$serviceReflection = new \ReflectionClass(TicketService::class);
		$service = $serviceReflection->newInstanceWithoutConstructor();

		$ticket = new Ticket();
		$ticket->setId(61);
		$ticket->setCreatorUid('usuario1');

		$comment = new Comment();
		$comment->setId(7);
		$comment->setTicketId(61);
		$comment->setVisibility('publico');

		$ticketMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\TicketMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['find'])
			->getMock();
		$ticketMapper->method('find')->with(61)->willReturn($ticket);

		$commentMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\CommentMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['find'])
			->getMock();
		$commentMapper->method('find')->with(7)->willReturn($comment);

		$attachmentService = $this->getMockBuilder(\OCA\ConsultasLegales\Service\AttachmentService::class)
			->disableOriginalConstructor()
			->onlyMethods(['create'])
			->getMock();
		$attachmentService->method('create')->willThrowException(new \RuntimeException('storage offline'));

		$permissionService = $this->createMock(\OCA\ConsultasLegales\Service\PermissionService::class);
		$permissionService->expects(self::once())->method('assertCanReadTicket')->with('usuario1', $ticket);

		$roleService = $this->createMock(RoleService::class);
		$roleService->method('getEffectiveRoles')->with('usuario1')->willReturn([RoleService::USER]);

		foreach ([
			'ticketMapper' => $ticketMapper,
			'commentMapper' => $commentMapper,
			'attachmentService' => $attachmentService,
			'permissionService' => $permissionService,
			'roleService' => $roleService,
		] as $property => $value) {
			$serviceReflection->getProperty($property)->setValue($service, $value);
		}

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionCode(503);
		$this->expectExceptionMessage('No se pudo guardar el adjunto en este momento.');

		$service->addAttachment('usuario1', 61, ['name' => 'x.pdf', 'tmp_name' => 'C:\\tmp\\x.pdf'], 7);
	}

	public function testCreateForAdminAppliesAutomaticAssignmentWhenNoManualAssigneeIsProvided(): void {
		$groupManager = $this->createMock(IGroupManager::class);
		$userManager = $this->createMock(IUserManager::class);
		$provinceCatalogService = $this->getMockBuilder(\OCA\ConsultasLegales\Service\ProvinceCatalogService::class)
			->disableOriginalConstructor()
			->onlyMethods(['normalize'])
			->getMock();
		$catalogService = $this->getMockBuilder(\OCA\ConsultasLegales\Service\CatalogService::class)
			->disableOriginalConstructor()
			->onlyMethods(['isClosedStatus'])
			->getMock();
		$ticketMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\TicketMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['insert'])
			->getMock();
		$commentMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\CommentMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findBy'])
			->getMock();
		$attachmentService = $this->getMockBuilder(\OCA\ConsultasLegales\Service\AttachmentService::class)
			->disableOriginalConstructor()
			->onlyMethods(['listForTicket'])
			->getMock();
		$historyMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\HistoryEntryMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['insert', 'findBy'])
			->getMock();
		$ticketDataMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\TicketDataMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['insert', 'findBy'])
			->getMock();
		$urgencyMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\UrgencyMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findAllOrdered'])
			->getMock();
		$ticketNumberService = $this->createMock(\OCA\ConsultasLegales\Service\TicketNumberService::class);
		$assignmentService = $this->createMock(\OCA\ConsultasLegales\Service\AssignmentService::class);
		$personalConfigService = $this->createMock(\OCA\ConsultasLegales\Service\PersonalConfigService::class);
		$permissionService = $this->createMock(\OCA\ConsultasLegales\Service\PermissionService::class);
		$ticketStatusPolicy = new TicketStatusPolicy();
		$ticketNotificationPublisher = $this->createMock(TicketNotificationPublisher::class);
		$taskSyncService = $this->createMock(\OCA\ConsultasLegales\Service\TaskSyncService::class);
		$richTextSanitizer = $this->createMock(RichTextSanitizer::class);
		$roleService = $this->createMock(RoleService::class);

		$assignedUser = $this->createMock(\OCP\IUser::class);
		$userManager->method('get')->willReturnMap([
			['soporte2', $assignedUser],
		]);

		$provinceCatalogService->expects(self::once())
			->method('normalize')
			->with('Madrid')
			->willReturn('Madrid');
		$roleService->expects(self::once())
			->method('getEffectiveRoles')
			->with('admin')
			->willReturn([RoleService::ADMIN]);
		$assignmentService->expects(self::once())
			->method('resolveForType')
			->with(12, 'Madrid')
			->willReturn([
				'assignedUserUid' => 'soporte2',
				'assignedGroupId' => null,
			]);
		$ticketNumberService->method('nextNumber')->willReturn('TK-0001');
		$richTextSanitizer->method('sanitize')->willReturnCallback(static fn (string $value): string => $value);
		$catalogService->method('isClosedStatus')->willReturn(false);
		$ticketMapper->method('insert')->willReturnCallback(static function (Ticket $ticket): Ticket {
			$ticket->setId(1001);
			return $ticket;
		});
		$personalConfigService->method('getForUser')->willReturn([]);
		$commentMapper->method('findBy')->willReturn([]);
		$attachmentService->method('listForTicket')->willReturn([]);
		$historyMapper->method('findBy')->willReturn([]);
		$ticketDataMapper->method('findBy')->willReturn([]);
		$permissionService->method('canReadTicket')->willReturn(true);
		$permissionService->method('canManageTicket')->willReturn(true);
		$permissionService->method('canCommentOnTicket')->willReturn(true);
		$taskSyncService->method('getSyncForTicket')->willReturn(null);
		$ticketNotificationPublisher->expects(self::once())
			->method('publishCreatedTicket')
			->with(self::isInstanceOf(Ticket::class));

		$serviceReflection = new \ReflectionClass(TicketService::class);
		$service = $serviceReflection->newInstanceWithoutConstructor();
		foreach ([
			'groupManager' => $groupManager,
			'userManager' => $userManager,
			'provinceCatalogService' => $provinceCatalogService,
			'catalogService' => $catalogService,
			'ticketMapper' => $ticketMapper,
			'commentMapper' => $commentMapper,
			'attachmentService' => $attachmentService,
			'historyMapper' => $historyMapper,
			'ticketDataMapper' => $ticketDataMapper,
			'urgencyMapper' => $urgencyMapper,
			'ticketNumberService' => $ticketNumberService,
			'assignmentService' => $assignmentService,
			'personalConfigService' => $personalConfigService,
			'permissionService' => $permissionService,
			'ticketStatusPolicy' => $ticketStatusPolicy,
			'ticketNotificationPublisher' => $ticketNotificationPublisher,
			'taskSyncService' => $taskSyncService,
			'richTextSanitizer' => $richTextSanitizer,
			'roleService' => $roleService,
		] as $property => $value) {
			$serviceReflection->getProperty($property)->setValue($service, $value);
		}

		$result = $service->create('admin', [
			'typeId' => 12,
			'province' => 'Madrid',
			'title' => 'Ticket desde administracion',
			'userDescription' => '<p>Descripcion</p>',
			'assignedUserUid' => null,
			'assignedGroupId' => null,
			'personalData' => [],
		]);

		self::assertSame('soporte2', $result['assignedUserUid']);
		self::assertNull($result['assignedGroupId']);
		self::assertSame('asignado', $result['status']);
	}

	private function buildTicketServiceForUpdateScenario(Ticket $ticket, array $expectedPayload, array &$notificationCalls, ?object $group = null): TicketService {
		$groupManager = $this->createMock(IGroupManager::class);
		$userManager = $this->createMock(IUserManager::class);
		$provinceCatalogService = $this->getMockBuilder(\OCA\ConsultasLegales\Service\ProvinceCatalogService::class)
			->disableOriginalConstructor()
			->getMock();
		$catalogService = $this->getMockBuilder(\OCA\ConsultasLegales\Service\CatalogService::class)
			->disableOriginalConstructor()
			->onlyMethods(['isClosedStatus'])
			->getMock();
		$ticketMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\TicketMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['find', 'update'])
			->getMock();
		$commentMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\CommentMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findBy'])
			->getMock();
		$attachmentService = $this->getMockBuilder(\OCA\ConsultasLegales\Service\AttachmentService::class)
			->disableOriginalConstructor()
			->onlyMethods(['listForTicket'])
			->getMock();
		$historyMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\HistoryEntryMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['insert', 'findBy'])
			->getMock();
		$ticketDataMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\TicketDataMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findBy'])
			->getMock();
		$urgencyMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\UrgencyMapper::class)
			->disableOriginalConstructor()
			->getMock();
		$ticketNumberService = $this->createMock(\OCA\ConsultasLegales\Service\TicketNumberService::class);
		$assignmentService = $this->createMock(\OCA\ConsultasLegales\Service\AssignmentService::class);
		$personalConfigService = $this->createMock(\OCA\ConsultasLegales\Service\PersonalConfigService::class);
		$permissionService = $this->createMock(\OCA\ConsultasLegales\Service\PermissionService::class);
		$ticketStatusPolicy = new TicketStatusPolicy();
		$ticketNotificationPublisher = $this->createMock(TicketNotificationPublisher::class);
		$taskSyncService = $this->createMock(\OCA\ConsultasLegales\Service\TaskSyncService::class);
		$richTextSanitizer = $this->createMock(RichTextSanitizer::class);
		$roleService = $this->createMock(RoleService::class);

		$assignedUser = new class {
			public function getUID(): string {
				return 'soporte1';
			}
		};
		$group ??= new class {
			public function inGroup(object $user): bool {
				return true;
			}

			public function getUsers(): array {
				return [];
			}
		};

		$catalogService->method('isClosedStatus')->willReturn(false);
		$ticketMapper->expects(self::once())->method('find')->with((int) $ticket->getId())->willReturn($ticket);
		$ticketMapper->expects(self::once())->method('update')->willReturnCallback(static fn (Ticket $updated): Ticket => $updated);
		$commentMapper->method('findBy')->willReturn([]);
		$attachmentService->method('listForTicket')->willReturn([]);
		$historyMapper->method('findBy')->willReturn([]);
		$ticketDataMapper->method('findBy')->willReturn([]);
		$permissionService->expects(self::once())->method('assertCanManageTicket')->with('admin', $ticket);
		$permissionService->method('canAssignGroup')->with('admin', $expectedPayload['assignedGroupId'])->willReturn(true);
		$taskSyncService->expects(self::once())->method('syncTicket')->with($ticket);
		$ticketNotificationPublisher->method('publishUpdatedTicket')
			->willReturnCallback(static function (Ticket $updatedTicket, string $previousStatus, ?string $previousAssignedUserUid, ?string $previousAssignedGroupId, bool $statusChanged, bool $assignmentChanged) use (&$notificationCalls): void {
				$eventName = null;
				$assignedUserChanged = $updatedTicket->getAssignedUserUid() !== $previousAssignedUserUid;
				$assignedGroupChanged = $updatedTicket->getAssignedGroupId() !== $previousAssignedGroupId;
				if ($statusChanged && (string) $updatedTicket->getStatus() === 'en_espera_usuario') {
					$eventName = 'ticket_waiting_for_creator';
				} elseif ($assignmentChanged) {
					if ($assignedUserChanged && $updatedTicket->getAssignedUserUid() !== null && $updatedTicket->getAssignedUserUid() !== '') {
						$eventName = 'ticket_assigned';
					}
				} elseif ($statusChanged) {
					$eventName = in_array((string) $updatedTicket->getStatus(), ['resuelto', 'cerrado'], true)
						? 'ticket_resolved'
						: 'ticket_status_changed';
				}

				if ($eventName !== null) {
					$notificationCalls[] = [
						'eventName' => $eventName,
						'ticketId' => $updatedTicket->getId(),
						'extraRecipients' => [],
						'context' => [
							'previousStatus' => $previousStatus,
							'previousAssignedUserUid' => $previousAssignedUserUid,
							'previousAssignedGroupId' => $previousAssignedGroupId,
						],
						'includeDefaultRecipients' => true,
					];
				}

				if ($assignedGroupChanged && ($updatedTicket->getAssignedUserUid() === null || $updatedTicket->getAssignedUserUid() === '') && $updatedTicket->getAssignedGroupId() === 'territorial_legal') {
					$notificationCalls[] = [
						'eventName' => 'ticket_group_assigned',
						'ticketId' => $updatedTicket->getId(),
						'extraRecipients' => ['soporte1'],
						'context' => ['previousAssignedGroupId' => $previousAssignedGroupId],
						'includeDefaultRecipients' => false,
					];
				}
			});
		$richTextSanitizer->method('sanitize')->willReturnCallback(static fn (string $value): string => $value);
		$userManager->method('get')->willReturnMap([
			['soporte1', $assignedUser],
			['soporte2', $assignedUser],
		]);
		$roleService->method('getEffectiveRoles')->willReturnMap([
			['admin', [RoleService::ADMIN]],
			['soporte1', [RoleService::SUPPORT]],
		]);
		$groupManager->method('get')->willReturnCallback(static function (?string $groupId) use ($expectedPayload, $ticket, $group): ?object {
			if ($groupId === $expectedPayload['assignedGroupId'] || $groupId === $ticket->getAssignedGroupId()) {
				return $group;
			}

			return null;
		});

		$serviceReflection = new \ReflectionClass(TicketService::class);
		$service = $serviceReflection->newInstanceWithoutConstructor();
		foreach ([
			'groupManager' => $groupManager,
			'userManager' => $userManager,
			'provinceCatalogService' => $provinceCatalogService,
			'catalogService' => $catalogService,
			'ticketMapper' => $ticketMapper,
			'commentMapper' => $commentMapper,
			'attachmentService' => $attachmentService,
			'historyMapper' => $historyMapper,
			'ticketDataMapper' => $ticketDataMapper,
			'urgencyMapper' => $urgencyMapper,
			'ticketNumberService' => $ticketNumberService,
			'assignmentService' => $assignmentService,
			'personalConfigService' => $personalConfigService,
			'permissionService' => $permissionService,
			'ticketStatusPolicy' => $ticketStatusPolicy,
			'ticketNotificationPublisher' => $ticketNotificationPublisher,
			'taskSyncService' => $taskSyncService,
			'richTextSanitizer' => $richTextSanitizer,
			'roleService' => $roleService,
		] as $property => $value) {
			$serviceReflection->getProperty($property)->setValue($service, $value);
		}

		return $service;
	}
}