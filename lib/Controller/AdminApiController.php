<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Controller;

use OCA\Gestion_incidencias\Service\AdminConfigService;
use OCA\Gestion_incidencias\Service\RoleService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class AdminApiController extends BaseApiController {
	public function __construct(string $appName, IRequest $request, private readonly IUserSession $userSession, private readonly RoleService $roleService, private readonly AdminConfigService $adminConfigService) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		$this->assertAdmin();
		return $this->ok($this->adminConfigService->getConfig());
	}

	#[NoAdminRequired]
	public function update(): DataResponse {
		$this->assertAdmin();
		return $this->ok($this->adminConfigService->update($this->request->getParams()));
	}

	private function assertAdmin(): void {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		if (!$this->roleService->hasRole($uid, RoleService::ADMIN)) {
			throw new \RuntimeException('Forbidden', 403);
		}
	}
}