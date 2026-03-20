<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Controller;

use OCA\ConsultasLegales\Service\SupportFilterService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class SupportApiController extends BaseApiController {
	public function __construct(string $appName, IRequest $request, private readonly IUserSession $userSession, private readonly SupportFilterService $supportFilterService) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function filters(): DataResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		return $this->ok(['items' => $this->supportFilterService->listForConsole($uid)]);
	}

	#[NoAdminRequired]
	public function saveFilter(): DataResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		return $this->created($this->supportFilterService->save($uid, $this->request->getParams()));
	}

	#[NoAdminRequired]
	public function filterSettings(): DataResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		return $this->ok(['items' => $this->supportFilterService->listForUserSettings($uid)]);
	}

	#[NoAdminRequired]
	public function updateFilterSettings(): DataResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		$items = $this->request->getParam('items') ?? [];
		return $this->ok(['items' => $this->supportFilterService->saveUserSettings($uid, is_array($items) ? $items : [])]);
	}

	#[NoAdminRequired]
	public function restoreFilterSettings(): DataResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		return $this->ok(['items' => $this->supportFilterService->restoreUserSettings($uid)]);
	}

	#[NoAdminRequired]
	public function deleteFilter(int $id): DataResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		$this->supportFilterService->delete($uid, $id);
		return $this->ok(['deleted' => true]);
	}
}