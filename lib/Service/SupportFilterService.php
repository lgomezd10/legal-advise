<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Service;

use OCA\ConsultasLegales\Db\SavedFilter;
use OCA\ConsultasLegales\Db\SavedFilterMapper;

class SupportFilterService {
	private const PREDEFINED_FILTERS = [
		['name' => 'Asignadas a mi', 'criteria' => ['assignedUser' => '__me__'], 'sortOrder' => 10, 'isDefault' => true],
		['name' => 'Asignadas a mis grupos', 'criteria' => ['assignedGroup' => '__my_groups__'], 'sortOrder' => 20, 'isDefault' => false],
		['name' => 'Sin asignar', 'criteria' => ['unassigned' => true], 'sortOrder' => 30, 'isDefault' => false],
		['name' => 'Abiertas', 'criteria' => ['status' => ['nuevo', 'asignado', 'en_progreso']], 'sortOrder' => 40, 'isDefault' => false],
		['name' => 'Pendientes de usuario', 'criteria' => ['status' => ['en_espera_usuario']], 'sortOrder' => 50, 'isDefault' => false],
		['name' => 'Cerradas recientes', 'criteria' => ['status' => ['resuelto', 'cerrado'], 'updatedWithinDays' => 30], 'sortOrder' => 60, 'isDefault' => false],
	];

	public function __construct(private readonly SavedFilterMapper $savedFilterMapper) {
	}

	public function listForConsole(string $uid): array {
		$this->ensureGlobalFilters();
		$rows = $this->mergeFiltersByName(
			$this->getScopeFilters('global', null, true),
			$this->getScopeFilters('user', $uid, true),
		);

		return $this->resolveDefaultSelection(array_map(static fn (SavedFilter $row): array => $row->jsonSerialize(), $rows));
	}

	public function listForUserSettings(string $uid): array {
		$this->ensureGlobalFilters();
		$rows = $this->mergeFiltersByName(
			$this->getScopeFilters('global'),
			$this->getScopeFilters('user', $uid),
		);

		return $this->resolveDefaultSelection(array_map(static fn (SavedFilter $row): array => $row->jsonSerialize(), $rows));
	}

	public function saveUserSettings(string $uid, array $rows): array {
		$this->saveScopeFilters('user', $uid, $rows);
		return $this->listForUserSettings($uid);
	}

	public function restoreUserSettings(string $uid): array {
		foreach ($this->getScopeFilters('user', $uid) as $filter) {
			$this->savedFilterMapper->delete($filter);
		}

		return $this->listForUserSettings($uid);
	}

	public function listForAdmin(): array {
		$this->ensureGlobalFilters();
		return array_map(static fn (SavedFilter $row): array => $row->jsonSerialize(), $this->getScopeFilters('global'));
	}

	public function saveForAdmin(array $rows): array {
		$this->ensureGlobalFilters();
		$this->saveScopeFilters('global', null, $rows);
		return $this->listForAdmin();
	}

	public function save(string $uid, array $payload): array {
		$this->ensureGlobalFilters();
		$name = trim((string) ($payload['name'] ?? 'Filtro'));
		if ($name === '') {
			throw new \InvalidArgumentException('Debes indicar un nombre para el filtro.');
		}

		$criteria = is_array($payload['criteria'] ?? null) ? $payload['criteria'] : [];
		$overwrite = (bool) ($payload['overwrite'] ?? false);
		$existing = $this->findFilterByName($name, $uid);

		if ($existing instanceof SavedFilter) {
			if ((bool) $existing->getIsPredefined()) {
				throw new \InvalidArgumentException('Ese nombre pertenece a un filtro predefinido y no se puede sobreescribir.');
			}

			if ($existing->getScopeType() !== 'user' || $existing->getOwnerUid() !== $uid) {
				throw new \InvalidArgumentException('Ese nombre ya existe y no se puede sobreescribir desde este perfil.');
			}

			if (!$overwrite) {
				throw new \InvalidArgumentException('Ese nombre ya existe. Indica si quieres sobrescribir el filtro.');
			}

			$existing->setCriteria($criteria);
			$existing->setActive(true);
			$existing->setIsPredefined(false);
			return $this->savedFilterMapper->update($existing)->jsonSerialize();
		}

		$filter = new SavedFilter();
		$filter->setOwnerUid($uid);
		$filter->setScopeType('user');
		$filter->setName($name);
		$filter->setCriteria($criteria);
		$filter->setIsPredefined(false);
		$filter->setActive(true);
		$filter->setIsDefault(false);
		$filter->setSortOrder((int) ($payload['sortOrder'] ?? 999));
		return $this->savedFilterMapper->insert($filter)->jsonSerialize();
	}

	public function delete(string $uid, int $id): void {
		$filter = $this->savedFilterMapper->find($id);
		if ($filter->getIsPredefined() || $filter->getOwnerUid() !== $uid || $filter->getScopeType() !== 'user') {
			throw new \RuntimeException('Forbidden', 403);
		}

		$this->savedFilterMapper->delete($filter);
	}

	private function ensureGlobalFilters(): void {
		$rows = $this->savedFilterMapper->findAllOrdered('sort_order', 'ASC');
		$byName = [];
		$hasGlobalDefault = false;
		foreach ($rows as $row) {
			if (!$row instanceof SavedFilter) {
				continue;
			}

			$scopeType = $row->getScopeType();
			if (!is_string($scopeType) || trim($scopeType) === '') {
				$scopeType = $row->getOwnerUid() === null ? 'global' : 'user';
				$row->setScopeType($scopeType);
			}

			$row->setIsPredefined($scopeType === 'global');
			if ($row->getActive() === null) {
				$row->setActive(true);
			}
			if ($row->getIsDefault() === null) {
				$row->setIsDefault(false);
			}
			if ($scopeType === 'global' && (bool) $row->getIsDefault() && (bool) $row->getActive()) {
				$hasGlobalDefault = true;
			}
			$this->savedFilterMapper->update($row);

			if ($scopeType !== 'global') {
				continue;
			}

			$byName[$row->getName()] = $row;
		}

		foreach (self::PREDEFINED_FILTERS as $definition) {
			$existing = $byName[$definition['name']] ?? null;
			if ($existing instanceof SavedFilter) {
				$existing->setIsPredefined(true);
				$existing->setScopeType('global');
				if ($existing->getCriteria() === null || $existing->getCriteria() === []) {
					$existing->setCriteria($definition['criteria']);
				}
				if ($existing->getActive() === null) {
					$existing->setActive(true);
				}
				if (!$hasGlobalDefault && (bool) $definition['isDefault']) {
					$existing->setIsDefault(true);
					$hasGlobalDefault = true;
				}
				$existing->setSortOrder($definition['sortOrder']);
				$existing->setOwnerUid(null);
				$this->savedFilterMapper->update($existing);
				continue;
			}

			$filter = new SavedFilter();
			$filter->setOwnerUid(null);
			$filter->setScopeType('global');
			$filter->setName($definition['name']);
			$filter->setCriteria($definition['criteria']);
			$filter->setIsPredefined(true);
			$filter->setActive(true);
			$filter->setIsDefault(!$hasGlobalDefault && (bool) $definition['isDefault']);
			if ((bool) $definition['isDefault']) {
				$hasGlobalDefault = true;
			}
			$filter->setSortOrder($definition['sortOrder']);
			$this->savedFilterMapper->insert($filter);
		}
	}

	/**
	 * @return list<SavedFilter>
	 */
	private function getScopeFilters(string $scopeType, ?string $uid = null, ?bool $activeOnly = null): array {
		$rows = $this->savedFilterMapper->findAllOrdered('sort_order', 'ASC');

		return array_values(array_filter($rows, static function ($row) use ($scopeType, $uid, $activeOnly): bool {
			if (!$row instanceof SavedFilter) {
				return false;
			}

			if ($row->getScopeType() !== $scopeType) {
				return false;
			}

			if ($scopeType === 'user' && $row->getOwnerUid() !== $uid) {
				return false;
			}

			if ($activeOnly !== null && (bool) $row->getActive() !== $activeOnly) {
				return false;
			}

			return true;
		}));
	}

	private function saveScopeFilters(string $scopeType, ?string $uid, array $rows): void {
		$existing = $this->getScopeFilters($scopeType, $uid);
		$existingById = [];
		foreach ($existing as $filter) {
			$existingById[(int) $filter->getId()] = $filter;
		}

		$normalizedRows = [];
		foreach ($rows as $index => $row) {
			if (!is_array($row)) {
				continue;
			}

			$name = trim((string) ($row['name'] ?? ''));
			if ($name === '') {
				continue;
			}

			$normalizedRows[] = [
				'id' => isset($row['id']) ? (int) $row['id'] : null,
				'name' => $name,
				'criteria' => is_array($row['criteria'] ?? null) ? $row['criteria'] : [],
				'isPredefined' => (bool) ($row['isPredefined'] ?? ($scopeType === 'global')),
				'active' => (bool) ($row['active'] ?? true),
				'isDefault' => (bool) ($row['isDefault'] ?? false),
				'sortOrder' => (int) ($row['sortOrder'] ?? (($index + 1) * 10)),
			];
		}

		$defaultIndex = null;
		foreach ($normalizedRows as $index => $row) {
			if ($row['active'] && $row['isDefault']) {
				$defaultIndex = $index;
				break;
			}
		}

		if ($defaultIndex === null) {
			foreach ($normalizedRows as $index => $row) {
				if ($row['active']) {
					$defaultIndex = $index;
					break;
				}
			}
		}

		$seenIds = [];
		foreach ($normalizedRows as $index => $row) {
			$entity = $row['id'] !== null && isset($existingById[$row['id']]) ? $existingById[$row['id']] : new SavedFilter();
			$entity->setOwnerUid($scopeType === 'user' ? $uid : null);
			$entity->setScopeType($scopeType);
			$entity->setName($row['name']);
			$entity->setCriteria($row['criteria']);
			$entity->setIsPredefined((bool) $row['isPredefined']);
			$entity->setActive($row['active']);
			$entity->setIsDefault($defaultIndex !== null && $defaultIndex === $index && $row['active']);
			$entity->setSortOrder(($index + 1) * 10);

			if ($row['id'] !== null && isset($existingById[$row['id']])) {
				$this->savedFilterMapper->update($entity);
				$seenIds[] = $row['id'];
				continue;
			}

			$inserted = $this->savedFilterMapper->insert($entity);
			$seenIds[] = (int) $inserted->getId();
		}

		foreach ($existing as $filter) {
			if (!in_array((int) $filter->getId(), $seenIds, true)) {
				$this->savedFilterMapper->delete($filter);
			}
		}
	}

	private function resolveDefaultSelection(array $rows): array {
		$userDefaultId = null;
		$globalDefaultId = null;
		foreach ($rows as $row) {
			if (!(bool) ($row['active'] ?? true) || !(bool) ($row['isDefault'] ?? false)) {
				continue;
			}

			if (($row['scopeType'] ?? '') === 'user') {
				$userDefaultId = (int) ($row['id'] ?? 0);
				break;
			}

			if (($row['scopeType'] ?? '') === 'global' && $globalDefaultId === null) {
				$globalDefaultId = (int) ($row['id'] ?? 0);
			}
		}

		$effectiveDefaultId = $userDefaultId ?? $globalDefaultId;

		return array_map(static function (array $row) use ($effectiveDefaultId): array {
			$row['isDefault'] = $effectiveDefaultId !== null && (int) ($row['id'] ?? 0) === $effectiveDefaultId;
			return $row;
		}, $rows);
	}

	/**
	 * @param list<SavedFilter> $globalFilters
	 * @param list<SavedFilter> $userFilters
	 * @return list<SavedFilter>
	 */
	private function mergeFiltersByName(array $globalFilters, array $userFilters): array {
		$merged = [];
		foreach ($globalFilters as $filter) {
			$merged[(string) $filter->getName()] = $filter;
		}

		foreach ($userFilters as $filter) {
			$merged[(string) $filter->getName()] = $filter;
		}

		$rows = array_values($merged);
		usort($rows, static function (SavedFilter $left, SavedFilter $right): int {
			return ((int) $left->getSortOrder()) <=> ((int) $right->getSortOrder());
		});

		return $rows;
	}

	private function findFilterByName(string $name, ?string $uid = null): ?SavedFilter {
		$normalizedName = $this->normalizeFilterName($name);
		foreach ($this->savedFilterMapper->findAllOrdered('sort_order', 'ASC') as $row) {
			if (!$row instanceof SavedFilter) {
				continue;
			}

			if ($uid !== null && $row->getScopeType() === 'user' && $row->getOwnerUid() !== $uid) {
				continue;
			}

			if ($this->normalizeFilterName((string) $row->getName()) !== $normalizedName) {
				continue;
			}

			return $row;
		}

		return null;
	}

	private function normalizeFilterName(string $name): string {
		return strtolower(trim($name));
	}
}