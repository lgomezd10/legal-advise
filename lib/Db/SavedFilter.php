<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Db;

use OCP\DB\Types;

class SavedFilter extends AbstractEntity {
	protected $ownerUid;
	protected $scopeType;
	protected $name;
	protected $criteria;
	protected $isPredefined;
	protected $active;
	protected $isDefault;
	protected $sortOrder;

	public function __construct() {
		$this->addType('criteria', Types::JSON);
		$this->addType('isPredefined', Types::BOOLEAN);
		$this->addType('active', Types::BOOLEAN);
		$this->addType('isDefault', Types::BOOLEAN);
		$this->addType('sortOrder', Types::INTEGER);
	}
}