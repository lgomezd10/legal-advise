<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Service;

use OCA\ConsultasLegales\Db\Comment;
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

	/**
	 * @param Comment[] $comments
	 */
	public function assertCanDeleteComment(string $uid, Ticket $ticket, Comment $comment, array $comments): void {
		if ($this->canDeleteComment($uid, $ticket, $comment, $comments)) {
			return;
		}

		throw new RuntimeException('Forbidden', 403);
	}

	/**
	 * @param Comment[] $comments
	 */
	public function assertCanEditComment(string $uid, Ticket $ticket, Comment $comment, array $comments): void {
		if ($this->canEditComment($uid, $ticket, $comment, $comments)) {
			return;
		}

		throw new RuntimeException('Forbidden', 403);
	}

	public function assertCanDeleteTicket(string $uid, Ticket $ticket): void {
		if ($this->canDeleteTicket($uid, $ticket)) {
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

		if (!in_array(RoleService::SUPPORT, $roles, true)) {
			return false;
		}

		$assignedUserUid = $this->normalizeOptionalString($ticket->getAssignedUserUid());
		if ($assignedUserUid !== null && $assignedUserUid === $uid) {
			return true;
		}

		$assignedGroupId = $this->normalizeOptionalString($ticket->getAssignedGroupId());
		if ($assignedGroupId !== null) {
			return $this->userBelongsToGroup($uid, $assignedGroupId);
		}

		return true;
	}

	public function canCommentOnTicket(string $uid, Ticket $ticket): bool {
		if ($ticket->getCreatorUid() === $uid) {
			return true;
		}

		return $this->canManageTicket($uid, $ticket);
	}

	/**
	 * @param Comment[] $comments
	 */
	public function canDeleteComment(string $uid, Ticket $ticket, Comment $comment, array $comments): bool {
		$roles = $this->roleService->getEffectiveRoles($uid);
		if (in_array(RoleService::ADMIN, $roles, true)) {
			return true;
		}

		if (!in_array(RoleService::SUPPORT, $roles, true) || !$this->canManageTicket($uid, $ticket)) {
			return false;
		}

		if ($comment->getAuthorUid() !== $uid) {
			return false;
		}

		return $this->isLatestCommentByAuthor($comment, $comments, $uid);
	}

	/**
	 * @param Comment[] $comments
	 */
	public function canEditComment(string $uid, Ticket $ticket, Comment $comment, array $comments): bool {
		$roles = $this->roleService->getEffectiveRoles($uid);
		if (in_array(RoleService::ADMIN, $roles, true)) {
			return true;
		}

		if (!in_array(RoleService::SUPPORT, $roles, true) || !$this->canManageTicket($uid, $ticket)) {
			return false;
		}

		if ($comment->getAuthorUid() !== $uid) {
			return false;
		}

		return $this->isLatestCommentByAuthor($comment, $comments, $uid);
	}

	/**
	 * @param Comment[] $comments
	 */
	public function canRestoreAssignedStatusAfterDeletingComment(string $uid, Ticket $ticket, Comment $comment, array $comments): bool {
		$roles = $this->roleService->getEffectiveRoles($uid);
		if (in_array(RoleService::ADMIN, $roles, true) || !in_array(RoleService::SUPPORT, $roles, true)) {
			return false;
		}

		if ((string) $ticket->getStatus() !== 'en_espera_usuario') {
			return false;
		}

		if (!$this->canDeleteComment($uid, $ticket, $comment, $comments)) {
			return false;
		}

		return $this->isLatestComment($comment, $comments);
	}

	public function canDeleteTicket(string $uid, Ticket $ticket): bool {
		$roles = $this->roleService->getEffectiveRoles($uid);
		return in_array(RoleService::ADMIN, $roles, true);
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

		return $this->userBelongsToGroup($uid, $groupId);
	}

	public function canSeeComment(string $uid, Ticket $ticket, string $visibility): bool {
		if ($visibility === 'publico') {
			return $this->canReadTicket($uid, $ticket);
		}

		$roles = $this->roleService->getEffectiveRoles($uid);
		return in_array(RoleService::SUPPORT, $roles, true) || in_array(RoleService::ADMIN, $roles, true);
	}

	private function userBelongsToGroup(string $uid, string $groupId): bool {
		$user = $this->userManager->get($uid);
		$group = $this->groupManager->get($groupId);

		if ($user === null || $group === null) {
			return false;
		}

		return $group->inGroup($user);
	}

	private function normalizeOptionalString(?string $value): ?string {
		if ($value === null) {
			return null;
		}

		$normalized = trim($value);
		return $normalized === '' ? null : $normalized;
	}

	/**
	 * @param Comment[] $comments
	 */
	private function isLatestCommentByAuthor(Comment $comment, array $comments, string $authorUid): bool {
		$latest = null;
		foreach ($comments as $entry) {
			if (!$entry instanceof Comment || $entry->getAuthorUid() !== $authorUid) {
				continue;
			}

			if ($latest === null || $this->isLaterComment($entry, $latest)) {
				$latest = $entry;
			}
		}

		return $latest instanceof Comment && (int) $latest->getId() === (int) $comment->getId();
	}

	/**
	 * @param Comment[] $comments
	 */
	private function isLatestComment(Comment $comment, array $comments): bool {
		$latest = null;
		foreach ($comments as $entry) {
			if (!$entry instanceof Comment) {
				continue;
			}

			if ($latest === null || $this->isLaterComment($entry, $latest)) {
				$latest = $entry;
			}
		}

		return $latest instanceof Comment && (int) $latest->getId() === (int) $comment->getId();
	}

	private function isLaterComment(Comment $left, Comment $right): bool {
		$leftCreatedAt = (int) $left->getCreatedAt();
		$rightCreatedAt = (int) $right->getCreatedAt();
		if ($leftCreatedAt !== $rightCreatedAt) {
			return $leftCreatedAt > $rightCreatedAt;
		}

		return (int) $left->getId() > (int) $right->getId();
	}
}