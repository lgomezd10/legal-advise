<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Db;

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class IncidentTypeMapper extends AbstractMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'tk_types', IncidentType::class);
	}

	public function findChildrenOf(?int $parentId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from('tk_types');

		if ($parentId === null) {
			$qb->where($qb->expr()->isNull('parent_id'));
		} else {
			$qb->where($qb->expr()->eq('parent_id', $qb->createNamedParameter($parentId, IQueryBuilder::PARAM_INT)));
		}

		$qb->orderBy('sort_order', 'ASC');
		return $this->findEntities($qb);
	}
}