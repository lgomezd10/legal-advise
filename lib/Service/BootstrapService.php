<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Service;

use OCA\ConsultasLegales\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;

class BootstrapService {
	public function __construct(
		private readonly DefaultConfigService $defaultConfigService,
		private readonly ProvinceCatalogService $provinceCatalogService,
		private readonly IUserSession $userSession,
		private readonly RoleService $roleService,
		private readonly CatalogService $catalogService,
		private readonly SupportFilterService $supportFilterService,
		private readonly TaskSyncService $taskSyncService,
		private readonly PersonalConfigService $personalConfigService,
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
		private readonly IAppManager $appManager,
		private readonly AppStorageUsageService $appStorageUsageService,
	) {
	}

	public function build(): array {
		$this->defaultConfigService->ensureDefaults();

		$user = $this->userSession->getUser();
		$uid = $user?->getUID() ?? '';
		if ($user !== null && !$this->appManager->isEnabledForUser(Application::APP_ID, $user)) {
			return $this->buildDisabledState($uid, $user?->getDisplayName() ?? '');
		}

		$roles = $uid === '' ? [] : $this->roleService->getEffectiveRoles($uid);

		$navigation = [
			['id' => 'mis-incidencias', 'label' => 'Mis tickets', 'route' => '/mis-incidencias', 'visible' => in_array(RoleService::USER, $roles, true)],
			['id' => 'soporte', 'label' => 'Consola de soporte', 'route' => '/soporte', 'visible' => in_array(RoleService::SUPPORT, $roles, true) || in_array(RoleService::ADMIN, $roles, true)],
			['id' => 'configuracion', 'label' => 'Configuración', 'route' => '/configuracion', 'visible' => $roles !== []],
		];

		$assignableUsers = $this->safeLoadAssignableUsers();
		$assignableGroups = $this->safeLoadAssignableGroups($assignableUsers);

		return [
			'currentUser' => [
				'uid' => $uid,
				'displayName' => $user?->getDisplayName() ?? '',
			],
			'appInfo' => $this->buildAppInfo(),
			'roles' => $roles,
			'navigation' => array_values(array_filter($navigation, static fn (array $item) => $item['visible'])),
			'catalogs' => [
				'statuses' => $this->catalogService->getStatuses(),
				'urgencies' => $this->catalogService->getUrgencies(),
				'types' => $this->catalogService->getTypeTree(),
				'fields' => $this->catalogService->getFields(),
				'provinces' => $this->provinceCatalogService->list(),
				'attachmentConfig' => $this->catalogService->getAttachmentConfig(),
			],
			'supportFilters' => $uid === '' ? [] : $this->safeSupportFilters($uid),
			'personalConfig' => $uid === '' ? [] : $this->safePersonalConfig($uid),
			'personalConfigHasStoredValues' => $uid !== '' && $this->safePersonalConfigHasStoredValues($uid),
			'assignables' => [
				'users' => $assignableUsers,
				'groups' => $assignableGroups,
			],
			'tasksIntegration' => $this->taskSyncService->getIntegrationStatus(),
		];
	}

	private function buildDisabledState(string $uid, string $displayName): array {
		return [
			'currentUser' => [
				'uid' => $uid,
				'displayName' => $displayName,
			],
			'appInfo' => $this->buildAppInfo(),
			'roles' => [],
			'navigation' => [],
			'catalogs' => [
				'statuses' => [],
				'urgencies' => [],
				'types' => [],
				'fields' => [],
				'provinces' => [],
				'attachmentConfig' => ['allowedExtensions' => [], 'maxFileSizeMb' => 100],
			],
			'supportFilters' => [],
			'personalConfig' => [],
			'personalConfigHasStoredValues' => false,
			'assignables' => [
				'users' => [],
				'groups' => [],
			],
			'tasksIntegration' => $this->taskSyncService->getIntegrationStatus(),
		];
	}

	private function buildAppInfo(): array {
		$storage = $this->appStorageUsageService->summarize();

		return [
			'id' => Application::APP_ID,
			'version' => $this->appManager->getAppVersion(Application::APP_ID),
			'storageBytes' => $storage['totalBytes'],
			'storageLabel' => $storage['totalLabel'],
			'appDataBytes' => $storage['appDataBytes'],
			'appDataLabel' => $storage['appDataLabel'],
			'databaseBytes' => $storage['databaseBytes'],
			'databaseLabel' => $storage['databaseLabel'],
			'attachmentBytes' => $storage['attachmentBytes'],
			'attachmentLabel' => $storage['attachmentLabel'],
		];
	}

	private function safeSupportFilters(string $uid): array {
		try {
			return $this->supportFilterService->listForConsole($uid);
		} catch (\Throwable) {
			return [];
		}
	}

	private function safePersonalConfig(string $uid): array {
		try {
			return $this->personalConfigService->getForUser($uid);
		} catch (\Throwable) {
			return [];
		}
	}

	private function safePersonalConfigHasStoredValues(string $uid): bool {
		try {
			return $this->personalConfigService->hasStoredValues($uid);
		} catch (\Throwable) {
			return false;
		}
	}

	private function safeLoadAssignableUsers(): array {
		try {
			return $this->loadAssignableUsers();
		} catch (\Throwable) {
			return [];
		}
	}

	private function safeLoadAssignableGroups(array $assignableUsers): array {
		try {
			return $this->loadAssignableGroups($assignableUsers);
		} catch (\Throwable) {
			return [];
		}
	}

	private function loadAssignableUsers(): array {
		$usersById = [];

		if (method_exists($this->userManager, 'search')) {
			$this->collectAssignableUsers($usersById, $this->userManager->search(''));
		}

		if (method_exists($this->userManager, 'searchDisplayName')) {
			$this->collectAssignableUsers($usersById, $this->userManager->searchDisplayName(''));
		}

		foreach ($this->groupManager->search('') as $group) {
			if (method_exists($group, 'searchUsers')) {
				$this->collectAssignableUsers($usersById, $group->searchUsers(''));
				continue;
			}

			if (method_exists($group, 'getUsers')) {
				$this->collectAssignableUsers($usersById, $group->getUsers());
			}
		}

		$users = array_values($usersById);
		usort($users, static function (array $left, array $right): int {
			$leftLabel = trim((string) ($left['displayName'] ?? $left['id'] ?? ''));
			$rightLabel = trim((string) ($right['displayName'] ?? $right['id'] ?? ''));
			return strcasecmp($leftLabel, $rightLabel);
		});

		return $users;
	}

	private function collectAssignableUsers(array &$usersById, iterable $users): void {
		foreach ($users as $user) {
			if (!is_object($user) || !method_exists($user, 'getUID') || !method_exists($user, 'getDisplayName')) {
				continue;
			}

			$uid = trim((string) $user->getUID());
			if ($uid === '') {
				continue;
			}

			$groupIds = array_map(
				static fn ($group) => $group->getGID(),
				$this->groupManager->getUserGroups($user),
			);

			$existingGroupIds = $usersById[$uid]['groupIds'] ?? [];
			$usersById[$uid] = [
				'id' => $uid,
				'displayName' => trim((string) $user->getDisplayName()) !== '' ? $user->getDisplayName() : $uid,
				'groupIds' => array_values(array_unique(array_merge($existingGroupIds, $groupIds))),
			];
		}
	}

	private function loadAssignableGroups(array $assignableUsers): array {
		$groups = $this->groupManager->search('');

		return array_map(static function ($group) use ($assignableUsers): array {
			$groupId = $group->getGID();
			$userIds = array_values(array_map(
				static fn (array $user) => (string) $user['id'],
				array_filter(
					$assignableUsers,
					static fn (array $user): bool => in_array($groupId, $user['groupIds'] ?? [], true),
				),
			));

			return [
				'id' => $groupId,
				'displayName' => $group->getDisplayName(),
				'userIds' => $userIds,
			];
		}, $groups);
	}
}