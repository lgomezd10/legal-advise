<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Service;

use OCA\ConsultasLegales\Db\AppSetting;
use OCA\ConsultasLegales\Db\IncidentType;
use OCA\ConsultasLegales\Service\DefaultConfigService;
use PHPUnit\Framework\TestCase;

class DefaultConfigServiceTest extends TestCase {
	public function testEnsureDefaultsDoesNotReseedTypesAfterInitialSeedWhenCatalogIsEmpty(): void {
		$statusCatalogSetting = new AppSetting();
		$statusCatalogSetting->setConfigKey('status_catalog');
		$statusCatalogSetting->setConfigValue([]);

		$tasksConfigSetting = new AppSetting();
		$tasksConfigSetting->setConfigKey('tasks_config');
		$tasksConfigSetting->setConfigValue(['enabled' => true]);

		$notificationPolicySetting = new AppSetting();
		$notificationPolicySetting->setConfigKey('notification_policy');
		$notificationPolicySetting->setConfigValue(['defaultChannelOrder' => ['nextcloud', 'mail']]);

		$attachmentConfigSetting = new AppSetting();
		$attachmentConfigSetting->setConfigKey('attachment_config');
		$attachmentConfigSetting->setConfigValue(['allowedExtensions' => ['pdf'], 'maxFileSizeMb' => 100]);

		$seededFlag = new AppSetting();
		$seededFlag->setConfigKey('types_defaults_seeded');
		$seededFlag->setConfigValue(true);

		$appDisplayNameSetting = new AppSetting();
		$appDisplayNameSetting->setConfigKey('app_display_name');
		$appDisplayNameSetting->setConfigValue('Consultas Legales');

		$settingMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\AppSettingMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findOneBy', 'update', 'insert'])
			->getMock();
		$settingMapper->method('findOneBy')->willReturnCallback(static function (string $field, string $value) use ($appDisplayNameSetting, $attachmentConfigSetting, $notificationPolicySetting, $seededFlag, $statusCatalogSetting, $tasksConfigSetting) {
			if ($field !== 'config_key') {
				return null;
			}

			return match ($value) {
				'status_catalog' => $statusCatalogSetting,
				'tasks_config' => $tasksConfigSetting,
				'notification_policy' => $notificationPolicySetting,
				'attachment_config' => $attachmentConfigSetting,
				'types_defaults_seeded' => $seededFlag,
				'app_display_name' => $appDisplayNameSetting,
				default => null,
			};
		});
		$settingMapper->expects(self::exactly(2))->method('update');
		$settingMapper->expects(self::never())->method('insert');

		$urgencyMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\UrgencyMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findOneBy', 'insert'])
			->getMock();
		$urgencyMapper->method('findOneBy')->willReturn(new \OCA\ConsultasLegales\Db\Urgency());
		$urgencyMapper->expects(self::never())->method('insert');

		$fieldMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\CustomFieldMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findOneBy', 'insert', 'update'])
			->getMock();
		$fieldMapper->method('findOneBy')->willReturn(new \OCA\ConsultasLegales\Db\CustomField());
		$fieldMapper->expects(self::never())->method('insert');
		$fieldMapper->expects(self::never())->method('update');

		$typeMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\IncidentTypeMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findAllOrdered', 'findOneBy', 'insert', 'update'])
			->getMock();
		$typeMapper->method('findAllOrdered')->with('level', 'ASC')->willReturn([]);
		$typeMapper->expects(self::never())->method('findOneBy');
		$typeMapper->expects(self::never())->method('insert');
		$typeMapper->expects(self::never())->method('update');

		$ruleMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\AssignmentRuleMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findOneBy', 'insert'])
			->getMock();
		$ruleMapper->expects(self::never())->method('findOneBy');
		$ruleMapper->expects(self::never())->method('insert');

		$notificationPreferenceMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\NotificationPreferenceMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findByMany', 'insert'])
			->getMock();
		$notificationPreferenceMapper->method('findByMany')->willReturn([new \OCA\ConsultasLegales\Db\NotificationPreference()]);
		$notificationPreferenceMapper->expects(self::never())->method('insert');

		$profileAssignmentMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\ProfileAssignmentMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findAllOrdered', 'insert'])
			->getMock();
		$profileAssignment = new \OCA\ConsultasLegales\Db\ProfileAssignment();
		$profileAssignment->setProfile('usuario');
		$profileAssignment->setPrincipalType('group');
		$profileAssignment->setPrincipalId('userLegal');
		$profileAssignmentMapper->method('findAllOrdered')->willReturn([$profileAssignment]);
		$profileAssignmentMapper->expects(self::never())->method('insert');

		$service = new DefaultConfigService(
			$settingMapper,
			$urgencyMapper,
			$fieldMapper,
			$typeMapper,
			$ruleMapper,
			$notificationPreferenceMapper,
			$profileAssignmentMapper,
		);

		$service->ensureDefaults();
		self::addToAssertionCount(1);
	}

	public function testEnsureDefaultsMarksTypesAsSeededWhenCatalogAlreadyExists(): void {
		$statusCatalogSetting = new AppSetting();
		$statusCatalogSetting->setConfigKey('status_catalog');
		$statusCatalogSetting->setConfigValue([]);

		$tasksConfigSetting = new AppSetting();
		$tasksConfigSetting->setConfigKey('tasks_config');
		$tasksConfigSetting->setConfigValue(['enabled' => true]);

		$notificationPolicySetting = new AppSetting();
		$notificationPolicySetting->setConfigKey('notification_policy');
		$notificationPolicySetting->setConfigValue(['defaultChannelOrder' => ['nextcloud', 'mail']]);

		$attachmentConfigSetting = new AppSetting();
		$attachmentConfigSetting->setConfigKey('attachment_config');
		$attachmentConfigSetting->setConfigValue(['allowedExtensions' => ['pdf'], 'maxFileSizeMb' => 100]);

		$appDisplayNameSetting = new AppSetting();
		$appDisplayNameSetting->setConfigKey('app_display_name');
		$appDisplayNameSetting->setConfigValue('Consultas Legales');

		$settingMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\AppSettingMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findOneBy', 'update', 'insert'])
			->getMock();
		$settingMapper->method('findOneBy')->willReturnCallback(static function (string $field, string $value) use ($appDisplayNameSetting, $attachmentConfigSetting, $notificationPolicySetting, $statusCatalogSetting, $tasksConfigSetting) {
			if ($field !== 'config_key') {
				return null;
			}

			return match ($value) {
				'status_catalog' => $statusCatalogSetting,
				'tasks_config' => $tasksConfigSetting,
				'notification_policy' => $notificationPolicySetting,
				'attachment_config' => $attachmentConfigSetting,
				'types_defaults_seeded' => null,
				'app_display_name' => $appDisplayNameSetting,
				default => null,
			};
		});
		$settingMapper->expects(self::once())->method('insert')->with(self::callback(static function (AppSetting $setting): bool {
			return $setting->getConfigKey() === 'types_defaults_seeded' && (bool) $setting->getConfigValue() === true;
		}));
		$settingMapper->expects(self::exactly(2))->method('update');

		$urgencyMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\UrgencyMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findOneBy', 'insert'])
			->getMock();
		$urgencyMapper->method('findOneBy')->willReturn(new \OCA\ConsultasLegales\Db\Urgency());

		$fieldMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\CustomFieldMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findOneBy', 'insert', 'update'])
			->getMock();
		$fieldMapper->method('findOneBy')->willReturn(new \OCA\ConsultasLegales\Db\CustomField());

		$existingType = new IncidentType();
		$existingType->setId(42);
		$existingType->setSlug('tipo-existente');

		$typeMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\IncidentTypeMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findAllOrdered', 'findOneBy', 'insert', 'update'])
			->getMock();
		$typeMapper->method('findAllOrdered')->with('level', 'ASC')->willReturn([$existingType]);
		$typeMapper->expects(self::never())->method('findOneBy');
		$typeMapper->expects(self::never())->method('insert');
		$typeMapper->expects(self::never())->method('update');

		$ruleMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\AssignmentRuleMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findOneBy', 'insert'])
			->getMock();
		$ruleMapper->expects(self::never())->method('findOneBy');
		$ruleMapper->expects(self::never())->method('insert');

		$notificationPreferenceMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\NotificationPreferenceMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findByMany', 'insert'])
			->getMock();
		$notificationPreferenceMapper->method('findByMany')->willReturn([new \OCA\ConsultasLegales\Db\NotificationPreference()]);

		$profileAssignmentMapper = $this->getMockBuilder(\OCA\ConsultasLegales\Db\ProfileAssignmentMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findAllOrdered', 'insert'])
			->getMock();
		$profileAssignment = new \OCA\ConsultasLegales\Db\ProfileAssignment();
		$profileAssignment->setProfile('usuario');
		$profileAssignment->setPrincipalType('group');
		$profileAssignment->setPrincipalId('userLegal');
		$profileAssignmentMapper->method('findAllOrdered')->willReturn([$profileAssignment]);

		$service = new DefaultConfigService(
			$settingMapper,
			$urgencyMapper,
			$fieldMapper,
			$typeMapper,
			$ruleMapper,
			$notificationPreferenceMapper,
			$profileAssignmentMapper,
		);

		$service->ensureDefaults();
		self::addToAssertionCount(1);
	}
}