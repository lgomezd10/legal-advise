<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Controller;

use OCA\ConsultasLegales\Controller\TicketApiController;
use OCA\ConsultasLegales\Service\AttachmentService;
use OCA\ConsultasLegales\Service\TicketService;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class TicketApiControllerTest extends TestCase {
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

		$attachmentService = $this->createMock(AttachmentService::class);

		$controller = new TicketApiController('legal_advice', $request, $userSession, $ticketService, $attachmentService);

		$response = $controller->update(2);

		self::assertInstanceOf(DataResponse::class, $response);
		self::assertSame(403, $response->getStatus());
		self::assertSame(['message' => 'Forbidden'], $response->getData());
	}
}