<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Controller;

use OCA\ConsultasLegales\Controller\PersonalConfigApiController;
use OCA\ConsultasLegales\Service\PersonalConfigService;
use OCA\ConsultasLegales\Service\RoleService;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;

class PersonalConfigApiControllerTest extends TestCase {
	public function testUpdateReadsValuesPayloadAndMarksStoredValues(): void {
		$request = $this->createMock(IRequest::class);
		$request->method('getParam')->with('values')->willReturn(['email' => 'nuevo@example.com']);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('usuario1');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$roleService = $this->createMock(RoleService::class);
		$roleService->method('hasAnyRole')->with('usuario1')->willReturn(true);

		$service = $this->createMock(PersonalConfigService::class);
		$service->expects(self::once())
			->method('saveForUser')
			->with('usuario1', ['email' => 'nuevo@example.com'])
			->willReturn(['email' => 'nuevo@example.com']);

		$controller = new PersonalConfigApiController('legal_advice', $request, $userSession, $roleService, $service);

		$response = $controller->update();

		self::assertInstanceOf(DataResponse::class, $response);
		self::assertSame(200, $response->getStatus());
		self::assertSame(['values' => ['email' => 'nuevo@example.com'], 'hasStoredValues' => true], $response->getData());
	}

	public function testRestoreReturnsNextcloudValuesAndClearsStoredFlag(): void {
		$request = $this->createMock(IRequest::class);

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('usuario1');

		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);

		$roleService = $this->createMock(RoleService::class);
		$roleService->method('hasAnyRole')->with('usuario1')->willReturn(true);

		$service = $this->createMock(PersonalConfigService::class);
		$service->expects(self::once())
			->method('restoreForUser')
			->with('usuario1')
			->willReturn(['email' => 'usuario@example.com']);

		$controller = new PersonalConfigApiController('legal_advice', $request, $userSession, $roleService, $service);

		$response = $controller->restore();

		self::assertInstanceOf(DataResponse::class, $response);
		self::assertSame(200, $response->getStatus());
		self::assertSame(['values' => ['email' => 'usuario@example.com'], 'hasStoredValues' => false], $response->getData());
	}
}
