<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import SupportFilterBuilder from '@/components/SupportFilterBuilder.vue'
import SupportTicketTable from '@/components/SupportTicketTable.vue'
import type { SupportColumnKey, Ticket } from '@/types'
import { useBootstrapStore } from '@/store/bootstrap'
import { useTicketsStore } from '@/store/tickets'
import { useSupportFiltersStore } from '@/store/supportFilters'
import { richTextToPlainText } from '@/utils/richText'
import { DEFAULT_COLUMN_EDITOR_ORDER, DEFAULT_SUPPORT_COLUMNS, DEFAULT_SUPPORT_SORT, loadSupportConsoleState, normalizeSupportColumnOrder, normalizeSupportColumns, saveSupportConsoleState } from '@/utils/supportConsoleState'

const router = useRouter()
const route = useRoute()
const bootstrapStore = useBootstrapStore()
const ticketsStore = useTicketsStore()
const supportFiltersStore = useSupportFiltersStore()
const criteria = ref<Record<string, unknown>>({})
const selectedFilterId = ref<number | null>(null)
const builderInitialFilterId = ref<number | null>(null)
const builderInitialCriteria = ref<Record<string, unknown>>({})
const statuses = computed(() => bootstrapStore.data.catalogs.statuses)
const types = computed(() => bootstrapStore.data.catalogs.types)
const provinces = computed(() => bootstrapStore.data.catalogs.provinces)
const users = computed(() => bootstrapStore.data.assignables.users)
const groups = computed(() => bootstrapStore.data.assignables.groups)
const initialCriteria = computed<Record<string, unknown>>(() => {
	const defaultFilter = supportFiltersStore.items.find((item) => item.id === supportFiltersStore.defaultFilterId)
	return normalizeSupportCriteria(defaultFilter?.criteria ?? {})
})
const columnEditorOpen = ref(false)
const selectedColumnCount = computed(() => visibleColumns.value.length)
const visibleColumns = ref<SupportColumnKey[]>([...DEFAULT_SUPPORT_COLUMNS])
const columnEditorOrder = ref<SupportColumnKey[]>([...DEFAULT_COLUMN_EDITOR_ORDER])
const sortKey = ref<SupportColumnKey | 'createdBy'>(DEFAULT_SUPPORT_SORT.sortKey)
const sortDirection = ref<'asc' | 'desc'>(DEFAULT_SUPPORT_SORT.sortDirection)
const consoleStateReady = ref(false)
const availableColumns: Array<{ key: SupportColumnKey, label: string }> = [
	{ key: 'number', label: 'Número de ticket' },
	{ key: 'updatedAt', label: 'Última modificación' },
	{ key: 'assignment', label: 'Asignación' },
	{ key: 'createdBy', label: 'Creado por' },
	{ key: 'province', label: 'Provincia' },
	{ key: 'title', label: 'Título' },
	{ key: 'userDescription', label: 'Descripción' },
	{ key: 'status', label: 'Estado' },
	{ key: 'urgency', label: 'Criticidad' },
	{ key: 'createdAt', label: 'Fecha de apertura' },
]
const orderedColumns = computed(() => {
	const orderIndex = new Map(columnEditorOrder.value.map((column, index) => [column, index]))
	return [...availableColumns].sort((left, right) => (orderIndex.get(left.key) ?? 999) - (orderIndex.get(right.key) ?? 999))
})
const orderedVisibleColumns = computed<SupportColumnKey[]>(() => columnEditorOrder.value.filter((column) => visibleColumns.value.includes(column)))
const sortedTickets = computed(() => [...ticketsStore.items].sort(compareTickets))

function compareTickets(left: Ticket, right: Ticket) {
	const leftValue = getSortValue(left, sortKey.value)
	const rightValue = getSortValue(right, sortKey.value)
	if (leftValue === rightValue) {
		return 0
	}
	const result = leftValue > rightValue ? 1 : -1
	return sortDirection.value === 'asc' ? result : -result
}

function getSortValue(ticket: Ticket, key: SupportColumnKey | 'createdBy') {
	if (key === 'number') {
		return ticket.number
	}
	if (key === 'createdBy') {
		return ticket.creatorUid || ''
	}
	if (key === 'title') {
		return ticket.title || ''
	}
	if (key === 'userDescription') {
		return richTextToPlainText(ticket.userDescription || '')
	}
	if (key === 'assignment') {
		return `${ticket.assignedUserUid ?? ''} ${ticket.assignedGroupId ?? ''}`.trim()
	}
	if (key === 'status') {
		return ticket.status || ''
	}
	if (key === 'urgency') {
		return Number(ticket.urgencyId ?? 0)
	}
	if (key === 'createdAt') {
		return Number(ticket.createdAt ?? 0)
	}
	return Number(ticket.updatedAt ?? 0)
}

function persistConsoleState() {
	saveSupportConsoleState({
		visibleColumns: [...visibleColumns.value],
		columnEditorOrder: [...columnEditorOrder.value],
		criteria: { ...criteria.value },
		sortKey: sortKey.value,
		sortDirection: sortDirection.value,
		selectedFilterId: selectedFilterId.value,
	})
}

function cloneCriteria(source: Record<string, unknown>) {
	const normalized: Record<string, unknown> = {}
	for (const [key, value] of Object.entries(source)) {
		normalized[key] = Array.isArray(value) ? [...value] : value
	}

	return normalized
}

function normalizeSupportCriteria(source: Record<string, unknown>) {
	const normalized = cloneCriteria(source)
	if ((!('province' in normalized) || normalized.province === null || normalized.province === '') && typeof normalized.city === 'string' && normalized.city.trim() !== '') {
		normalized.province = normalized.city.trim()
	}
	delete normalized.city
	return normalized
}

function getRouteFilterId() {
	const raw = Array.isArray(route.query.filterId) ? route.query.filterId[0] : route.query.filterId
	const parsed = Number(raw)
	return Number.isInteger(parsed) && parsed > 0 ? parsed : null
}

function resolveFilterCriteria(filterId: number | null) {
	if (filterId === null) {
		return null
	}

	return supportFiltersStore.items.find((item) => item.id === filterId) ?? null
}

function syncBuilderState(nextCriteria: Record<string, unknown>, nextSelectedFilterId: number | null) {
	builderInitialCriteria.value = cloneCriteria(nextCriteria)
	builderInitialFilterId.value = nextSelectedFilterId
}

async function applySelectedFilter(filterId: number | null) {
	const filter = resolveFilterCriteria(filterId)
	if (!filter) {
		return
	}

	const nextCriteria = cloneCriteria(filter.criteria ?? {})
	syncBuilderState(nextCriteria, filter.id)
	await apply(nextCriteria, filter.id)
}

onMounted(async() => {
	const savedState = loadSupportConsoleState()
	if (savedState) {
		columnEditorOrder.value = normalizeSupportColumnOrder(savedState.columnEditorOrder, DEFAULT_COLUMN_EDITOR_ORDER)
		visibleColumns.value = columnEditorOrder.value.filter((column) => normalizeSupportColumns(savedState.visibleColumns, DEFAULT_SUPPORT_COLUMNS).includes(column))
		criteria.value = normalizeSupportCriteria(savedState.criteria ?? {})
		sortKey.value = savedState.sortKey ?? DEFAULT_SUPPORT_SORT.sortKey
		sortDirection.value = savedState.sortDirection ?? DEFAULT_SUPPORT_SORT.sortDirection
		selectedFilterId.value = savedState.selectedFilterId ?? null
	} else {
		visibleColumns.value = [...DEFAULT_SUPPORT_COLUMNS]
		columnEditorOrder.value = [...DEFAULT_COLUMN_EDITOR_ORDER]
	}

	await supportFiltersStore.load()
	const routeFilterId = getRouteFilterId()
	if (routeFilterId !== null && resolveFilterCriteria(routeFilterId)) {
		selectedFilterId.value = routeFilterId
		criteria.value = normalizeSupportCriteria(resolveFilterCriteria(routeFilterId)?.criteria ?? {})
	} else if (Object.keys(criteria.value).length === 0) {
		criteria.value = normalizeSupportCriteria(initialCriteria.value)
		if (selectedFilterId.value === null && Object.keys(criteria.value).length > 0) {
			selectedFilterId.value = supportFiltersStore.defaultFilterId
		}
	}
	syncBuilderState(criteria.value, selectedFilterId.value)
	consoleStateReady.value = true
	await ticketsStore.load('support', criteria.value)
	persistConsoleState()
})

watch([visibleColumns, columnEditorOrder, criteria, sortKey, sortDirection, selectedFilterId], () => {
	persistConsoleState()
}, { deep: true })

watch(() => route.query.filterId, async() => {
	if (!consoleStateReady.value) {
		return
	}

	const routeFilterId = getRouteFilterId()
	if (routeFilterId === null || routeFilterId === selectedFilterId.value) {
		return
	}

	await applySelectedFilter(routeFilterId)
})

async function exportCurrent() {
	const result = await ticketsStore.export('support', criteria.value, visibleColumns.value)
	const binary = atob(result.content)
	const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0))
	const blob = new Blob([bytes], { type: result.mimeType })
	const link = document.createElement('a')
	link.href = URL.createObjectURL(blob)
	link.download = result.filename
	link.click()
	URL.revokeObjectURL(link.href)
}

async function apply(nextCriteria: Record<string, unknown>, nextSelectedFilterId?: number | null) {
	criteria.value = nextCriteria
	selectedFilterId.value = nextSelectedFilterId ?? null
	await ticketsStore.load('support', nextCriteria)
}

async function saveFilter(payload: Record<string, unknown>) {
	await supportFiltersStore.save(payload)
}

function createTicket() {
	void router.push('/soporte/nuevo')
}

function openTicket(ticketId: number) {
	void router.push(`/soporte/${ticketId}`)
}

function toggleColumn(columnKey: SupportColumnKey, checked: boolean) {
	if (checked) {
		visibleColumns.value = columnEditorOrder.value.filter((key) => key === columnKey || visibleColumns.value.includes(key))
		return
	}

	visibleColumns.value = visibleColumns.value.filter((item: SupportColumnKey) => item !== columnKey)
	if (visibleColumns.value.length === 0) {
		visibleColumns.value = ['number']
	}
}

function moveColumn(columnKey: SupportColumnKey, direction: -1 | 1) {
	const currentVisibleOrder = [...orderedVisibleColumns.value]
	const index = currentVisibleOrder.indexOf(columnKey)
	if (index === -1) {
		return
	}

	const nextIndex = index + direction
	if (nextIndex < 0 || nextIndex >= currentVisibleOrder.length) {
		return
	}

	const targetColumnKey = currentVisibleOrder[nextIndex]
	const next = [...columnEditorOrder.value]
	const sourceOrderIndex = next.indexOf(columnKey)
	const targetOrderIndex = next.indexOf(targetColumnKey)
	if (sourceOrderIndex === -1 || targetOrderIndex === -1) {
		return
	}

	;[next[sourceOrderIndex], next[targetOrderIndex]] = [next[targetOrderIndex], next[sourceOrderIndex]]
	columnEditorOrder.value = next
	visibleColumns.value = next.filter((column) => visibleColumns.value.includes(column))
}

function restoreDefaultColumns() {
	visibleColumns.value = [...DEFAULT_SUPPORT_COLUMNS]
	columnEditorOrder.value = [...DEFAULT_COLUMN_EDITOR_ORDER]
}

function onSortChange(payload: { key: SupportColumnKey | 'createdBy', direction: 'asc' | 'desc' }) {
	sortKey.value = payload.key
	sortDirection.value = payload.direction
}

function closeColumnEditor() {
	columnEditorOpen.value = false
}
</script>

<template>
	<section class="gi-page gi-page--support">
		<header class="gi-page__header gi-page__header--dense gi-support-console-header">
			<div class="gi-support-header-actions">
				<button class="gi-primary-button" type="button" @click="createTicket">Nuevo ticket</button>
				<button class="gi-secondary-button" type="button" @click="columnEditorOpen = true">Editar columnas</button>
				<button class="gi-secondary-button" @click="exportCurrent">Exportar CSV</button>
			</div>
		</header>
		<SupportFilterBuilder
			v-if="consoleStateReady"
			:filters="supportFiltersStore.items"
			:statuses="statuses"
			:types="types"
			:provinces="provinces"
			:users="users"
			:groups="groups"
			:initial-filter-id="builderInitialFilterId"
			:initial-criteria="builderInitialCriteria"
			@apply="apply"
			@save="saveFilter"
			@delete="supportFiltersStore.remove" />
		<SupportTicketTable
			:tickets="sortedTickets"
			:types="types"
			:visible-columns="orderedVisibleColumns"
			:sort-key="sortKey"
			:sort-direction="sortDirection"
			empty-label="No hay tickets para los criterios actuales"
			@open="openTicket"
			@sort="onSortChange"
		/>

		<div v-if="columnEditorOpen" class="gi-app-dialog-backdrop gi-dialog-backdrop" @click.self="closeColumnEditor">
			<section class="gi-app-dialog gi-dialog gi-dialog--medium gi-dialog--min-tall" aria-label="Editar columnas visibles">
				<header class="gi-dialog__header">
					<h2 class="gi-dialog__title">Editar columnas</h2>
					<button class="gi-modal-close" type="button" aria-label="Cerrar ventana" @click="closeColumnEditor">x</button>
				</header>
				<div class="gi-support-column-editor-modal__grid">
					<div v-for="column in orderedColumns" :key="column.key" class="gi-switch-row gi-support-column-editor__item">
						<input :checked="visibleColumns.includes(column.key)" type="checkbox" @change="toggleColumn(column.key, ($event.target as HTMLInputElement).checked)" />
						<span>{{ column.label }}</span>
						<div class="gi-support-column-editor__order-actions">
							<button class="gi-round-icon-button gi-support-column-editor__move-button" type="button" aria-label="Subir columna" title="Subir columna" :disabled="!visibleColumns.includes(column.key) || orderedVisibleColumns.indexOf(column.key) === 0" @click="moveColumn(column.key, -1)">
								<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false">
									<path d="M8 3.5 3.5 8h2.25v4.5h4.5V8H12.5L8 3.5Z" />
								</svg>
							</button>
							<button class="gi-round-icon-button gi-support-column-editor__move-button" type="button" aria-label="Bajar columna" title="Bajar columna" :disabled="!visibleColumns.includes(column.key) || orderedVisibleColumns.indexOf(column.key) === orderedVisibleColumns.length - 1" @click="moveColumn(column.key, 1)">
								<svg viewBox="0 0 16 16" aria-hidden="true" focusable="false">
									<path d="M5.75 3.5v4.5H3.5L8 12.5 12.5 8h-2.25V3.5h-4.5Z" />
								</svg>
							</button>
						</div>
					</div>
				</div>
				<footer class="gi-dialog__footer">
					<button class="gi-ghost-button" type="button" @click="restoreDefaultColumns">Restaurar por defecto</button>
					<button class="gi-secondary-button" type="button" @click="closeColumnEditor">Listo</button>
				</footer>
			</section>
		</div>
	</section>
</template>

<style scoped>
.gi-page--support {
	padding: .7rem .75rem 1rem;
	width: 100%;
}

.gi-support-console-header {
	justify-content: flex-start;
}

.gi-support-header-actions {
	align-items: flex-start;
}

.gi-support-column-editor__item {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: .75rem;
	padding: .55rem .65rem;
}

.gi-support-column-editor__order-actions {
	display: flex;
	gap: .4rem;
	margin-left: auto;
}

.gi-support-column-editor__move-button {
	width: 1.9rem;
	height: 1.9rem;
}

.gi-support-column-editor-modal__grid {
	display: grid;
	gap: .45rem;
	grid-template-columns: 1fr;
}

@media (max-width: 900px) {
	.gi-page--support {
		padding: .6rem .55rem .9rem;
	}

	.gi-support-console-header {
		gap: .75rem;
	}

}
</style>