<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Db;

use OCP\DB\Types;

class AppSetting extends AbstractEntity {
	protected $configKey;
	protected $configValue;

	public function __construct() {
		$this->addType('configValue', Types::JSON);
	}
}