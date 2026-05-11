<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Service;

use OCA\ConsultasLegales\Service\AdminConfigService;
use OCA\ConsultasLegales\Service\CatalogService;
use OCA\ConsultasLegales\Service\DefaultConfigService;
use OCA\ConsultasLegales\Service\ProvinceCatalogService;
use OCA\ConsultasLegales\Service\SupportFilterService;
use PHPUnit\Framework\TestCase;

class AdminConfigServiceTest extends TestCase {
	public function testGetConfigKeepsCoreAdminDataWhenOptionalSlicesFail(): void {
		$defaultConfigService = $this->createMock(DefaultConfigService::class);
		$defaultConfigService->expects(self::once())->method('ensureDefaults');

		$provinceCatalogService = $this->createMock(ProvinceCatalogService::class);
		$catalogService = $this->createMock(CatalogService::class);
		$catalogService->method('getStatuses')->willReturn([]);
		$catalogService->method('getTypeTree')->willReturn([]);
		$catalogService->method('getUrgencies')->willReturn([]);
		$catalogService->method('getFields')->with(false)->willReturn([]);
		$catalogService->method('getAttachmentConfig')->willThrowException(new \RuntimeException('attachment settings offline'));
		$catalogService->method('getTaskConfig')->willThrowException(new \RuntimeException('task settings offline'));

		$supportFilterService = $this->createMock(SupportFilterService::class);
		$supportFilterService->method('listForAdmin')->willThrowException(new \RuntimeException('filters offline'));

		$typeMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\IncidentTypeMapper::class)->disableOriginalConstructor()->getMock();
		$urgencyMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\UrgencyMapper::class)->disableOriginalConstructor()->getMock();
		$fieldMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\CustomFieldMapper::class)->disableOriginalConstructor()->getMock();
		$ruleMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\AssignmentRuleMapper::class)->disableOriginalConstructor()->onlyMethods(['findAllOrdered'])->getMock();
		$ruleMapper->method('findAllOrdered')->willReturn([]);
		$profileMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\ProfileAssignmentMapper::class)->disableOriginalConstructor()->onlyMethods(['findAllOrdered'])->getMock();
		$profileMapper->method('findAllOrdered')->willReturn([]);
		$notificationPreferenceMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\NotificationPreferenceMapper::class)->disableOriginalConstructor()->onlyMethods(['findAllOrdered'])->getMock();
		$notificationPreferenceMapper->method('findAllOrdered')->willThrowException(new \RuntimeException('notifications offline'));
		$settingMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\AppSettingMapper::class)->disableOriginalConstructor()->getMock();

		$service = new AdminConfigService(
			$defaultConfigService,
			$provinceCatalogService,
			$catalogService,
			$supportFilterService,
			$typeMapper,
			$urgencyMapper,
			$fieldMapper,
			$ruleMapper,
			$profileMapper,
			$notificationPreferenceMapper,
			$settingMapper,
		);

		$result = $service->getConfig();

		self::assertSame([], $result['filters']);
		self::assertSame([], $result['notifications']);
		self::assertSame(['allowedExtensions' => [], 'maxFileSizeMb' => 100], $result['attachmentConfig']);
		self::assertSame([], $result['tasksConfig']);
		self::assertArrayHasKey('statuses', $result);
		self::assertArrayHasKey('rules', $result);
	}
}