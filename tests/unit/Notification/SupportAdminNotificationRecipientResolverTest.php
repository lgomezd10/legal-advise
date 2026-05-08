<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Notification;

use OCA\ConsultasLegales\Notification\SupportAdminNotificationRecipientResolver;
use OCA\ConsultasLegales\Service\RoleService;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

class SupportAdminNotificationRecipientResolverTest extends TestCase {
	public function testResolveReturnsSupportAndAdminUsersOnly(): void {
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('search')->with('')->willReturn([
			new class {
				public function getUID(): string {
					return 'soporte1';
				}
			},
			new class {
				public function getUID(): string {
					return 'adminqa';
				}
			},
			new class {
				public function getUID(): string {
					return 'usuario1';
				}
			},
		]);

		$roleService = $this->createMock(RoleService::class);
		$roleService->method('getEffectiveRoles')->willReturnMap([
			['soporte1', [RoleService::SUPPORT]],
			['adminqa', [RoleService::ADMIN]],
			['usuario1', [RoleService::USER]],
		]);

		$resolver = new SupportAdminNotificationRecipientResolver($userManager, $roleService);

		self::assertSame(['soporte1', 'adminqa'], $resolver->resolve());
	}
}