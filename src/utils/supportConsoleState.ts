import type { SupportColumnKey } from '@/types'

export type SupportSortDirection = 'asc' | 'desc'

export type SupportConsoleState = {
	visibleColumns: SupportColumnKey[]
	columnEditorOrder: SupportColumnKey[]
	criteria: Record<string, unknown>
	sortKey: SupportColumnKey | 'createdBy'
	sortDirection: SupportSortDirection
	selectedFilterId: number | null
}

const STORAGE_KEY = 'legal_advice:support_console_state'

const LEGACY_DEFAULT_SUPPORT_COLUMNS: SupportColumnKey[] = ['number', 'updatedAt', 'assignment', 'createdBy', 'title', 'userDescription']

export const DEFAULT_SUPPORT_COLUMNS: SupportColumnKey[] = ['updatedAt', 'province', 'title', 'status', 'userDescription']
export const DEFAULT_COLUMN_EDITOR_ORDER: SupportColumnKey[] = ['updatedAt', 'province', 'title', 'status', 'userDescription', 'number', 'assignment', 'createdBy', 'attachments', 'urgency', 'createdAt']
export const DEFAULT_SUPPORT_SORT: Pick<SupportConsoleState, 'sortKey' | 'sortDirection'> = {
	sortKey: 'updatedAt',
	sortDirection: 'desc',
}

const KNOWN_COLUMN_KEYS: SupportColumnKey[] = ['number', 'createdBy', 'province', 'title', 'userDescription', 'assignment', 'attachments', 'status', 'urgency', 'createdAt', 'updatedAt']

export function loadSupportConsoleState(): SupportConsoleState | null {
	if (typeof window === 'undefined') {
		return null
	}

	try {
		const raw = window.localStorage.getItem(STORAGE_KEY)
		if (!raw) {
			return null
		}

		const parsed = JSON.parse(raw) as SupportConsoleState
		const visibleColumns = normalizeSupportColumns(parsed.visibleColumns, DEFAULT_SUPPORT_COLUMNS)
		const columnEditorOrder = normalizeSupportColumnOrder(parsed.columnEditorOrder, DEFAULT_COLUMN_EDITOR_ORDER)

		if (isSameColumns(visibleColumns, LEGACY_DEFAULT_SUPPORT_COLUMNS)) {
			parsed.visibleColumns = [...DEFAULT_SUPPORT_COLUMNS]
			parsed.columnEditorOrder = [...DEFAULT_COLUMN_EDITOR_ORDER]
			return parsed
		}

		parsed.visibleColumns = visibleColumns
		parsed.columnEditorOrder = columnEditorOrder
		return parsed
	} catch {
		return null
	}
}

export function saveSupportConsoleState(value: SupportConsoleState) {
	if (typeof window === 'undefined') {
		return
	}

	window.localStorage.setItem(STORAGE_KEY, JSON.stringify(value))
}

export function normalizeSupportColumns(value: unknown, fallback: SupportColumnKey[]) {
	if (!Array.isArray(value)) {
		return [...fallback]
	}

	const knownKeys = new Set<SupportColumnKey>(KNOWN_COLUMN_KEYS)
	const normalized = value.filter((item): item is SupportColumnKey => typeof item === 'string' && knownKeys.has(item as SupportColumnKey))
	return normalized.length > 0 ? Array.from(new Set(normalized)) : [...fallback]
}

export function normalizeSupportColumnOrder(value: unknown, fallback: SupportColumnKey[]) {
	const normalized = normalizeSupportColumns(value, fallback)
	const existing = new Set<SupportColumnKey>(normalized)
	const missing = KNOWN_COLUMN_KEYS.filter((key) => !existing.has(key))
	return [...normalized, ...missing]
}

function isSameColumns(left: SupportColumnKey[], right: SupportColumnKey[]) {
	return left.length === right.length && left.every((column, index) => column === right[index])
}