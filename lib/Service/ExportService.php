<?php

declare(strict_types=1);

namespace OCA\ConsultasLegales\Service;

class ExportService {
	public function __construct(private readonly TicketService $ticketService) {
	}

	public function exportTickets(string $uid, array $criteria, bool $supportScope, array $columns = []): array {
		$rows = $this->ticketService->list($uid, $criteria, $supportScope);
		$selectedColumns = $this->normalizeColumns($columns);
		$handle = fopen('php://temp', 'r+');
		fputcsv($handle, array_map(fn (string $column) => $this->columnLabel($column), $selectedColumns));

		foreach ($rows as $row) {
			fputcsv($handle, array_map(fn (string $column) => $this->columnValue($column, $row), $selectedColumns));
		}

		rewind($handle);
		$content = stream_get_contents($handle) ?: '';
		fclose($handle);

		return [
			'filename' => 'tickets-' . date('Ymd-His') . '.csv',
			'mimeType' => 'text/csv',
			'content' => base64_encode($content),
		];
	}

	private function normalizeColumns(array $columns): array {
		$allowed = ['number', 'createdBy', 'title', 'userDescription', 'assignment', 'status', 'urgency', 'createdAt', 'updatedAt'];
		$selected = array_values(array_filter($columns, static fn (string $column) => in_array($column, $allowed, true)));

		return $selected !== [] ? $selected : ['number', 'createdBy', 'title', 'userDescription', 'assignment'];
	}

	private function columnLabel(string $column): string {
		return match ($column) {
			'number' => 'numero_ticket',
			'createdBy' => 'creado_por',
			'title' => 'titulo',
			'userDescription' => 'descripcion',
			'assignment' => 'asignacion',
			'status' => 'estado',
			'urgency' => 'urgencia',
			'createdAt' => 'fecha_apertura',
			'updatedAt' => 'fecha_ultima_edicion',
			default => $column,
		};
	}

	private function columnValue(string $column, array $row): string {
		return match ($column) {
			'number' => (string) ($row['number'] ?? ''),
			'createdBy' => (string) ($row['creatorUid'] ?? ''),
			'title' => (string) ($row['title'] ?? ''),
			'userDescription' => (string) ($row['userDescription'] ?? ''),
			'assignment' => $this->formatAssignment($row),
			'status' => (string) ($row['status'] ?? ''),
			'urgency' => isset($row['urgencyId']) && $row['urgencyId'] !== null ? (string) $row['urgencyId'] : '',
			'createdAt' => date('c', (int) ($row['createdAt'] ?? time())),
			'updatedAt' => date('c', (int) ($row['updatedAt'] ?? time())),
			default => '',
		};
	}

	private function formatAssignment(array $row): string {
		$parts = [];
		if (!empty($row['assignedUserUid'])) {
			$parts[] = (string) $row['assignedUserUid'];
		}
		if (!empty($row['assignedGroupId'])) {
			$parts[] = 'Grupo ' . (string) $row['assignedGroupId'];
		}

		return $parts !== [] ? implode(' / ', $parts) : 'Sin asignar';
	}
}