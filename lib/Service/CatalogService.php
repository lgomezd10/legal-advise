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
		'maxFileSizeMb' => 100,
	];

	public const CORE_STATUS_DEFINITIONS = [
		['id' => 'nuevo', 'label' => 'Nuevo', 'description' => 'Estado inicial al registrar un ticket nuevo.', 'active' => true, 'closed' => false, 'fixed' => true, 'toggleable' => false],
		['id' => 'asignado', 'label' => 'Asignado', 'description' => 'El ticket ya esta en gestion por soporte.', 'active' => true, 'closed' => false, 'fixed' => true, 'toggleable' => false],
		['id' => 'en_espera_usuario', 'label' => 'En espera usuario', 'description' => 'Soporte necesita una respuesta o acción del usuario.', 'active' => true, 'closed' => false, 'fixed' => true, 'toggleable' => false],
		['id' => 'en_progreso', 'label' => 'En progreso', 'description' => 'El ticket se esta tramitando internamente.', 'active' => true, 'closed' => false, 'fixed' => true, 'toggleable' => false],
		['id' => 'resuelto', 'label' => 'Resuelto', 'description' => 'El ticket ya tiene una resolucion comunicada.', 'active' => true, 'closed' => true, 'fixed' => true, 'toggleable' => true],
		['id' => 'cerrado', 'label' => 'Cerrado', 'description' => 'El ticket ha quedado cerrado definitivamente.', 'active' => true, 'closed' => true, 'fixed' => true, 'toggleable' => false],
		['id' => 'abierto_personalizado_1', 'label' => 'Estado abierto 1', 'description' => 'Estado abierto editable adicional.', 'active' => false, 'closed' => false, 'fixed' => false, 'toggleable' => true],
		['id' => 'abierto_personalizado_2', 'label' => 'Estado abierto 2', 'description' => 'Estado abierto editable adicional.', 'active' => false, 'closed' => false, 'fixed' => false, 'toggleable' => true],
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
			'active' => (bool) $definition['active'],
			'closed' => (bool) $definition['closed'],
			'fixed' => (bool) $definition['fixed'],
			'toggleable' => (bool) $definition['toggleable'],
		], self::CORE_STATUS_DEFINITIONS);
	}

	public static function getDefaultStatusCatalogFromCurrent(mixed $entries): array {
		return array_map(static fn (array $status): array => [
			'id' => $status['id'],
			'label' => $status['label'],
			'active' => (bool) ($status['active'] ?? true),
			'closed' => (bool) ($status['closed'] ?? false),
			'fixed' => (bool) ($status['fixed'] ?? true),
			'toggleable' => (bool) ($status['toggleable'] ?? false),
		], self::normalizeStatusCatalog($entries));
	}

	public static function normalizeStatusCatalog(mixed $entries): array {
		$labelsById = [];
		$currentOrder = [];
		if (is_array($entries)) {
			foreach ($entries as $entry) {
				if (!is_array($entry)) {
					continue;
				}

				$id = isset($entry['id']) && is_string($entry['id']) ? trim($entry['id']) : '';
				$label = isset($entry['label']) && is_string($entry['label']) ? trim($entry['label']) : '';
				if ($id !== '' && $label !== '') {
					$labelsById[$id] = [
						'label' => $label,
						'active' => isset($entry['active']) ? (bool) $entry['active'] : null,
					];
					$currentOrder[] = $id;
				}
			}
		}

		$definitionsById = [];
		foreach (self::CORE_STATUS_DEFINITIONS as $definition) {
			$definitionsById[$definition['id']] = $definition;
		}

		$orderedIds = [];
		foreach ($currentOrder as $id) {
			if (isset($definitionsById[$id]) && !in_array($id, $orderedIds, true)) {
				$orderedIds[] = $id;
			}
		}

		foreach (self::CORE_STATUS_DEFINITIONS as $definition) {
			if (!in_array($definition['id'], $orderedIds, true)) {
				$orderedIds[] = $definition['id'];
			}
		}

		return array_map(static function (string $id) use ($definitionsById, $labelsById): array {
			$definition = $definitionsById[$id];
			$id = $definition['id'];
			$current = $labelsById[$id] ?? [];
			return [
				'id' => $id,
				'label' => $current['label'] ?? $definition['label'],
				'active' => $definition['toggleable'] ? (bool) ($current['active'] ?? $definition['active']) : (bool) $definition['active'],
				'closed' => (bool) $definition['closed'],
				'fixed' => (bool) $definition['fixed'],
				'toggleable' => (bool) $definition['toggleable'],
				'description' => $definition['description'],
			];
		}, $orderedIds);
	}

	public function isClosedStatus(string $statusId): bool {
		foreach ($this->getStatuses() as $status) {
			if (($status['id'] ?? '') === $statusId) {
				return (bool) ($status['closed'] ?? false);
			}
		}

		return false;
	}

	public function isActiveStatus(string $statusId): bool {
		foreach ($this->getStatuses() as $status) {
			if (($status['id'] ?? '') === $statusId) {
				return (bool) ($status['active'] ?? true);
			}
		}

		return false;
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