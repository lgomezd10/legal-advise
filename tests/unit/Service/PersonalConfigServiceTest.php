<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Service;

use OCA\ConsultasLegales\Db\AppSetting;
use OCA\ConsultasLegales\Db\AppSettingMapper;
use OCA\ConsultasLegales\Db\CustomField;
use OCA\ConsultasLegales\Db\CustomFieldMapper;
use OCA\ConsultasLegales\Service\PersonalConfigService;
use OCP\Accounts\IAccount;
use OCP\Accounts\IAccountManager;
use OCP\Accounts\IAccountProperty;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

class PersonalConfigServiceTest extends TestCase {
	public function testHasStoredValuesReturnsTrueWhenAppSettingExists(): void {
		$settingMapper = $this->createMock(AppSettingMapper::class);
		$settingMapper->method('findOneBy')
			->with('config_key', 'personal_profile:usuario1')
			->willReturn(new AppSetting());

		$service = $this->createService($settingMapper);

		self::assertTrue($service->hasStoredValues('usuario1'));
	}

	public function testRestoreForUserDeletesStoredOverrideAndReloadsNextcloudDefaults(): void {
		$setting = new AppSetting();
		$setting->setConfigKey('personal_profile:usuario1');

		$settingMapper = $this->createMock(AppSettingMapper::class);
		$settingMapper->expects(self::once())
			->method('findOneBy')
			->with('config_key', 'personal_profile:usuario1')
			->willReturn($setting);
		$settingMapper->expects(self::once())
			->method('delete')
			->with($setting);

		$fieldMapper = $this->createMock(CustomFieldMapper::class);
		$fieldMapper->method('findAllOrdered')->willReturn([
			$this->createField('email', 'email'),
			$this->createField('city', 'location'),
		]);

		$user = $this->createMock(IUser::class);
		$user->method('getEMailAddress')->willReturn('usuario@example.com');

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->with('usuario1')->willReturn($user);

		$addressProperty = $this->createMock(IAccountProperty::class);
		$addressProperty->method('getValue')->willReturn('Madrid');

		$account = $this->createMock(IAccount::class);
		$account->method('getProperty')->willReturnMap([
			[IAccountManager::PROPERTY_ADDRESS, $addressProperty],
		]);

		$accountManager = $this->createMock(IAccountManager::class);
		$accountManager->method('getAccount')->with($user)->willReturn($account);

		$service = new PersonalConfigService($settingMapper, $fieldMapper, $userManager, $accountManager);

		self::assertSame(
			['email' => 'usuario@example.com', 'city' => 'Madrid'],
			$service->restoreForUser('usuario1'),
		);
	}

	private function createService(?AppSettingMapper $settingMapper = null): PersonalConfigService {
		$fieldMapper = $this->createMock(CustomFieldMapper::class);
		$userManager = $this->createMock(IUserManager::class);
		$accountManager = $this->createMock(IAccountManager::class);

		return new PersonalConfigService(
			$settingMapper ?? $this->createMock(AppSettingMapper::class),
			$fieldMapper,
			$userManager,
			$accountManager,
		);
	}

	private function createField(string $key, string $preloadSource): CustomField {
		$field = new CustomField();
		$field->setFieldKey($key);
		$field->setLabel($key);
		$field->setFieldType('text');
		$field->setRequired(false);
		$field->setSortOrder(10);
		$field->setActive(true);
		$field->setPreloadSource($preloadSource);

		return $field;
	}
}
