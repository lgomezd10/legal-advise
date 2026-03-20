<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Db;

use OCP\DB\Types;

class TaskSync extends AbstractEntity {
	protected $ticketId;
	protected $assigneeUid;
	protected $calendarUri;
	protected $objectUri;
	protected $objectUid;
	protected $syncStatus;
	protected $lastSyncedAt;
	protected $lastError;
	protected $payload;

	public function __construct() {
		$this->addType('ticketId', Types::INTEGER);
		$this->addType('lastSyncedAt', Types::INTEGER);
		$this->addType('payload', Types::JSON);
	}
}