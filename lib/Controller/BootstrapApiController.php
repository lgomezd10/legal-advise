<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Controller;

use OCA\ConsultasLegales\Service\BootstrapService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class BootstrapApiController extends BaseApiController {
	public function __construct(string $appName, IRequest $request, private readonly BootstrapService $bootstrapService) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		return $this->respond(fn (): array => $this->bootstrapService->build());
	}
}