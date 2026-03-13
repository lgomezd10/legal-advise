<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import SupportFilterBuilder from '@/components/SupportFilterBuilder.vue'
import SupportTicketTable from '@/components/SupportTicketTable.vue'
import type { SupportColumnKey } from '@/types'
import { useBootstrapStore } from '@/store/bootstrap'
import { useTicketsStore } from '@/store/tickets'
import { useSupportFiltersStore } from '@/store/supportFilters'

const router = useRouter()
const bootstrapStore = useBootstrapStore()
const ticketsStore = useTicketsStore()
const supportFiltersStore = useSupportFiltersStore()
const criteria = ref<Record<string, unknown>>({})
const statuses = computed(() => bootstrapStore.data.catalogs.statuses)
const types = computed(() => bootstrapStore.data.catalogs.types)
const users = computed(() => bootstrapStore.data.assignables.users)
const groups = computed(() => bootstrapStore.data.assignables.groups)
const columnEditorOpen = ref(false)
const selectedColumnCount = computed(() => visibleColumns.value.length)
const visibleColumns = ref<SupportColumnKey[]>(['number', 'title', 'userDescription', 'assignment'])
const availableColumns: Array<{ key: SupportColumnKey, label: string }> = [
	{ key: 'number', label: 'Numero de ticket' },
	{ key: 'title', label: 'Titulo' },
	{ key: 'userDescription', label: 'Descripcion' },
	{ key: 'assignment', label: 'Asignacion' },
	{ key: 'status', label: 'Estado' },
	{ key: 'urgency', label: 'Criticidad' },
	{ key: 'createdAt', label: 'Fecha de apertura' },
]

onMounted(async() => {
	await supportFiltersStore.load()
	await ticketsStore.load('support')
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

async function apply(nextCriteria: Record<string, unknown>) {
	criteria.value = nextCriteria
	await ticketsStore.load('support', nextCriteria)
}

async function saveFilter(payload: Record<string, unknown>) {
	await supportFiltersStore.save(payload)
}

function openTicket(ticketId: number) {
	void router.push(`/soporte/${ticketId}`)
}

function toggleColumn(columnKey: SupportColumnKey, checked: boolean) {
	if (checked) {
		visibleColumns.value = Array.from(new Set([...visibleColumns.value, columnKey]))
		return
	}

	visibleColumns.value = visibleColumns.value.filter((item: SupportColumnKey) => item !== columnKey)
	if (visibleColumns.value.length === 0) {
		visibleColumns.value = ['number']
	}
}

function closeColumnEditor() {
	columnEditorOpen.value = false
}
</script>

<template>
	<section class="gi-page gi-page--support">
		<header class="gi-page__header gi-page__header--dense">
			<div class="gi-support-header-actions">
				<button class="gi-secondary-button" type="button" @click="columnEditorOpen = true">Editar Campos</button>
				<button class="gi-secondary-button" @click="exportCurrent">Exportar CSV</button>
			</div>
		</header>
		<SupportFilterBuilder
			:filters="supportFiltersStore.items"
			:statuses="statuses"
			:types="types"
			:users="users"
			:groups="groups"
			@apply="apply"
			@save="saveFilter"
			@delete="supportFiltersStore.remove" />
		<SupportTicketTable
			:tickets="ticketsStore.items"
			:visible-columns="visibleColumns"
			empty-label="No hay incidencias para los criterios actuales"
			@open="openTicket"
		/>

		<div v-if="columnEditorOpen" class="gi-support-column-editor-modal" @click.self="closeColumnEditor">
			<section class="gi-support-column-editor-modal__panel" aria-label="Editar campos visibles">
				<header class="gi-support-column-editor-modal__header">
					<div>
						<h2>Editar Campos</h2>
						<p>{{ selectedColumnCount }} columnas visibles en la tabla filtrada.</p>
					</div>
					<button class="gi-ghost-button" type="button" @click="closeColumnEditor">Cerrar</button>
				</header>
				<div class="gi-support-column-editor-modal__grid">
					<label v-for="column in availableColumns" :key="column.key" class="gi-switch-row gi-support-column-editor__item">
						<input :checked="visibleColumns.includes(column.key)" type="checkbox" @change="toggleColumn(column.key, ($event.target as HTMLInputElement).checked)" />
						<span>{{ column.label }}</span>
					</label>
				</div>
				<footer class="gi-support-column-editor-modal__footer">
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

.gi-page--support .gi-page__subtitle {
	max-width: none;
}

.gi-support-header-actions {
	display: flex;
	gap: .75rem;
	align-items: center;
	flex-wrap: wrap;
}

.gi-support-column-editor__item {
	padding: .55rem .65rem;
}

.gi-support-column-editor-modal {
	position: fixed;
	inset: 0;
	z-index: 90;
	display: grid;
	place-items: center;
	padding: 1rem;
	background: rgba(24, 38, 34, .34);
}

.gi-support-column-editor-modal__panel {
	width: min(34rem, 100%);
	display: grid;
	gap: .9rem;
	padding: 1rem;
	border-radius: 22px;
	background: rgba(255, 255, 255, .99);
	border: 1px solid rgba(49, 96, 91, .14);
	box-shadow: 0 24px 64px rgba(20, 34, 30, .18);
}

.gi-support-column-editor-modal__header,
.gi-support-column-editor-modal__footer {
	display: flex;
	justify-content: space-between;
	gap: .75rem;
	align-items: center;
}

.gi-support-column-editor-modal__header h2,
.gi-support-column-editor-modal__header p {
	margin: 0;
}

.gi-support-column-editor-modal__header p {
	margin-top: .2rem;
	color: #5e706a;
}

.gi-support-column-editor-modal__grid {
	display: grid;
	gap: .45rem;
	grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
}

@media (max-width: 900px) {
	.gi-page--support {
		padding: .6rem .55rem .9rem;
	}

	.gi-support-column-editor-modal__header,
	.gi-support-column-editor-modal__footer {
		flex-direction: column;
		align-items: flex-start;
	}
}
</style>