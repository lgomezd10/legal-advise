<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Db;

use OCP\DB\Types;

class SavedFilter extends AbstractEntity {
	protected $ownerUid;
	protected $name;
	protected $criteria;
	protected $isPredefined;
	protected $sortOrder;

	public function __construct() {
		$this->addType('criteria', Types::JSON);
		$this->addType('isPredefined', Types::BOOLEAN);
		$this->addType('sortOrder', Types::INTEGER);
	}
}