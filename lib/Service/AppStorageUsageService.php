<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Service;

use OCA\ConsultasLegales\Db\AttachmentMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\IAppData;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\IDBConnection;
use OCP\Util;

class AppStorageUsageService {
	private const APP_TABLES = [
		'tk_tickets',
		'tk_comments',
		'tk_attach',
		'tk_history',
		'tk_types',
		'tk_urgencies',
		'tk_fields',
		'tk_ticket_data',
		'tk_rules',
		'tk_profile_map',
		'tk_filters',
		'tk_notif_prefs',
		'tk_task_sync',
		'tk_settings',
	];

	public function __construct(
		private readonly IAppData $appData,
		private readonly IDBConnection $db,
		private readonly AttachmentMapper $attachmentMapper,
	) {
	}

	public function summarize(): array {
		$attachmentBytes = $this->safeMeasure(fn (): int => $this->attachmentMapper->getTotalStoredBytes());
		$appDataBytes = $this->safeMeasure(fn (): int => $this->measureAppDataBytes());
		$databaseBytes = $this->safeMeasure(fn (): int => $this->measureDatabaseBytes());
		$totalBytes = max(0, $appDataBytes + $databaseBytes);

		return [
			'totalBytes' => $totalBytes,
			'totalLabel' => Util::humanFileSize($totalBytes),
			'appDataBytes' => $appDataBytes,
			'appDataLabel' => Util::humanFileSize($appDataBytes),
			'databaseBytes' => $databaseBytes,
			'databaseLabel' => Util::humanFileSize($databaseBytes),
			'attachmentBytes' => $attachmentBytes,
			'attachmentLabel' => Util::humanFileSize($attachmentBytes),
		];
	}

	private function measureAppDataBytes(): int {
		$total = 0;

		foreach ($this->appData->getDirectoryListing() as $folder) {
			$total += $this->measureFolderBytes($folder);
		}

		return max(0, $total);
	}

	private function measureFolderBytes(ISimpleFolder $folder): int {
		$total = 0;

		foreach ($folder->getDirectoryListing() as $file) {
			$total += max(0, (int) $file->getSize());
		}

		foreach ($this->listChildFolders($folder) as $childFolder) {
			$total += $this->measureFolderBytes($childFolder);
		}

		return $total;
	}

	private function listChildFolders(ISimpleFolder $folder): array {
		$children = [];

		foreach (['getDirectoryListing', 'getFolder'] as $method) {
			if (!method_exists($folder, $method)) {
				return [];
			}
		}

		foreach ($folder->getDirectoryListing() as $entry) {
			if (!is_object($entry) || !method_exists($entry, 'getName')) {
				continue;
			}

			$name = trim((string) $entry->getName());
			if ($name === '') {
				continue;
			}

			try {
				$children[] = $folder->getFolder($name);
			} catch (\Throwable) {
				continue;
			}
		}

		return $children;
	}

	private function measureDatabaseBytes(): int {
		$tables = $this->resolveExistingAppTables();
		if ($tables === []) {
			return 0;
		}

		return match ($this->db->getDatabaseProvider()) {
			IDBConnection::PLATFORM_MYSQL => $this->measureMysqlTableBytes($tables),
			IDBConnection::PLATFORM_POSTGRES => $this->measurePostgresTableBytes($tables),
			IDBConnection::PLATFORM_SQLITE => $this->measureSqliteTableBytes($tables),
			default => 0,
		};
	}

	private function resolveExistingAppTables(): array {
		$matches = [];
		$schema = $this->db->createSchema();

		foreach ($schema->getTables() as $table) {
			$name = (string) $table->getName();

			foreach (self::APP_TABLES as $appTable) {
				if ($name === $appTable || str_ends_with($name, $appTable)) {
					$matches[$appTable] = $name;
				}
			}
		}

		return array_values($matches);
	}

	private function measureMysqlTableBytes(array $tables): int {
		$placeholders = implode(', ', array_fill(0, count($tables), '?'));
		$sql = 'SELECT COALESCE(SUM(data_length + index_length), 0) AS total_bytes '
			. 'FROM information_schema.TABLES '
			. 'WHERE table_schema = DATABASE() AND table_name IN (' . $placeholders . ')';

		return $this->fetchSingleInt($sql, $tables);
	}

	private function measurePostgresTableBytes(array $tables): int {
		$placeholders = implode(', ', array_fill(0, count($tables), '?'));
		$sql = 'SELECT COALESCE(SUM(pg_total_relation_size(quote_ident(schemaname) || \'.\' || quote_ident(tablename))), 0) AS total_bytes '
			. 'FROM pg_tables '
			. 'WHERE schemaname NOT IN (\'pg_catalog\', \'information_schema\') '
			. 'AND tablename IN (' . $placeholders . ')';

		return $this->fetchSingleInt($sql, $tables);
	}

	private function measureSqliteTableBytes(array $tables): int {
		$placeholders = implode(', ', array_fill(0, count($tables), '?'));
		$sql = 'SELECT COALESCE(SUM(pgsize), 0) AS total_bytes FROM dbstat WHERE name IN (' . $placeholders . ')';

		return $this->fetchSingleInt($sql, $tables);
	}

	private function fetchSingleInt(string $sql, array $params): int {
		$result = $this->db->executeQuery(
			$sql,
			$params,
			array_fill(0, count($params), IQueryBuilder::PARAM_STR),
		);

		$value = $result->fetchOne();
		$result->closeCursor();

		return max(0, (int) $value);
	}

	private function safeMeasure(callable $measurement): int {
		try {
			return max(0, (int) $measurement());
		} catch (\Throwable) {
			return 0;
		}
	}
}