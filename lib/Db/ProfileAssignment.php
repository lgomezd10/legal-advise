<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Db;

class ProfileAssignment extends AbstractEntity {
	protected $profile;
	protected $principalType;
	protected $principalId;
}