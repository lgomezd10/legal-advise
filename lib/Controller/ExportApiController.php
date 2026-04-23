<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Controller;

use OCA\ConsultasLegales\Service\ExportService;
use OCA\ConsultasLegales\Service\RoleService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class ExportApiController extends BaseApiController {
	public function __construct(string $appName, IRequest $request, IUserSession $userSession, RoleService $roleService, private readonly ExportService $exportService) {
		parent::__construct($appName, $request, $userSession, $roleService);
	}

	#[NoAdminRequired]
	public function tickets(): DataResponse {
		return $this->respond(function (): array {
			$uid = $this->assertAppAccess();
			$criteria = $this->request->getParam('criteria') ?? [];
			$columns = $this->request->getParam('columns') ?? [];
			$scope = (string) ($this->request->getParam('scope') ?? 'support');
			return $this->exportService->exportTickets($uid, is_array($criteria) ? $criteria : [], $scope === 'support', is_array($columns) ? $columns : []);
		});
	}
}