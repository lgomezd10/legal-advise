<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Ticket;

class TicketStatusPolicy {
	private const STATUS_NEW = 'nuevo';
	private const STATUS_ASSIGNED = 'asignado';

	public function resolveCreationStatus(?string $assignedUserUid, ?string $assignedGroupId): string {
		return $this->resolveAssignmentAwareStatus(self::STATUS_NEW, $assignedUserUid, $assignedGroupId);
	}

	public function resolveUpdatedStatus(
		string $currentStatus,
		?string $assignedUserUid,
		?string $assignedGroupId,
		bool $assignmentChanged,
		bool $statusExplicitlyRequested,
	): string {
		$statusAnchor = $currentStatus;
		if ($assignmentChanged && !$statusExplicitlyRequested) {
			$statusAnchor = self::STATUS_ASSIGNED;
		}

		return $this->resolveAssignmentAwareStatus($statusAnchor, $assignedUserUid, $assignedGroupId);
	}

	public function resolveReopenedStatus(?string $assignedUserUid, ?string $assignedGroupId): string {
		return $this->resolveAssignmentAwareStatus(self::STATUS_NEW, $assignedUserUid, $assignedGroupId);
	}

	private function resolveAssignmentAwareStatus(string $currentStatus, ?string $assignedUserUid, ?string $assignedGroupId): string {
		$hasAssignment = ($assignedUserUid !== null && $assignedUserUid !== '') || ($assignedGroupId !== null && $assignedGroupId !== '');
		if ($hasAssignment && $currentStatus === self::STATUS_NEW) {
			return self::STATUS_ASSIGNED;
		}

		if (!$hasAssignment && $currentStatus === self::STATUS_ASSIGNED) {
			return self::STATUS_NEW;
		}

		return $currentStatus;
	}
}