<?php

declare(strict_types=1);

namespace Doctrine\DBAL {
	if (!class_exists(ParameterType::class)) {
		final class ParameterType {
			public const NULL = 0;
			public const INTEGER = 1;
			public const STRING = 2;
			public const LARGE_OBJECT = 3;
		}
	}

	if (!class_exists(ArrayParameterType::class)) {
		final class ArrayParameterType {
			public const INTEGER = 101;
			public const STRING = 102;
		}
	}

	if (!class_exists(Connection::class)) {
		class Connection {
		}
	}
}

namespace Doctrine\DBAL\Types {
	if (!class_exists(Types::class)) {
		final class Types {
			public const BOOLEAN = 'boolean';
		}
	}
}

namespace {
	require_once __DIR__ . '/../vendor/autoload.php';

	spl_autoload_register(static function (string $class): void {
		if (!str_starts_with($class, 'OCP\\')) {
			return;
		}

		$file = __DIR__ . '/../vendor/nextcloud/ocp/' . str_replace('\\', '/', $class) . '.php';
		if (is_file($file)) {
			require_once $file;
		}
	});
}