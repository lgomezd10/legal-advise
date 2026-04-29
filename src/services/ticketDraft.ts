import type { Ticket, TicketDraft, TypeNode, UrgencyCatalogItem } from '@/types'

type TicketDataRow = {
	fieldKey?: string
	fieldValue?: string
}

export function getDefaultUrgencyId(urgencies: UrgencyCatalogItem[]): string | null {
	const activeUrgencies = urgencies
		.filter((urgency) => urgency.active)
		.slice()
		.sort((left, right) => left.weight - right.weight)

	return activeUrgencies[0]?.id ? String(activeUrgencies[0].id) : null
}

export function createDefaultTicketDraft(personalConfig: Record<string, string>, urgencies: UrgencyCatalogItem[]): TicketDraft {
	return {
		selectedPath: [],
		typeId: null,
		province: personalConfig.province?.trim() || null,
		title: '',
		userDescription: '',
		urgencyId: getDefaultUrgencyId(urgencies),
		personalData: { ...personalConfig },
		attachments: { files: [], links: [] },
	}
}

export function getTypePath(types: TypeNode[], targetTypeId?: number | null): number[] {
	if (!targetTypeId) {
		return []
	}

	for (const type of types) {
		const currentPath = findTypePath(type, targetTypeId, [])
		if (currentPath.length > 0) {
			return currentPath
		}
	}

	return []
}

function findTypePath(type: TypeNode, targetTypeId: number, path: number[]): number[] {
	const nextPath = [...path, type.id]
	if (type.id === targetTypeId) {
		return nextPath
	}

	for (const child of type.children) {
		const match = findTypePath(child, targetTypeId, nextPath)
		if (match.length > 0) {
			return match
		}
	}

	return []
}

export function getTypeLabelsForPath(types: TypeNode[], path: number[]): string[] {
	const labels: string[] = []
	let currentLevel = types

	for (const typeId of path) {
		const current = currentLevel.find((item) => item.id === typeId)
		if (!current) {
			break
		}

		labels.push(current.name)
		currentLevel = current.children
	}

	return labels
}

export function getTypeLabel(types: TypeNode[], targetTypeId?: number | null, separator = ' > '): string {
	const path = getTypePath(types, targetTypeId)
	return getTypeLabelsForPath(types, path).join(separator)
}

export function getTicketPersonalDataRecord(ticket: Ticket | null | undefined): Record<string, string> {
	const rows = Array.isArray(ticket?.personalData) ? ticket?.personalData as TicketDataRow[] : []

	return rows.reduce<Record<string, string>>((result, row) => {
		if (row.fieldKey) {
			result[row.fieldKey] = String(row.fieldValue ?? '')
		}
		return result
	}, {})
}

export function createRepeatTicketDraft(ticket: Ticket, types: TypeNode[], personalConfig: Record<string, string>, urgencies: UrgencyCatalogItem[]): TicketDraft {
	const ticketPersonalData = getTicketPersonalDataRecord(ticket)
	const selectedPath = getTypePath(types, ticket.typeId ?? null)

	return {
		selectedPath,
		typeId: ticket.typeId ?? null,
		province: ticket.province ?? null,
		title: ticket.title,
		userDescription: ticket.userDescription,
		urgencyId: ticket.urgencyId ? String(ticket.urgencyId) : getDefaultUrgencyId(urgencies),
		personalData: {
			...personalConfig,
			...ticketPersonalData,
		},
		attachments: { files: [], links: [] },
	}
}