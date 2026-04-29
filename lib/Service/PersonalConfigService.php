<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Service;

use OCA\ConsultasLegales\Db\AppSetting;
use OCA\ConsultasLegales\Db\AppSettingMapper;
use OCA\ConsultasLegales\Db\CustomFieldMapper;
use OCP\Accounts\IAccountManager;
use OCP\Accounts\PropertyDoesNotExistException;
use OCP\IUser;
use OCP\IUserManager;

class PersonalConfigService {
	private const SETTING_PREFIX = 'personal_profile:';

	public function __construct(
		private readonly AppSettingMapper $settingMapper,
		private readonly CustomFieldMapper $fieldMapper,
		private readonly IUserManager $userManager,
		private readonly IAccountManager $accountManager,
	) {
	}

	public function getForUser(string $uid): array {
		if ($uid === '') {
			return [];
		}

		$existing = $this->getStoredSetting($uid);
		if ($existing instanceof AppSetting) {
			$configValue = $existing->getConfigValue();
			if (is_array($configValue)) {
				return $this->normalizeValues($configValue);
			}
		}

		$user = $this->userManager->get($uid);
		if ($user === null) {
			return [];
		}

		return $this->loadDefaultsFromNextcloud($user);
	}

	public function hasStoredValues(string $uid): bool {
		if ($uid === '') {
			return false;
		}

		return $this->getStoredSetting($uid) instanceof AppSetting;
	}

	public function saveForUser(string $uid, array $values): array {
		$normalized = $this->normalizeValues($values);
		$existing = $this->getStoredSetting($uid);

		if (!$existing instanceof AppSetting) {
			$existing = new AppSetting();
			$existing->setConfigKey($this->buildSettingKey($uid));
			$existing->setConfigValue($normalized);
			$this->settingMapper->insert($existing);
			return $normalized;
		}

		$existing->setConfigValue($normalized);
		$this->settingMapper->update($existing);

		return $normalized;
	}

	public function restoreForUser(string $uid): array {
		if ($uid === '') {
			return [];
		}

		$existing = $this->getStoredSetting($uid);
		if ($existing instanceof AppSetting) {
			$this->settingMapper->delete($existing);
		}

		$user = $this->userManager->get($uid);
		if ($user === null) {
			return [];
		}

		return $this->loadDefaultsFromNextcloud($user);
	}

	private function buildSettingKey(string $uid): string {
		return self::SETTING_PREFIX . $uid;
	}

	private function getStoredSetting(string $uid): ?AppSetting {
		$setting = $this->settingMapper->findOneBy('config_key', $this->buildSettingKey($uid));
		return $setting instanceof AppSetting ? $setting : null;
	}

	private function normalizeValues(array $values): array {
		$normalized = [];
		foreach ($this->fieldMapper->findAllOrdered('sort_order', 'ASC') as $field) {
			if (!(bool) $field->getActive()) {
				continue;
			}

			$fieldKey = (string) $field->getFieldKey();
			$normalized[$fieldKey] = trim((string) ($values[$fieldKey] ?? ''));
		}

		return $normalized;
	}

	private function loadDefaultsFromNextcloud(IUser $user): array {
		$defaults = [];
		$account = $this->accountManager->getAccount($user);

		foreach ($this->fieldMapper->findAllOrdered('sort_order', 'ASC') as $field) {
			if (!(bool) $field->getActive()) {
				continue;
			}

			$fieldKey = (string) $field->getFieldKey();
			$source = (string) ($field->getPreloadSource() ?? '');
			$defaults[$fieldKey] = match ($source) {
				'displayName' => $user->getDisplayName(),
				'email' => $this->resolveEmail($user, $account),
				'phone' => $this->resolveAccountProperty($account, IAccountManager::PROPERTY_PHONE),
				'location' => $this->resolveAccountProperty($account, IAccountManager::PROPERTY_ADDRESS),
				default => '',
			};
		}

		return $defaults;
	}

	private function resolveEmail(IUser $user, $account): string {
		if (method_exists($user, 'getEMailAddress')) {
			$email = $user->getEMailAddress();
			if (is_string($email) && $email !== '') {
				return $email;
			}
		}

		return $this->resolveAccountProperty($account, IAccountManager::PROPERTY_EMAIL);
	}

	private function resolveAccountProperty($account, string $propertyName): string {
		try {
			return $account->getProperty($propertyName)->getValue();
		} catch (PropertyDoesNotExistException) {
			return '';
		}
	}
}