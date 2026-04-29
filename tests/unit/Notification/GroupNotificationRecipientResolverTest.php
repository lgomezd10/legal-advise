<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Notification;

use OCA\ConsultasLegales\Notification\GroupNotificationRecipientResolver;
use OCA\ConsultasLegales\Service\RoleService;
use OCP\IGroupManager;
use PHPUnit\Framework\TestCase;

class GroupNotificationRecipientResolverTest extends TestCase {
	public function testResolveReturnsOnlySupportAndAdminUsers(): void {
		$groupManager = $this->createMock(IGroupManager::class);
		$roleService = $this->createMock(RoleService::class);

		$group = new class {
			public function getUsers(): array {
				return [
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
					new class {
						public function getUID(): string {
							return 'soporte1';
						}
					},
				];
			}
		};

		$groupManager->method('get')->with('territorial_legal')->willReturn($group);
		$roleService->method('getEffectiveRoles')->willReturnMap([
			['soporte1', [RoleService::SUPPORT]],
			['adminqa', [RoleService::ADMIN]],
			['usuario1', [RoleService::USER]],
		]);

		$resolver = new GroupNotificationRecipientResolver($groupManager, $roleService);

		self::assertSame(['soporte1', 'adminqa'], $resolver->resolve('territorial_legal'));
	}

	public function testResolveReturnsEmptyArrayWhenGroupDoesNotExist(): void {
		$groupManager = $this->createMock(IGroupManager::class);
		$roleService = $this->createMock(RoleService::class);
		$groupManager->method('get')->with('missing-group')->willReturn(null);

		$resolver = new GroupNotificationRecipientResolver($groupManager, $roleService);

		self::assertSame([], $resolver->resolve('missing-group'));
	}
}