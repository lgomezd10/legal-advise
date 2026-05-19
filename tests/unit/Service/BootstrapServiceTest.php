<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Service;

use OCA\ConsultasLegales\AppInfo\Application;
use OCA\ConsultasLegales\Service\AppStorageUsageService;
use OCA\ConsultasLegales\Service\BootstrapService;
use OCA\ConsultasLegales\Service\CatalogService;
use OCA\ConsultasLegales\Service\DefaultConfigService;
use OCA\ConsultasLegales\Service\PersonalConfigService;
use OCA\ConsultasLegales\Service\ProvinceCatalogService;
use OCA\ConsultasLegales\Service\RoleService;
use OCA\ConsultasLegales\Service\SupportFilterService;
use OCA\ConsultasLegales\Service\TaskSyncService;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\App\IAppManager;
use PHPUnit\Framework\TestCase;

class BootstrapServiceTest extends TestCase {
	public function testBuildKeepsBootstrapWhenOptionalSlicesFail(): void {
		$defaultConfigService = $this->createMock(DefaultConfigService::class);
		$defaultConfigService->expects(self::once())->method('ensureDefaults');

		$provinceCatalogService = $this->createMock(ProvinceCatalogService::class);
		$provinceCatalogService->method('list')->willReturn(['Madrid']);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('usuario1');
		$user->method('getDisplayName')->willReturn('Usuario Uno');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$roleService = $this->createMock(RoleService::class);
		$roleService->method('getEffectiveRoles')->with('usuario1')->willReturn([RoleService::USER]);

		$catalogService = $this->createMock(CatalogService::class);
		$catalogService->method('getStatuses')->willReturn([]);
		$catalogService->method('getUrgencies')->willReturn([]);
		$catalogService->method('getTypeTree')->willReturn([]);
		$catalogService->method('getFields')->willReturn([]);
		$catalogService->method('getAttachmentConfig')->willReturn(['allowedExtensions' => ['pdf'], 'maxFileSizeMb' => 25]);

		$supportFilterService = $this->createMock(SupportFilterService::class);
		$supportFilterService->method('listForConsole')->willThrowException(new \RuntimeException('filters offline'));

		$taskSyncService = $this->createMock(TaskSyncService::class);
		$taskSyncService->method('getIntegrationStatus')->willReturn(['available' => false, 'config' => []]);

		$personalConfigService = $this->createMock(PersonalConfigService::class);
		$personalConfigService->method('getForUser')->willThrowException(new \RuntimeException('profile offline'));
		$personalConfigService->method('hasStoredValues')->willThrowException(new \RuntimeException('profile offline'));

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('search')->willThrowException(new \RuntimeException('groups offline'));

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('search')->willThrowException(new \RuntimeException('users offline'));
		$userManager->method('searchDisplayName')->willThrowException(new \RuntimeException('users offline'));

		$appManager = $this->createMock(IAppManager::class);
		$appManager->method('isEnabledForUser')->with(Application::APP_ID, $user)->willReturn(true);
		$appManager->method('getAppVersion')->with(Application::APP_ID)->willReturn('0.3.0');

		$appStorageUsageService = $this->createMock(AppStorageUsageService::class);
		$appStorageUsageService->method('summarize')->willReturn([
			'totalBytes' => 0,
			'totalLabel' => '0 B',
			'appDataBytes' => 0,
			'appDataLabel' => '0 B',
			'databaseBytes' => 0,
			'databaseLabel' => '0 B',
			'attachmentBytes' => 0,
			'attachmentLabel' => '0 B',
		]);

		$service = new BootstrapService(
			$defaultConfigService,
			$provinceCatalogService,
			$userSession,
			$roleService,
			$catalogService,
			$supportFilterService,
			$taskSyncService,
			$personalConfigService,
			$groupManager,
			$userManager,
			$appManager,
			$appStorageUsageService,
		);

		$result = $service->build();

		self::assertSame([], $result['supportFilters']);
		self::assertSame([], $result['personalConfig']);
		self::assertFalse($result['personalConfigHasStoredValues']);
		self::assertSame([], $result['assignables']['users']);
		self::assertSame([], $result['assignables']['groups']);
		self::assertSame(['available' => false, 'config' => []], $result['tasksIntegration']);
		self::assertSame('usuario1', $result['currentUser']['uid']);
	}
}