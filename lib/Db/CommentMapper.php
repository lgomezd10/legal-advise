<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Db;

use OCP\IDBConnection;

class CommentMapper extends AbstractMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'tk_comments', Comment::class);
	}
}