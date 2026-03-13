<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Db;

use OCP\IDBConnection;

class NotificationPreferenceMapper extends AbstractMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'tk_notif_prefs', NotificationPreference::class);
	}
}