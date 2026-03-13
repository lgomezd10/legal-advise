<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import type { AssignableOption, SavedFilter, SearchableSelectOption, StatusOption, TypeNode } from '@/types'
import SearchableSelect from './SearchableSelect.vue'

type CriteriaKey = 'status' | 'assignedUser' | 'assignedGroup' | 'typeId' | 'city' | 'text' | 'updatedWithinDays' | 'unassigned'

type FilterCriteriaState = {
	status: string[]
	assignedUser: string
	assignedGroup: string
	typeId: string
	city: string
	text: string
	updatedWithinDays: string
	unassigned: boolean
}

type FilterChip = {
	key: CriteriaKey
	label: string
}

const props = defineProps<{
	filters: SavedFilter[]
	statuses: StatusOption[]
	types: TypeNode[]
	users: AssignableOption[]
	groups: AssignableOption[]
}>()

const emit = defineEmits<{
	(e: 'apply', criteria: Record<string, unknown>): void
	(e: 'save', payload: Record<string, unknown>): void
	(e: 'delete', id: number): void
}>()

const criteria = reactive<FilterCriteriaState>({
	status: [],
	assignedUser: '',
	assignedGroup: '',
	typeId: '',
	city: '',
	text: '',
	updatedWithinDays: '',
	unassigned: false,
})

const enabledCriteria = reactive<Record<CriteriaKey, boolean>>({
	status: false,
	assignedUser: false,
	assignedGroup: false,
	typeId: false,
	city: false,
	text: false,
	updatedWithinDays: false,
	unassigned: false,
})

const draftCriteria = reactive<FilterCriteriaState>({
	status: [],
	assignedUser: '',
	assignedGroup: '',
	typeId: '',
	city: '',
	text: '',
	updatedWithinDays: '',
	unassigned: false,
})

const saveName = ref('')
const selectedPredefined = ref('')
const selectedCustom = ref('')
const modalOpen = ref(false)
const modalCriterionKey = ref<CriteriaKey | ''>('')

function normalizeCollection<T>(items: T[] | Record<string, T> | undefined | null): T[] {
	if (Array.isArray(items)) {
		return items
	}

	if (items && typeof items === 'object') {
		return Object.values(items)
	}

	return []
}

const criterionOptions: Array<{ key: CriteriaKey, label: string }> = [
	{ key: 'status', label: 'Estado' },
	{ key: 'assignedUser', label: 'Usuario asignado' },
	{ key: 'assignedGroup', label: 'Grupo asignado' },
	{ key: 'typeId', label: 'Tipo' },
	{ key: 'city', label: 'Ciudad' },
	{ key: 'text', label: 'Texto libre' },
	{ key: 'updatedWithinDays', label: 'Fecha reciente' },
	{ key: 'unassigned', label: 'Sin asignar' },
]

const safeFilters = computed<SavedFilter[]>(() => normalizeCollection<SavedFilter>(props.filters))
const safeStatuses = computed<StatusOption[]>(() => normalizeCollection<StatusOption>(props.statuses))
const safeTypes = computed<TypeNode[]>(() => normalizeCollection<TypeNode>(props.types))
const safeUsers = computed<AssignableOption[]>(() => normalizeCollection<AssignableOption>(props.users))
const safeGroups = computed<AssignableOption[]>(() => normalizeCollection<AssignableOption>(props.groups))
const typeOptions = computed(() => flattenTypes(safeTypes.value))
const predefinedFilters = computed(() => safeFilters.value.filter((filter: SavedFilter) => filter.isPredefined))
const customFilters = computed(() => safeFilters.value.filter((filter: SavedFilter) => !filter.isPredefined))
const statusLabelMap = computed(() => new Map(safeStatuses.value.map((status: StatusOption) => [status.id, status.label])))
const userLabelMap = computed(() => new Map(safeUsers.value.map((user: AssignableOption) => [user.id, user.displayName])))
const groupLabelMap = computed(() => new Map(safeGroups.value.map((group: AssignableOption) => [group.id, group.displayName])))
const predefinedFilterOptions = computed<SearchableSelectOption[]>(() => predefinedFilters.value.map((filter: SavedFilter) => ({
	value: String(filter.id),
	label: filter.name,
})))
const customFilterOptions = computed<SearchableSelectOption[]>(() => customFilters.value.map((filter: SavedFilter) => ({
	value: String(filter.id),
	label: filter.name,
})))
const criterionTypeOptions = computed<SearchableSelectOption[]>(() => criterionOptions.map((option: { key: CriteriaKey, label: string }) => ({
	value: option.key,
	label: option.label,
})))
const draftUserOptions = computed<SearchableSelectOption[]>(() => [
	{ value: '__me__', label: 'Asignadas a mi' },
	...safeUsers.value.map((user: AssignableOption) => ({ value: user.id, label: user.displayName, searchText: [user.id, ...(user.groupIds ?? [])].join(' ') })),
])
const draftGroupOptions = computed<SearchableSelectOption[]>(() => [
	{ value: '__my_groups__', label: 'Mis grupos' },
	...safeGroups.value.map((group: AssignableOption) => ({ value: group.id, label: group.displayName, searchText: [group.id, ...(group.userIds ?? [])].join(' ') })),
])
const typeSelectOptions = computed<SearchableSelectOption[]>(() => typeOptions.value.map((item) => ({
	value: String(item.id),
	label: item.label,
})))
const activeCriteria = computed<Record<string, unknown>>(() => {
	const next: Record<string, unknown> = {}
	if (enabledCriteria.status && criteria.status.length > 0) {
		next.status = [...criteria.status]
	}
	if (enabledCriteria.assignedUser && criteria.assignedUser) {
		next.assignedUser = criteria.assignedUser
	}
	if (enabledCriteria.assignedGroup && criteria.assignedGroup) {
		next.assignedGroup = criteria.assignedGroup
	}
	if (enabledCriteria.typeId && criteria.typeId) {
		next.typeId = Number(criteria.typeId)
	}
	if (enabledCriteria.city && criteria.city.trim()) {
		next.city = criteria.city.trim()
	}
	if (enabledCriteria.text && criteria.text.trim()) {
		next.text = criteria.text.trim()
	}
	if (enabledCriteria.updatedWithinDays && criteria.updatedWithinDays) {
		next.updatedWithinDays = Number(criteria.updatedWithinDays)
	}
	if (enabledCriteria.unassigned && criteria.unassigned) {
		next.unassigned = true
	}
	return next
})
const activeFilterChips = computed<FilterChip[]>(() => {
	const chips: FilterChip[] = []
	if (enabledCriteria.status && criteria.status.length > 0) {
		chips.push({ key: 'status', label: `Estado: ${formatStatusList(criteria.status)}` })
	}
	if (enabledCriteria.assignedUser && criteria.assignedUser) {
		chips.push({ key: 'assignedUser', label: `Usuario: ${formatAssignedUser(criteria.assignedUser)}` })
	}
	if (enabledCriteria.assignedGroup && criteria.assignedGroup) {
		chips.push({ key: 'assignedGroup', label: `Grupo: ${formatAssignedGroup(criteria.assignedGroup)}` })
	}
	if (enabledCriteria.typeId && criteria.typeId) {
		chips.push({ key: 'typeId', label: `Tipo: ${formatType(criteria.typeId)}` })
	}
	if (enabledCriteria.city && criteria.city.trim()) {
		chips.push({ key: 'city', label: `Ciudad: ${criteria.city.trim()}` })
	}
	if (enabledCriteria.text && criteria.text.trim()) {
		chips.push({ key: 'text', label: `Texto: ${criteria.text.trim()}` })
	}
	if (enabledCriteria.updatedWithinDays && criteria.updatedWithinDays) {
		chips.push({ key: 'updatedWithinDays', label: `Ultimos ${criteria.updatedWithinDays} dias` })
	}
	if (enabledCriteria.unassigned && criteria.unassigned) {
		chips.push({ key: 'unassigned', label: 'Sin asignar' })
	}
	return chips
})

watch(activeCriteria, (value) => emit('apply', value), { deep: true, immediate: true })

function flattenTypes(types: TypeNode[], prefix = ''): Array<{ id: number, label: string }> {
	return types.flatMap((item) => {
		const label = prefix ? `${prefix} > ${item.name}` : item.name
		return [{ id: item.id, label }, ...flattenTypes(item.children, label)]
	})
}

function toggleDraftStatus(statusId: string, checked: boolean) {
	draftCriteria.status = checked
		? Array.from(new Set([...draftCriteria.status, statusId]))
		: draftCriteria.status.filter((item) => item !== statusId)
}

function clearCriterionValue(key: CriteriaKey) {
	if (key === 'status') {
		criteria.status = []
	} else if (key === 'assignedUser') {
		criteria.assignedUser = ''
	} else if (key === 'assignedGroup') {
		criteria.assignedGroup = ''
	} else if (key === 'typeId') {
		criteria.typeId = ''
	} else if (key === 'city') {
		criteria.city = ''
	} else if (key === 'text') {
		criteria.text = ''
	} else if (key === 'updatedWithinDays') {
		criteria.updatedWithinDays = ''
	} else {
		criteria.unassigned = false
	}
}

function resetDraft() {
	draftCriteria.status = []
	draftCriteria.assignedUser = ''
	draftCriteria.assignedGroup = ''
	draftCriteria.typeId = ''
	draftCriteria.city = ''
	draftCriteria.text = ''
	draftCriteria.updatedWithinDays = ''
	draftCriteria.unassigned = false
	modalCriterionKey.value = ''
}

function loadDraftForCriterion(key: CriteriaKey) {
	draftCriteria.status = key === 'status' ? [...criteria.status] : []
	draftCriteria.assignedUser = key === 'assignedUser' ? criteria.assignedUser : ''
	draftCriteria.assignedGroup = key === 'assignedGroup' ? criteria.assignedGroup : ''
	draftCriteria.typeId = key === 'typeId' ? criteria.typeId : ''
	draftCriteria.city = key === 'city' ? criteria.city : ''
	draftCriteria.text = key === 'text' ? criteria.text : ''
	draftCriteria.updatedWithinDays = key === 'updatedWithinDays' ? criteria.updatedWithinDays : ''
	draftCriteria.unassigned = key === 'unassigned' ? criteria.unassigned : true
	modalCriterionKey.value = key
}

function openAddCriterionModal() {
	resetDraft()
	modalOpen.value = true
}

function onModalCriterionChange(value: string) {
	if (!value) {
		resetDraft()
		return
	}
	loadDraftForCriterion(value as CriteriaKey)
	modalCriterionKey.value = value as CriteriaKey
}

function closeAddCriterionModal() {
	modalOpen.value = false
	resetDraft()
}

function hasDraftValue(key: CriteriaKey) {
	if (key === 'status') {
		return draftCriteria.status.length > 0
	}
	if (key === 'assignedUser') {
		return Boolean(draftCriteria.assignedUser)
	}
	if (key === 'assignedGroup') {
		return Boolean(draftCriteria.assignedGroup)
	}
	if (key === 'typeId') {
		return Boolean(draftCriteria.typeId)
	}
	if (key === 'city') {
		return Boolean(draftCriteria.city.trim())
	}
	if (key === 'text') {
		return Boolean(draftCriteria.text.trim())
	}
	if (key === 'updatedWithinDays') {
		return Boolean(draftCriteria.updatedWithinDays)
	}
	return draftCriteria.unassigned
}

function applyDraftCriterion() {
	if (!modalCriterionKey.value || !hasDraftValue(modalCriterionKey.value)) {
		return
	}

	const key = modalCriterionKey.value
	enabledCriteria[key] = true
	if (key === 'status') {
		criteria.status = [...draftCriteria.status]
	} else if (key === 'assignedUser') {
		criteria.assignedUser = draftCriteria.assignedUser
	} else if (key === 'assignedGroup') {
		criteria.assignedGroup = draftCriteria.assignedGroup
	} else if (key === 'typeId') {
		criteria.typeId = draftCriteria.typeId
	} else if (key === 'city') {
		criteria.city = draftCriteria.city.trim()
	} else if (key === 'text') {
		criteria.text = draftCriteria.text.trim()
	} else if (key === 'updatedWithinDays') {
		criteria.updatedWithinDays = draftCriteria.updatedWithinDays
	} else {
		criteria.unassigned = true
	}

	closeAddCriterionModal()
	selectedPredefined.value = ''
	selectedCustom.value = ''
	if (key === 'unassigned') {
		criteria.assignedUser = ''
		criteria.assignedGroup = ''
	}
	if (key === 'assignedUser' || key === 'assignedGroup') {
		enabledCriteria.unassigned = false
		criteria.unassigned = false
	}
	closeAddCriterionModal()
}

function removeCriterion(key: CriteriaKey) {
	enabledCriteria[key] = false
	clearCriterionValue(key)
	selectedPredefined.value = ''
	selectedCustom.value = ''
}

function resetBuilder() {
	criteria.status = []
	criteria.assignedUser = ''
	criteria.assignedGroup = ''
	criteria.typeId = ''
	criteria.city = ''
	criteria.text = ''
	criteria.updatedWithinDays = ''
	criteria.unassigned = false
	for (const key of Object.keys(enabledCriteria) as CriteriaKey[]) {
		enabledCriteria[key] = false
	}
	saveName.value = ''
	selectedPredefined.value = ''
	selectedCustom.value = ''
	closeAddCriterionModal()
	resetDraft()
}

function applyFilter(filter: SavedFilter) {
	resetBuilder()
	const nextCriteria = filter.criteria ?? {}
	for (const [key, rawValue] of Object.entries(nextCriteria)) {
		if (!(key in enabledCriteria)) {
			continue
		}
		enabledCriteria[key as CriteriaKey] = true
		if (key === 'status') {
			criteria.status = Array.isArray(rawValue) ? rawValue.map(String) : String(rawValue).split(',').map((item: string) => item.trim()).filter(Boolean)
		} else if (key === 'assignedUser') {
			criteria.assignedUser = String(rawValue)
		} else if (key === 'assignedGroup') {
			criteria.assignedGroup = String(rawValue)
		} else if (key === 'typeId') {
			criteria.typeId = String(rawValue)
		} else if (key === 'city') {
			criteria.city = String(rawValue)
		} else if (key === 'text') {
			criteria.text = String(rawValue)
		} else if (key === 'updatedWithinDays') {
			criteria.updatedWithinDays = String(rawValue)
		} else if (key === 'unassigned') {
			criteria.unassigned = Boolean(rawValue)
		}
	}
	saveName.value = filter.isPredefined ? `Copia de ${filter.name}` : filter.name
	selectedPredefined.value = filter.isPredefined ? String(filter.id) : ''
	selectedCustom.value = filter.isPredefined ? '' : String(filter.id)
}

function saveCurrentFilter() {
	emit('save', {
		name: saveName.value.trim() || 'Nuevo filtro',
		criteria: activeCriteria.value,
	})
	selectedPredefined.value = ''
	selectedCustom.value = ''
}

function onPredefinedChange() {
	const filter = predefinedFilters.value.find((item: SavedFilter) => item.id === Number(selectedPredefined.value))
	if (filter) {
		applyFilter(filter)
	}
	selectedCustom.value = ''
}

function onCustomChange() {
	const filter = customFilters.value.find((item: SavedFilter) => item.id === Number(selectedCustom.value))
	if (filter) {
		applyFilter(filter)
	}
	selectedPredefined.value = ''
}

function onPredefinedSelect(value: string | number | null) {
	selectedPredefined.value = value ? String(value) : ''
	onPredefinedChange()
}

function onCustomSelect(value: string | number | null) {
	selectedCustom.value = value ? String(value) : ''
	onCustomChange()
}

function onDeleteSelect(value: string | number | null) {
	if (!value) {
		return
	}
	emit('delete', Number(value))
}

function onDraftAssignedUserSelect(value: string | number | null) {
	draftCriteria.assignedUser = value ? String(value) : ''
}

function onDraftAssignedGroupSelect(value: string | number | null) {
	draftCriteria.assignedGroup = value ? String(value) : ''
}

function onDraftTypeSelect(value: string | number | null) {
	draftCriteria.typeId = value ? String(value) : ''
}

function formatStatusList(statusIds: string[]) {
	return statusIds.map((statusId) => statusLabelMap.value.get(statusId) ?? statusId).join(', ')
}

function formatAssignedUser(userId: string) {
	if (userId === '__me__') {
		return 'Asignadas a mi'
	}
	return userLabelMap.value.get(userId) ?? userId
}

function formatAssignedGroup(groupId: string) {
	if (groupId === '__my_groups__') {
		return 'Mis grupos'
	}
	return groupLabelMap.value.get(groupId) ?? groupId
}

function formatType(typeId: string) {
	const selected = typeOptions.value.find((item) => item.id === Number(typeId))
	return selected?.label ?? typeId
}
</script>

<template>
	<section class="gi-filter-panel">
		<div class="gi-filter-panel__top">
			<div>
				<p class="gi-kicker">Soporte</p>
				<h2>Filtros compactos</h2>
			</div>
			<div class="gi-filter-panel__actions">
				<input v-model="saveName" class="gi-input gi-input--compact" placeholder="Nombre del filtro" />
				<button class="gi-secondary-button" type="button" @click="saveCurrentFilter">Guardar filtro</button>
				<button class="gi-ghost-button" type="button" @click="resetBuilder">Limpiar</button>
			</div>
		</div>

		<div class="gi-filter-toolbar">
			<label class="gi-field gi-filter-toolbar__field">
				<span>Predefinidos</span>
				<SearchableSelect :model-value="selectedPredefined || null" :options="predefinedFilterOptions" placeholder="Selecciona un filtro" @update:modelValue="onPredefinedSelect" />
			</label>

			<label v-if="customFilters.length > 0" class="gi-field gi-filter-toolbar__field">
				<span>Guardados</span>
				<SearchableSelect :model-value="selectedCustom || null" :options="customFilterOptions" placeholder="Selecciona un filtro" @update:modelValue="onCustomSelect" />
			</label>

			<div class="gi-field gi-filter-toolbar__field gi-filter-toolbar__action-field">
				<span>Anadir filtro</span>
				<button class="gi-secondary-button gi-filter-toolbar__button" type="button" @click="openAddCriterionModal">Abrir selector</button>
			</div>
		</div>

		<div class="gi-filter-chip-bar">
			<span class="gi-filter-chip-bar__label">Aplicando</span>
			<div class="gi-filter-chip-bar__items">
				<div v-if="activeFilterChips.length === 0" class="gi-filter-chip gi-filter-chip--empty">
					<span>Sin filtros activos</span>
				</div>
				<div v-for="chip in activeFilterChips" :key="chip.key" class="gi-filter-chip">
					<span class="gi-filter-chip__text">{{ chip.label }}</span>
					<button class="gi-filter-chip__remove" type="button" :aria-label="`Quitar ${chip.label}`" @click="removeCriterion(chip.key)">x</button>
				</div>
			</div>
		</div>

		<div v-if="customFilters.length > 0" class="gi-filter-delete-row">
			<label class="gi-field">
				<span>Eliminar filtro guardado</span>
				<SearchableSelect :model-value="null" :options="customFilterOptions" placeholder="Selecciona un filtro" @update:modelValue="onDeleteSelect" />
			</label>
		</div>

		<div v-if="modalOpen" class="gi-filter-modal-backdrop" @click.self="closeAddCriterionModal">
			<section class="gi-filter-modal" aria-label="Anadir filtro">
				<header class="gi-filter-modal__header">
					<div>
						<h3>Anadir filtro</h3>
						<p>Selecciona el criterio y asigna un valor antes de incorporarlo a la linea activa.</p>
					</div>
					<button class="gi-ghost-button" type="button" @click="closeAddCriterionModal">Cerrar</button>
				</header>

				<label class="gi-field">
					<span>Tipo de filtro</span>
					<SearchableSelect :model-value="modalCriterionKey || null" :options="criterionTypeOptions" placeholder="Selecciona un criterio" @update:modelValue="onModalCriterionChange(String($event ?? ''))" />
				</label>

				<div v-if="modalCriterionKey" class="gi-filter-modal__body">
					<div v-if="modalCriterionKey === 'status'" class="gi-option-grid gi-option-grid--compact">
						<label v-for="status in props.statuses" :key="status.id" class="gi-check-tile">
							<input :checked="draftCriteria.status.includes(status.id)" type="checkbox" @change="toggleDraftStatus(status.id, ($event.target as HTMLInputElement).checked)" />
							<span>{{ status.label }}</span>
						</label>
					</div>

					<label v-else-if="modalCriterionKey === 'assignedUser'" class="gi-field">
						<span>Usuario asignado</span>
						<SearchableSelect :model-value="draftCriteria.assignedUser || null" :options="draftUserOptions" placeholder="Selecciona un usuario" @update:modelValue="onDraftAssignedUserSelect" />
					</label>

					<label v-else-if="modalCriterionKey === 'assignedGroup'" class="gi-field">
						<span>Grupo asignado</span>
						<SearchableSelect :model-value="draftCriteria.assignedGroup || null" :options="draftGroupOptions" placeholder="Selecciona un grupo" @update:modelValue="onDraftAssignedGroupSelect" />
					</label>

					<label v-else-if="modalCriterionKey === 'typeId'" class="gi-field">
						<span>Tipo</span>
						<SearchableSelect :model-value="draftCriteria.typeId || null" :options="typeSelectOptions" placeholder="Selecciona un tipo" @update:modelValue="onDraftTypeSelect" />
					</label>

					<label v-else-if="modalCriterionKey === 'city'" class="gi-field">
						<span>Ciudad</span>
						<input v-model="draftCriteria.city" class="gi-input" placeholder="Ej. Madrid" />
					</label>

					<label v-else-if="modalCriterionKey === 'text'" class="gi-field">
						<span>Texto libre</span>
						<input v-model="draftCriteria.text" class="gi-input" placeholder="Titulo, descripcion o seguimiento" />
					</label>

					<label v-else-if="modalCriterionKey === 'updatedWithinDays'" class="gi-field">
						<span>Ultimos dias</span>
						<input v-model="draftCriteria.updatedWithinDays" class="gi-input" inputmode="numeric" placeholder="30" />
					</label>

					<label v-else class="gi-switch-row gi-switch-row--modal">
						<input v-model="draftCriteria.unassigned" type="checkbox" />
						<span>Mostrar solo tickets sin usuario ni grupo asignado</span>
					</label>
				</div>

				<footer class="gi-filter-modal__footer">
					<button class="gi-ghost-button" type="button" @click="closeAddCriterionModal">Cancelar</button>
					<button class="gi-primary-button" type="button" :disabled="!modalCriterionKey || !hasDraftValue(modalCriterionKey)" @click="applyDraftCriterion">Anadir</button>
				</footer>
			</section>
		</div>
	</section>
</template>

<style scoped>
.gi-filter-panel {
	display: grid;
	gap: .9rem;
	padding: 1rem 1.1rem;
	border-radius: 24px;
	background: rgba(255, 255, 255, .92);
	border: 1px solid rgba(49, 96, 91, .12);
	box-shadow: 0 20px 48px rgba(34, 62, 55, .08);
	margin-bottom: 1.2rem;
}

.gi-filter-panel__top,
.gi-filter-panel__actions,
.gi-filter-modal__header,
.gi-filter-modal__footer,
.gi-filter-chip,
.gi-filter-chip-bar {
	display: flex;
	gap: .75rem;
}

.gi-filter-panel__top,
.gi-filter-modal__header,
.gi-filter-modal__footer,
.gi-filter-chip,
.gi-filter-chip-bar {
	align-items: center;
	justify-content: space-between;
}

.gi-filter-panel__actions {
	align-items: center;
	flex-wrap: wrap;
}

.gi-filter-toolbar {
	display: grid;
	gap: .85rem;
	grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
	align-items: end;
}

.gi-filter-toolbar__field {
	min-width: 0;
}

.gi-filter-toolbar__action-field {
	align-self: stretch;
}

.gi-filter-toolbar__button {
	width: 100%;
	justify-content: center;
}

.gi-filter-chip-bar {
	align-items: flex-start;
	gap: .9rem;
	padding: .8rem .9rem;
	border-radius: 18px;
	background: rgba(242, 246, 243, .92);
	border: 1px solid rgba(49, 96, 91, .1);
}

.gi-filter-chip-bar__label {
	font-size: .82rem;
	font-weight: 700;
	letter-spacing: .04em;
	text-transform: uppercase;
	color: #4b645d;
	padding-top: .35rem;
	white-space: nowrap;
}

.gi-filter-chip-bar__items {
	display: flex;
	gap: .5rem;
	flex-wrap: wrap;
	min-width: 0;
	flex: 1;
}

.gi-filter-chip {
	justify-content: flex-start;
	max-width: 100%;
	padding: .45rem .55rem .45rem .8rem;
	border-radius: 999px;
	background: rgba(49, 96, 91, .1);
	color: #214f45;
	min-height: 2.2rem;
}

.gi-filter-chip--empty {
	background: rgba(49, 96, 91, .05);
	color: #5f726b;
}

.gi-filter-chip__text {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	max-width: 100%;
}

.gi-filter-chip__remove {
	border: none;
	background: rgba(33, 79, 69, .12);
	color: #214f45;
	width: 1.6rem;
	height: 1.6rem;
	border-radius: 999px;
	cursor: pointer;
	font: inherit;
	line-height: 1;
	padding: 0;
	flex: none;
}

.gi-filter-delete-row {
	max-width: 22rem;
}

.gi-option-grid {
	display: grid;
	gap: .6rem;
	grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
}

.gi-option-grid--compact {
	grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
}

.gi-check-tile,
.gi-switch-row {
	display: flex;
	align-items: center;
	gap: .65rem;
	padding: .7rem .8rem;
	border-radius: 14px;
	background: rgba(226, 235, 232, .66);
}

.gi-switch-row--modal {
	margin-top: .35rem;
}

.gi-inline-action,
.gi-ghost-button {
	border: none;
	background: transparent;
	color: #255d52;
	font: inherit;
	cursor: pointer;
	padding: 0;
}

.gi-input--compact {
	min-width: 14rem;
	max-width: 18rem;
}

.gi-filter-modal-backdrop {
	position: fixed;
	inset: 0;
	background: rgba(24, 38, 34, .34);
	display: grid;
	place-items: center;
	padding: 1.2rem;
	z-index: 80;
}

.gi-filter-modal {
	width: min(42rem, 100%);
	display: grid;
	gap: 1rem;
	padding: 1.15rem;
	border-radius: 24px;
	background: rgba(255, 255, 255, .99);
	box-shadow: 0 24px 64px rgba(20, 34, 30, .18);
	border: 1px solid rgba(49, 96, 91, .14);
}

.gi-filter-modal__header {
	align-items: flex-start;
}

.gi-filter-modal__header h3,
.gi-filter-modal__header p {
	margin: 0;
}

.gi-filter-modal__header p {
	margin-top: .3rem;
	color: #5f726b;
}

.gi-filter-modal__body {
	display: grid;
	gap: .85rem;
}

.gi-filter-modal__footer {
	justify-content: flex-end;
	flex-wrap: wrap;
}

@media (max-width: 900px) {
	.gi-filter-panel__top,
	.gi-filter-chip-bar,
	.gi-filter-modal__header {
		align-items: flex-start;
		flex-direction: column;
	}

	.gi-filter-panel__actions,
	.gi-filter-modal__footer {
		width: 100%;
	}

	.gi-input--compact {
		max-width: none;
	}

	.gi-filter-delete-row {
		max-width: none;
	}

	.gi-filter-modal {
		padding: 1rem;
	}
	}
</style>
