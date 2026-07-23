<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\AppInfo;

use OCA\ConsultasLegales\Notification\Notifier;
use OCA\ConsultasLegales\Service\CatalogService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\INavigationManager;
use OCP\IURLGenerator;

class Application extends App implements IBootstrap {
	public const APP_ID = 'legal_advice';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerNotifierService(Notifier::class);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(function (INavigationManager $navigationManager, IURLGenerator $urlGenerator, CatalogService $catalogService): void {
			$navigationManager->add(static function () use ($urlGenerator, $catalogService): array {
				$name = CatalogService::DEFAULT_APP_DISPLAY_NAME;
				try {
					$name = $catalogService->getAppDisplayName();
				} catch (\Throwable) {
				}

				return [
					'id' => Application::APP_ID,
					'order' => 60,
					'type' => 'link',
					'href' => $urlGenerator->linkToRoute(Application::APP_ID . '.page.open'),
					'icon' => $urlGenerator->imagePath(Application::APP_ID, 'app.svg'),
					'name' => $name,
				];
			});
		});
	}
}