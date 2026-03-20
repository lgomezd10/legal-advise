<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Service;

use OCA\ConsultasLegales\Db\Ticket;
use OCA\ConsultasLegales\Service\PermissionService;
use OCA\ConsultasLegales\Service\RoleService;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

class PermissionServiceTest extends TestCase {
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
}