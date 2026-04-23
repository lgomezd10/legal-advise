<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Service;

use OCA\ConsultasLegales\Db\AssignmentRule;
use OCA\ConsultasLegales\Db\AssignmentRuleMapper;
use OCA\ConsultasLegales\Db\IncidentType;
use OCA\ConsultasLegales\Db\IncidentTypeMapper;
use OCA\ConsultasLegales\Service\AssignmentService;
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

	public function testPrefersProvinceSpecificRuleOverGenericRule(): void {
		$genericRule = new AssignmentRule();
		$genericRule->setAssignedGroupId('support');

		$specificRule = new AssignmentRule();
		$specificRule->setAssignedUserUid('soporte-madrid');
		$specificRule->setAssignedGroupId('madrid');
		$specificRule->setProvince('Madrid');

		$ruleMapper = $this->createMock(AssignmentRuleMapper::class);
		$ruleMapper->expects(self::once())
			->method('findBy')
			->with('type_id', 11, 'priority', 'DESC')
			->willReturn([$genericRule, $specificRule]);

		$typeMapper = $this->createMock(IncidentTypeMapper::class);
		$typeMapper->expects(self::never())->method('find');

		$service = new AssignmentService($ruleMapper, $typeMapper);

		self::assertSame(
			['assignedUserUid' => 'soporte-madrid', 'assignedGroupId' => 'madrid'],
			$service->resolveForType(11, 'Madrid'),
		);
	}

	public function testKeepsGenericRuleWhenProvinceDoesNotMatchConfiguredSpecificRule(): void {
		$genericRule = new AssignmentRule();
		$genericRule->setAssignedGroupId('support');

		$specificRule = new AssignmentRule();
		$specificRule->setAssignedUserUid('soporte-sevilla');
		$specificRule->setAssignedGroupId('sevilla');
		$specificRule->setProvince('Sevilla');

		$ruleMapper = $this->createMock(AssignmentRuleMapper::class);
		$ruleMapper->expects(self::once())
			->method('findBy')
			->with('type_id', 11, 'priority', 'DESC')
			->willReturn([$specificRule, $genericRule]);

		$typeMapper = $this->createMock(IncidentTypeMapper::class);
		$typeMapper->expects(self::never())->method('find');

		$service = new AssignmentService($ruleMapper, $typeMapper);

		self::assertSame(
			['assignedUserUid' => null, 'assignedGroupId' => 'support'],
			$service->resolveForType(11, 'Madrid'),
		);
	}
}