<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Db;

use OCP\IDBConnection;

class ProfileAssignmentMapper extends AbstractMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'tk_profile_map', ProfileAssignment::class);
	}
}