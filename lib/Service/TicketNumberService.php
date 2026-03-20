<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Service;

use OCA\ConsultasLegales\Db\TicketMapper;

class TicketNumberService {
	public function __construct(private readonly TicketMapper $ticketMapper) {
	}

	public function nextNumber(?int $timestamp = null): string {
		$now = $timestamp ?? time();
		$year = (int) date('Y', $now);
		$sequence = $this->ticketMapper->findLatestYearSequence($year) + 1;

		return sprintf('%d-%06d', $year, $sequence);
	}
}