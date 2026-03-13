<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class TicketMapper extends AbstractMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'tk_tickets', Ticket::class);
	}

	public function findLatestYearSequence(int $year): int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('number')
			->from('tk_tickets')
			->where($qb->expr()->like('number', $qb->createNamedParameter($year . '-%', IQueryBuilder::PARAM_STR)))
			->orderBy('number', 'DESC')
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row === false) {
			return 0;
		}

		return (int) substr((string) $row['number'], 5);
	}
}