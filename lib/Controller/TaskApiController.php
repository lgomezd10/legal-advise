<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Controller;

use OCA\ConsultasLegales\Service\RoleService;
use OCA\ConsultasLegales\Service\TaskSyncService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class TaskApiController extends BaseApiController {
	public function __construct(string $appName, IRequest $request, IUserSession $userSession, RoleService $roleService, private readonly TaskSyncService $taskSyncService) {
		parent::__construct($appName, $request, $userSession, $roleService);
	}

	#[NoAdminRequired]
	public function status(): DataResponse {
		return $this->respond(function (): array {
			$this->assertAppAccess();
			return $this->taskSyncService->getIntegrationStatus();
		});
	}
}