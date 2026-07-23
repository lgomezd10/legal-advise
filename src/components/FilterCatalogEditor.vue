<script setup lang="ts">
import { ref, watch } from 'vue'
import type { AssignableOption, SavedFilter, StatusOption, TypeNode } from '@/types'
import FilterCriteriaEditor from './FilterCriteriaEditor.vue'

type FilterDraft = SavedFilter & { clientId: string }

const props = defineProps<{
	filters: SavedFilter[]
	statuses: StatusOption[]
	types: TypeNode[]
	users: AssignableOption[]
	groups: AssignableOption[]
	title: string
	description: string
	saveLabel?: string
	emptyLabel?: string
	secondaryActionLabel?: string
	lockPredefinedFilters?: boolean
}>()

const emit = defineEmits<{
	(e: 'save', filters: SavedFilter[]): void
	(e: 'secondary-action'): void
}>()

const drafts = ref<FilterDraft[]>([])
const defaultGroupName = `default-filter-${Math.random().toString(36).slice(2, 8)}`

watch(() => props.filters, (value) => {
	drafts.value = value.map((filter: SavedFilter, index: number) => ({ ...filter, active: filter.active ?? true, isDefault: filter.isDefault ?? false, clientId: `${filter.id}-${index}-${Math.random().toString(36).slice(2, 8)}` }))
}, { deep: true, immediate: true })

function addFilter() {
	drafts.value.push({ id: -(drafts.value.length + 1), name: '', criteria: {}, isPredefined: true, active: true, isDefault: drafts.value.length === 0, sortOrder: (drafts.value.length + 1) * 10, clientId: `new-${Math.random().toString(36).slice(2, 8)}` })
}

function removeFilter(clientId: string) {
	drafts.value = drafts.value.filter((item) => item.clientId !== clientId)
	if (!drafts.value.some((item) => Boolean(item.isDefault))) {
		const firstActive = drafts.value.find((item) => Boolean(item.active))
		if (firstActive) setDefault(firstActive.clientId)
	}
}

function canRemove(filter: FilterDraft) {
	return !(props.lockPredefinedFilters && filter.isPredefined)
}

function setDefault(clientId: string) {
	drafts.value = drafts.value.map((item) => ({ ...item, isDefault: item.clientId === clientId && Boolean(item.active) }))
}

function save() {
	emit('save', drafts.value.map(({ clientId: _clientId, ...filter }) => filter))
}
</script>

<template>
	<section class="gi-admin-card gi-admin-card--fullwidth">
		<div class="gi-admin-card__header">
			<div>
				<h2>{{ title }}</h2>
				<p>{{ description }}</p>
			</div>
			<div class="gi-admin-card__toolbar">
				<button v-if="secondaryActionLabel" class="gi-ghost-button" type="button" @click="emit('secondary-action')">{{ secondaryActionLabel }}</button>
				<button class="gi-secondary-button" type="button" @click="addFilter">Añadir filtro</button>
				<button class="gi-primary-button" type="button" @click="save">{{ saveLabel || 'Guardar' }}</button>
			</div>
		</div>
		<div v-if="drafts.length === 0" class="gi-admin-feedback">{{ emptyLabel || 'No hay filtros configurados.' }}</div>
		<ul v-else class="gi-admin-list gi-admin-list--stacked">
			<li v-for="filter in drafts" :key="filter.clientId" class="gi-admin-row gi-admin-row--stacked gi-filter-catalog-row">
				<div class="gi-filter-catalog-row__header">
					<label class="gi-field gi-filter-catalog-row__name-field">
						<span>Nombre</span>
						<input :id="`filter-name-${filter.clientId}`" v-model="filter.name" :name="`filter-name-${filter.clientId}`" class="gi-input" type="text" placeholder="Nombre del filtro" :disabled="lockPredefinedFilters && filter.isPredefined" />
					</label>
					<div class="gi-filter-catalog-row__toggle-field">
						<input :id="`filter-active-${filter.clientId}`" v-model="filter.active" :name="`filter-active-${filter.clientId}`" type="checkbox" aria-label="Filtro activo" title="Activo" />
					</div>
					<div class="gi-filter-catalog-row__toggle-field">
						<input :id="`filter-default-${filter.clientId}`" :checked="Boolean(filter.isDefault)" :disabled="!filter.active" type="radio" :name="defaultGroupName" aria-label="Filtro predeterminado" title="Predeterminado" @change="setDefault(filter.clientId)" />
					</div>
					<div class="gi-filter-catalog-row__actions">
						<button v-if="canRemove(filter)" class="gi-ghost-button" type="button" @click="removeFilter(filter.clientId)">Eliminar</button>
					</div>
				</div>
				<FilterCriteriaEditor v-model="filter.criteria" :statuses="statuses" :types="types" :users="users" :groups="groups" />
			</li>
		</ul>
	</section>
</template>

<style scoped>
.gi-filter-catalog-row {
	padding: 1rem;
	border-radius: 18px;
	border: 1px solid var(--gi-color-border, rgba(49, 96, 91, .12));
	background: var(--gi-color-surface-plain, rgba(255, 255, 255, .86));
	color: var(--gi-color-text, #222222);
}

.gi-filter-catalog-row__header {
	display: grid;
	gap: 1rem;
	grid-template-columns: minmax(18rem, 1.8fr) auto auto auto;
	align-items: end;
}

.gi-filter-catalog-row__actions {
	display: flex;
	justify-content: flex-end;
	align-items: flex-end;
}

.gi-filter-catalog-row__name-field,
.gi-filter-catalog-row__toggle-field {
	min-width: 0;
}


.gi-filter-catalog-row__toggle-field {
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: 2.9rem;
	padding-bottom: .1rem;
}

.gi-filter-catalog-row__toggle-field input {
	margin: 0;
	width: 1rem;
	height: 1rem;
}

@media (max-width: 960px) {
	.gi-filter-catalog-row__header {
		grid-template-columns: minmax(14rem, 1fr) auto auto auto;
	}

	.gi-filter-catalog-row__actions {
		grid-column: 4;
	}
}

@media (max-width: 720px) {
	.gi-filter-catalog-row__header {
		grid-template-columns: 1fr auto auto;
		align-items: center;
	}

	.gi-filter-catalog-row__name-field {
		grid-column: 1 / -1;
	}

	.gi-filter-catalog-row__actions {
		grid-column: 1 / -1;
	}

	.gi-filter-catalog-row__actions {
		justify-content: flex-start;
	}
}
</style>