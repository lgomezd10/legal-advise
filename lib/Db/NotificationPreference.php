<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Db;

use OCP\DB\Types;

class NotificationPreference extends AbstractEntity {
	protected $scopeType;
	protected $scopeId;
	protected $eventName;
	protected $channel;
	protected $enabled;

	public function __construct() {
		$this->addType('enabled', Types::BOOLEAN);
	}
}