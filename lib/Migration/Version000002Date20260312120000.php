<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000002Date20260312120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if ($schema->hasTable('tk_tickets')) {
			$table = $schema->getTable('tk_tickets');
			if (!$table->hasColumn('province')) {
				$table->addColumn('province', Types::STRING, ['length' => 128, 'notnull' => false]);
			}
			if (!$table->hasIndex('tk_tickets_province_ix')) {
				$table->addIndex(['province'], 'tk_tickets_province_ix');
			}
		}

		if ($schema->hasTable('tk_rules')) {
			$table = $schema->getTable('tk_rules');
			if (!$table->hasColumn('province')) {
				$table->addColumn('province', Types::STRING, ['length' => 128, 'notnull' => false]);
			}
			if (!$table->hasIndex('tk_rules_type_province_ix')) {
				$table->addIndex(['type_id', 'province'], 'tk_rules_type_province_ix');
			}
		}

		return $schema;
	}
}