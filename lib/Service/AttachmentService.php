<?php

declare(strict_types=1);

namespace OCA\Gestion_incidencias\Service;

use OCA\Gestion_incidencias\Db\Attachment;
use OCA\Gestion_incidencias\Db\AttachmentMapper;
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
		$attachment->setCreatedAt(time());

		return $this->attachmentMapper->insert($attachment)->jsonSerialize();
	}

	public function download(int $attachmentId): array {
		$attachment = $this->attachmentMapper->find($attachmentId);
		$folder = $this->getOrCreateFolder('attachments')->getFolder((string) $attachment->getTicketId());
		$file = $folder->getFile($attachment->getStoredName());

		return [
			'meta' => $attachment->jsonSerialize(),
			'content' => base64_encode($file->getContent()),
		];
	}

	public function listForTicket(int $ticketId): array {
		return array_map(static fn ($row) => $row->jsonSerialize(), $this->attachmentMapper->findBy('ticket_id', $ticketId, 'created_at', 'ASC'));
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

	private function getOrCreateFolder(string $name) {
		try {
			return $this->appData->getFolder($name);
		} catch (\Throwable) {
			return $this->appData->newFolder($name);
		}
	}
}