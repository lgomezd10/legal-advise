<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Controller;

use OCA\ConsultasLegales\AppInfo\Application;
use OCA\ConsultasLegales\Service\BootstrapService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Util;

class PageController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IInitialState $initialState,
		private readonly BootstrapService $bootstrapService,
		private readonly IURLGenerator $urlGenerator,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function open(): RedirectResponse {
		$bootstrap = $this->bootstrapService->build();
		$navigation = $bootstrap['navigation'] ?? [];
		$landingRoute = '/';

		if (is_array($navigation) && isset($navigation[0]) && is_array($navigation[0])) {
			$route = $navigation[0]['route'] ?? '/';
			if (is_string($route) && $route !== '') {
				$landingRoute = $route;
			}
		}

		return new RedirectResponse($this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.page.index') . '#' . $landingRoute);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		$this->initialState->provideInitialState('bootstrap', $this->bootstrapService->build());

		Util::addStyle(Application::APP_ID, 'style');
		Util::addScript(Application::APP_ID, 'main');

		return new TemplateResponse(Application::APP_ID, 'main');
	}
}