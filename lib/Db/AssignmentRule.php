<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Db;

use OCP\DB\Types;

class AssignmentRule extends AbstractEntity {
	protected $typeId;
	protected $province;
	protected $assignedUserUid;
	protected $assignedGroupId;
	protected $priority;

	public function __construct() {
		$this->addType('typeId', Types::INTEGER);
		$this->addType('priority', Types::INTEGER);
	}
}