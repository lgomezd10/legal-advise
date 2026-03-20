<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Db;

use OCP\DB\Types;

class IncidentType extends AbstractEntity {
	protected $parentId;
	protected $name;
	protected $slug;
	protected $level;
	protected $sortOrder;
	protected $active;

	public function __construct() {
		$this->addType('parentId', Types::INTEGER);
		$this->addType('level', Types::INTEGER);
		$this->addType('sortOrder', Types::INTEGER);
		$this->addType('active', Types::BOOLEAN);
	}
}