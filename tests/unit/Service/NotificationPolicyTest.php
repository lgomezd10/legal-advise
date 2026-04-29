<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Service;

use OCA\ConsultasLegales\Notification\NotificationPolicy;
use OCA\ConsultasLegales\Service\RoleService;
use PHPUnit\Framework\TestCase;

class NotificationPolicyTest extends TestCase {
	public function testUserProfileDoesNotIncludeGroupAssignmentEvent(): void {
		self::assertSame(
			[
				'ticket_created',
				'ticket_waiting_for_creator',
				'ticket_status_changed',
				'ticket_resolved',
				'ticket_public_reply',
			],
			NotificationPolicy::getNotificationEventsForProfile(RoleService::USER),
		);
	}

	public function testSupportAndAdminHaveTicketAssignedEnabledOnBothChannels(): void {
		foreach ([RoleService::SUPPORT, RoleService::ADMIN] as $profile) {
			self::assertTrue(NotificationPolicy::getDefaultChannelEnabledForProfile($profile, 'ticket_assigned', NotificationPolicy::CHANNEL_NEXTCLOUD));
			self::assertTrue(NotificationPolicy::getDefaultChannelEnabledForProfile($profile, 'ticket_assigned', NotificationPolicy::CHANNEL_MAIL));
			self::assertSame(
				NotificationPolicy::DELIVERY_BOTH,
				NotificationPolicy::defaultDeliveryModeForProfile($profile, 'ticket_assigned'),
			);
		}
	}

	public function testSupportAndAdminHaveGroupAssignmentEnabledOnBothChannels(): void {
		foreach ([RoleService::SUPPORT, RoleService::ADMIN] as $profile) {
			self::assertTrue(NotificationPolicy::getDefaultChannelEnabledForProfile($profile, 'ticket_group_assigned', NotificationPolicy::CHANNEL_NEXTCLOUD));
			self::assertTrue(NotificationPolicy::getDefaultChannelEnabledForProfile($profile, 'ticket_group_assigned', NotificationPolicy::CHANNEL_MAIL));
			self::assertSame(
				NotificationPolicy::DELIVERY_BOTH,
				NotificationPolicy::defaultDeliveryModeForProfile($profile, 'ticket_group_assigned'),
			);
		}
	}

	public function testSupportAndAdminHaveUnassignedCreatedEnabledOnBothChannelsButPersonalDefaultNextcloud(): void {
		foreach ([RoleService::SUPPORT, RoleService::ADMIN] as $profile) {
			self::assertTrue(NotificationPolicy::getDefaultChannelEnabledForProfile($profile, 'ticket_unassigned_created', NotificationPolicy::CHANNEL_NEXTCLOUD));
			self::assertTrue(NotificationPolicy::getDefaultChannelEnabledForProfile($profile, 'ticket_unassigned_created', NotificationPolicy::CHANNEL_MAIL));
			self::assertSame(
				NotificationPolicy::DELIVERY_BOTH,
				NotificationPolicy::defaultDeliveryModeForProfile($profile, 'ticket_unassigned_created'),
			);
			self::assertSame(
				NotificationPolicy::DELIVERY_NEXTCLOUD,
				NotificationPolicy::resolveDefaultUserDeliveryModeForRoles([$profile], 'ticket_unassigned_created', true, true),
			);
		}
	}

	public function testUserRoleSetFiltersSupportedEventsForPreferences(): void {
		self::assertSame(
			[
				'ticket_created',
				'ticket_waiting_for_creator',
				'ticket_status_changed',
				'ticket_resolved',
				'ticket_public_reply',
			],
			NotificationPolicy::getSupportedEventsForRoles([RoleService::USER]),
		);

		self::assertContains('ticket_group_assigned', NotificationPolicy::getSupportedEventsForRoles([RoleService::SUPPORT]));
		self::assertContains('ticket_unassigned_created', NotificationPolicy::getSupportedEventsForRoles([RoleService::SUPPORT]));
	}

	public function testUserHasWaitingForCreatorEnabledOnBothChannels(): void {
		self::assertTrue(NotificationPolicy::getDefaultChannelEnabledForProfile(RoleService::USER, 'ticket_waiting_for_creator', NotificationPolicy::CHANNEL_NEXTCLOUD));
		self::assertTrue(NotificationPolicy::getDefaultChannelEnabledForProfile(RoleService::USER, 'ticket_waiting_for_creator', NotificationPolicy::CHANNEL_MAIL));
		self::assertSame(
			NotificationPolicy::DELIVERY_BOTH,
			NotificationPolicy::defaultDeliveryModeForProfile(RoleService::USER, 'ticket_waiting_for_creator'),
		);
	}

	public function testNormalizeDeliveryModeFallsBackToBoth(): void {
		self::assertSame(NotificationPolicy::DELIVERY_BOTH, NotificationPolicy::normalizeDeliveryMode('desconocido'));
		self::assertSame(NotificationPolicy::DELIVERY_MAIL, NotificationPolicy::normalizeDeliveryMode(NotificationPolicy::DELIVERY_MAIL));
	}

	public function testAdminDeliveryModeNormalizesMailOnlyToNextcloud(): void {
		self::assertSame(NotificationPolicy::DELIVERY_NEXTCLOUD, NotificationPolicy::normalizeAdminDeliveryMode(NotificationPolicy::DELIVERY_MAIL));
		self::assertSame(NotificationPolicy::DELIVERY_BOTH, NotificationPolicy::normalizeAdminDeliveryMode(NotificationPolicy::DELIVERY_BOTH));
	}

	public function testUserDeliveryModeOnlyAllowsNextcloudOrBoth(): void {
		self::assertSame(NotificationPolicy::DELIVERY_NEXTCLOUD, NotificationPolicy::normalizeUserDeliveryMode(NotificationPolicy::DELIVERY_MAIL));
		self::assertSame(NotificationPolicy::DELIVERY_NEXTCLOUD, NotificationPolicy::normalizeUserDeliveryMode(NotificationPolicy::DELIVERY_NONE));
		self::assertSame(NotificationPolicy::DELIVERY_BOTH, NotificationPolicy::normalizeUserDeliveryMode(NotificationPolicy::DELIVERY_BOTH));
	}

	public function testPersonalDeliveryModeAllowsNoneOnlyForSupportAndAdmin(): void {
		self::assertSame(NotificationPolicy::DELIVERY_NONE, NotificationPolicy::normalizePersonalDeliveryModeForRoles([RoleService::SUPPORT], NotificationPolicy::DELIVERY_NONE));
		self::assertSame(NotificationPolicy::DELIVERY_NONE, NotificationPolicy::resolvePersonalDeliveryModeForRoles([RoleService::ADMIN], false, false));
		self::assertSame(NotificationPolicy::DELIVERY_NEXTCLOUD, NotificationPolicy::normalizePersonalDeliveryModeForRoles([RoleService::USER], NotificationPolicy::DELIVERY_NONE));
		self::assertSame(NotificationPolicy::DELIVERY_NEXTCLOUD, NotificationPolicy::resolvePersonalDeliveryModeForRoles([RoleService::USER], false, false));
	}
}