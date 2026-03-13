<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

abstract class AbstractMapper extends QBMapper {
	public function __construct(IDBConnection $db, string $tableName, string $entityClass) {
		parent::__construct($db, $tableName, $entityClass);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function find(int $id): object {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		return $this->findEntity($qb);
	}

	public function findAllOrdered(string $orderBy = 'id', string $direction = 'ASC'): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy($orderBy, $direction);

		return $this->findEntities($qb);
	}

	public function findBy(string $column, mixed $value, string $orderBy = 'id', string $direction = 'ASC'): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq($column, $qb->createNamedParameter($value, $this->detectType($value))))
			->orderBy($orderBy, $direction);

		return $this->findEntities($qb);
	}

	public function findOneBy(string $column, mixed $value): ?object {
		$results = $this->findBy($column, $value);
		return $results[0] ?? null;
	}

	public function findByMany(array $criteria, string $orderBy = 'id', string $direction = 'ASC'): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());

		$index = 0;
		foreach ($criteria as $column => $value) {
			$expr = $qb->expr()->eq($column, $qb->createNamedParameter($value, $this->detectType($value), ':param' . $index));
			$index === 0 ? $qb->where($expr) : $qb->andWhere($expr);
			$index++;
		}

		$qb->orderBy($orderBy, $direction);
		return $this->findEntities($qb);
	}

	public function deleteBy(string $column, mixed $value): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq($column, $qb->createNamedParameter($value, $this->detectType($value))));

		return $qb->executeStatement();
	}

	protected function detectType(mixed $value): int {
		return match (true) {
			is_int($value) => IQueryBuilder::PARAM_INT,
			is_bool($value) => IQueryBuilder::PARAM_BOOL,
			default => IQueryBuilder::PARAM_STR,
		};
	}
}