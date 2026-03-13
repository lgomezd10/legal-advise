<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Service;

use OCA\Gestion_incidencias\Db\AssignmentRuleMapper;
use OCA\Gestion_incidencias\Db\IncidentTypeMapper;

class AssignmentService {
	public function __construct(
		private readonly AssignmentRuleMapper $ruleMapper,
		private readonly IncidentTypeMapper $typeMapper,
	) {
	}

	public function resolveForType(?int $typeId, ?string $province = null): array {
		if ($typeId === null) {
			return ['assignedUserUid' => null, 'assignedGroupId' => null];
		}

		$normalizedProvince = $this->normalizeProvince($province);
		$current = $typeId;
		while ($current !== null) {
			$rules = $this->ruleMapper->findBy('type_id', $current, 'priority', 'DESC');
			$rule = $this->pickBestRule($rules, $normalizedProvince);
			if ($rule !== null) {
				return [
					'assignedUserUid' => $rule->getAssignedUserUid(),
					'assignedGroupId' => $rule->getAssignedGroupId(),
				];
			}

			$type = $this->typeMapper->find($current);
			$current = $type->getParentId();
		}

		return ['assignedUserUid' => null, 'assignedGroupId' => null];
	}

	private function pickBestRule(array $rules, ?string $province): ?object {
		$genericRule = null;

		foreach ($rules as $rule) {
			$ruleProvince = $this->normalizeProvince($rule->getProvince());
			if ($province !== null && $ruleProvince !== null && $ruleProvince === $province) {
				return $rule;
			}

			if ($ruleProvince === null && $genericRule === null) {
				$genericRule = $rule;
			}
		}

		return $genericRule;
	}

	private function normalizeProvince(?string $province): ?string {
		$trimmed = trim((string) $province);
		if ($trimmed === '') {
			return null;
		}

		return function_exists('mb_strtolower') ? mb_strtolower($trimmed, 'UTF-8') : strtolower($trimmed);
	}
}