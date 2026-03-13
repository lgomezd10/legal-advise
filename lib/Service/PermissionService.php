<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Service;

use OCA\Gestion_incidencias\Db\Ticket;
use OCP\IGroupManager;
use OCP\IUserManager;
use RuntimeException;

class PermissionService {
	public function __construct(
		private readonly RoleService $roleService,
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
	) {
	}

	public function assertCanReadTicket(string $uid, Ticket $ticket): void {
		if ($this->canReadTicket($uid, $ticket)) {
			return;
		}

		throw new RuntimeException('Forbidden', 403);
	}

	public function assertCanManageTicket(string $uid, Ticket $ticket): void {
		if ($this->canManageTicket($uid, $ticket)) {
			return;
		}

		throw new RuntimeException('Forbidden', 403);
	}

	public function canReadTicket(string $uid, Ticket $ticket): bool {
		$roles = $this->roleService->getEffectiveRoles($uid);
		if ($ticket->getCreatorUid() === $uid) {
			return true;
		}

		if (in_array(RoleService::ADMIN, $roles, true)) {
			return true;
		}

		if (in_array(RoleService::SUPPORT, $roles, true)) {
			if ($ticket->getAssignedUserUid() === null && $ticket->getAssignedGroupId() === null) {
				return true;
			}

			if ($ticket->getAssignedUserUid() === $uid) {
				return true;
			}

			if ($ticket->getAssignedGroupId() !== null) {
				$group = $this->groupManager->get($ticket->getAssignedGroupId());
				$user = $this->userManager->get($uid);
				return $group !== null && $user !== null && $group->inGroup($user);
			}
		}

		return false;
	}

	public function canManageTicket(string $uid, Ticket $ticket): bool {
		$roles = $this->roleService->getEffectiveRoles($uid);
		if (in_array(RoleService::ADMIN, $roles, true)) {
			return true;
		}

		if (!in_array(RoleService::SUPPORT, $roles, true)) {
			return false;
		}

		return $this->canReadTicket($uid, $ticket);
	}

	public function canSeeComment(string $uid, Ticket $ticket, string $visibility): bool {
		if ($visibility === 'publico') {
			return $this->canReadTicket($uid, $ticket);
		}

		$roles = $this->roleService->getEffectiveRoles($uid);
		return in_array(RoleService::SUPPORT, $roles, true) || in_array(RoleService::ADMIN, $roles, true);
	}
}