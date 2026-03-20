<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Service;

use OCA\ConsultasLegales\Db\TicketMapper;
use OCA\ConsultasLegales\Service\TicketNumberService;
use PHPUnit\Framework\TestCase;

class TicketNumberServiceTest extends TestCase {
	public function testGeneratesPaddedNumber(): void {
		$mapper = $this->createMock(TicketMapper::class);
		$mapper->expects(self::once())
			->method('findLatestYearSequence')
			->with(2026)
			->willReturn(41);

		$service = new TicketNumberService($mapper);

		self::assertSame('2026-000042', $service->nextNumber(strtotime('2026-03-10 12:00:00')));
	}
}