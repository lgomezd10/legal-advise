<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Service;

use OCA\ConsultasLegales\Db\Ticket;
use OCA\ConsultasLegales\Db\Comment;
use OCA\ConsultasLegales\Service\RichTextSanitizer;
use OCA\ConsultasLegales\Service\RoleService;
use OCA\ConsultasLegales\Service\TicketService;
use OCP\IGroupManager;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

class TicketServiceTest extends TestCase {
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
		$notificationService = $this->createMock(\OCA\ConsultasLegales\Service\NotificationService::class);
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
			'notificationService' => $notificationService,
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
}