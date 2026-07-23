<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Service;

use OCA\ConsultasLegales\Db\AppSettingMapper;
use OCA\ConsultasLegales\Db\AssignmentRule;
use OCA\ConsultasLegales\Db\AssignmentRuleMapper;
use OCA\ConsultasLegales\Db\CustomField;
use OCA\ConsultasLegales\Db\CustomFieldMapper;
use OCA\ConsultasLegales\Db\NotificationPreference;
use OCA\ConsultasLegales\Db\NotificationPreferenceMapper;
use OCA\ConsultasLegales\Notification\NotificationPolicy;
use OCP\AppFramework\Db\DoesNotExistException;
use OCA\ConsultasLegales\Db\IncidentType;
use OCA\ConsultasLegales\Db\IncidentTypeMapper;
use OCA\ConsultasLegales\Db\ProfileAssignment;
use OCA\ConsultasLegales\Db\ProfileAssignmentMapper;
use OCA\ConsultasLegales\Db\Urgency;
use OCA\ConsultasLegales\Db\UrgencyMapper;

class AdminConfigService {
	private const ALLOWED_PROFILES = [RoleService::USER, RoleService::SUPPORT, RoleService::ADMIN];
	private const FALLBACK_ATTACHMENT_CONFIG = [
		'allowedExtensions' => [],
		'maxFileSizeMb' => 100,
	];

	public function __construct(
		private readonly DefaultConfigService $defaultConfigService,
		private readonly ProvinceCatalogService $provinceCatalogService,
		private readonly CatalogService $catalogService,
		private readonly SupportFilterService $supportFilterService,
		private readonly IncidentTypeMapper $typeMapper,
		private readonly UrgencyMapper $urgencyMapper,
		private readonly CustomFieldMapper $fieldMapper,
		private readonly AssignmentRuleMapper $ruleMapper,
		private readonly ProfileAssignmentMapper $profileMapper,
		private readonly NotificationPreferenceMapper $notificationPreferenceMapper,
		private readonly AppSettingMapper $settingMapper,
	) {
	}

	public function getConfig(): array {
		$this->defaultConfigService->ensureDefaults();

		return [
			'statuses' => $this->catalogService->getStatuses(),
			'types' => $this->catalogService->getTypeTree(),
			'urgencies' => $this->catalogService->getUrgencies(),
			'fields' => $this->catalogService->getFields(false),
			'filters' => $this->safeAdminFilters(),
			'rules' => array_map(static fn ($row) => $row->jsonSerialize(), $this->ruleMapper->findAllOrdered('priority', 'DESC')),
			'profiles' => array_map(static fn ($row) => $row->jsonSerialize(), $this->profileMapper->findAllOrdered('profile', 'ASC')),
			'notifications' => $this->safeProfileNotificationPreferences(),
			'attachmentConfig' => $this->safeAttachmentConfig(),
			'tasksConfig' => $this->safeTasksConfig(),
			'appDisplayName' => $this->safeAppDisplayName(),
		];
	}

	private function safeAppDisplayName(): string {
		try {
			return $this->catalogService->getAppDisplayName();
		} catch (\Throwable) {
			return CatalogService::DEFAULT_APP_DISPLAY_NAME;
		}
	}

	private function safeAdminFilters(): array {
		try {
			return $this->supportFilterService->listForAdmin();
		} catch (\Throwable) {
			return [];
		}
	}

	private function safeProfileNotificationPreferences(): array {
		try {
			return $this->getProfileNotificationPreferences();
		} catch (\Throwable) {
			return [];
		}
	}

	private function safeAttachmentConfig(): array {
		try {
			return $this->catalogService->getAttachmentConfig();
		} catch (\Throwable) {
			return self::FALLBACK_ATTACHMENT_CONFIG;
		}
	}

	private function safeTasksConfig(): array {
		try {
			return $this->catalogService->getTaskConfig();
		} catch (\Throwable) {
			return [];
		}
	}

	public function update(array $payload): array {
		$this->defaultConfigService->ensureDefaults();

		if (isset($payload['statuses']) && is_array($payload['statuses'])) {
			$setting = $this->settingMapper->findOneBy('config_key', 'status_catalog');
			$setting->setConfigValue(CatalogService::getDefaultStatusCatalogFromCurrent(array_map(static function (array $status): array {
				return [
					'id' => (string) ($status['id'] ?? ''),
					'label' => (string) ($status['label'] ?? ''),
					'active' => (bool) ($status['active'] ?? true),
				];
			}, $payload['statuses'])));
			$this->settingMapper->update($setting);
		}

		if (isset($payload['urgencies']) && is_array($payload['urgencies'])) {
			foreach ($payload['urgencies'] as $row) {
				$entity = new Urgency();
				$entity->setName((string) $row['name']);
				$entity->setWeight((int) $row['weight']);
				$entity->setColor((string) $row['color']);
				$entity->setRestrictions($row['restrictions'] ?? []);
				$entity->setActive((bool) ($row['active'] ?? true));
				if (isset($row['id'])) {
					$entity->setId((int) $row['id']);
					$this->urgencyMapper->update($entity);
				} else {
					$this->urgencyMapper->insert($entity);
				}
			}
		}

		if (isset($payload['fields']) && is_array($payload['fields'])) {
			foreach ($payload['fields'] as $row) {
				$entity = new CustomField();
				$entity->setFieldKey((string) $row['fieldKey']);
				$entity->setLabel((string) $row['label']);
				$entity->setFieldType((string) $row['fieldType']);
				$entity->setRequired((bool) ($row['required'] ?? false));
				$entity->setPreloadSource((string) ($row['preloadSource'] ?? ''));
				$entity->setSortOrder((int) ($row['sortOrder'] ?? 0));
				$entity->setActive((bool) ($row['active'] ?? true));
				if (isset($row['id'])) {
					$entity->setId((int) $row['id']);
					$this->fieldMapper->update($entity);
				} else {
					$this->fieldMapper->insert($entity);
				}
			}
		}

		if (isset($payload['filters']) && is_array($payload['filters'])) {
			$this->supportFilterService->saveForAdmin($payload['filters']);
		}

		if (isset($payload['types']) && is_array($payload['types'])) {
			$this->syncTypes($payload['types']);
		}

		if (isset($payload['rules']) && is_array($payload['rules'])) {
			$persistedRuleIds = [];
			foreach ($payload['rules'] as $row) {
				$province = $this->provinceCatalogService->normalize(is_string($row['province'] ?? null) ? (string) $row['province'] : null);
				if (($row['province'] ?? null) !== null && trim((string) $row['province']) !== '' && $province === null) {
					throw new \InvalidArgumentException('La provincia de la regla no es valida.');
				}

				$entity = new AssignmentRule();
				$entity->setTypeId((int) $row['typeId']);
				$entity->setProvince($province);
				$entity->setAssignedUserUid($row['assignedUserUid'] ?? null);
				$entity->setAssignedGroupId($row['assignedGroupId'] ?? null);
				$entity->setPriority((int) ($row['priority'] ?? 0));
				if (isset($row['id'])) {
					$entity->setId((int) $row['id']);
					$this->ruleMapper->update($entity);
					$persistedRuleIds[] = (int) $row['id'];
				} else {
					$entity = $this->ruleMapper->insert($entity);
					$persistedRuleIds[] = (int) $entity->getId();
				}
			}

			foreach ($this->ruleMapper->findAllOrdered('id', 'ASC') as $existingRule) {
				if (!in_array((int) $existingRule->getId(), $persistedRuleIds, true)) {
					$this->ruleMapper->delete($existingRule);
				}
			}
		}

		if (isset($payload['profiles']) && is_array($payload['profiles'])) {
			$normalizedProfiles = [];
			foreach ($payload['profiles'] as $row) {
				$profile = trim((string) ($row['profile'] ?? ''));
				if (!in_array($profile, self::ALLOWED_PROFILES, true)) {
					throw new \InvalidArgumentException('El perfil indicado no es válido.');
				}

				$principalType = trim((string) ($row['principalType'] ?? ''));
				if (!in_array($principalType, ['user', 'group'], true)) {
					throw new \InvalidArgumentException('El tipo de principal no es válido.');
				}

				$principalId = trim((string) ($row['principalId'] ?? ''));
				if ($principalId === '') {
					throw new \InvalidArgumentException('Debes indicar un usuario o grupo para el perfil.');
				}

				$normalizedProfiles[$profile . '|' . $principalType . '|' . $principalId] = [
					'profile' => $profile,
					'principalType' => $principalType,
					'principalId' => $principalId,
				];
			}

			$hasAdminGroup = array_reduce(
				array_values($normalizedProfiles),
				static fn (bool $carry, array $row): bool => $carry || ($row['profile'] === RoleService::ADMIN && $row['principalType'] === 'group'),
				false,
			);

			if (!$hasAdminGroup) {
				throw new \InvalidArgumentException('El perfil Administrador debe conservar al menos un grupo asignado.');
			}

			foreach ($this->profileMapper->findAllOrdered('id', 'ASC') as $existingProfile) {
				$this->profileMapper->delete($existingProfile);
			}

			foreach (array_values($normalizedProfiles) as $row) {
				$entity = new ProfileAssignment();
				$entity->setProfile($row['profile']);
				$entity->setPrincipalType($row['principalType']);
				$entity->setPrincipalId($row['principalId']);
				$this->profileMapper->insert($entity);
			}
		}

		if (isset($payload['notifications']) && is_array($payload['notifications'])) {
			$this->saveProfileNotificationPreferences($payload['notifications']);
		}

		if (isset($payload['tasksConfig'])) {
			$setting = $this->settingMapper->findOneBy('config_key', 'tasks_config');
			$setting->setConfigValue($payload['tasksConfig']);
			$this->settingMapper->update($setting);
		}

		if (isset($payload['attachmentConfig']) && is_array($payload['attachmentConfig'])) {
			$setting = $this->settingMapper->findOneBy('config_key', 'attachment_config');
			$setting->setConfigValue([
				'allowedExtensions' => $this->normalizeAllowedExtensions($payload['attachmentConfig']['allowedExtensions'] ?? []),
				'maxFileSizeMb' => $this->normalizeMaxFileSizeMb($payload['attachmentConfig']['maxFileSizeMb'] ?? null),
			]);
			$this->settingMapper->update($setting);
		}

		if (array_key_exists('appDisplayName', $payload)) {
			$name = is_string($payload['appDisplayName']) ? trim($payload['appDisplayName']) : '';
			if ($name === '') {
				throw new \InvalidArgumentException('El nombre visible de la aplicación no puede quedar vacío.');
			}
			if (mb_strlen($name) > 100) {
				throw new \InvalidArgumentException('El nombre visible de la aplicación es demasiado largo.');
			}

			$setting = $this->settingMapper->findOneBy('config_key', 'app_display_name');
			$setting->setConfigValue($name);
			$this->settingMapper->update($setting);
		}

		return $this->getConfig();
	}

	private function getProfileNotificationPreferences(): array {
		$itemsByProfileAndEvent = [];
		foreach (self::ALLOWED_PROFILES as $profile) {
			foreach (NotificationPolicy::getNotificationEventsForProfile($profile) as $eventName) {
				$itemsByProfileAndEvent[$profile . ':' . $eventName] = [
					'scopeId' => $profile,
					'eventName' => $eventName,
					'deliveryMode' => NotificationPolicy::defaultDeliveryModeForProfile($profile, $eventName),
				];
			}
		}

		foreach ($this->notificationPreferenceMapper->findAllOrdered('scope_id', 'ASC') as $row) {
			if ($row->getScopeType() !== 'profile') {
				continue;
			}

			$profile = (string) $row->getScopeId();
			$eventName = (string) $row->getEventName();
			if (!in_array($profile, self::ALLOWED_PROFILES, true) || !in_array($eventName, NotificationPolicy::getSupportedEvents(), true)) {
				continue;
			}

			$key = $profile . ':' . $eventName;
			$current = $itemsByProfileAndEvent[$key] ?? null;
			if ($current === null) {
				continue;
			}

			$nextcloudEnabled = $current['deliveryMode'] === 'nextcloud' || $current['deliveryMode'] === 'both';
			$mailEnabled = $current['deliveryMode'] === 'mail' || $current['deliveryMode'] === 'both';
			if ($row->getChannel() === 'nextcloud') {
				$nextcloudEnabled = (bool) $row->getEnabled();
			}
			if ($row->getChannel() === 'mail') {
				$mailEnabled = (bool) $row->getEnabled();
			}

			$itemsByProfileAndEvent[$key]['deliveryMode'] = NotificationPolicy::normalizeAdminDeliveryMode(NotificationPolicy::resolveDeliveryMode($nextcloudEnabled, $mailEnabled));
		}

		return array_values($itemsByProfileAndEvent);
	}

	private function saveProfileNotificationPreferences(array $items): void {
		$this->notificationPreferenceMapper->deleteBy('scope_type', 'profile');

		foreach ($items as $item) {
			$profile = trim((string) ($item['scopeId'] ?? ''));
			$eventName = trim((string) ($item['eventName'] ?? ''));
			if (!in_array($profile, self::ALLOWED_PROFILES, true) || !in_array($eventName, NotificationPolicy::getNotificationEventsForProfile($profile), true)) {
				continue;
			}

			$deliveryMode = NotificationPolicy::normalizeAdminDeliveryMode($item['deliveryMode'] ?? null);
			$this->insertNotificationPreference($profile, $eventName, NotificationPolicy::CHANNEL_NEXTCLOUD, in_array($deliveryMode, [NotificationPolicy::DELIVERY_NEXTCLOUD, NotificationPolicy::DELIVERY_BOTH], true));
			$this->insertNotificationPreference($profile, $eventName, NotificationPolicy::CHANNEL_MAIL, $deliveryMode === NotificationPolicy::DELIVERY_BOTH);
		}
	}

	private function insertNotificationPreference(string $profile, string $eventName, string $channel, bool $enabled): void {
		$entity = new NotificationPreference();
		$entity->setScopeType('profile');
		$entity->setScopeId($profile);
		$entity->setEventName($eventName);
		$entity->setChannel($channel);
		$entity->setEnabled($enabled);
		$this->notificationPreferenceMapper->insert($entity);
	}

	private function normalizeAllowedExtensions(mixed $extensions): array {
		if (!is_array($extensions)) {
			return [];
		}

		$normalized = array_map(
			static fn ($extension) => is_string($extension) ? strtolower(trim(ltrim($extension, '.'))) : '',
			$extensions,
		);

		return array_values(array_unique(array_filter($normalized, static fn (string $extension): bool => $extension !== '')));
	}

	private function normalizeMaxFileSizeMb(mixed $value): int {
		$normalized = (int) $value;
		return max(1, $normalized > 0 ? $normalized : 25);
	}

	private function syncTypes(array $rows): void {
		$persistedTypeIds = [];
		$this->saveTypes($rows, null, '', 0, $persistedTypeIds);

		$obsoleteTypes = array_values(array_filter(
			$this->typeMapper->findAllOrdered('level', 'DESC'),
			static fn (IncidentType $type): bool => !in_array((int) $type->getId(), $persistedTypeIds, true),
		));

		foreach ($obsoleteTypes as $type) {
			$this->ruleMapper->deleteBy('type_id', (int) $type->getId());
		}

		foreach ($obsoleteTypes as $type) {
			$this->typeMapper->delete($type);
		}
	}

	private function saveTypes(array $rows, ?int $parentId = null, string $parentSlug = '', int $level = 0, array &$persistedTypeIds = []): void {
		foreach ($rows as $index => $row) {
			$name = trim((string) ($row['name'] ?? ''));
			if ($name === '') {
				continue;
			}

			$entity = new IncidentType();
			$existingSlug = null;
			if (isset($row['id'])) {
				try {
					/** @var IncidentType $existing */
					$existing = $this->typeMapper->find((int) $row['id']);
					$entity = $existing;
					$existingSlug = (string) $existing->getSlug();
				} catch (DoesNotExistException) {
				}
			}

			$entity->setParentId($parentId);
			$entity->setName($name);
			$slug = trim((string) ($row['slug'] ?? ''));
			if ($slug === '') {
				$slug = $existingSlug ?? $this->buildTypeSlug($parentSlug, $name, $index + 1);
			}
			$entity->setSlug($slug);
			$entity->setLevel((int) ($row['level'] ?? $level));
			$entity->setSortOrder((int) ($row['sortOrder'] ?? ($index + 1) * 10));
			$entity->setActive((bool) ($row['active'] ?? true));

			if (isset($row['id'])) {
				$entity->setId((int) $row['id']);
				$entity = $this->typeMapper->update($entity);
			} else {
				$entity = $this->typeMapper->insert($entity);
			}

			$persistedTypeIds[] = (int) $entity->getId();

			if (isset($row['children']) && is_array($row['children'])) {
				$this->saveTypes($row['children'], (int) $entity->getId(), $slug, $level + 1, $persistedTypeIds);
			}
		}
	}

	private function buildTypeSlug(string $parentSlug, string $name, int $fallbackIndex): string {
		$normalized = $name;
		if (function_exists('iconv')) {
			$transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
			if ($transliterated !== false) {
				$normalized = $transliterated;
			}
		}

		$slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $normalized), '-'));
		if ($slug === '') {
			$slug = 'tipo-' . $fallbackIndex;
		}

		return $parentSlug === '' ? $slug : $parentSlug . '-' . $slug;
	}
}