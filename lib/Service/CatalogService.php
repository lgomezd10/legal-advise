<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Service;

use OCA\Gestion_incidencias\Db\AppSettingMapper;
use OCA\Gestion_incidencias\Db\CustomFieldMapper;
use OCA\Gestion_incidencias\Db\IncidentTypeMapper;
use OCA\Gestion_incidencias\Db\UrgencyMapper;

class CatalogService {
	public function __construct(
		private readonly IncidentTypeMapper $typeMapper,
		private readonly UrgencyMapper $urgencyMapper,
		private readonly CustomFieldMapper $fieldMapper,
		private readonly AppSettingMapper $settingMapper,
	) {
	}

	public function getStatuses(): array {
		$setting = $this->settingMapper->findOneBy('config_key', 'status_catalog');
		return $setting?->getConfigValue() ?? [];
	}

	public function getUrgencies(): array {
		return array_map(static fn ($item) => $item->jsonSerialize(), $this->urgencyMapper->findAllOrdered('weight', 'ASC'));
	}

	public function getFields(): array {
		return array_map(static fn ($item) => $item->jsonSerialize(), $this->fieldMapper->findAllOrdered('sort_order', 'ASC'));
	}

	public function getTypeTree(): array {
		$items = array_map(static fn ($item) => $item->jsonSerialize(), $this->typeMapper->findAllOrdered('sort_order', 'ASC'));
		$indexed = [];
		foreach ($items as $item) {
			$item['children'] = [];
			$indexed[$item['id']] = $item;
		}

		$tree = [];
		foreach ($indexed as $id => $item) {
			$parentId = $item['parentId'] ?? null;
			if ($parentId === null || !isset($indexed[$parentId])) {
				$tree[] = &$indexed[$id];
				continue;
			}

			$indexed[$parentId]['children'][] = &$indexed[$id];
		}

		return array_values($tree);
	}

	public function getTaskConfig(): array {
		$setting = $this->settingMapper->findOneBy('config_key', 'tasks_config');
		return $setting?->getConfigValue() ?? ['enabled' => false];
	}

	public function getAttachmentConfig(): array {
		$setting = $this->settingMapper->findOneBy('config_key', 'attachment_config');
		$config = $setting?->getConfigValue();

		if (!is_array($config)) {
			return ['allowedExtensions' => []];
		}

		return [
			'allowedExtensions' => array_values(array_filter(
				array_map(
					static fn ($extension) => is_string($extension) ? strtolower(trim(ltrim($extension, '.'))) : '',
					$config['allowedExtensions'] ?? [],
				),
				static fn (string $extension): bool => $extension !== '',
			)),
		];
	}

	public function getAllowedAttachmentExtensions(): array {
		return $this->getAttachmentConfig()['allowedExtensions'] ?? [];
	}
}