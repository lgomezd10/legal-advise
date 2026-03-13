<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Service;

use OCA\Gestion_incidencias\Db\ProfileAssignmentMapper;
use OCP\IGroupManager;
use OCP\IUserManager;

class RoleService {
	public const USER = 'usuario';
	public const SUPPORT = 'soporte';
	public const ADMIN = 'administrador';

	public function __construct(
		private readonly ProfileAssignmentMapper $profileAssignmentMapper,
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
	) {
	}

	public function getEffectiveRoles(string $uid): array {
		$roles = [self::USER => true];

		if ($this->groupManager->isAdmin($uid)) {
			$roles[self::ADMIN] = true;
		}

		foreach ($this->profileAssignmentMapper->findAllOrdered('id', 'ASC') as $assignment) {
			$user = $this->userManager->get($uid);
			if ($assignment->getPrincipalType() === 'user' && $assignment->getPrincipalId() === $uid) {
				$roles[$assignment->getProfile()] = true;
			}

			if ($assignment->getPrincipalType() === 'group') {
				$group = $this->groupManager->get($assignment->getPrincipalId());
				if ($group !== null && $user !== null && $group->inGroup($user)) {
					$roles[$assignment->getProfile()] = true;
				}
			}
		}

		return array_keys($roles);
	}

	public function hasRole(string $uid, string $role): bool {
		return in_array($role, $this->getEffectiveRoles($uid), true);
	}
}