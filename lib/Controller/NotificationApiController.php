<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Controller;

use OCA\Gestion_incidencias\Service\NotificationService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class NotificationApiController extends BaseApiController {
	public function __construct(string $appName, IRequest $request, private readonly IUserSession $userSession, private readonly NotificationService $notificationService) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function preferences(): DataResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		return $this->ok(['items' => $this->notificationService->getPreferencesForUser($uid)]);
	}

	#[NoAdminRequired]
	public function updatePreferences(): DataResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		$items = $this->request->getParam('items') ?? [];
		return $this->ok(['items' => $this->notificationService->updateUserPreferences($uid, is_array($items) ? $items : [])]);
	}
}