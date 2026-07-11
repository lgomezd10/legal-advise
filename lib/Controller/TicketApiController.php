<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Controller;

use OCA\ConsultasLegales\Service\AttachmentService;
use OCA\ConsultasLegales\Service\RoleService;
use OCA\ConsultasLegales\Service\TicketService;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUserSession;

class TicketApiController extends BaseApiController {
	public function __construct(
		string $appName,
		IRequest $request,
		IUserSession $userSession,
		RoleService $roleService,
		private readonly TicketService $ticketService,
		private readonly AttachmentService $attachmentService,
	) {
		parent::__construct($appName, $request, $userSession, $roleService);
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		return $this->respond(function (): array {
			$uid = $this->assertAppAccess();
			$scope = (string) ($this->request->getParam('scope') ?? 'user');
			$criteria = $this->request->getParam('criteria') ?? [];
			return ['items' => $this->ticketService->list($uid, is_array($criteria) ? $criteria : [], $scope === 'support')];
		});
	}

	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		return $this->respond(function () use ($id): array {
			$uid = $this->assertAppAccess();
			return $this->ticketService->show($uid, $id);
		});
	}

	#[NoAdminRequired]
	public function create(): DataResponse {
		return $this->respond(function (): array {
			$uid = $this->assertAppAccess();
			return $this->ticketService->create($uid, $this->getRequestPayload());
		}, 201);
	}

	#[NoAdminRequired]
	public function update(int $id): DataResponse {
		return $this->respond(function () use ($id): array {
			$uid = $this->assertAppAccess();
			return $this->ticketService->update($uid, $id, $this->getRequestPayload());
		});
	}

	#[NoAdminRequired]
	public function destroy(int $id): DataResponse {
		return $this->respond(function () use ($id): array {
			$uid = $this->assertAppAccess();
			return $this->ticketService->deleteTicket($uid, $id);
		});
	}

	#[NoAdminRequired]
	public function reopen(int $id): DataResponse {
		return $this->respond(function () use ($id): array {
			$uid = $this->assertAppAccess();
			return $this->ticketService->reopen($uid, $id);
		});
	}

	#[NoAdminRequired]
	public function comment(int $id): DataResponse {
		return $this->respond(function () use ($id): array {
			$uid = $this->assertAppAccess();
			return $this->ticketService->addComment($uid, $id, $this->getRequestPayload());
		}, 201);
	}

	#[NoAdminRequired]
	public function updateComment(int $id, int $commentId): DataResponse {
		return $this->respond(function () use ($id, $commentId): array {
			$uid = $this->assertAppAccess();
			return $this->ticketService->updateComment($uid, $id, $commentId, $this->getRequestPayload());
		});
	}

	#[NoAdminRequired]
	public function deleteComment(int $id, int $commentId): DataResponse {
		return $this->respond(function () use ($id, $commentId): array {
			$uid = $this->assertAppAccess();
			$restoreAssignedStatus = filter_var($this->request->getParam('restoreAssignedStatus') ?? false, FILTER_VALIDATE_BOOLEAN);
			return $this->ticketService->deleteComment($uid, $id, $commentId, $restoreAssignedStatus);
		});
	}

	private function getRequestPayload(): array {
		$params = $this->request->getParams();
		$rawJsonPayload = $this->getJsonRequestPayload();

		if ($rawJsonPayload === []) {
			return $params;
		}

		return array_merge($params, $rawJsonPayload);
	}

	private function getJsonRequestPayload(): array {
		$contentType = trim($this->request->getHeader('Content-Type'));
		if ($contentType === '' || preg_match(IRequest::JSON_CONTENT_TYPE_REGEX, $contentType) !== 1) {
			return [];
		}

		$method = strtoupper($this->request->getMethod());
		if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
			return [];
		}

		$rawBody = null;
		if ($method === 'PUT' && isset($this->request->put)) {
			$rawBody = is_resource($this->request->put)
				? stream_get_contents($this->request->put)
				: $this->request->put;
		}

		if (!is_string($rawBody) || trim($rawBody) === '') {
			return [];
		}

		$decoded = json_decode($rawBody, true);
		return is_array($decoded) ? $decoded : [];
	}

	#[NoAdminRequired]
	public function uploadAttachment(int $id): DataResponse {
		return $this->respond(function () use ($id): array {
			$uid = $this->assertAppAccess();
			$commentId = (int) ($this->request->getParam('commentId') ?? 0);
			$sourceUrl = $this->request->getParam('sourceUrl');
			$originalName = $this->request->getParam('originalName');
			return $this->ticketService->addAttachment(
				$uid,
				$id,
				$this->request->getUploadedFile('file') ?? [],
				$commentId,
				is_string($sourceUrl) ? $sourceUrl : null,
				is_string($originalName) ? $originalName : null,
			);
		}, 201);
	}

	#[NoAdminRequired]
	public function downloadAttachment(int $id): DataResponse {
		return $this->respond(function () use ($id): array {
			$this->assertAppAccess();
			return $this->attachmentService->download($id);
		});
	}
}