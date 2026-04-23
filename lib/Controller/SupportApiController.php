<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Controller;

use OCA\ConsultasLegales\Service\RoleService;
use OCA\ConsultasLegales\Service\SupportFilterService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class SupportApiController extends BaseApiController {
	public function __construct(string $appName, IRequest $request, IUserSession $userSession, RoleService $roleService, private readonly SupportFilterService $supportFilterService) {
		parent::__construct($appName, $request, $userSession, $roleService);
	}

	#[NoAdminRequired]
	public function filters(): DataResponse {
		return $this->respond(function (): array {
			$uid = $this->assertAppAccess();
			return ['items' => $this->supportFilterService->listForConsole($uid)];
		});
	}

	#[NoAdminRequired]
	public function saveFilter(): DataResponse {
		return $this->respond(function (): array {
			$uid = $this->assertAppAccess();
			return $this->supportFilterService->save($uid, $this->request->getParams());
		}, 201);
	}

	#[NoAdminRequired]
	public function filterSettings(): DataResponse {
		return $this->respond(function (): array {
			$uid = $this->assertAppAccess();
			return ['items' => $this->supportFilterService->listForUserSettings($uid)];
		});
	}

	#[NoAdminRequired]
	public function updateFilterSettings(): DataResponse {
		return $this->respond(function (): array {
			$uid = $this->assertAppAccess();
			$items = $this->request->getParam('items') ?? [];
			return ['items' => $this->supportFilterService->saveUserSettings($uid, is_array($items) ? $items : [])];
		});
	}

	#[NoAdminRequired]
	public function restoreFilterSettings(): DataResponse {
		return $this->respond(function (): array {
			$uid = $this->assertAppAccess();
			return ['items' => $this->supportFilterService->restoreUserSettings($uid)];
		});
	}

	#[NoAdminRequired]
	public function deleteFilter(int $id): DataResponse {
		return $this->respond(function () use ($id): array {
			$uid = $this->assertAppAccess();
			$this->supportFilterService->delete($uid, $id);
			return ['deleted' => true];
		});
	}
}