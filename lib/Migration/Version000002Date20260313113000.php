<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000002Date20260313113000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('tk_attach')) {
			return $schema;
		}

		$table = $schema->getTable('tk_attach');
		if (!$table->hasColumn('comment_id')) {
			$table->addColumn('comment_id', Types::INTEGER, ['notnull' => false]);
		}

		$hasCommentIndex = false;
		foreach ($table->getIndexes() as $index) {
			if ($index->getName() === 'tk_attach_comment_ix') {
				$hasCommentIndex = true;
				break;
			}
		}

		if (!$hasCommentIndex) {
			$table->addIndex(['comment_id'], 'tk_attach_comment_ix');
		}

		return $schema;
	}
}