<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Db;

use OCP\DB\Types;

class TicketData extends AbstractEntity {
	protected $ticketId;
	protected $fieldKey;
	protected $fieldLabel;
	protected $fieldValue;

	public function __construct() {
		$this->addType('ticketId', Types::INTEGER);
	}
}