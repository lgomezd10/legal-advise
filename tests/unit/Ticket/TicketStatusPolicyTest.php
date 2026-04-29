<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Ticket;

use OCA\ConsultasLegales\Ticket\TicketStatusPolicy;
use PHPUnit\Framework\TestCase;

class TicketStatusPolicyTest extends TestCase {
	public function testResolveUpdatedStatusMarksTicketAsAssignedWhenAssignmentChangesWithoutExplicitStatus(): void {
		$policy = new TicketStatusPolicy();

		$result = $policy->resolveUpdatedStatus('en_espera_usuario', 'soporte2', 'territorial', true, false);

		self::assertSame('asignado', $result);
	}

	public function testResolveUpdatedStatusPreservesExplicitStatusWhenAssignmentChanges(): void {
		$policy = new TicketStatusPolicy();

		$result = $policy->resolveUpdatedStatus('en_espera_usuario', 'soporte2', 'territorial', true, true);

		self::assertSame('en_espera_usuario', $result);
	}

	public function testResolveCreationStatusMarksTicketsWithAssignmentAsAsignado(): void {
		$policy = new TicketStatusPolicy();

		self::assertSame('asignado', $policy->resolveCreationStatus(null, 'territorial'));
	}

	public function testResolveReopenedStatusReturnsNuevoWithoutAssignment(): void {
		$policy = new TicketStatusPolicy();

		self::assertSame('nuevo', $policy->resolveReopenedStatus(null, null));
	}
}