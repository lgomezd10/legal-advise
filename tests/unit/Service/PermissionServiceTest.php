<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Tests\Unit\Service;

use OCA\Gestion_incidencias\Db\Ticket;
use OCA\Gestion_incidencias\Service\PermissionService;
use OCA\Gestion_incidencias\Service\RoleService;
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
}