<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Controller;

use OCA\ConsultasLegales\Controller\TicketApiController;
use OCA\ConsultasLegales\Service\AttachmentService;
use OCA\ConsultasLegales\Service\RoleService;
use OCA\ConsultasLegales\Service\TicketService;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class TicketApiControllerTest extends TestCase {
	public function testUpdateMergesJsonPutPayloadIntoRequestParams(): void {
		$request = $this->createMock(IRequest::class);
		$request->method('getParams')->willReturn(['format' => 'json']);
		$request->method('getMethod')->willReturn('PUT');
		$request->method('getHeader')->with('Content-Type')->willReturn('application/json');

		$jsonStream = fopen('php://temp', 'r+');
		fwrite($jsonStream, '{"status":"en_espera_usuario"}');
		rewind($jsonStream);
		$request->put = $jsonStream;

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('soporte1');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$ticketService = $this->createMock(TicketService::class);
		$ticketService->expects(self::once())
			->method('update')
			->with('soporte1', 2, ['format' => 'json', 'status' => 'en_espera_usuario'])
			->willReturn(['id' => 2, 'status' => 'en_espera_usuario']);

		$roleService = $this->createMock(RoleService::class);
		$roleService->expects(self::once())
			->method('hasAnyRole')
			->with('soporte1')
			->willReturn(true);

		$attachmentService = $this->createMock(AttachmentService::class);

		$controller = new TicketApiController('legal_advice', $request, $userSession, $roleService, $ticketService, $attachmentService);

		$response = $controller->update(2);

		self::assertInstanceOf(DataResponse::class, $response);
		self::assertSame(200, $response->getStatus());
		self::assertSame(['id' => 2, 'status' => 'en_espera_usuario'], $response->getData());
	}

	public function testUpdateReturnsForbiddenDataResponseWhenAssignmentIsRejected(): void {
		$request = $this->createMock(IRequest::class);
		$request->method('getParams')->willReturn([]);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('soporte1');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$ticketService = $this->createMock(TicketService::class);
		$ticketService->expects(self::once())
			->method('update')
			->with('soporte1', 2, [])
			->willThrowException(new \RuntimeException('Forbidden', 403));

		$roleService = $this->createMock(RoleService::class);
		$roleService->expects(self::once())
			->method('hasAnyRole')
			->with('soporte1')
			->willReturn(true);

		$attachmentService = $this->createMock(AttachmentService::class);

		$controller = new TicketApiController('legal_advice', $request, $userSession, $roleService, $ticketService, $attachmentService);

		$response = $controller->update(2);

		self::assertInstanceOf(DataResponse::class, $response);
		self::assertSame(403, $response->getStatus());
		self::assertSame(['message' => 'Forbidden'], $response->getData());
	}

	public function testDownloadAttachmentsArchiveDelegatesSelectedAttachmentIdsToTicketService(): void {
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->with('attachmentIds')->willReturn([11, 12]);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('usuario1');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$ticketService = $this->createMock(TicketService::class);
		$ticketService->expects(self::once())
			->method('downloadAttachmentsArchive')
			->with('usuario1', 44, [11, 12])
			->willReturn(['filename' => 'adjuntos-2026-000044.zip', 'mimeType' => 'application/zip', 'content' => 'UEsDB']);

		$roleService = $this->createMock(RoleService::class);
		$roleService->expects(self::once())->method('hasAnyRole')->with('usuario1')->willReturn(true);
		$attachmentService = $this->createMock(AttachmentService::class);
		$controller = new TicketApiController('legal_advice', $request, $userSession, $roleService, $ticketService, $attachmentService);

		$response = $controller->downloadAttachmentsArchive(44);

		self::assertSame(200, $response->getStatus());
		self::assertSame('adjuntos-2026-000044.zip', $response->getData()['filename']);
	}
}