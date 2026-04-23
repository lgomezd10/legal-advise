<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Db;

use OCP\DB\Types;

class CustomField extends AbstractEntity {
	protected $fieldKey;
	protected $label;
	protected $fieldType;
	protected $required;
	protected $preloadSource;
	protected $sortOrder;
	protected $active;

	public function __construct() {
		$this->addType('required', Types::BOOLEAN);
		$this->addType('sortOrder', Types::INTEGER);
		$this->addType('active', Types::BOOLEAN);
	}
}