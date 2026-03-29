<?php

declare(strict_types=1);

return [
	'routes' => [
		['name' => 'page#open', 'url' => '/open', 'verb' => 'GET'],
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
	],
	'ocs' => [
		['name' => 'bootstrap_api#index', 'url' => '/api/v1/bootstrap', 'verb' => 'GET'],
		['name' => 'ticket_api#index', 'url' => '/api/v1/tickets', 'verb' => 'GET'],
		['name' => 'ticket_api#show', 'url' => '/api/v1/tickets/{id}', 'verb' => 'GET'],
		['name' => 'ticket_api#create', 'url' => '/api/v1/tickets', 'verb' => 'POST'],
		['name' => 'ticket_api#update', 'url' => '/api/v1/tickets/{id}', 'verb' => 'PUT'],
		['name' => 'ticket_api#reopen', 'url' => '/api/v1/tickets/{id}/reopen', 'verb' => 'POST'],
		['name' => 'ticket_api#comment', 'url' => '/api/v1/tickets/{id}/comments', 'verb' => 'POST'],
		['name' => 'ticket_api#uploadAttachment', 'url' => '/api/v1/tickets/{id}/attachments', 'verb' => 'POST'],
		['name' => 'ticket_api#downloadAttachment', 'url' => '/api/v1/attachments/{id}', 'verb' => 'GET'],
		['name' => 'support_api#filters', 'url' => '/api/v1/support/filters', 'verb' => 'GET'],
		['name' => 'support_api#saveFilter', 'url' => '/api/v1/support/filters', 'verb' => 'POST'],
		['name' => 'support_api#deleteFilter', 'url' => '/api/v1/support/filters/{id}', 'verb' => 'DELETE'],
		['name' => 'support_api#filterSettings', 'url' => '/api/v1/support/filter-settings', 'verb' => 'GET'],
		['name' => 'support_api#updateFilterSettings', 'url' => '/api/v1/support/filter-settings', 'verb' => 'PUT'],
		['name' => 'support_api#restoreFilterSettings', 'url' => '/api/v1/support/filter-settings', 'verb' => 'DELETE'],
		['name' => 'export_api#tickets', 'url' => '/api/v1/export/tickets', 'verb' => 'GET'],
		['name' => 'admin_api#index', 'url' => '/api/v1/admin/config', 'verb' => 'GET'],
		['name' => 'admin_api#update', 'url' => '/api/v1/admin/config', 'verb' => 'PUT'],
		['name' => 'notification_api#preferences', 'url' => '/api/v1/notifications/preferences', 'verb' => 'GET'],
		['name' => 'notification_api#updatePreferences', 'url' => '/api/v1/notifications/preferences', 'verb' => 'PUT'],
		['name' => 'personal_config_api#show', 'url' => '/api/v1/personal-config', 'verb' => 'GET'],
		['name' => 'personal_config_api#update', 'url' => '/api/v1/personal-config', 'verb' => 'PUT'],
		['name' => 'task_api#status', 'url' => '/api/v1/tasks/status', 'verb' => 'GET'],
	],
];