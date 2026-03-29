<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Service;

use OCA\ConsultasLegales\Db\Comment;
use OCA\ConsultasLegales\Service\RichTextSanitizer;
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

		$serviceReflection = new \ReflectionClass(TicketService::class);
		$service = $serviceReflection->newInstanceWithoutConstructor();
		$sanitizerProperty = $serviceReflection->getProperty('richTextSanitizer');
		$sanitizerProperty->setValue($service, $sanitizer);

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
			function (array $comments): string {
				/** @var TicketService $this */
				return $this->buildPublicCommentSearchText($comments);
			},
			$service,
			TicketService::class,
		);

		$result = $invoke([$publicCommentA, $internalComment, $publicCommentB]);

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
}