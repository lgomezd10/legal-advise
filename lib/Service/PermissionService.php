<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Service;

use OCA\ConsultasLegales\Db\Ticket;
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
			return true;
		}

		return false;
	}

	public function canManageTicket(string $uid, Ticket $ticket): bool {
		$roles = $this->roleService->getEffectiveRoles($uid);
		if (in_array(RoleService::ADMIN, $roles, true)) {
			return true;
		}

		return in_array(RoleService::SUPPORT, $roles, true);
	}

	public function canCommentOnTicket(string $uid, Ticket $ticket): bool {
		if ($ticket->getCreatorUid() === $uid) {
			return true;
		}

		return $this->canManageTicket($uid, $ticket);
	}

	public function canAssignGroup(string $uid, ?string $groupId): bool {
		if ($groupId === null || $groupId === '') {
			return true;
		}

		$roles = $this->roleService->getEffectiveRoles($uid);
		if (in_array(RoleService::ADMIN, $roles, true)) {
			return true;
		}

		if (!in_array(RoleService::SUPPORT, $roles, true)) {
			return false;
		}

		return $this->groupManager->get($groupId) !== null;
	}

	public function canSeeComment(string $uid, Ticket $ticket, string $visibility): bool {
		if ($visibility === 'publico') {
			return $this->canReadTicket($uid, $ticket);
		}

		$roles = $this->roleService->getEffectiveRoles($uid);
		return in_array(RoleService::SUPPORT, $roles, true) || in_array(RoleService::ADMIN, $roles, true);
	}
}