<script setup lang="ts">
import { computed, onBeforeUnmount, reactive } from 'vue'
import type { SupportColumnKey, Ticket } from '@/types'
import { formatDateTime } from '@/utils/formatting'
import { excerptRichText, richTextToPlainText } from '@/utils/richText'

const MIN_COLUMN_WIDTH = 56

type SupportColumn = {
	key: SupportColumnKey
	label: string
	defaultVisible: boolean
	sticky?: boolean
	defaultWidth: number
}

const props = defineProps<{
	tickets: Ticket[]
	emptyLabel: string
	visibleColumns: SupportColumnKey[]
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
	title: 260,
	userDescription: 380,
	assignment: 240,
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
		{ key: 'number', label: 'Ticket', defaultVisible: true, sticky: true, defaultWidth: 160 },
		{ key: 'updatedAt', label: 'Ultima modificacion', defaultVisible: true, defaultWidth: 210 },
		{ key: 'assignment', label: 'Asignacion', defaultVisible: true, defaultWidth: 240 },
		{ key: 'createdBy', label: 'Creado por', defaultVisible: true, defaultWidth: 180 },
		{ key: 'title', label: 'Titulo', defaultVisible: true, defaultWidth: 260 },
		{ key: 'userDescription', label: 'Descripcion', defaultVisible: true, defaultWidth: 380 },
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

function formatAssignment(ticket: Ticket) {
	const parts = [ticket.assignedUserUid, ticket.assignedGroupId ? `Grupo ${ticket.assignedGroupId}` : '']
		.filter(Boolean)
		.map(String)
	return parts.length > 0 ? parts.join(' / ') : 'Sin asignar'
}

function excerpt(text: string, maxLength = 140) {
	return excerptRichText(text, maxLength)
}

function getColumnStyle(column: SupportColumn) {
	const width = columnWidths[column.key] ?? column.defaultWidth
	return {
		width: `${width}px`,
		minWidth: `${width}px`,
	}
}

function getCellTitle(columnKey: SupportColumnKey, ticket: Ticket) {
	if (columnKey === 'number') {
		return ticket.number
	}

	if (columnKey === 'title') {
		return ticket.title || ''
	}

	if (columnKey === 'createdBy') {
		return ticket.creatorUid || ''
	}

	if (columnKey === 'userDescription') {
		return richTextToPlainText(ticket.userDescription || '')
	}

	if (columnKey === 'assignment') {
		return formatAssignment(ticket)
	}

	if (columnKey === 'status') {
		return ticket.status || ''
	}

	if (columnKey === 'urgency') {
		return String(ticket.urgencyId ?? 'Sin criticidad')
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
						<th v-for="column in columns" :key="column.key" :class="{ 'gi-support-table__cell--sticky': column.sticky }" :style="getColumnStyle(column)">
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
						<td v-for="column in columns" :key="column.key" :class="{ 'gi-support-table__cell--sticky': column.sticky }" :style="getColumnStyle(column)" :title="getCellTitle(column.key, ticket)">
							<template v-if="column.key === 'number'">
								<strong class="gi-support-table__cell-text">{{ ticket.number }}</strong>
							</template>
							<template v-else-if="column.key === 'title'">
								<div class="gi-support-table__title gi-support-table__cell-text">{{ ticket.title }}</div>
							</template>
							<template v-else-if="column.key === 'createdBy'">
								<span class="gi-support-table__cell-text">{{ ticket.creatorUid }}</span>
							</template>
							<template v-else-if="column.key === 'userDescription'">
								<span class="gi-support-table__description gi-support-table__cell-text">{{ excerpt(ticket.userDescription || '') }}</span>
							</template>
							<template v-else-if="column.key === 'assignment'">
								<span class="gi-support-table__cell-text">{{ formatAssignment(ticket) }}</span>
							</template>
							<template v-else-if="column.key === 'status'">
								<span class="gi-badge gi-support-table__cell-text">{{ ticket.status }}</span>
							</template>
							<template v-else-if="column.key === 'urgency'">
								<span class="gi-support-table__cell-text">{{ ticket.urgencyId ?? 'Sin criticidad' }}</span>
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
	border: 1px solid rgba(49, 96, 91, .12);
	border-radius: 20px;
	background: rgba(255, 255, 255, .94);
	box-shadow: 0 20px 48px rgba(34, 62, 55, .06);
	overflow: hidden;
}

.gi-table-scroll {
	overflow: auto;
	max-width: 100%;
}

.gi-support-table {
	min-width: 0;
	border-collapse: separate;
	border-spacing: 0;
	table-layout: fixed;
}

.gi-support-table th,
.gi-support-table td {
	min-width: 0;
	max-width: 0;
	padding: .95rem 1rem;
	text-align: left;
	vertical-align: top;
	border-bottom: 1px solid rgba(49, 96, 91, .08);
	background: rgba(255, 255, 255, .96);
	overflow: hidden;
}

.gi-support-table thead th {
	position: sticky;
	top: 0;
	z-index: 1;
	background: rgba(239, 245, 241, .98);
	font-size: .83rem;
	text-transform: uppercase;
	letter-spacing: .04em;
	color: #526861;
}

.gi-support-table__header-content {
	position: relative;
	min-height: 1.8rem;
	min-width: 0;
	padding-right: 1rem;
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
	max-width: calc(100% - 1rem);
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

.gi-support-table__resize-handle {
	position: absolute;
	right: 0;
	top: 50%;
	transform: translateY(-50%);
	width: .8rem;
	height: 1.8rem;
	border: none;
	border-radius: 999px;
	background: linear-gradient(180deg, rgba(49, 96, 91, .12), rgba(49, 96, 91, .28));
	cursor: col-resize;
	padding: 0;
}

.gi-support-table__row {
	cursor: pointer;
}

.gi-support-table__row:hover td {
	background: rgba(244, 248, 246, .98);
}

.gi-support-table__cell--sticky {
	position: sticky;
	left: 0;
	z-index: 1;
}

.gi-support-table tbody .gi-support-table__cell--sticky {
	background: rgba(255, 255, 255, .98);
}

.gi-support-table__title {
	font-weight: 600;
}

.gi-support-table__description {
	display: inline-block;
	max-width: 100%;
	color: #596d66;
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