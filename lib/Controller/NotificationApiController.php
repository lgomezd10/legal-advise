<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Controller;

use OCA\ConsultasLegales\Service\NotificationService;
use OCA\ConsultasLegales\Service\RoleService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class NotificationApiController extends BaseApiController {
	public function __construct(string $appName, IRequest $request, IUserSession $userSession, RoleService $roleService, private readonly NotificationService $notificationService) {
		parent::__construct($appName, $request, $userSession, $roleService);
	}

	#[NoAdminRequired]
	public function preferences(): DataResponse {
		return $this->respond(function (): array {
			$uid = $this->assertAppAccess();
			return ['items' => $this->notificationService->getPreferencesForUser($uid)];
		});
	}

	#[NoAdminRequired]
	public function updatePreferences(): DataResponse {
		return $this->respond(function (): array {
			$uid = $this->assertAppAccess();
			$items = $this->request->getParam('items') ?? [];
			return ['items' => $this->notificationService->updateUserPreferences($uid, is_array($items) ? $items : [])];
		});
	}
}