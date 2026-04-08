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
	emit('save', drafts.value.map(({ clientId, ...filter }) => filter))
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
				<button class="gi-secondary-button" type="button" @click="addFilter">Anadir filtro</button>
				<button class="gi-primary-button" type="button" @click="save">{{ saveLabel || 'Guardar' }}</button>
			</div>
		</div>
		<div v-if="drafts.length === 0" class="gi-admin-feedback">{{ emptyLabel || 'No hay filtros configurados.' }}</div>
		<ul v-else class="gi-admin-list gi-admin-list--stacked">
			<li v-for="filter in drafts" :key="filter.clientId" class="gi-admin-row gi-admin-row--stacked gi-filter-catalog-row">
				<div class="gi-filter-catalog-row__header">
					<label class="gi-field gi-field--wide">
						<span>Nombre</span>
						<input v-model="filter.name" class="gi-input" type="text" placeholder="Nombre del filtro" :disabled="lockPredefinedFilters && filter.isPredefined" />
					</label>
					<label class="gi-switch-row"><input v-model="filter.active" type="checkbox" /><span>Activo</span></label>
					<label class="gi-switch-row"><input :checked="Boolean(filter.isDefault)" :disabled="!filter.active" type="radio" :name="defaultGroupName" @change="setDefault(filter.clientId)" /><span>Predeterminado</span></label>
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
	border: 1px solid rgba(49, 96, 91, .12);
	background: rgba(255, 255, 255, .86);
}

.gi-filter-catalog-row__header {
	align-items: center;
	justify-content: space-between;
}

.gi-filter-catalog-row__actions {
	align-items: center;
	justify-content: flex-end;
}
</style>