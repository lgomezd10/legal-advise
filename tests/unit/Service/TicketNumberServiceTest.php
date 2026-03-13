<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Tests\Unit\Service;

use OCA\Gestion_incidencias\Db\TicketMapper;
use OCA\Gestion_incidencias\Service\TicketNumberService;
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