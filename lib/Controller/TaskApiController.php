<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Controller;

use OCA\Gestion_incidencias\Service\TaskSyncService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class TaskApiController extends BaseApiController {
	public function __construct(string $appName, IRequest $request, private readonly TaskSyncService $taskSyncService) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function status(): DataResponse {
		return $this->ok($this->taskSyncService->getIntegrationStatus());
	}
}