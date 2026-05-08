<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Notification;

use OCA\ConsultasLegales\Service\RoleService;

class NotificationPolicy {
	public const CHANNEL_NEXTCLOUD = 'nextcloud';
	public const CHANNEL_MAIL = 'mail';
	public const DELIVERY_NONE = 'none';
	public const DELIVERY_NEXTCLOUD = 'nextcloud';
	public const DELIVERY_MAIL = 'mail';
	public const DELIVERY_BOTH = 'both';

	private const SUPPORTED_EVENTS = [
		'ticket_created',
		'ticket_unassigned_created',
		'ticket_assigned',
		'ticket_waiting_for_creator',
		'ticket_group_assigned',
		'ticket_status_changed',
		'ticket_resolved',
		'ticket_public_reply',
	];

	public static function getSupportedEvents(): array {
		return self::SUPPORTED_EVENTS;
	}

	public static function getSupportedEventsForRoles(array $roles): array {
		if (!in_array(RoleService::SUPPORT, $roles, true) && !in_array(RoleService::ADMIN, $roles, true)) {
			return array_values(array_filter(
				self::SUPPORTED_EVENTS,
				static fn (string $eventName): bool => !in_array($eventName, ['ticket_unassigned_created', 'ticket_group_assigned', 'ticket_assigned'], true),
			));
		}

		return self::SUPPORTED_EVENTS;
	}

	public static function getNotificationEventsForProfile(string $profile): array {
		if (in_array($profile, [RoleService::SUPPORT, RoleService::ADMIN], true)) {
			return self::SUPPORTED_EVENTS;
		}

		return array_values(array_filter(
			self::SUPPORTED_EVENTS,
			static fn (string $eventName): bool => !in_array($eventName, ['ticket_unassigned_created', 'ticket_group_assigned', 'ticket_assigned'], true),
		));
	}

	public static function resolveDefaultUserDeliveryModeForRoles(array $roles, string $eventName, bool $nextcloudEnabled, bool $mailEnabled): ?string {
		if (!self::isUserConfigurable($nextcloudEnabled, $mailEnabled)) {
			return null;
		}

		if ($eventName === 'ticket_unassigned_created'
			&& (in_array(RoleService::SUPPORT, $roles, true) || in_array(RoleService::ADMIN, $roles, true))) {
			return self::DELIVERY_NEXTCLOUD;
		}

		return self::resolveUserDeliveryMode($nextcloudEnabled, $mailEnabled);
	}

	public static function normalizeDeliveryMode(mixed $value): string {
		$mode = trim((string) $value);
		if (in_array($mode, [self::DELIVERY_NONE, self::DELIVERY_NEXTCLOUD, self::DELIVERY_MAIL, self::DELIVERY_BOTH], true)) {
			return $mode;
		}

		return self::DELIVERY_BOTH;
	}

	public static function normalizeAdminDeliveryMode(mixed $value): string {
		$mode = trim((string) $value);
		if (in_array($mode, [self::DELIVERY_NONE, self::DELIVERY_NEXTCLOUD, self::DELIVERY_BOTH], true)) {
			return $mode;
		}

		if ($mode === self::DELIVERY_MAIL) {
			return self::DELIVERY_NEXTCLOUD;
		}

		return self::DELIVERY_BOTH;
	}

	public static function normalizeUserDeliveryMode(mixed $value): string {
		$mode = trim((string) $value);
		if (in_array($mode, [self::DELIVERY_NEXTCLOUD, self::DELIVERY_BOTH], true)) {
			return $mode;
		}

		return self::DELIVERY_NEXTCLOUD;
	}

	public static function normalizePersonalDeliveryModeForRoles(array $roles, mixed $value): string {
		if (in_array(RoleService::SUPPORT, $roles, true) || in_array(RoleService::ADMIN, $roles, true)) {
			return self::normalizeAdminDeliveryMode($value);
		}

		return self::normalizeUserDeliveryMode($value);
	}

	public static function isUserConfigurable(bool $nextcloudEnabled, bool $mailEnabled): bool {
		return $nextcloudEnabled && $mailEnabled;
	}

	public static function resolveUserDeliveryMode(bool $nextcloudEnabled, bool $mailEnabled): string {
		return $nextcloudEnabled && $mailEnabled
			? self::DELIVERY_BOTH
			: self::DELIVERY_NEXTCLOUD;
	}

	public static function resolvePersonalDeliveryModeForRoles(array $roles, bool $nextcloudEnabled, bool $mailEnabled): string {
		if (in_array(RoleService::SUPPORT, $roles, true) || in_array(RoleService::ADMIN, $roles, true)) {
			return self::normalizeAdminDeliveryMode(self::resolveDeliveryMode($nextcloudEnabled, $mailEnabled));
		}

		return self::resolveUserDeliveryMode($nextcloudEnabled, $mailEnabled);
	}

	public static function resolveDeliveryMode(bool $nextcloudEnabled, bool $mailEnabled): string {
		if (!$nextcloudEnabled && !$mailEnabled) {
			return self::DELIVERY_NONE;
		}

		if ($nextcloudEnabled && $mailEnabled) {
			return self::DELIVERY_BOTH;
		}

		if ($mailEnabled) {
			return self::DELIVERY_MAIL;
		}

		return self::DELIVERY_NEXTCLOUD;
	}

	public static function defaultDeliveryModeForProfile(string $profile, string $eventName): string {
		return self::resolveDeliveryMode(
			self::getDefaultChannelEnabledForProfile($profile, $eventName, self::CHANNEL_NEXTCLOUD),
			self::getDefaultChannelEnabledForProfile($profile, $eventName, self::CHANNEL_MAIL),
		);
	}

	public static function getDefaultChannelEnabledForProfile(string $profile, string $eventName, string $channel): bool {
		if ($channel === self::CHANNEL_NEXTCLOUD) {
			return match ($profile) {
				RoleService::SUPPORT, RoleService::ADMIN => true,
				RoleService::USER => in_array($eventName, ['ticket_created', 'ticket_waiting_for_creator', 'ticket_resolved'], true),
				default => false,
			};
		}

		if ($channel !== self::CHANNEL_MAIL) {
			return false;
		}

		return match ($profile) {
			RoleService::SUPPORT, RoleService::ADMIN => true,
			RoleService::USER => in_array($eventName, ['ticket_created', 'ticket_waiting_for_creator', 'ticket_resolved'], true),
			default => false,
		};
	}
}