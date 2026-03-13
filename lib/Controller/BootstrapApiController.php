<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Controller;

use OCA\Gestion_incidencias\Service\BootstrapService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class BootstrapApiController extends BaseApiController {
	public function __construct(string $appName, IRequest $request, private readonly BootstrapService $bootstrapService) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		return $this->ok($this->bootstrapService->build());
	}
}