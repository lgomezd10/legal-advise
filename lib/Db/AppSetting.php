<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Db;

class AppSetting extends AbstractEntity {
	protected $configKey;
	protected $configValue;

	public function __construct() {
		$this->addType('configValue', 'json');
	}

	public function setConfigValue(mixed $value): self {
		$this->markFieldUpdated('configValue');
		$this->configValue = $value;
		return $this;
	}

	public function getConfigValue(): mixed {
		return $this->configValue;
	}
}