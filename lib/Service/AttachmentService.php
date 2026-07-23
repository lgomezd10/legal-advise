<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Service;

use OCA\ConsultasLegales\Db\Attachment;
use OCA\ConsultasLegales\Db\AttachmentMapper;
use OCP\Files\IAppData;

class AttachmentService {
	public function __construct(
		private readonly AttachmentMapper $attachmentMapper,
		private readonly CatalogService $catalogService,
		private readonly IAppData $appData,
	) {
	}

	public function create(int $ticketId, string $uid, array $uploadedFile, ?int $commentId = null): array {
		$this->assertAllowedExtension($uploadedFile);
		$this->assertAllowedFileSize($uploadedFile);

		$folder = $this->getTicketFolder($ticketId);
		$storedName = uniqid('att_', true) . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', (string) $uploadedFile['name']);
		$content = file_get_contents((string) $uploadedFile['tmp_name']);

		$file = $folder->newFile($storedName);
		$file->putContent($content === false ? '' : $content);

		$attachment = new Attachment();
		$attachment->setTicketId($ticketId);
		$attachment->setCommentId($commentId);
		$attachment->setUploadedBy($uid);
		$attachment->setOriginalName((string) $uploadedFile['name']);
		$attachment->setStoredName($storedName);
		$attachment->setMimeType((string) ($uploadedFile['type'] ?? 'application/octet-stream'));
		$attachment->setSize((int) ($uploadedFile['size'] ?? 0));
		$attachment->setSourceUrl(null);
		$attachment->setCreatedAt(time());

		return $this->attachmentMapper->insert($attachment)->jsonSerialize();
	}

	public function createFromUrl(int $ticketId, string $uid, string $sourceUrl, ?string $originalName = null, ?int $commentId = null): array {
		$normalizedUrl = trim($sourceUrl);
		if ($normalizedUrl === '' || filter_var($normalizedUrl, FILTER_VALIDATE_URL) === false) {
			throw new \InvalidArgumentException('La ruta URL del adjunto no es valida.');
		}

		$attachment = new Attachment();
		$attachment->setTicketId($ticketId);
		$attachment->setCommentId($commentId);
		$attachment->setUploadedBy($uid);
		$attachment->setOriginalName(trim((string) $originalName) !== '' ? trim((string) $originalName) : $normalizedUrl);
		$attachment->setStoredName('');
		$attachment->setMimeType('text/uri-list');
		$attachment->setSize(0);
		$attachment->setSourceUrl($normalizedUrl);
		$attachment->setCreatedAt(time());

		return $this->attachmentMapper->insert($attachment)->jsonSerialize();
	}

	public function download(int $attachmentId): array {
		try {
			$attachment = $this->attachmentMapper->find($attachmentId);
			$folder = $this->getOrCreateFolder('attachments')->getFolder((string) $attachment->getTicketId());
			$file = $folder->getFile($attachment->getStoredName());

			return [
				'meta' => $attachment->jsonSerialize(),
				'content' => base64_encode($file->getContent()),
			];
		} catch (\RuntimeException $exception) {
			$status = (int) $exception->getCode();
			if ($status >= 400 && $status <= 599) {
				throw $exception;
			}

			throw new \RuntimeException('No se pudo descargar el adjunto en este momento.', 503);
		} catch (\Throwable) {
			throw new \RuntimeException('No se pudo descargar el adjunto en este momento.', 503);
		}
	}

	/**
	 * @param array<int, array<string, mixed>> $attachments
	 */
	public function downloadArchive(array $attachments, string $ticketNumber): array {
		if ($attachments === []) {
			throw new \InvalidArgumentException('No hay archivos disponibles para descargar.');
		}

		if (!class_exists(\ZipArchive::class)) {
			throw new \RuntimeException('La descarga de adjuntos en ZIP no está disponible en este momento.', 503);
		}

		$temporaryFile = tempnam(sys_get_temp_dir(), 'legal-advice-attachments-');
		if ($temporaryFile === false) {
			throw new \RuntimeException('No se pudo preparar la descarga de adjuntos.', 503);
		}

		try {
			$archive = new \ZipArchive();
			if ($archive->open($temporaryFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
				throw new \RuntimeException('No se pudo preparar la descarga de adjuntos.', 503);
			}

			$usedNames = [];
			foreach ($attachments as $attachment) {
				$storedName = trim((string) ($attachment['storedName'] ?? ''));
				$attachmentTicketId = (int) ($attachment['ticketId'] ?? 0);
				if ($storedName === '' || $attachmentTicketId <= 0) {
					continue;
				}

				$file = $this->getOrCreateFolder('attachments')->getFolder((string) $attachmentTicketId)->getFile($storedName);
				$entryName = $this->uniqueArchiveEntryName((string) ($attachment['originalName'] ?? 'adjunto'), $usedNames);
				if ($archive->addFromString($entryName, $file->getContent()) === false) {
					throw new \RuntimeException('No se pudo añadir un adjunto al archivo ZIP.', 503);
				}
			}

			$archive->close();
			$content = file_get_contents($temporaryFile);
			if ($content === false) {
				throw new \RuntimeException('No se pudo preparar la descarga de adjuntos.', 503);
			}

			$safeTicketNumber = preg_replace('/[^A-Za-z0-9_.-]/', '_', $ticketNumber) ?: 'ticket';
			return [
				'filename' => "adjuntos-{$safeTicketNumber}.zip",
				'mimeType' => 'application/zip',
				'content' => base64_encode($content),
			];
		} catch (\RuntimeException $exception) {
			$status = (int) $exception->getCode();
			if ($status >= 400 && $status <= 599) {
				throw $exception;
			}

			throw new \RuntimeException('No se pudo descargar los adjuntos en este momento.', 503);
		} catch (\Throwable) {
			throw new \RuntimeException('No se pudo descargar los adjuntos en este momento.', 503);
		} finally {
			@unlink($temporaryFile);
		}
	}

	public function listForTicket(int $ticketId): array {
		return array_map(static fn ($row) => $row->jsonSerialize(), $this->attachmentMapper->findBy('ticket_id', $ticketId, 'created_at', 'ASC'));
	}

	public function hasForTicket(int $ticketId): bool {
		return $this->attachmentMapper->findBy('ticket_id', $ticketId, 'created_at', 'ASC') !== [];
	}

	public function deleteForComment(int $commentId): void {
		foreach ($this->attachmentMapper->findBy('comment_id', $commentId, 'created_at', 'ASC') as $attachment) {
			if ($attachment instanceof Attachment) {
				$this->deleteAttachmentEntity($attachment);
			}
		}
	}

	public function deleteForTicket(int $ticketId): void {
		foreach ($this->attachmentMapper->findBy('ticket_id', $ticketId, 'created_at', 'ASC') as $attachment) {
			if ($attachment instanceof Attachment) {
				$this->deleteAttachmentEntity($attachment);
			}
		}

		try {
			$this->getOrCreateFolder('attachments')->getFolder((string) $ticketId)->delete();
		} catch (\Throwable) {
		}
	}

	private function getTicketFolder(int $ticketId) {
		$attachmentsFolder = $this->getOrCreateFolder('attachments');

		try {
			return $attachmentsFolder->getFolder((string) $ticketId);
		} catch (\Throwable) {
			return $attachmentsFolder->newFolder((string) $ticketId);
		}
	}

	private function assertAllowedExtension(array $uploadedFile): void {
		$originalName = trim((string) ($uploadedFile['name'] ?? ''));
		if ($originalName === '') {
			throw new \InvalidArgumentException('El adjunto no es valido.');
		}

		$extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
		$allowedExtensions = $this->catalogService->getAllowedAttachmentExtensions();

		if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
			throw new \InvalidArgumentException('La extension del archivo no esta permitida.');
		}
	}

	private function assertAllowedFileSize(array $uploadedFile): void {
		$size = (int) ($uploadedFile['size'] ?? 0);
		$maxSize = $this->catalogService->getMaxAttachmentFileSizeBytes();
		if ($size > $maxSize) {
			throw new \InvalidArgumentException('El archivo supera el tamano maximo permitido. Para videos grandes, adjunta una ruta URL.');
		}
	}

	private function getOrCreateFolder(string $name) {
		try {
			return $this->appData->getFolder($name);
		} catch (\Throwable) {
			return $this->appData->newFolder($name);
		}
	}

	/**
	 * @param array<string, int> $usedNames
	 */
	private function uniqueArchiveEntryName(string $originalName, array &$usedNames): string {
		$baseName = trim(basename($originalName));
		$baseName = $baseName === '' ? 'adjunto' : $baseName;
		if (!isset($usedNames[$baseName])) {
			$usedNames[$baseName] = 1;
			return $baseName;
		}

		$usedNames[$baseName]++;
		$extension = pathinfo($baseName, PATHINFO_EXTENSION);
		$name = pathinfo($baseName, PATHINFO_FILENAME);
		return $name . ' (' . $usedNames[$baseName] . ')' . ($extension !== '' ? '.' . $extension : '');
	}

	private function deleteAttachmentEntity(Attachment $attachment): void {
		$storedName = trim((string) $attachment->getStoredName());
		if ($storedName !== '') {
			try {
				$this->getTicketFolder((int) $attachment->getTicketId())->getFile($storedName)->delete();
			} catch (\Throwable) {
			}
		}

		$this->attachmentMapper->delete($attachment);
	}
}