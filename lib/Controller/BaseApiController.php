<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Controller;

use OCA\ConsultasLegales\Service\RoleService;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use RuntimeException;
use InvalidArgumentException;

abstract class BaseApiController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly RoleService $roleService,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @param callable(): array $callback
	 */
	protected function respond(callable $callback, int $successStatus = 200): DataResponse {
		try {
			return new DataResponse($callback(), $successStatus);
		} catch (InvalidArgumentException $exception) {
			return new DataResponse(['message' => $exception->getMessage()], 400);
		} catch (RuntimeException $exception) {
			$status = (int) $exception->getCode();
			if ($status < 400 || $status > 599) {
				$status = 500;
			}

			return new DataResponse(['message' => $exception->getMessage()], $status);
		}
	}

	protected function ok(array $data): DataResponse {
		return new DataResponse($data, 200);
	}

	protected function created(array $data): DataResponse {
		return new DataResponse($data, 201);
	}

	protected function assertAppAccess(): string {
		$uid = $this->userSession->getUser()?->getUID() ?? '';
		if (!$this->roleService->hasAnyRole($uid)) {
			throw new RuntimeException('Forbidden', 403);
		}

		return $uid;
	}
}