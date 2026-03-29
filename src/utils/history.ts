import type { AssignableOption, StatusOption, TicketHistoryEntry, UrgencyCatalogItem } from '@/types'
import { getStatusLabel } from './formatting'

type HistoryContext = {
	statuses: StatusOption[]
	users: AssignableOption[]
	groups: AssignableOption[]
	urgencies: UrgencyCatalogItem[]
}

export type FormattedHistoryEntry = {
	id: number
	actor: string
	title: string
	details: string[]
	createdAt: number
	visibility: 'interno' | 'publico'
}

function getUserLabel(userId: string | null | undefined, users: AssignableOption[]) {
	if (!userId) {
		return 'Sin usuario'
	}

	return users.find((item) => item.id === userId)?.displayName ?? userId
}

function getGroupLabel(groupId: string | null | undefined, groups: AssignableOption[]) {
	if (!groupId) {
		return 'Sin grupo'
	}

	return groups.find((item) => item.id === groupId)?.displayName ?? groupId
}

function getUrgencyLabel(urgencyId: unknown, urgencies: UrgencyCatalogItem[]) {
	const numericId = typeof urgencyId === 'number' ? urgencyId : Number(urgencyId ?? 0)
	if (!numericId) {
		return 'Sin criticidad'
	}

	return urgencies.find((item) => Number(item.id ?? 0) === numericId)?.name ?? `Criticidad ${numericId}`
}

function humanizeActor(entry: TicketHistoryEntry, users: AssignableOption[]) {
	if (entry.actorUid) {
		return getUserLabel(entry.actorUid, users)
	}

	return entry.actorRole === 'soporte' ? 'Soporte' : 'Sistema'
}

function buildUpdateDetails(entry: TicketHistoryEntry, context: HistoryContext) {
	const payload = entry.payload ?? {}
	const details: string[] = []

	if (Object.prototype.hasOwnProperty.call(payload, 'status')) {
		details.push(`Estado: ${getStatusLabel(String(payload.status ?? ''), context.statuses)}`)
	}

	if (Object.prototype.hasOwnProperty.call(payload, 'title')) {
		details.push(`Titulo: ${String(payload.title ?? '')}`)
	}

	if (Object.prototype.hasOwnProperty.call(payload, 'urgencyId')) {
		details.push(`Criticidad: ${getUrgencyLabel(payload.urgencyId, context.urgencies)}`)
	}

	if (Object.prototype.hasOwnProperty.call(payload, 'assignedUserUid') || Object.prototype.hasOwnProperty.call(payload, 'assignedGroupId')) {
		details.push(`Asignacion: ${getUserLabel(payload.assignedUserUid as string | null | undefined, context.users)} / ${getGroupLabel(payload.assignedGroupId as string | null | undefined, context.groups)}`)
	}

	if (Object.prototype.hasOwnProperty.call(payload, 'supportDescription')) {
		details.push('Descripcion de soporte actualizada')
	}

	if (Object.prototype.hasOwnProperty.call(payload, 'userDescription')) {
		details.push('Descripcion del ticket actualizada')
	}

	return details.length > 0 ? details : ['Ticket actualizado']
}

export function formatHistoryEntries(entries: TicketHistoryEntry[], context: HistoryContext): FormattedHistoryEntry[] {
	return entries.map((entry) => {
		const actor = humanizeActor(entry, context.users)

		switch (entry.eventType) {
			case 'ticket_created':
				return {
					id: entry.id,
					actor,
					title: 'Ticket creado',
					details: entry.payload?.status ? [`Estado inicial: ${getStatusLabel(String(entry.payload.status), context.statuses)}`] : [],
					createdAt: entry.createdAt,
					visibility: entry.visibility,
				}
			case 'comment_added':
				return {
					id: entry.id,
					actor,
					title: 'Comentario creado',
					details: [],
					createdAt: entry.createdAt,
					visibility: entry.visibility,
				}
			case 'attachment_added':
				return {
					id: entry.id,
					actor,
					title: 'Adjunto añadido',
					details: [],
					createdAt: entry.createdAt,
					visibility: entry.visibility,
				}
			case 'ticket_updated':
				return {
					id: entry.id,
					actor,
					title: 'Ticket actualizado',
					details: buildUpdateDetails(entry, context),
					createdAt: entry.createdAt,
					visibility: entry.visibility,
				}
			default:
				return {
					id: entry.id,
					actor,
					title: entry.eventType.replace(/_/g, ' '),
					details: [],
					createdAt: entry.createdAt,
					visibility: entry.visibility,
				}
		}
	})
}