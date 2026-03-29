import type { StatusOption } from '@/types'

const dateTimeFormatter = new Intl.DateTimeFormat('es-ES', {
	day: '2-digit',
	month: '2-digit',
	year: 'numeric',
	hour: '2-digit',
	minute: '2-digit',
	hour12: false,
})

export function formatDateTime(timestamp?: number | null, emptyLabel = 'Sin fecha') {
	if (!timestamp) {
		return emptyLabel
	}

	return dateTimeFormatter.format(new Date(timestamp * 1000))
}

export function getStatusLabel(statusId: string | null | undefined, statuses: StatusOption[] | null | undefined) {
	if (!statusId) {
		return ''
	}

	const match = (statuses ?? []).find((status: StatusOption) => status.id === statusId)
	return match?.label ?? statusId
}