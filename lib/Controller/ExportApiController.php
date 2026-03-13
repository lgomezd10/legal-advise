<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Controller;

use OCA\Gestion_incidencias\Service\ExportService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class ExportApiController extends BaseApiController {
	public function __construct(string $appName, IRequest $request, private readonly IUserSession $userSession, private readonly ExportService $exportService) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function tickets(): DataResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		$criteria = $this->request->getParam('criteria') ?? [];
		$columns = $this->request->getParam('columns') ?? [];
		$scope = (string) ($this->request->getParam('scope') ?? 'support');
		return $this->ok($this->exportService->exportTickets($uid, is_array($criteria) ? $criteria : [], $scope === 'support', is_array($columns) ? $columns : []));
	}
}