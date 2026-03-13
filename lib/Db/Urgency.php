<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Db;

use OCP\DB\Types;

class Urgency extends AbstractEntity {
	protected $name;
	protected $weight;
	protected $color;
	protected $restrictions;
	protected $active;

	public function __construct() {
		$this->addType('weight', Types::INTEGER);
		$this->addType('restrictions', Types::JSON);
		$this->addType('active', Types::BOOLEAN);
	}
}