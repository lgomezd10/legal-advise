<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Notification;

use OCA\ConsultasLegales\Service\RoleService;
use OCP\IUserManager;

class SupportAdminNotificationRecipientResolver {
	public function __construct(
		private readonly IUserManager $userManager,
		private readonly RoleService $roleService,
	) {
	}

	public function resolve(): array {
		if (!method_exists($this->userManager, 'search')) {
			return [];
		}

		$recipientUids = [];
		foreach ($this->userManager->search('') as $user) {
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