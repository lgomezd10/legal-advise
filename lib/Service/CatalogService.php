<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Service;

use OCA\ConsultasLegales\Db\AppSettingMapper;
use OCA\ConsultasLegales\Db\CustomFieldMapper;
use OCA\ConsultasLegales\Db\IncidentTypeMapper;
use OCA\ConsultasLegales\Db\UrgencyMapper;

class CatalogService {
	private const DEFAULT_ATTACHMENT_CONFIG = [
		'allowedExtensions' => [
			'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf', 'txt',
			'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tif', 'tiff',
			'mp3', 'wav', 'ogg', 'oga', 'm4a', 'aac', 'flac', 'opus', 'wma',
			'mp4', 'm4v', 'mov', 'avi', 'mkv', 'webm', 'mpeg', 'mpg', '3gp', 'wmv', 'ogv',
		],
		'maxFileSizeMb' => 25,
	];

	public const CORE_STATUS_DEFINITIONS = [
		['id' => 'nuevo', 'label' => 'Nuevo', 'description' => 'Estado inicial al registrar una nueva consulta.'],
		['id' => 'asignado', 'label' => 'Asignado', 'description' => 'La consulta ya esta en gestion por soporte.'],
		['id' => 'en_espera_usuario', 'label' => 'En espera usuario', 'description' => 'Soporte necesita una respuesta o accion del usuario.'],
		['id' => 'en_progreso', 'label' => 'En progreso', 'description' => 'La consulta se esta tramitando internamente.'],
		['id' => 'resuelto', 'label' => 'Resuelto', 'description' => 'La consulta ya tiene una resolucion comunicada.'],
		['id' => 'cerrado', 'label' => 'Cerrado', 'description' => 'La consulta ha quedado cerrada definitivamente.'],
	];

	public function __construct(
		private readonly IncidentTypeMapper $typeMapper,
		private readonly UrgencyMapper $urgencyMapper,
		private readonly CustomFieldMapper $fieldMapper,
		private readonly AppSettingMapper $settingMapper,
	) {
	}

	public function getStatuses(): array {
		$setting = $this->settingMapper->findOneBy('config_key', 'status_catalog');
		return self::normalizeStatusCatalog($setting?->getConfigValue());
	}

	public static function getDefaultStatusCatalog(): array {
		return array_map(static fn (array $definition): array => [
			'id' => $definition['id'],
			'label' => $definition['label'],
		], self::CORE_STATUS_DEFINITIONS);
	}

	public static function getDefaultStatusCatalogFromCurrent(mixed $entries): array {
		return array_map(static fn (array $status): array => [
			'id' => $status['id'],
			'label' => $status['label'],
		], self::normalizeStatusCatalog($entries));
	}

	public static function normalizeStatusCatalog(mixed $entries): array {
		$labelsById = [];
		if (is_array($entries)) {
			foreach ($entries as $entry) {
				if (!is_array($entry)) {
					continue;
				}

				$id = isset($entry['id']) && is_string($entry['id']) ? trim($entry['id']) : '';
				$label = isset($entry['label']) && is_string($entry['label']) ? trim($entry['label']) : '';
				if ($id !== '' && $label !== '') {
					$labelsById[$id] = $label;
				}
			}
		}

		return array_map(static function (array $definition) use ($labelsById): array {
			$id = $definition['id'];
			return [
				'id' => $id,
				'label' => $labelsById[$id] ?? $definition['label'],
				'fixed' => true,
				'description' => $definition['description'],
			];
		}, self::CORE_STATUS_DEFINITIONS);
	}

	public function getUrgencies(): array {
		return array_map(static fn ($item) => $item->jsonSerialize(), $this->urgencyMapper->findAllOrdered('weight', 'ASC'));
	}

	public function getFields(bool $onlyActive = true): array {
		$items = $this->fieldMapper->findAllOrdered('sort_order', 'ASC');
		if ($onlyActive) {
			$items = array_values(array_filter($items, static fn ($item): bool => (bool) $item->getActive()));
		}

		return array_map(static fn ($item) => $item->jsonSerialize(), $items);
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
			return self::DEFAULT_ATTACHMENT_CONFIG;
		}

		$allowedExtensions = array_values(array_unique(array_filter(array_map(
			static fn ($extension) => is_string($extension) ? strtolower(trim(ltrim($extension, '.'))) : '',
			array_merge(self::DEFAULT_ATTACHMENT_CONFIG['allowedExtensions'], is_array($config['allowedExtensions'] ?? null) ? $config['allowedExtensions'] : []),
		), static fn (string $extension): bool => $extension !== '')));

		return [
			'allowedExtensions' => $allowedExtensions,
			'maxFileSizeMb' => max(1, (int) ($config['maxFileSizeMb'] ?? self::DEFAULT_ATTACHMENT_CONFIG['maxFileSizeMb'])),
		];
	}

	public function getAllowedAttachmentExtensions(): array {
		return $this->getAttachmentConfig()['allowedExtensions'] ?? [];
	}

	public function getMaxAttachmentFileSizeBytes(): int {
		return ((int) ($this->getAttachmentConfig()['maxFileSizeMb'] ?? self::DEFAULT_ATTACHMENT_CONFIG['maxFileSizeMb'])) * 1024 * 1024;
	}
}