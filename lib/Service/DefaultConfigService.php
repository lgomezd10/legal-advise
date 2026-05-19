<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Service;

use OCA\ConsultasLegales\Db\AppSetting;
use OCA\ConsultasLegales\Db\AppSettingMapper;
use OCA\ConsultasLegales\Db\AssignmentRule;
use OCA\ConsultasLegales\Db\AssignmentRuleMapper;
use OCA\ConsultasLegales\Db\CustomField;
use OCA\ConsultasLegales\Db\CustomFieldMapper;
use OCA\ConsultasLegales\Db\IncidentType;
use OCA\ConsultasLegales\Db\IncidentTypeMapper;
use OCA\ConsultasLegales\Db\NotificationPreference;
use OCA\ConsultasLegales\Db\NotificationPreferenceMapper;
use OCA\ConsultasLegales\Notification\NotificationPolicy;
use OCA\ConsultasLegales\Db\ProfileAssignment;
use OCA\ConsultasLegales\Db\ProfileAssignmentMapper;
use OCA\ConsultasLegales\Db\Urgency;
use OCA\ConsultasLegales\Db\UrgencyMapper;

class DefaultConfigService {
	private bool $ensured = false;

	public function __construct(
		private readonly AppSettingMapper $settingMapper,
		private readonly UrgencyMapper $urgencyMapper,
		private readonly CustomFieldMapper $fieldMapper,
		private readonly IncidentTypeMapper $typeMapper,
		private readonly AssignmentRuleMapper $ruleMapper,
		private readonly NotificationPreferenceMapper $notificationPreferenceMapper,
		private readonly ProfileAssignmentMapper $profileAssignmentMapper,
	) {
	}

	public function ensureDefaults(): void {
		if ($this->ensured) {
			return;
		}

		$this->ensureSettings();
		$this->ensureUrgencies();
		$this->ensureFields();
		$typeIds = $this->ensureTypes();
		$this->ensureAssignmentRules($typeIds);
		$this->ensureNotificationPreferences();
		$this->ensureProfileAssignments();

		$this->ensured = true;
	}

	private function ensureSettings(): void {
		$settings = [
			'status_catalog' => CatalogService::getDefaultStatusCatalog(),
			'tasks_config' => [
				'enabled' => true,
				'defaultStrategy' => 'firstWritable',
				'missingListBehavior' => 'skip',
			],
			'notification_policy' => [
				'defaultChannelOrder' => ['nextcloud', 'mail'],
				'allowUserOverrides' => true,
			],
			'attachment_config' => [
				'allowedExtensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tif', 'tiff', 'mp3', 'wav', 'ogg', 'oga', 'm4a', 'aac', 'flac', 'opus', 'wma', 'mp4', 'm4v', 'mov', 'avi', 'mkv', 'webm', 'mpeg', 'mpg', '3gp', 'wmv', 'ogv'],
				'maxFileSizeMb' => 100,
			],
		];

		foreach ($settings as $key => $value) {
			$existing = $this->settingMapper->findOneBy('config_key', $key);
			if ($existing instanceof AppSetting) {
				if ($key === 'status_catalog') {
					$existing->setConfigValue(CatalogService::getDefaultStatusCatalogFromCurrent($existing->getConfigValue()));
					$this->settingMapper->update($existing);
				} elseif ($key === 'attachment_config') {
					$current = is_array($existing->getConfigValue()) ? $existing->getConfigValue() : [];
					$currentExtensions = is_array($current['allowedExtensions'] ?? null) ? $current['allowedExtensions'] : [];
					$current['allowedExtensions'] = array_values(array_unique(array_filter(array_map(static fn ($extension) => is_string($extension) ? strtolower(trim(ltrim($extension, '.'))) : '', array_merge($value['allowedExtensions'], $currentExtensions)), static fn (string $extension): bool => $extension !== '')));
					$hasCurrentMax = array_key_exists('maxFileSizeMb', $current);
					$currentMax = $hasCurrentMax ? max(1, (int) $current['maxFileSizeMb']) : 0;
					$current['maxFileSizeMb'] = !$hasCurrentMax || $currentMax === 25 ? $value['maxFileSizeMb'] : $currentMax;
					$existing->setConfigValue($current);
					$this->settingMapper->update($existing);
				}
				continue;
			}

			$setting = new AppSetting();
			$setting->setConfigKey($key);
			$setting->setConfigValue($value);
			$this->settingMapper->insert($setting);
		}
	}

	private function ensureUrgencies(): void {
		$defaults = [
			['name' => 'Alta', 'weight' => 1, 'color' => '#D96C3F'],
			['name' => 'Media', 'weight' => 2, 'color' => '#D9A441'],
			['name' => 'Baja', 'weight' => 3, 'color' => '#7A8F62'],
		];

		foreach ($defaults as $row) {
			if ($this->urgencyMapper->findOneBy('name', $row['name']) instanceof Urgency) {
				continue;
			}

			$entity = new Urgency();
			$entity->setName($row['name']);
			$entity->setWeight($row['weight']);
			$entity->setColor($row['color']);
			$entity->setRestrictions(null);
			$entity->setActive(true);
			$this->urgencyMapper->insert($entity);
		}
	}

	private function ensureFields(): void {
		$defaults = [
			['fieldKey' => 'name', 'label' => 'Nombre', 'fieldType' => 'text', 'required' => true, 'preloadSource' => 'displayName', 'sortOrder' => 10],
			['fieldKey' => 'email', 'label' => 'Email', 'fieldType' => 'email', 'required' => true, 'preloadSource' => 'email', 'sortOrder' => 20],
			['fieldKey' => 'phone', 'label' => 'Teléfono', 'fieldType' => 'tel', 'required' => false, 'preloadSource' => 'phone', 'sortOrder' => 30],
			['fieldKey' => 'city', 'label' => 'Ciudad', 'fieldType' => 'text', 'required' => false, 'preloadSource' => 'location', 'sortOrder' => 40],
			['fieldKey' => 'province', 'label' => 'Provincia', 'fieldType' => 'text', 'required' => false, 'preloadSource' => '', 'sortOrder' => 50],
		];

		foreach ($defaults as $row) {
			$existing = $this->fieldMapper->findOneBy('field_key', $row['fieldKey']);
			if ($existing instanceof CustomField) {
				if ($existing->getLabel() === 'Telefono') {
					$existing->setLabel($row['label']);
					$this->fieldMapper->update($existing);
				}
				continue;
			}

			$entity = new CustomField();
			$entity->setFieldKey($row['fieldKey']);
			$entity->setLabel($row['label']);
			$entity->setFieldType($row['fieldType']);
			$entity->setRequired($row['required']);
			$entity->setPreloadSource($row['preloadSource']);
			$entity->setSortOrder($row['sortOrder']);
			$entity->setActive(true);
			$this->fieldMapper->insert($entity);
		}
	}

	/**
	 * @return array<string, int>
	 */
	private function ensureTypes(): array {
		$existingTypes = $this->typeMapper->findAllOrdered('level', 'ASC');
		if ($existingTypes !== []) {
			$this->markTypeDefaultsSeeded();

			return array_reduce($existingTypes, static function (array $typeIds, IncidentType $type): array {
				$typeIds[(string) $type->getSlug()] = (int) $type->getId();
				return $typeIds;
			}, []);
		}

		if ($this->hasSeededTypeDefaults()) {
			return [];
		}

		$defaults = [
			['slug' => 'necesito-asesoramiento', 'name' => 'Necesito asesoramiento', 'legacyNames' => ['Neceisto asesoramiento'], 'parentSlug' => null, 'level' => 0, 'sortOrder' => 10],
			['slug' => 'necesito-asesoramiento-solo-territorial', 'name' => 'Solo Territorial', 'parentSlug' => 'necesito-asesoramiento', 'level' => 1, 'sortOrder' => 10],
			['slug' => 'necesito-asesoramiento-territorial-y-legal', 'name' => 'Territorial y Legal', 'parentSlug' => 'necesito-asesoramiento', 'level' => 1, 'sortOrder' => 20],
			['slug' => 'necesito-asesoramiento-territorial-y-comunicacion', 'name' => 'Territorial y Comunicación', 'legacyNames' => ['Territorial y Comunicacion'], 'parentSlug' => 'necesito-asesoramiento', 'level' => 1, 'sortOrder' => 30],
			['slug' => 'necesito-asesoramiento-territorial-legal-y-comunicacion', 'name' => 'Territorial, Legal y Comunicación', 'legacyNames' => ['Territoral, Legal y Comunicacion'], 'parentSlug' => 'necesito-asesoramiento', 'level' => 1, 'sortOrder' => 40],
			['slug' => 'quiero-informar', 'name' => 'Quiero informar', 'parentSlug' => null, 'level' => 0, 'sortOrder' => 20],
		];

		$typeIds = [];
		foreach ($defaults as $row) {
			$existing = $this->typeMapper->findOneBy('slug', $row['slug']);
			if ($existing instanceof IncidentType) {
				$legacyNames = $row['legacyNames'] ?? [];
				if ($existing->getName() !== $row['name'] && in_array($existing->getName(), $legacyNames, true)) {
					$existing->setName($row['name']);
					$this->typeMapper->update($existing);
				}
				$typeIds[$row['slug']] = (int) $existing->getId();
				continue;
			}

			$entity = new IncidentType();
			$entity->setParentId($row['parentSlug'] === null ? null : ($typeIds[$row['parentSlug']] ?? null));
			$entity->setName($row['name']);
			$entity->setSlug($row['slug']);
			$entity->setLevel($row['level']);
			$entity->setSortOrder($row['sortOrder']);
			$entity->setActive(true);
			$typeIds[$row['slug']] = (int) $this->typeMapper->insert($entity)->getId();
		}

		$this->markTypeDefaultsSeeded();

		return $typeIds;
	}

	private function hasSeededTypeDefaults(): bool {
		$existing = $this->settingMapper->findOneBy('config_key', 'types_defaults_seeded');
		if (!$existing instanceof AppSetting) {
			return false;
		}

		return (bool) $existing->getConfigValue();
	}

	private function markTypeDefaultsSeeded(): void {
		$existing = $this->settingMapper->findOneBy('config_key', 'types_defaults_seeded');
		if ($existing instanceof AppSetting) {
			if ((bool) $existing->getConfigValue() === true) {
				return;
			}

			$existing->setConfigValue(true);
			$this->settingMapper->update($existing);
			return;
		}

		$setting = new AppSetting();
		$setting->setConfigKey('types_defaults_seeded');
		$setting->setConfigValue(true);
		$this->settingMapper->insert($setting);
	}

	/**
	 * @param array<string, int> $typeIds
	 */
	private function ensureAssignmentRules(array $typeIds): void {
		$defaults = [];

		foreach ($defaults as $row) {
			$typeId = $typeIds[$row['slug']] ?? null;
			if ($typeId === null || $this->ruleMapper->findOneBy('type_id', $typeId) instanceof AssignmentRule) {
				continue;
			}

			$entity = new AssignmentRule();
			$entity->setTypeId($typeId);
			$entity->setAssignedUserUid($row['assignedUserUid']);
			$entity->setAssignedGroupId($row['assignedGroupId']);
			$entity->setPriority($row['priority']);
			$this->ruleMapper->insert($entity);
		}
	}

	private function ensureNotificationPreferences(): void {
		foreach (['usuario', 'soporte', 'administrador'] as $profile) {
			foreach (NotificationPolicy::getNotificationEventsForProfile($profile) as $eventName) {
				$this->ensureNotificationPreference($profile, $eventName, NotificationPolicy::CHANNEL_NEXTCLOUD, NotificationPolicy::getDefaultChannelEnabledForProfile($profile, $eventName, NotificationPolicy::CHANNEL_NEXTCLOUD));
				$this->ensureNotificationPreference($profile, $eventName, NotificationPolicy::CHANNEL_MAIL, NotificationPolicy::getDefaultChannelEnabledForProfile($profile, $eventName, NotificationPolicy::CHANNEL_MAIL));
			}
		}
	}

	private function ensureNotificationPreference(string $profile, string $eventName, string $channel, bool $enabled): void {
		$existing = $this->notificationPreferenceMapper->findByMany([
			'scope_type' => 'profile',
			'scope_id' => $profile,
			'event_name' => $eventName,
			'channel' => $channel,
		]);

		if ($existing !== []) {
			return;
		}

		$entity = new NotificationPreference();
		$entity->setScopeType('profile');
		$entity->setScopeId($profile);
		$entity->setEventName($eventName);
		$entity->setChannel($channel);
		$entity->setEnabled($enabled);
		$this->notificationPreferenceMapper->insert($entity);
	}

	private function ensureProfileAssignments(): void {
		if ($this->profileAssignmentMapper->findAllOrdered('id', 'ASC') !== []) {
			return;
		}

		$defaults = [
			['profile' => RoleService::USER, 'principalType' => 'group', 'principalId' => 'userLegal'],
			['profile' => RoleService::USER, 'principalType' => 'group', 'principalId' => 'admin'],
			['profile' => RoleService::SUPPORT, 'principalType' => 'group', 'principalId' => 'supportLegal'],
			['profile' => RoleService::SUPPORT, 'principalType' => 'group', 'principalId' => 'admin'],
			['profile' => RoleService::ADMIN, 'principalType' => 'group', 'principalId' => 'admin'],
		];

		foreach ($defaults as $row) {
			$entity = new ProfileAssignment();
			$entity->setProfile($row['profile']);
			$entity->setPrincipalType($row['principalType']);
			$entity->setPrincipalId($row['principalId']);
			$this->profileAssignmentMapper->insert($entity);
		}
	}
}