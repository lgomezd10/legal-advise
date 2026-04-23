<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Integration\Controller;

use PHPUnit\Framework\TestCase;

class CleanStackSupportPermissionsTest extends TestCase {
	private const BASE_URL = 'http://localhost:8090';
	private const ADMIN_USER = 'adminqa';
	private const ADMIN_PASSWORD = 'TickAdmin!2025';
	private const SUPPORT_USER = 'soporte1';
	private const SUPPORT_PASSWORD = 'TickSupp1!2025';
	private const RRHH_USER = 'rrhh1';
	private const RRHH_PASSWORD = 'TickRrhh1!2025';

	public function testSupportUserCannotManageForeignGroupTicketInCleanStack(): void {
		if (!$this->isCleanStackAvailable()) {
			self::markTestSkipped('El stack limpio no esta disponible en http://localhost:8090.');
		}

		$candidate = $this->findRestorableCandidateTicket();
		if ($candidate === null) {
			self::markTestSkipped('No hay un ticket abierto y sin grupo asignado util para la prueba.');
		}

		$ticketId = (int) $candidate['id'];
		$wasAssignedToRrhh = false;

		try {
			$supportBefore = $this->requestJson('GET', '/ocs/v2.php/apps/legal_advice/api/v1/tickets/' . $ticketId . '?format=json', null, self::SUPPORT_USER, self::SUPPORT_PASSWORD);
			self::assertSame(200, $supportBefore['statusCode']);
			self::assertTrue((bool) ($supportBefore['body']['ocs']['data']['canManage'] ?? false));

			$forbiddenAssignment = $this->requestJson(
				'PUT',
				'/ocs/v2.php/apps/legal_advice/api/v1/tickets/' . $ticketId . '?format=json',
				['assignedGroupId' => 'rrhh'],
				self::SUPPORT_USER,
				self::SUPPORT_PASSWORD,
			);

			self::assertSame(403, $forbiddenAssignment['statusCode']);
			self::assertSame('failure', $forbiddenAssignment['body']['ocs']['meta']['status'] ?? null);
			self::assertSame(403, $forbiddenAssignment['body']['ocs']['meta']['statuscode'] ?? null);

			$assigned = $this->requestJson(
				'PUT',
				'/ocs/v2.php/apps/legal_advice/api/v1/tickets/' . $ticketId . '?format=json',
				['assignedGroupId' => 'rrhh'],
				self::ADMIN_USER,
				self::ADMIN_PASSWORD,
			);

			self::assertSame(200, $assigned['statusCode']);
			self::assertSame('rrhh', $assigned['body']['ocs']['data']['assignedGroupId'] ?? null);
			$wasAssignedToRrhh = true;

			$supportAfter = $this->requestJson('GET', '/ocs/v2.php/apps/legal_advice/api/v1/tickets/' . $ticketId . '?format=json', null, self::SUPPORT_USER, self::SUPPORT_PASSWORD);
			self::assertSame(200, $supportAfter['statusCode']);
			self::assertFalse((bool) ($supportAfter['body']['ocs']['data']['canManage'] ?? true));

			$rrhhAfter = $this->requestJson('GET', '/ocs/v2.php/apps/legal_advice/api/v1/tickets/' . $ticketId . '?format=json', null, self::RRHH_USER, self::RRHH_PASSWORD);
			self::assertSame(200, $rrhhAfter['statusCode']);
			self::assertTrue((bool) ($rrhhAfter['body']['ocs']['data']['canManage'] ?? false));
		} finally {
			if ($wasAssignedToRrhh) {
				$restored = $this->requestJson(
					'PUT',
					'/ocs/v2.php/apps/legal_advice/api/v1/tickets/' . $ticketId . '?format=json',
					['assignedGroupId' => ''],
					self::ADMIN_USER,
					self::ADMIN_PASSWORD,
				);

				self::assertSame(200, $restored['statusCode']);
				self::assertSame('', (string) ($restored['body']['ocs']['data']['assignedGroupId'] ?? ''));
				self::assertSame('nuevo', $restored['body']['ocs']['data']['status'] ?? null);
			}
		}
	}

	private function isCleanStackAvailable(): bool {
		$response = $this->requestRaw('GET', '/status.php');
		if ($response['statusCode'] !== 200) {
			return false;
		}

		$payload = json_decode($response['body'], true);
		return is_array($payload) && ($payload['installed'] ?? false) === true;
	}

	private function findRestorableCandidateTicket(): ?array {
		$response = $this->requestJson(
			'GET',
			'/ocs/v2.php/apps/legal_advice/api/v1/tickets?scope=support&format=json',
			null,
			self::ADMIN_USER,
			self::ADMIN_PASSWORD,
		);

		if ($response['statusCode'] !== 200) {
			return null;
		}

		$items = $response['body']['ocs']['data']['items'] ?? [];
		if (!is_array($items)) {
			return null;
		}

		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$assignedGroupId = is_string($item['assignedGroupId'] ?? null)
				? trim((string) $item['assignedGroupId'])
				: null;
			if ($assignedGroupId !== null && $assignedGroupId !== '') {
				continue;
			}

			$assignedUserUid = is_string($item['assignedUserUid'] ?? null)
				? trim((string) $item['assignedUserUid'])
				: null;
			if ($assignedUserUid !== null && $assignedUserUid !== '') {
				continue;
			}

			if (($item['status'] ?? null) !== 'nuevo') {
				continue;
			}

			return $item;
		}

		return null;
	}

	/**
	 * @return array{statusCode:int, body:array<string, mixed>}
	 */
	private function requestJson(string $method, string $path, ?array $formData, string $user, string $password): array {
		$response = $this->requestRaw($method, $path, $formData, $user, $password);
		$body = json_decode($response['body'], true);
		self::assertIsArray($body, 'La respuesta no contiene JSON valido.');

		return [
			'statusCode' => $response['statusCode'],
			'body' => $body,
		];
	}

	/**
	 * @return array{statusCode:int, body:string}
	 */
	private function requestRaw(string $method, string $path, ?array $formData = null, ?string $user = null, ?string $password = null): array {
		$headers = [
			'Accept: application/json',
			'OCS-APIRequest: true',
		];

		if ($user !== null && $password !== null) {
			$headers[] = 'Authorization: Basic ' . base64_encode($user . ':' . $password);
		}

		$content = null;
		if ($formData !== null) {
			$headers[] = 'Content-Type: application/x-www-form-urlencoded';
			$content = http_build_query($formData, '', '&', PHP_QUERY_RFC3986);
		}

		$context = stream_context_create([
			'http' => [
				'method' => $method,
				'header' => implode("\r\n", $headers),
				'content' => $content,
				'ignore_errors' => true,
				'timeout' => 15,
			],
		]);

		$body = @file_get_contents(self::BASE_URL . $path, false, $context);
		$statusCode = $this->extractStatusCode($http_response_header ?? []);

		return [
			'statusCode' => $statusCode,
			'body' => is_string($body) ? $body : '',
		];
	}

	/**
	 * @param list<string> $headers
	 */
	private function extractStatusCode(array $headers): int {
		foreach ($headers as $header) {
			if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', $header, $matches) === 1) {
				return (int) $matches[1];
			}
		}

		return 0;
	}
}