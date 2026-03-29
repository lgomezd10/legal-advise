<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Controller;

use OCA\ConsultasLegales\Service\PersonalConfigService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class PersonalConfigApiController extends BaseApiController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly PersonalConfigService $personalConfigService,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function show(): DataResponse {
		return $this->respond(function (): array {
			$uid = $this->userSession->getUser()?->getUID() ?? '';
			return ['values' => $this->personalConfigService->getForUser($uid)];
		});
	}

	#[NoAdminRequired]
	public function update(): DataResponse {
		return $this->respond(function (): array {
			$uid = $this->userSession->getUser()?->getUID() ?? '';
			$values = $this->request->getParam('values') ?? [];
			return ['values' => $this->personalConfigService->saveForUser($uid, is_array($values) ? $values : [])];
		});
	}
}