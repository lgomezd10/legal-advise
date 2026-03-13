<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Tests\Unit\Service;

use OCA\Gestion_incidencias\Db\AssignmentRule;
use OCA\Gestion_incidencias\Db\AssignmentRuleMapper;
use OCA\Gestion_incidencias\Db\IncidentType;
use OCA\Gestion_incidencias\Db\IncidentTypeMapper;
use OCA\Gestion_incidencias\Service\AssignmentService;
use PHPUnit\Framework\TestCase;

class AssignmentServiceTest extends TestCase {
	public function testFallsBackToParentRuleWhenLeafHasNone(): void {
		$rule = new AssignmentRule();
		$rule->setAssignedGroupId('support');

		$type = new IncidentType();
		$type->setParentId(100);

		$ruleMapper = $this->createMock(AssignmentRuleMapper::class);
		$ruleMapper->expects(self::exactly(2))
			->method('findBy')
			->willReturnOnConsecutiveCalls([], [$rule]);

		$typeMapper = $this->createMock(IncidentTypeMapper::class);
		$typeMapper->expects(self::once())
			->method('find')
			->with(200)
			->willReturn($type);

		$service = new AssignmentService($ruleMapper, $typeMapper);

		self::assertSame(['assignedUserUid' => null, 'assignedGroupId' => 'support'], $service->resolveForType(200));
	}
}