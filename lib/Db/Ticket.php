<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Db;

use OCP\DB\Types;

class Ticket extends AbstractEntity {
	protected $number;
	protected $creatorUid;
	protected $createdAt;
	protected $updatedAt;
	protected $statusUpdatedAt;
	protected $status;
	protected $urgencyId;
	protected $typeId;
	protected $title;
	protected $userDescription;
	protected $supportDescription;
	protected $assignedUserUid;
	protected $assignedGroupId;
	protected $province;
	protected $city;
	protected $metadata;

	public function __construct() {
		$this->addType('createdAt', Types::INTEGER);
		$this->addType('updatedAt', Types::INTEGER);
		$this->addType('statusUpdatedAt', Types::INTEGER);
		$this->addType('urgencyId', Types::INTEGER);
		$this->addType('typeId', Types::INTEGER);
		$this->addType('metadata', Types::JSON);
	}
}