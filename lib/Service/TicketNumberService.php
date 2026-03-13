<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Service;

use OCA\Gestion_incidencias\Db\TicketMapper;

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