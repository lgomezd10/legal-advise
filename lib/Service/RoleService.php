<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Service;

use OCA\ConsultasLegales\Db\ProfileAssignmentMapper;
use OCP\IGroupManager;
use OCP\IUserManager;

class RoleService {
	public const USER = 'usuario';
	public const SUPPORT = 'soporte';
	public const ADMIN = 'administrador';
	private const ALLOWED_ROLES = [self::USER, self::SUPPORT, self::ADMIN];

	public function __construct(
		private readonly ProfileAssignmentMapper $profileAssignmentMapper,
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
	) {
	}

	public function getEffectiveRoles(string $uid): array {
		$uid = trim($uid);
		if ($uid === '') {
			return [];
		}

		$user = $this->userManager->get($uid);
		if ($user === null) {
			return [];
		}

		$roles = [];

		foreach ($this->profileAssignmentMapper->findAllOrdered('id', 'ASC') as $assignment) {
			$profile = trim((string) $assignment->getProfile());
			if (!in_array($profile, self::ALLOWED_ROLES, true)) {
				continue;
			}

			if ($assignment->getPrincipalType() === 'user' && $assignment->getPrincipalId() === $uid) {
				$roles[$profile] = true;
			}

			if ($assignment->getPrincipalType() === 'group') {
				$group = $this->groupManager->get($assignment->getPrincipalId());
				if ($group !== null && $user !== null && $group->inGroup($user)) {
					$roles[$profile] = true;
				}
			}
		}

		return array_keys($roles);
	}

	public function hasAnyRole(string $uid): bool {
		return $this->getEffectiveRoles($uid) !== [];
	}

	public function hasRole(string $uid, string $role): bool {
		return in_array($role, $this->getEffectiveRoles($uid), true);
	}
}