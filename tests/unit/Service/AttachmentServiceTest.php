<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Tests\Unit\Service;

use OCA\ConsultasLegales\Db\Attachment;
use OCA\ConsultasLegales\Db\AttachmentMapper;
use OCA\ConsultasLegales\Service\AttachmentService;
use OCA\ConsultasLegales\Service\CatalogService;
use OCP\Files\IAppData;
use PHPUnit\Framework\TestCase;

class AttachmentServiceTest extends TestCase {
	public function testDownloadConvertsStorageFailuresIntoHandledRuntimeException(): void {
		$attachment = new Attachment();
		$attachment->setId(9);
		$attachment->setTicketId(12);
		$attachment->setStoredName('broken.pdf');

		$attachmentMapper = $this->createMock(AttachmentMapper::class);
		$attachmentMapper->method('find')->with(9)->willReturn($attachment);

		$catalogService = $this->createMock(CatalogService::class);

		$appData = $this->createMock(IAppData::class);
		$appData->method('getFolder')->with('attachments')->willThrowException(new \RuntimeException('storage offline'));
		$appData->method('newFolder')->with('attachments')->willThrowException(new \RuntimeException('storage offline'));

		$service = new AttachmentService($attachmentMapper, $catalogService, $appData);

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionCode(503);
		$this->expectExceptionMessage('No se pudo descargar el adjunto en este momento.');

		$service->download(9);
	}
}