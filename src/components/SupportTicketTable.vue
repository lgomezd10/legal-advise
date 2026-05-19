<script setup lang="ts">
import { computed, onBeforeUnmount, reactive } from 'vue'
import type { StatusOption, SupportColumnKey, Ticket, TypeNode, UrgencyCatalogItem } from '@/types'
import { getTypeLabel } from '@/services/ticketDraft'
import { formatDateTime } from '@/utils/formatting'
import { excerptRichText, richTextToPlainText } from '@/utils/richText'

const MIN_COLUMN_WIDTH = 56

type SupportColumn = {
	key: SupportColumnKey
	label: string
	defaultVisible: boolean
	defaultWidth: number
}

const props = defineProps<{
	tickets: Ticket[]
	emptyLabel: string
	visibleColumns: SupportColumnKey[]
	types?: TypeNode[]
	statuses?: StatusOption[]
	urgencies?: UrgencyCatalogItem[]
	sortKey?: SupportColumnKey | 'createdBy'
	sortDirection?: 'asc' | 'desc'
}>()

const emit = defineEmits<{
	(e: 'open', id: number): void
	(e: 'sort', payload: { key: SupportColumnKey | 'createdBy', direction: 'asc' | 'desc' }): void
}>()

const columnWidths = reactive<Record<SupportColumnKey, number>>({
	number: 160,
	createdBy: 180,
	province: 160,
	title: 260,
	userDescription: 380,
	assignment: 240,
	attachments: 260,
	status: 150,
	urgency: 150,
	createdAt: 210,
	updatedAt: 210,
})

let activeResize: {
	columnKey: SupportColumnKey
	startX: number
	startWidth: number
} | null = null

const columns = computed<SupportColumn[]>(() => {
	const available: SupportColumn[] = [
		{ key: 'number', label: 'Ticket', defaultVisible: true, defaultWidth: 160 },
		{ key: 'updatedAt', label: 'Última modificación', defaultVisible: true, defaultWidth: 210 },
		{ key: 'assignment', label: 'Asignación', defaultVisible: true, defaultWidth: 240 },
		{ key: 'createdBy', label: 'Creado por', defaultVisible: true, defaultWidth: 180 },
		{ key: 'province', label: 'Provincia', defaultVisible: false, defaultWidth: 160 },
		{ key: 'title', label: 'Título', defaultVisible: true, defaultWidth: 260 },
		{ key: 'userDescription', label: 'Descripción', defaultVisible: true, defaultWidth: 380 },
		{ key: 'attachments', label: 'Adjuntos', defaultVisible: false, defaultWidth: 260 },
		{ key: 'status', label: 'Estado', defaultVisible: false, defaultWidth: 150 },
		{ key: 'urgency', label: 'Criticidad', defaultVisible: false, defaultWidth: 150 },
		{ key: 'createdAt', label: 'Fecha apertura', defaultVisible: false, defaultWidth: 210 },
	]

	return available.filter((column) => props.visibleColumns.includes(column.key))
		.sort((left, right) => props.visibleColumns.indexOf(left.key) - props.visibleColumns.indexOf(right.key))
})

const tableStyle = computed(() => ({
	width: `${columns.value.reduce((total, column) => total + (columnWidths[column.key] ?? column.defaultWidth), 0)}px`,
}))
const safeTypes = computed<TypeNode[]>(() => props.types ?? [])
const statusMap = computed<Map<string, StatusOption>>(() => new Map((props.statuses ?? []).map((status: StatusOption) => [String(status.id), status])))
const urgencyMap = computed<Map<number, UrgencyCatalogItem>>(() => new Map((props.urgencies ?? []).map((urgency: UrgencyCatalogItem) => [Number(urgency.id ?? 0), urgency])))

function formatAssignment(ticket: Ticket) {
	const parts = [ticket.assignedUserUid, ticket.assignedGroupId ? `Grupo ${ticket.assignedGroupId}` : '']
		.filter(Boolean)
		.map(String)
	return parts.length > 0 ? parts.join(' / ') : 'Sin asignar'
}

function excerpt(text: string, maxLength = 140) {
	return excerptRichText(text, maxLength)
}

function resolveTypeLabel(ticket: Ticket) {
	const typeLabel = getTypeLabel(safeTypes.value, ticket.typeId) || 'Sin tipo'
	const province = typeof ticket.province === 'string' ? ticket.province.trim() : ''
	return province ? `${province}: ${typeLabel}` : typeLabel
}

function resolveUrgency(ticket: Ticket): UrgencyCatalogItem | null {
	const urgencyId = Number(ticket.urgencyId ?? 0)
	if (!urgencyId) {
		return null
	}

	return urgencyMap.value.get(urgencyId) ?? null
}

function getStatusLabel(ticket: Ticket) {
	const statusId = typeof ticket.status === 'string' ? ticket.status.trim() : ''
	if (statusId === '') {
		return ''
	}

	return statusMap.value.get(statusId)?.label ?? statusId
}

function getUrgencyLabel(ticket: Ticket) {
	const urgency = resolveUrgency(ticket)
	if (!urgency) {
		return ticket.urgencyId ? `Criticidad ${ticket.urgencyId}` : 'Sin criticidad'
	}

	return urgency.name
}

function getUrgencyStyle(ticket: Ticket) {
	const urgency = resolveUrgency(ticket)
	if (!urgency?.color) {
		return undefined
	}

	const color = urgency.color
	const textColor = getReadableTextColor(color)
	return {
		'--gi-urgency-color': color,
		'--gi-urgency-text-color': textColor,
	}
}

function getReadableTextColor(color: string) {
	const normalized = color.trim().replace('#', '')
	if (!/^[0-9a-fA-F]{6}$/.test(normalized)) {
		return '#1f2937'
	}

	const red = Number.parseInt(normalized.slice(0, 2), 16)
	const green = Number.parseInt(normalized.slice(2, 4), 16)
	const blue = Number.parseInt(normalized.slice(4, 6), 16)
	const luminance = (0.299 * red) + (0.587 * green) + (0.114 * blue)
	return luminance > 160 ? '#1f2937' : '#ffffff'
}

function getColumnStyle(column: SupportColumn) {
	const width = columnWidths[column.key] ?? column.defaultWidth
	return {
		width: `${width}px`,
		minWidth: `${width}px`,
	}
}

function isBadgeColumn(columnKey: SupportColumnKey) {
	return columnKey === 'status' || columnKey === 'urgency'
}

function getCellTitle(columnKey: SupportColumnKey, ticket: Ticket) {
	if (columnKey === 'number') {
		return ticket.number
	}

	if (columnKey === 'title') {
		return [ticket.title || '', resolveTypeLabel(ticket)].filter(Boolean).join(' · ')
	}

	if (columnKey === 'createdBy') {
		return ticket.creatorUid || ''
	}

	if (columnKey === 'province') {
		return ticket.province || ''
	}

	if (columnKey === 'userDescription') {
		return richTextToPlainText(ticket.userDescription || '')
	}

	if (columnKey === 'assignment') {
		return formatAssignment(ticket)
	}

	if (columnKey === 'attachments') {
		return (ticket.attachmentNames ?? []).join(' | ')
	}

	if (columnKey === 'status') {
		return getStatusLabel(ticket)
	}

	if (columnKey === 'urgency') {
		return getUrgencyLabel(ticket)
	}

	if (columnKey === 'createdAt') {
		return formatDateTime(ticket.createdAt)
	}

	if (columnKey === 'updatedAt') {
		return formatDateTime(ticket.updatedAt)
	}

	return ''
}

function toggleSort(columnKey: SupportColumnKey) {
	const nextDirection = props.sortKey === columnKey && props.sortDirection === 'asc' ? 'desc' : 'asc'
	emit('sort', { key: columnKey, direction: nextDirection })
}

function sortIcon(columnKey: SupportColumnKey) {
	if (props.sortKey !== columnKey) {
		return '↕'
	}

	return props.sortDirection === 'asc' ? '↑' : '↓'
}

function onResizeMove(event: MouseEvent) {
	if (!activeResize) {
		return
	}

	const nextWidth = Math.max(MIN_COLUMN_WIDTH, activeResize.startWidth + (event.clientX - activeResize.startX))
	columnWidths[activeResize.columnKey] = nextWidth
	document.body.classList.add('gi-column-resizing')
}

function stopResize() {
	activeResize = null
	window.removeEventListener('mousemove', onResizeMove)
	window.removeEventListener('mouseup', stopResize)
	document.body.classList.remove('gi-column-resizing')
}

function startResize(event: MouseEvent, column: SupportColumn) {
	event.preventDefault()
	event.stopPropagation()
	activeResize = {
		columnKey: column.key,
		startX: event.clientX,
		startWidth: columnWidths[column.key] ?? column.defaultWidth,
	}
	window.addEventListener('mousemove', onResizeMove)
	window.addEventListener('mouseup', stopResize)
}

onBeforeUnmount(() => {
	stopResize()
})
</script>

<template>
	<section class="gi-table-shell">
		<div v-if="tickets.length === 0" class="gi-empty-state">
			<p>{{ emptyLabel }}</p>
		</div>
		<div v-else class="gi-table-scroll">
			<table class="gi-support-table" :style="tableStyle">
				<colgroup>
					<col v-for="column in columns" :key="column.key" :style="getColumnStyle(column)" />
				</colgroup>
				<thead>
					<tr>
						<th v-for="column in columns" :key="column.key" :class="{ 'gi-support-table__column--badge': isBadgeColumn(column.key) }" :style="getColumnStyle(column)">
							<div class="gi-support-table__header-content">
								<button class="gi-support-table__sort-button" type="button" @click="toggleSort(column.key)">
									<span class="gi-support-table__header-label">{{ column.label }}</span>
									<span class="gi-support-table__sort-icon" aria-hidden="true">{{ sortIcon(column.key) }}</span>
								</button>
								<button class="gi-support-table__resize-handle" type="button" :aria-label="`Cambiar ancho de ${column.label}`" @mousedown="startResize($event, column)" />
							</div>
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="ticket in tickets" :key="ticket.id" class="gi-support-table__row" @click="emit('open', ticket.id)">
						<td v-for="column in columns" :key="column.key" :class="{ 'gi-support-table__column--badge': isBadgeColumn(column.key) }" :style="getColumnStyle(column)" :title="getCellTitle(column.key, ticket)">
							<template v-if="column.key === 'number'">
								<strong class="gi-support-table__cell-text">{{ ticket.number }}</strong>
							</template>
							<template v-else-if="column.key === 'title'">
								<div class="gi-support-table__title gi-support-table__cell-text">{{ ticket.title }}</div>
								<div class="gi-support-table__type gi-support-table__cell-text">{{ resolveTypeLabel(ticket) }}</div>
							</template>
							<template v-else-if="column.key === 'createdBy'">
								<span class="gi-support-table__cell-text">{{ ticket.creatorUid }}</span>
							</template>
							<template v-else-if="column.key === 'province'">
								<span class="gi-support-table__cell-text">{{ ticket.province || 'Sin provincia' }}</span>
							</template>
							<template v-else-if="column.key === 'userDescription'">
								<span class="gi-support-table__description gi-support-table__cell-text">{{ excerpt(ticket.userDescription || '') }}</span>
							</template>
							<template v-else-if="column.key === 'assignment'">
								<span class="gi-support-table__cell-text">{{ formatAssignment(ticket) }}</span>
							</template>
							<template v-else-if="column.key === 'attachments'">
								<span class="gi-support-table__description gi-support-table__cell-text">{{ (ticket.attachmentNames ?? []).join(' | ') || 'Sin adjuntos' }}</span>
							</template>
							<template v-else-if="column.key === 'status'">
								<span class="gi-badge gi-badge--success gi-support-table__badge gi-support-table__status">{{ getStatusLabel(ticket) }}</span>
							</template>
							<template v-else-if="column.key === 'urgency'">
								<span class="gi-badge gi-support-table__badge gi-support-table__urgency" :style="getUrgencyStyle(ticket)">{{ getUrgencyLabel(ticket) }}</span>
							</template>
							<template v-else-if="column.key === 'createdAt'">
								<span class="gi-support-table__cell-text">{{ formatDateTime(ticket.createdAt) }}</span>
							</template>
							<template v-else-if="column.key === 'updatedAt'">
								<span class="gi-support-table__cell-text">{{ formatDateTime(ticket.updatedAt) }}</span>
							</template>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</section>
</template>

<style scoped>
.gi-table-shell {
	border: 1px solid var(--gi-color-border);
	border-radius: 20px;
	background: var(--gi-color-surface);
	box-shadow: 0 20px 48px var(--gi-color-shadow-soft);
	overflow: hidden;
}

.gi-table-scroll {
	overflow: auto;
	max-width: 100%;
}

.gi-support-table {
	--gi-column-padding-x: .8rem;
	--gi-column-padding-x-badge: 1.15rem;
	--gi-resize-handle-width: .7rem;
	--gi-resize-handle-gap: .35rem;
	min-width: 0;
	border-collapse: separate;
	border-spacing: 0;
	table-layout: fixed;
}

.gi-support-table th,
.gi-support-table td {
	min-width: 0;
	max-width: 0;
	padding: .95rem var(--gi-column-padding-x);
	text-align: left;
	vertical-align: top;
	border-bottom: 1px solid var(--gi-color-border);
	background: var(--gi-color-surface);
	overflow: hidden;
}

.gi-support-table th.gi-support-table__column--badge,
.gi-support-table td.gi-support-table__column--badge {
	padding-left: var(--gi-column-padding-x-badge);
	padding-right: var(--gi-column-padding-x-badge);
}

.gi-support-table thead th {
	position: sticky;
	top: 0;
	z-index: 1;
	background: var(--gi-color-surface-subtle);
	font-size: .83rem;
	text-transform: uppercase;
	letter-spacing: .04em;
	color: var(--gi-color-text-muted);
}

.gi-support-table__header-content {
	position: relative;
	min-height: 1.8rem;
	min-width: 0;
	padding-right: calc((var(--gi-resize-handle-width) / 2) + var(--gi-resize-handle-gap));
}

.gi-support-table__header-label {
	display: block;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.gi-support-table__sort-button {
	display: inline-flex;
	align-items: center;
	gap: .35rem;
	max-width: calc(100% - ((var(--gi-resize-handle-width) / 2) + var(--gi-resize-handle-gap)));
	min-width: 0;
	border: none;
	padding: 0;
	background: transparent;
	color: inherit;
	font: inherit;
	text-transform: inherit;
	letter-spacing: inherit;
	cursor: pointer;
}

.gi-support-table__sort-icon {
	flex: none;
	font-size: .9rem;
}

.gi-support-table__type {
	margin-top: .25rem;
	font-size: .8rem;
	font-weight: 600;
	color: var(--gi-color-text-muted);
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.gi-support-table__badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: auto;
	max-width: 100%;
	margin-inline: auto;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.gi-support-table__status,
.gi-support-table__urgency {
	text-align: center;
	background: var(--gi-urgency-color, var(--gi-color-surface-subtle));
	color: var(--gi-urgency-text-color, var(--gi-color-text));
}

.gi-support-table__status {
	background: #e3f4e6;
	color: #1f7a31;
}

.gi-support-table__resize-handle {
	position: absolute;
	right: calc(var(--gi-resize-handle-width) / -2);
	top: 50%;
	transform: translateY(-50%);
	width: var(--gi-resize-handle-width);
	height: 1.8rem;
	border: none;
	border-radius: 999px;
	background: linear-gradient(180deg, var(--gi-color-border), var(--gi-color-border-strong));
	cursor: col-resize;
	padding: 0;
	z-index: 3;
}

.gi-support-table__row {
	cursor: pointer;
}

.gi-support-table__row:hover td {
	background: var(--gi-color-surface-subtle);
}

.gi-support-table__title {
	font-weight: 600;
}

.gi-support-table__description {
	display: inline-block;
	max-width: 100%;
	color: var(--gi-color-text-muted);
}

.gi-support-table__cell-text {
	display: block;
	width: 100%;
	max-width: 100%;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

</style>