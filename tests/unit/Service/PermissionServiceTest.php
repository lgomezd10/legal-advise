<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Service;

use OCA\ConsultasLegales\Db\Comment;
use OCA\ConsultasLegales\Db\Ticket;
use OCA\ConsultasLegales\Service\PermissionService;
use OCA\ConsultasLegales\Service\RoleService;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

class PermissionServiceTest extends TestCase {
	public function testSupportCanManageOwnAssignedGroupTicket(): void {
		$roleService = $this->createMock(RoleService::class);
		$roleService->method('getEffectiveRoles')->willReturn([RoleService::USER, RoleService::SUPPORT]);

		$user = $this->createMock(IUser::class);
		$group = $this->createMock(IGroup::class);
		$group->method('inGroup')->with($user)->willReturn(true);

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('get')->with('soporte')->willReturn($group);

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->with('agent-1')->willReturn($user);

		$service = new PermissionService($roleService, $groupManager, $userManager);

		$ticket = new Ticket();
		$ticket->setCreatorUid('creator-1');
		$ticket->setAssignedGroupId('soporte');

		self::assertTrue($service->canManageTicket('agent-1', $ticket));
	}

	public function testSupportCannotManageForeignGroupTicket(): void {
		$roleService = $this->createMock(RoleService::class);
		$roleService->method('getEffectiveRoles')->willReturn([RoleService::USER, RoleService::SUPPORT]);

		$user = $this->createMock(IUser::class);
		$group = $this->createMock(IGroup::class);
		$group->method('inGroup')->with($user)->willReturn(false);

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('get')->with('rrhh')->willReturn($group);

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->with('agent-1')->willReturn($user);

		$service = new PermissionService($roleService, $groupManager, $userManager);

		$ticket = new Ticket();
		$ticket->setCreatorUid('creator-1');
		$ticket->setAssignedGroupId('rrhh');

		self::assertFalse($service->canManageTicket('agent-1', $ticket));
	}

	public function testSupportCanAssignOwnGroup(): void {
		$roleService = $this->createMock(RoleService::class);
		$roleService->method('getEffectiveRoles')->willReturn([RoleService::USER, RoleService::SUPPORT]);

		$user = $this->createMock(IUser::class);
		$group = $this->createMock(IGroup::class);
		$group->method('inGroup')->with($user)->willReturn(true);

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('get')->with('soporte')->willReturn($group);

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->with('agent-1')->willReturn($user);

		$service = new PermissionService($roleService, $groupManager, $userManager);

		self::assertTrue($service->canAssignGroup('agent-1', 'soporte'));
	}

	public function testSupportCannotAssignForeignGroup(): void {
		$roleService = $this->createMock(RoleService::class);
		$roleService->method('getEffectiveRoles')->willReturn([RoleService::USER, RoleService::SUPPORT]);

		$user = $this->createMock(IUser::class);
		$group = $this->createMock(IGroup::class);
		$group->method('inGroup')->with($user)->willReturn(false);

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('get')->with('rrhh')->willReturn($group);

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->with('agent-1')->willReturn($user);

		$service = new PermissionService($roleService, $groupManager, $userManager);

		self::assertFalse($service->canAssignGroup('agent-1', 'rrhh'));
	}

	public function testSupportCanDeleteOwnLatestComment(): void {
		$service = $this->buildPermissionService([RoleService::SUPPORT], true);

		$ticket = new Ticket();
		$ticket->setCreatorUid('creator-1');
		$ticket->setAssignedGroupId('soporte');

		$commentA = new Comment();
		$commentA->setId(10);
		$commentA->setAuthorUid('agent-1');
		$commentA->setCreatedAt(100);

		$commentB = new Comment();
		$commentB->setId(11);
		$commentB->setAuthorUid('agent-1');
		$commentB->setCreatedAt(200);

		self::assertTrue($service->canDeleteComment('agent-1', $ticket, $commentB, [$commentA, $commentB]));
	}

	public function testSupportCannotDeleteOlderOwnComment(): void {
		$service = $this->buildPermissionService([RoleService::SUPPORT], true);

		$ticket = new Ticket();
		$ticket->setCreatorUid('creator-1');
		$ticket->setAssignedGroupId('soporte');

		$commentA = new Comment();
		$commentA->setId(10);
		$commentA->setAuthorUid('agent-1');
		$commentA->setCreatedAt(100);

		$commentB = new Comment();
		$commentB->setId(11);
		$commentB->setAuthorUid('agent-1');
		$commentB->setCreatedAt(200);

		self::assertFalse($service->canDeleteComment('agent-1', $ticket, $commentA, [$commentA, $commentB]));
	}

	public function testSupportCanEditOwnLatestCommentButNotOlderOne(): void {
		$service = $this->buildPermissionService([RoleService::SUPPORT], true);

		$ticket = new Ticket();
		$ticket->setCreatorUid('creator-1');
		$ticket->setAssignedGroupId('soporte');

		$commentA = new Comment();
		$commentA->setId(10);
		$commentA->setAuthorUid('agent-1');
		$commentA->setCreatedAt(100);

		$commentB = new Comment();
		$commentB->setId(11);
		$commentB->setAuthorUid('agent-1');
		$commentB->setCreatedAt(200);

		self::assertFalse($service->canEditComment('agent-1', $ticket, $commentA, [$commentA, $commentB]));
		self::assertTrue($service->canEditComment('agent-1', $ticket, $commentB, [$commentA, $commentB]));
	}

	public function testAdminCanDeleteAnyCommentAndTicket(): void {
		$roleService = $this->createMock(RoleService::class);
		$roleService->method('getEffectiveRoles')->willReturn([RoleService::ADMIN]);

		$service = new PermissionService($roleService, $this->createMock(IGroupManager::class), $this->createMock(IUserManager::class));

		$ticket = new Ticket();
		$ticket->setCreatorUid('creator-1');

		$comment = new Comment();
		$comment->setId(44);
		$comment->setAuthorUid('usuario1');
		$comment->setCreatedAt(100);

		self::assertTrue($service->canEditComment('admin', $ticket, $comment, [$comment]));
		self::assertTrue($service->canDeleteComment('admin', $ticket, $comment, [$comment]));
		self::assertTrue($service->canDeleteTicket('admin', $ticket));
	}

	private function buildPermissionService(array $roles, bool $isInGroup): PermissionService {
		$roleService = $this->createMock(RoleService::class);
		$roleService->method('getEffectiveRoles')->willReturn($roles);

		$user = $this->createMock(IUser::class);
		$group = $this->createMock(IGroup::class);
		$group->method('inGroup')->with($user)->willReturn($isInGroup);

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('get')->willReturn($group);

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->with('agent-1')->willReturn($user);

		return new PermissionService($roleService, $groupManager, $userManager);
	}
}