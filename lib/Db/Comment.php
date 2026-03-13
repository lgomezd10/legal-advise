<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Db;

use OCP\DB\Types;

class Comment extends AbstractEntity {
	protected $ticketId;
	protected $authorUid;
	protected $authorRole;
	protected $body;
	protected $visibility;
	protected $createdAt;

	public function __construct() {
		$this->addType('ticketId', Types::INTEGER);
		$this->addType('createdAt', Types::INTEGER);
	}
}