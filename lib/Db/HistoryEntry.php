<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Db;

use OCP\DB\Types;

class HistoryEntry extends AbstractEntity {
	protected $ticketId;
	protected $actorUid;
	protected $actorRole;
	protected $eventType;
	protected $visibility;
	protected $payload;
	protected $createdAt;

	public function __construct() {
		$this->addType('ticketId', Types::INTEGER);
		$this->addType('payload', Types::JSON);
		$this->addType('createdAt', Types::INTEGER);
	}
}