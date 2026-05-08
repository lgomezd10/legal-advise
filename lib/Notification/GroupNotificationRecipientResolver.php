<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Notification;

use OCA\ConsultasLegales\Service\RoleService;
use OCP\IGroupManager;

class GroupNotificationRecipientResolver {
	public function __construct(
		private readonly IGroupManager $groupManager,
		private readonly RoleService $roleService,
	) {
	}

	public function resolve(string $groupId): array {
		$group = $this->groupManager->get($groupId);
		if ($group === null) {
			return [];
		}

		$users = [];
		if (method_exists($group, 'getUsers')) {
			$users = $group->getUsers();
		} elseif (method_exists($group, 'searchUsers')) {
			$users = $group->searchUsers('');
		}

		$recipientUids = [];
		foreach ($users as $user) {
			if (!is_object($user) || !method_exists($user, 'getUID')) {
				continue;
			}

			$userUid = trim((string) $user->getUID());
			if ($userUid === '') {
				continue;
			}

			$roles = $this->roleService->getEffectiveRoles($userUid);
			if (!in_array(RoleService::SUPPORT, $roles, true) && !in_array(RoleService::ADMIN, $roles, true)) {
				continue;
			}

			$recipientUids[] = $userUid;
		}

		return array_values(array_unique($recipientUids));
	}
}