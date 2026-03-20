<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Db;

use OCP\AppFramework\Db\Entity;

abstract class AbstractEntity extends Entity implements \JsonSerializable {
	public function toArray(): array {
		return get_object_vars($this);
	}

	public function jsonSerialize(): array {
		return $this->toArray();
	}
}