<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version000001Date20260310090000 extends SimpleMigrationStep {
	public function __construct(private readonly IDBConnection $db) {
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('tk_tickets')) {
			$table = $schema->createTable('tk_tickets');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('number', Types::STRING, ['length' => 20, 'notnull' => true]);
			$table->addColumn('creator_uid', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('created_at', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('updated_at', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('status_updated_at', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('status', Types::STRING, ['length' => 32, 'notnull' => true]);
			$table->addColumn('urgency_id', Types::INTEGER, ['notnull' => false]);
			$table->addColumn('type_id', Types::INTEGER, ['notnull' => false]);
			$table->addColumn('title', Types::STRING, ['length' => 190, 'notnull' => true]);
			$table->addColumn('user_description', Types::TEXT, ['notnull' => true]);
			$table->addColumn('support_description', Types::TEXT, ['notnull' => false]);
			$table->addColumn('assigned_user_uid', Types::STRING, ['length' => 64, 'notnull' => false]);
			$table->addColumn('assigned_group_id', Types::STRING, ['length' => 64, 'notnull' => false]);
			$table->addColumn('province', Types::STRING, ['length' => 128, 'notnull' => false]);
			$table->addColumn('city', Types::STRING, ['length' => 128, 'notnull' => false]);
			$table->addColumn('metadata', Types::JSON, ['notnull' => false]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['number'], 'tk_tickets_num_uq');
			$table->addIndex(['creator_uid'], 'tk_tickets_creator_ix');
			$table->addIndex(['assigned_user_uid'], 'tk_tickets_auser_ix');
			$table->addIndex(['assigned_group_id'], 'tk_tickets_agrp_ix');
			$table->addIndex(['province'], 'tk_tickets_province_ix');
			$table->addIndex(['status'], 'tk_tickets_status_ix');
		}

		if (!$schema->hasTable('tk_comments')) {
			$table = $schema->createTable('tk_comments');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('ticket_id', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('author_uid', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('author_role', Types::STRING, ['length' => 32, 'notnull' => true]);
			$table->addColumn('body', Types::TEXT, ['notnull' => true]);
			$table->addColumn('visibility', Types::STRING, ['length' => 16, 'notnull' => true]);
			$table->addColumn('created_at', Types::INTEGER, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['ticket_id'], 'tk_comments_ticket_ix');
		}

		if (!$schema->hasTable('tk_attach')) {
			$table = $schema->createTable('tk_attach');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('ticket_id', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('comment_id', Types::INTEGER, ['notnull' => false]);
			$table->addColumn('uploaded_by', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('original_name', Types::STRING, ['length' => 255, 'notnull' => true]);
			$table->addColumn('stored_name', Types::STRING, ['length' => 255, 'notnull' => true]);
			$table->addColumn('mime_type', Types::STRING, ['length' => 190, 'notnull' => true]);
			$table->addColumn('size', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('created_at', Types::INTEGER, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['ticket_id'], 'tk_attach_ticket_ix');
			$table->addIndex(['comment_id'], 'tk_attach_comment_ix');
		}

		if (!$schema->hasTable('tk_history')) {
			$table = $schema->createTable('tk_history');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('ticket_id', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('actor_uid', Types::STRING, ['length' => 64, 'notnull' => false]);
			$table->addColumn('actor_role', Types::STRING, ['length' => 32, 'notnull' => false]);
			$table->addColumn('event_type', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('visibility', Types::STRING, ['length' => 16, 'notnull' => true]);
			$table->addColumn('payload', Types::JSON, ['notnull' => false]);
			$table->addColumn('created_at', Types::INTEGER, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['ticket_id'], 'tk_history_ticket_ix');
		}

		if (!$schema->hasTable('tk_types')) {
			$table = $schema->createTable('tk_types');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('parent_id', Types::INTEGER, ['notnull' => false]);
			$table->addColumn('name', Types::STRING, ['length' => 190, 'notnull' => true]);
			$table->addColumn('slug', Types::STRING, ['length' => 190, 'notnull' => true]);
			$table->addColumn('level', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->addColumn('active', Types::BOOLEAN, ['notnull' => false, 'default' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['slug'], 'tk_types_slug_uq');
		}

		if (!$schema->hasTable('tk_urgencies')) {
			$table = $schema->createTable('tk_urgencies');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('name', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('weight', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('color', Types::STRING, ['length' => 32, 'notnull' => true]);
			$table->addColumn('restrictions', Types::JSON, ['notnull' => false]);
			$table->addColumn('active', Types::BOOLEAN, ['notnull' => false, 'default' => true]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('tk_fields')) {
			$table = $schema->createTable('tk_fields');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('field_key', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('label', Types::STRING, ['length' => 128, 'notnull' => true]);
			$table->addColumn('field_type', Types::STRING, ['length' => 32, 'notnull' => true]);
			$table->addColumn('required', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
			$table->addColumn('preload_source', Types::STRING, ['length' => 64, 'notnull' => false]);
			$table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->addColumn('active', Types::BOOLEAN, ['notnull' => false, 'default' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['field_key'], 'tk_fields_key_uq');
		}

		if (!$schema->hasTable('tk_ticket_data')) {
			$table = $schema->createTable('tk_ticket_data');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('ticket_id', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('field_key', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('field_label', Types::STRING, ['length' => 128, 'notnull' => true]);
			$table->addColumn('field_value', Types::TEXT, ['notnull' => false]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['ticket_id'], 'tk_ticket_data_ix');
		}

		if (!$schema->hasTable('tk_rules')) {
			$table = $schema->createTable('tk_rules');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('type_id', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('province', Types::STRING, ['length' => 128, 'notnull' => false]);
			$table->addColumn('assigned_user_uid', Types::STRING, ['length' => 64, 'notnull' => false]);
			$table->addColumn('assigned_group_id', Types::STRING, ['length' => 64, 'notnull' => false]);
			$table->addColumn('priority', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
			$table->addIndex(['type_id'], 'tk_rules_type_ix');
			$table->addIndex(['type_id', 'province'], 'tk_rules_type_province_ix');
		}

		if (!$schema->hasTable('tk_profile_map')) {
			$table = $schema->createTable('tk_profile_map');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('profile', Types::STRING, ['length' => 32, 'notnull' => true]);
			$table->addColumn('principal_type', Types::STRING, ['length' => 16, 'notnull' => true]);
			$table->addColumn('principal_id', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['profile', 'principal_type', 'principal_id'], 'tk_profile_map_uq');
		}

		if (!$schema->hasTable('tk_filters')) {
			$table = $schema->createTable('tk_filters');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('owner_uid', Types::STRING, ['length' => 64, 'notnull' => false]);
			$table->addColumn('name', Types::STRING, ['length' => 128, 'notnull' => true]);
			$table->addColumn('criteria', Types::JSON, ['notnull' => true]);
			$table->addColumn('is_predefined', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
			$table->addColumn('sort_order', Types::INTEGER, ['notnull' => true, 'default' => 0]);
			$table->setPrimaryKey(['id']);
		}

		if (!$schema->hasTable('tk_notif_prefs')) {
			$table = $schema->createTable('tk_notif_prefs');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('scope_type', Types::STRING, ['length' => 16, 'notnull' => true]);
			$table->addColumn('scope_id', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('event_name', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('channel', Types::STRING, ['length' => 16, 'notnull' => true]);
			$table->addColumn('enabled', Types::BOOLEAN, ['notnull' => false, 'default' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['scope_type', 'scope_id', 'event_name', 'channel'], 'tk_notif_prefs_uq');
		}

		if (!$schema->hasTable('tk_task_sync')) {
			$table = $schema->createTable('tk_task_sync');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('ticket_id', Types::INTEGER, ['notnull' => true]);
			$table->addColumn('assignee_uid', Types::STRING, ['length' => 64, 'notnull' => false]);
			$table->addColumn('calendar_uri', Types::STRING, ['length' => 190, 'notnull' => false]);
			$table->addColumn('object_uri', Types::STRING, ['length' => 190, 'notnull' => false]);
			$table->addColumn('object_uid', Types::STRING, ['length' => 190, 'notnull' => false]);
			$table->addColumn('sync_status', Types::STRING, ['length' => 32, 'notnull' => true]);
			$table->addColumn('last_synced_at', Types::INTEGER, ['notnull' => false]);
			$table->addColumn('last_error', Types::TEXT, ['notnull' => false]);
			$table->addColumn('payload', Types::JSON, ['notnull' => false]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['ticket_id'], 'tk_task_sync_ticket_uq');
		}

		if (!$schema->hasTable('tk_settings')) {
			$table = $schema->createTable('tk_settings');
			$table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$table->addColumn('config_key', Types::STRING, ['length' => 64, 'notnull' => true]);
			$table->addColumn('config_value', Types::JSON, ['notnull' => true]);
			$table->setPrimaryKey(['id']);
			$table->addUniqueIndex(['config_key'], 'tk_settings_key_uq');
		}

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void {
		$this->seedSettings();
		$this->seedUrgencies();
		$this->seedFields();
		$this->seedTypesAndRules();
		$this->seedFilters();
		$this->seedNotificationPreferences();
	}

	private function exists(string $table, string $column, mixed $value): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from($table)
			->where($qb->expr()->eq($column, $qb->createNamedParameter($value)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $this->fetchRow($result);
		$result->closeCursor();

		return $row !== false;
	}

	private function insert(string $table, array $data): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert($table);

		foreach ($data as $column => $value) {
			$qb->setValue($column, $qb->createNamedParameter($value));
		}

		$qb->executeStatement();
	}

	private function fetchTypeId(string $slug): ?int {
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')
			->from('tk_types')
			->where($qb->expr()->eq('slug', $qb->createNamedParameter($slug)))
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $this->fetchRow($result);
		$result->closeCursor();

		return $row === false ? null : (int) $row['id'];
	}

	private function seedSettings(): void {
		$settings = [
			'status_catalog' => [
				['id' => 'nuevo', 'label' => 'Nuevo'],
				['id' => 'asignado', 'label' => 'Asignado'],
				['id' => 'en_espera_usuario', 'label' => 'En espera usuario'],
				['id' => 'en_progreso', 'label' => 'En progreso'],
				['id' => 'resuelto', 'label' => 'Resuelto'],
				['id' => 'cerrado', 'label' => 'Cerrado'],
			],
			'tasks_config' => [
				'enabled' => true,
				'defaultStrategy' => 'firstWritable',
				'missingListBehavior' => 'skip',
			],
			'attachment_config' => [
				'allowedExtensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tif', 'tiff'],
			],
			'notification_policy' => [
				'defaultChannelOrder' => ['nextcloud', 'mail'],
				'allowUserOverrides' => true,
			],
		];

		foreach ($settings as $key => $value) {
			if (!$this->exists('tk_settings', 'config_key', $key)) {
				$this->insert('tk_settings', ['config_key' => $key, 'config_value' => json_encode($value, JSON_THROW_ON_ERROR)]);
			}
		}
	}

	private function seedUrgencies(): void {
		$rows = [
			['name' => 'Baja', 'weight' => 1, 'color' => '#7A8F62', 'restrictions' => null, 'active' => true],
			['name' => 'Media', 'weight' => 2, 'color' => '#D9A441', 'restrictions' => null, 'active' => true],
			['name' => 'Alta', 'weight' => 3, 'color' => '#D96C3F', 'restrictions' => null, 'active' => true],
		];

		foreach ($rows as $row) {
			if (!$this->exists('tk_urgencies', 'name', $row['name'])) {
				$row['restrictions'] = $row['restrictions'] === null ? null : json_encode($row['restrictions'], JSON_THROW_ON_ERROR);
				$this->insert('tk_urgencies', $row);
			}
		}
	}

	private function seedFields(): void {
		$rows = [
			['field_key' => 'name', 'label' => 'Nombre', 'field_type' => 'text', 'required' => true, 'preload_source' => 'displayName', 'sort_order' => 10, 'active' => true],
			['field_key' => 'email', 'label' => 'Email', 'field_type' => 'email', 'required' => true, 'preload_source' => 'email', 'sort_order' => 20, 'active' => true],
			['field_key' => 'phone', 'label' => 'Telefono', 'field_type' => 'tel', 'required' => false, 'preload_source' => 'phone', 'sort_order' => 30, 'active' => true],
			['field_key' => 'city', 'label' => 'Ciudad', 'field_type' => 'text', 'required' => false, 'preload_source' => 'location', 'sort_order' => 40, 'active' => true],
		];

		foreach ($rows as $row) {
			if (!$this->exists('tk_fields', 'field_key', $row['field_key'])) {
				$this->insert('tk_fields', $row);
			}
		}
	}

	private function seedTypesAndRules(): void {
		$types = [
			['slug' => 'necesito-asesoramiento', 'name' => 'Neceisto asesoramiento', 'parent' => null, 'level' => 0, 'sort_order' => 10],
			['slug' => 'necesito-asesoramiento-solo-territorial', 'name' => 'Solo Territorial', 'parent' => 'necesito-asesoramiento', 'level' => 1, 'sort_order' => 10],
			['slug' => 'necesito-asesoramiento-territorial-y-legal', 'name' => 'Territorial y Legal', 'parent' => 'necesito-asesoramiento', 'level' => 1, 'sort_order' => 20],
			['slug' => 'necesito-asesoramiento-territorial-y-comunicacion', 'name' => 'Territorial y Comunicacion', 'parent' => 'necesito-asesoramiento', 'level' => 1, 'sort_order' => 30],
			['slug' => 'necesito-asesoramiento-territorial-legal-y-comunicacion', 'name' => 'Territoral, Legal y Comunicacion', 'parent' => 'necesito-asesoramiento', 'level' => 1, 'sort_order' => 40],
			['slug' => 'quiero-informar', 'name' => 'Quiero informar', 'parent' => null, 'level' => 0, 'sort_order' => 20],
		];

		foreach ($types as $type) {
			if ($this->exists('tk_types', 'slug', $type['slug'])) {
				continue;
			}

			$parentId = $type['parent'] === null ? null : $this->fetchTypeId($type['parent']);
			$this->insert('tk_types', [
				'parent_id' => $parentId,
				'name' => $type['name'],
				'slug' => $type['slug'],
				'level' => $type['level'],
				'sort_order' => $type['sort_order'],
				'active' => true,
			]);
		}

		$ruleTargets = [];

		foreach ($ruleTargets as $rule) {
			$typeId = $this->fetchTypeId($rule['slug']);
			if ($typeId === null || $this->exists('tk_rules', 'type_id', $typeId)) {
				continue;
			}

			$this->insert('tk_rules', [
				'type_id' => $typeId,
				'assigned_user_uid' => $rule['assigned_user_uid'],
				'assigned_group_id' => $rule['assigned_group_id'],
				'priority' => $rule['priority'],
			]);
		}
	}

	private function seedFilters(): void {
		$filters = [
			['name' => 'Asignadas a mi', 'criteria' => ['assignedUser' => '__me__'], 'is_predefined' => true, 'sort_order' => 10],
			['name' => 'Asignadas a mis grupos', 'criteria' => ['assignedGroup' => '__my_groups__'], 'is_predefined' => true, 'sort_order' => 20],
			['name' => 'Sin asignar', 'criteria' => ['unassigned' => true], 'is_predefined' => true, 'sort_order' => 30],
			['name' => 'Abiertas', 'criteria' => ['status' => ['nuevo', 'asignado', 'en_progreso']], 'is_predefined' => true, 'sort_order' => 40],
			['name' => 'Pendientes de usuario', 'criteria' => ['status' => ['en_espera_usuario']], 'is_predefined' => true, 'sort_order' => 50],
			['name' => 'Cerradas recientes', 'criteria' => ['status' => ['resuelto', 'cerrado'], 'updatedWithinDays' => 30], 'is_predefined' => true, 'sort_order' => 60],
		];

		foreach ($filters as $filter) {
			if (!$this->exists('tk_filters', 'name', $filter['name'])) {
				$this->insert('tk_filters', [
					'owner_uid' => null,
					'name' => $filter['name'],
					'criteria' => json_encode($filter['criteria'], JSON_THROW_ON_ERROR),
					'is_predefined' => $filter['is_predefined'],
					'sort_order' => $filter['sort_order'],
				]);
			}
		}
	}

	private function seedNotificationPreferences(): void {
		$rows = [];
		foreach (['usuario', 'soporte', 'administrador'] as $profile) {
			foreach (['ticket_created', 'ticket_assigned', 'ticket_public_reply', 'ticket_resolved'] as $eventName) {
				$rows[] = ['scope_type' => 'profile', 'scope_id' => $profile, 'event_name' => $eventName, 'channel' => 'nextcloud', 'enabled' => true];
				$rows[] = ['scope_type' => 'profile', 'scope_id' => $profile, 'event_name' => $eventName, 'channel' => 'mail', 'enabled' => $profile !== 'soporte'];
			}
		}

		foreach ($rows as $row) {
			$key = implode(':', [$row['scope_type'], $row['scope_id'], $row['event_name'], $row['channel']]);
			unset($key);
			if ($this->exists('tk_notif_prefs', 'scope_id', $row['scope_id'])) {
				$qb = $this->db->getQueryBuilder();
				$qb->select('id')
					->from('tk_notif_prefs')
					->where($qb->expr()->eq('scope_type', $qb->createNamedParameter($row['scope_type'])))
					->andWhere($qb->expr()->eq('scope_id', $qb->createNamedParameter($row['scope_id'])))
					->andWhere($qb->expr()->eq('event_name', $qb->createNamedParameter($row['event_name'])))
					->andWhere($qb->expr()->eq('channel', $qb->createNamedParameter($row['channel'])));
				$result = $qb->executeQuery();
				$existing = $this->fetchRow($result);
				$result->closeCursor();
				if ($existing !== false) {
					continue;
				}
			}

			$this->insert('tk_notif_prefs', $row);
		}
	}

	private function fetchRow(object $result): array|false {
		if (method_exists($result, 'fetchAssociative')) {
			return $result->fetchAssociative();
		}

		if (method_exists($result, 'fetch')) {
			return $result->fetch();
		}

		return false;
	}
}