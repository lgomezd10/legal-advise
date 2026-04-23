<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Db;

use OCP\DB\Types;

class Attachment extends AbstractEntity {
	protected $ticketId;
	protected $commentId;
	protected $uploadedBy;
	protected $originalName;
	protected $storedName;
	protected $mimeType;
	protected $size;
	protected $sourceUrl;
	protected $createdAt;

	public function __construct() {
		$this->addType('ticketId', Types::INTEGER);
		$this->addType('commentId', Types::INTEGER);
		$this->addType('size', Types::INTEGER);
		$this->addType('createdAt', Types::INTEGER);
	}
}