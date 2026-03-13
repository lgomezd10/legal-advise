<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Service;

use OCA\Gestion_incidencias\Db\AppSettingMapper;
use OCA\Gestion_incidencias\Db\AssignmentRule;
use OCA\Gestion_incidencias\Db\AssignmentRuleMapper;
use OCA\Gestion_incidencias\Db\CustomField;
use OCA\Gestion_incidencias\Db\CustomFieldMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCA\Gestion_incidencias\Db\IncidentType;
use OCA\Gestion_incidencias\Db\IncidentTypeMapper;
use OCA\Gestion_incidencias\Db\ProfileAssignment;
use OCA\Gestion_incidencias\Db\ProfileAssignmentMapper;
use OCA\Gestion_incidencias\Db\Urgency;
use OCA\Gestion_incidencias\Db\UrgencyMapper;

class AdminConfigService {
	public function __construct(
		private readonly DefaultConfigService $defaultConfigService,
		private readonly ProvinceCatalogService $provinceCatalogService,
		private readonly CatalogService $catalogService,
		private readonly IncidentTypeMapper $typeMapper,
		private readonly UrgencyMapper $urgencyMapper,
		private readonly CustomFieldMapper $fieldMapper,
		private readonly AssignmentRuleMapper $ruleMapper,
		private readonly ProfileAssignmentMapper $profileMapper,
		private readonly AppSettingMapper $settingMapper,
	) {
	}

	public function getConfig(): array {
		$this->defaultConfigService->ensureDefaults();

		return [
			'types' => $this->catalogService->getTypeTree(),
			'urgencies' => $this->catalogService->getUrgencies(),
			'fields' => $this->catalogService->getFields(),
			'rules' => array_map(static fn ($row) => $row->jsonSerialize(), $this->ruleMapper->findAllOrdered('priority', 'DESC')),
			'profiles' => array_map(static fn ($row) => $row->jsonSerialize(), $this->profileMapper->findAllOrdered('profile', 'ASC')),
			'attachmentConfig' => $this->catalogService->getAttachmentConfig(),
			'tasksConfig' => $this->catalogService->getTaskConfig(),
		];
	}

	public function update(array $payload): array {
		$this->defaultConfigService->ensureDefaults();

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

		if (isset($payload['types']) && is_array($payload['types'])) {
			$this->saveTypes($payload['types']);
		}

		if (isset($payload['rules']) && is_array($payload['rules'])) {
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
				} else {
					$this->ruleMapper->insert($entity);
				}
			}
		}

		if (isset($payload['profiles']) && is_array($payload['profiles'])) {
			foreach ($payload['profiles'] as $row) {
				$entity = new ProfileAssignment();
				$entity->setProfile((string) $row['profile']);
				$entity->setPrincipalType((string) $row['principalType']);
				$entity->setPrincipalId((string) $row['principalId']);
				if (isset($row['id'])) {
					$entity->setId((int) $row['id']);
					$this->profileMapper->update($entity);
				} else {
					$this->profileMapper->insert($entity);
				}
			}
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
			]);
			$this->settingMapper->update($setting);
		}

		return $this->getConfig();
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

	private function saveTypes(array $rows, ?int $parentId = null, string $parentSlug = '', int $level = 0): void {
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

			if (isset($row['children']) && is_array($row['children'])) {
				$this->saveTypes($row['children'], (int) $entity->getId(), $slug, $level + 1);
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