<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Db;

use OCP\IDBConnection;

class AttachmentMapper extends AbstractMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'tk_attach', Attachment::class);
	}

	public function getTotalStoredBytes(): int {
		$qb = $this->db->getQueryBuilder();
		$qb->selectAlias($qb->func()->sum('size'), 'total_size')
			->from($this->getTableName());

		$result = $qb->executeQuery();
		$value = $result->fetchOne();
		$result->closeCursor();

		return max(0, (int) $value);
	}
}