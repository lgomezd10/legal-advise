<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Service;

use OCA\Gestion_incidencias\Db\SavedFilter;
use OCA\Gestion_incidencias\Db\SavedFilterMapper;

class SupportFilterService {
	private const PREDEFINED_FILTERS = [
		['name' => 'Asignadas a mi', 'criteria' => ['assignedUser' => '__me__'], 'sortOrder' => 10],
		['name' => 'Asignadas a mis grupos', 'criteria' => ['assignedGroup' => '__my_groups__'], 'sortOrder' => 20],
		['name' => 'Sin asignar', 'criteria' => ['unassigned' => true], 'sortOrder' => 30],
		['name' => 'Abiertas', 'criteria' => ['status' => ['nuevo', 'asignado', 'en_progreso']], 'sortOrder' => 40],
		['name' => 'Pendientes de usuario', 'criteria' => ['status' => ['en_espera_usuario']], 'sortOrder' => 50],
		['name' => 'Cerradas recientes', 'criteria' => ['status' => ['resuelto', 'cerrado'], 'updatedWithinDays' => 30], 'sortOrder' => 60],
	];

	public function __construct(private readonly SavedFilterMapper $savedFilterMapper) {
	}

	public function list(string $uid): array {
		$this->ensurePredefinedFilters();
		$rows = $this->savedFilterMapper->findAllOrdered('sort_order', 'ASC');
		return array_values(array_filter(array_map(static fn ($row) => $row->jsonSerialize(), $rows), static function (array $row) use ($uid): bool {
			return (bool) ($row['isPredefined'] ?? false) || ($row['ownerUid'] ?? null) === $uid;
		}));
	}

	public function save(string $uid, array $payload): array {
		$filter = new SavedFilter();
		$filter->setOwnerUid($uid);
		$filter->setName((string) ($payload['name'] ?? 'Filtro'));
		$filter->setCriteria($payload['criteria'] ?? []);
		$filter->setIsPredefined(false);
		$filter->setSortOrder((int) ($payload['sortOrder'] ?? 999));
		return $this->savedFilterMapper->insert($filter)->jsonSerialize();
	}

	public function delete(string $uid, int $id): void {
		$filter = $this->savedFilterMapper->find($id);
		if ($filter->getIsPredefined() || $filter->getOwnerUid() !== $uid) {
			throw new \RuntimeException('Forbidden', 403);
		}

		$this->savedFilterMapper->delete($filter);
	}

	private function ensurePredefinedFilters(): void {
		$rows = $this->savedFilterMapper->findAllOrdered('sort_order', 'ASC');
		$byName = [];
		foreach ($rows as $row) {
			$byName[$row->getName()] = $row;
		}

		foreach (self::PREDEFINED_FILTERS as $definition) {
			$existing = $byName[$definition['name']] ?? null;
			if ($existing instanceof SavedFilter) {
				$existing->setCriteria($definition['criteria']);
				$existing->setIsPredefined(true);
				$existing->setSortOrder($definition['sortOrder']);
				$existing->setOwnerUid(null);
				$this->savedFilterMapper->update($existing);
				continue;
			}

			$filter = new SavedFilter();
			$filter->setOwnerUid(null);
			$filter->setName($definition['name']);
			$filter->setCriteria($definition['criteria']);
			$filter->setIsPredefined(true);
			$filter->setSortOrder($definition['sortOrder']);
			$this->savedFilterMapper->insert($filter);
		}
	}
}