<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Controller;

use OCA\ConsultasLegales\Service\AttachmentService;
use OCA\ConsultasLegales\Service\TicketService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class TicketApiController extends BaseApiController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly TicketService $ticketService,
		private readonly AttachmentService $attachmentService,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		$scope = (string) ($this->request->getParam('scope') ?? 'user');
		$criteria = $this->request->getParam('criteria') ?? [];
		return $this->ok(['items' => $this->ticketService->list($uid, is_array($criteria) ? $criteria : [], $scope === 'support')]);
	}

	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		return $this->ok($this->ticketService->show($uid, $id));
	}

	#[NoAdminRequired]
	public function create(): DataResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		return $this->created($this->ticketService->create($uid, $this->request->getParams()));
	}

	#[NoAdminRequired]
	public function update(int $id): DataResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		return $this->ok($this->ticketService->update($uid, $id, $this->request->getParams()));
	}

	#[NoAdminRequired]
	public function comment(int $id): DataResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		return $this->created($this->ticketService->addComment($uid, $id, $this->request->getParams()));
	}

	#[NoAdminRequired]
	public function uploadAttachment(int $id): DataResponse {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		$commentId = (int) ($this->request->getParam('commentId') ?? 0);
		$sourceUrl = $this->request->getParam('sourceUrl');
		$originalName = $this->request->getParam('originalName');
		return $this->created($this->ticketService->addAttachment(
			$uid,
			$id,
			$this->request->getUploadedFile('file') ?? [],
			$commentId,
			is_string($sourceUrl) ? $sourceUrl : null,
			is_string($originalName) ? $originalName : null,
		));
	}

	#[NoAdminRequired]
	public function downloadAttachment(int $id): DataResponse {
		return $this->ok($this->attachmentService->download($id));
	}
}