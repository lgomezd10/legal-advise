<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Controller;

use OCA\ConsultasLegales\Service\BootstrapService;
use OCA\ConsultasLegales\Service\RoleService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class BootstrapApiController extends BaseApiController {
	public function __construct(string $appName, IRequest $request, IUserSession $userSession, RoleService $roleService, private readonly BootstrapService $bootstrapService) {
		parent::__construct($appName, $request, $userSession, $roleService);
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		return $this->respond(function (): array {
			$this->assertAppAccess();
			return $this->bootstrapService->build();
		});
	}
}