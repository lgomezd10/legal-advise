<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Controller;

use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

abstract class BaseApiController extends OCSController {
	public function __construct(string $appName, IRequest $request) {
		parent::__construct($appName, $request);
	}

	protected function ok(array $data): DataResponse {
		return new DataResponse($data, 200);
	}

	protected function created(array $data): DataResponse {
		return new DataResponse($data, 201);
	}
}