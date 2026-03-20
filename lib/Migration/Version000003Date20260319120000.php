<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000003Date20260319120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('tk_attach')) {
			return $schema;
		}

		$table = $schema->getTable('tk_attach');
		if (!$table->hasColumn('source_url')) {
			$table->addColumn('source_url', Types::STRING, ['length' => 2048, 'notnull' => false]);
		}

		return $schema;
	}
}