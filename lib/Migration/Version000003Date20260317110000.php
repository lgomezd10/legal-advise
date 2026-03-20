<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000003Date20260317110000 extends SimpleMigrationStep {
	public function __construct(private readonly IDBConnection $db) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('tk_filters')) {
			return $schema;
		}

		$table = $schema->getTable('tk_filters');
		if (!$table->hasColumn('scope_type')) {
			$table->addColumn('scope_type', Types::STRING, ['length' => 16, 'notnull' => false]);
		}
		if (!$table->hasColumn('active')) {
			$table->addColumn('active', Types::BOOLEAN, ['default' => true, 'notnull' => true]);
		}
		if (!$table->hasColumn('is_default')) {
			$table->addColumn('is_default', Types::BOOLEAN, ['default' => false, 'notnull' => true]);
		}
		if (!$table->hasIndex('tk_filters_scope_owner_ix')) {
			$table->addIndex(['scope_type', 'owner_uid'], 'tk_filters_scope_owner_ix');
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id', 'owner_uid', 'name')
			->from('tk_filters')
			->orderBy('sort_order', 'ASC');

		$result = $qb->executeQuery();
		$rows = [];
		if (method_exists($result, 'fetchAssociative')) {
			$fetchAssociative = [$result, 'fetchAssociative'];
			while (($row = $fetchAssociative()) !== false) {
				$rows[] = $row;
			}
		} elseif (method_exists($result, 'fetch')) {
			$fetch = [$result, 'fetch'];
			while (($row = $fetch()) !== false) {
				$rows[] = $row;
			}
		}
		$result->closeCursor();

		$hasGlobalDefault = false;
		foreach ($rows as $row) {
			$scopeType = (($row['owner_uid'] ?? null) === null || $row['owner_uid'] === '') ? 'global' : 'user';
			$isDefault = !$hasGlobalDefault && $scopeType === 'global' && (string) ($row['name'] ?? '') === 'Asignadas a mi';
			if ($isDefault) {
				$hasGlobalDefault = true;
			}

			$update = $this->db->getQueryBuilder();
			$update->update('tk_filters')
				->set('scope_type', $update->createNamedParameter($scopeType))
				->set('active', $update->createNamedParameter(true, IQueryBuilder::PARAM_BOOL))
				->set('is_default', $update->createNamedParameter($isDefault, IQueryBuilder::PARAM_BOOL))
				->where($update->expr()->eq('id', $update->createNamedParameter((int) $row['id'], IQueryBuilder::PARAM_INT)));
			$update->executeStatement();
		}
	}
}