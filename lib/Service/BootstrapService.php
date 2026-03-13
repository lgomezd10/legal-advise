<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Service;

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
	) {
	}

	public function build(): array {
		$this->defaultConfigService->ensureDefaults();

		$user = $this->userSession->getUser();
		$uid = $user?->getUID() ?? '';
		$roles = $uid === '' ? [] : $this->roleService->getEffectiveRoles($uid);

		$navigation = [
			['id' => 'mis-incidencias', 'label' => 'Mis incidencias', 'route' => '/mis-incidencias', 'visible' => in_array(RoleService::USER, $roles, true)],
			['id' => 'configuracion-personal', 'label' => 'Configuracion personal', 'route' => '/configuracion-personal', 'visible' => in_array(RoleService::USER, $roles, true)],
			['id' => 'soporte', 'label' => 'Consola de soporte', 'route' => '/soporte', 'visible' => in_array(RoleService::SUPPORT, $roles, true) || in_array(RoleService::ADMIN, $roles, true)],
			['id' => 'admin', 'label' => 'Administración', 'route' => '/administracion', 'visible' => in_array(RoleService::ADMIN, $roles, true)],
		];

		$assignableUsers = $this->loadAssignableUsers();
		$assignableGroups = $this->loadAssignableGroups($assignableUsers);

		return [
			'currentUser' => [
				'uid' => $uid,
				'displayName' => $user?->getDisplayName() ?? '',
			],
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
			'supportFilters' => $uid === '' ? [] : $this->supportFilterService->list($uid),
			'personalConfig' => $uid === '' ? [] : $this->personalConfigService->getForUser($uid),
			'assignables' => [
				'users' => $assignableUsers,
				'groups' => $assignableGroups,
			],
			'tasksIntegration' => $this->taskSyncService->getIntegrationStatus(),
		];
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