<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import type { AssignableOption, SavedFilter, SearchableSelectOption, StatusOption, TypeNode } from '@/types'
import SearchableSelect from './SearchableSelect.vue'

type CriteriaKey = 'status' | 'assignedUser' | 'assignedGroup' | 'typeId' | 'city' | 'text' | 'updatedWithinDays' | 'unassigned' | 'hasAttachments'

type FilterCriteriaState = {
	status: string[]
	assignedUser: string
	assignedGroup: string
	typeId: string
	city: string
	text: string
	updatedWithinDays: string
	unassigned: boolean
	hasAttachments: boolean
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
	initialFilterId?: number | null
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
	hasAttachments: false,
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
	hasAttachments: false,
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
	hasAttachments: false,
})

const saveName = ref('')
const selectedFilter = ref('')
const modalOpen = ref(false)
const modalCriterionKey = ref<CriteriaKey | ''>('')
const saveModalOpen = ref(false)
const saveModalError = ref('')
const deleteModalOpen = ref(false)
const overwriteCandidateId = ref<number | null>(null)
const initializedDefault = ref(false)

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
	{ key: 'hasAttachments', label: 'Documentos adjuntos' },
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
const filterOptions = computed<SearchableSelectOption[]>(() => safeFilters.value.map((filter: SavedFilter) => ({
	value: String(filter.id),
	label: filter.name,
	searchText: `${filter.name} ${filter.isPredefined ? 'predefinido' : 'guardado'}`,
})))
const selectedSavedFilter = computed(() => safeFilters.value.find((item: SavedFilter) => item.id === Number(selectedFilter.value)) ?? null)
const canDeleteSelectedFilter = computed(() => Boolean(selectedSavedFilter.value && !selectedSavedFilter.value.isPredefined))
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
	if (enabledCriteria.hasAttachments && criteria.hasAttachments) {
		next.hasAttachments = true
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
	if (enabledCriteria.hasAttachments && criteria.hasAttachments) {
		chips.push({ key: 'hasAttachments', label: 'Con adjuntos' })
	}
	return chips
})

watch(activeCriteria, (value) => emit('apply', value), { deep: true, immediate: true })

watch(() => [props.initialFilterId, safeFilters.value], ([initialFilterId]) => {
	if (initializedDefault.value || !initialFilterId) {
		return
	}

	const filter = safeFilters.value.find((item: SavedFilter) => item.id === Number(initialFilterId))
	if (!filter) {
		return
	}

	initializedDefault.value = true
	applyFilter(filter)
}, { deep: true, immediate: true })

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
	} else if (key === 'hasAttachments') {
		criteria.hasAttachments = false
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
	draftCriteria.hasAttachments = false
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
	draftCriteria.hasAttachments = key === 'hasAttachments' ? criteria.hasAttachments : true
	modalCriterionKey.value = key
}

function openAddCriterionModal() {
	resetDraft()
	modalOpen.value = true
}

function openSaveModal() {
	saveModalError.value = ''
	overwriteCandidateId.value = null
	if (!saveName.value.trim()) {
		saveName.value = getSuggestedSaveName()
	}
	saveModalOpen.value = true
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

function closeSaveModal() {
	saveModalOpen.value = false
	saveModalError.value = ''
	overwriteCandidateId.value = null
}

function openDeleteModal() {
	if (!selectedSavedFilter.value || selectedSavedFilter.value.isPredefined) {
		return
	}
	deleteModalOpen.value = true
}

function closeDeleteModal() {
	deleteModalOpen.value = false
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
	if (key === 'hasAttachments') {
		return draftCriteria.hasAttachments
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
	} else if (key === 'hasAttachments') {
		criteria.hasAttachments = true
	} else {
		criteria.unassigned = true
	}

	closeAddCriterionModal()
	selectedFilter.value = ''
	if (key === 'unassigned') {
		criteria.assignedUser = ''
		criteria.assignedGroup = ''
	}
	if (key === 'assignedUser' || key === 'assignedGroup') {
		enabledCriteria.unassigned = false
		criteria.unassigned = false
	}
}

function removeCriterion(key: CriteriaKey) {
	enabledCriteria[key] = false
	clearCriterionValue(key)
	selectedFilter.value = ''
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
	criteria.hasAttachments = false
	for (const key of Object.keys(enabledCriteria) as CriteriaKey[]) {
		enabledCriteria[key] = false
	}
	saveName.value = ''
	selectedFilter.value = ''
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
		} else if (key === 'hasAttachments') {
			criteria.hasAttachments = Boolean(rawValue)
		} else if (key === 'unassigned') {
			criteria.unassigned = Boolean(rawValue)
		}
	}
	saveName.value = filter.isPredefined ? `Copia de ${filter.name}` : filter.name
	selectedFilter.value = String(filter.id)
}

function saveCurrentFilter(overwrite = false) {
	const normalizedName = saveName.value.trim()
	if (normalizedName === '') {
		saveModalError.value = 'Debes asignar un nombre al filtro.'
		return
	}

	const duplicate = safeFilters.value.find((filter: SavedFilter) => normalizeFilterName(filter.name) === normalizeFilterName(normalizedName))
	if (duplicate) {
		if (duplicate.isPredefined) {
			overwriteCandidateId.value = null
			saveModalError.value = 'Ese nombre pertenece a un filtro predefinido y no se puede sobreescribir. Asigna otro nombre.'
			return
		}

		if (duplicate.scopeType !== 'user') {
			overwriteCandidateId.value = null
			saveModalError.value = 'Ese nombre ya existe en un filtro global y no se puede sobreescribir desde soporte. Asigna otro nombre.'
			return
		}

		if (!overwrite || overwriteCandidateId.value !== duplicate.id) {
			overwriteCandidateId.value = duplicate.id
			saveModalError.value = 'Ese nombre ya existe. Si quieres, puedes sobrescribir ese filtro guardado.'
			return
		}
	}

	emit('save', {
		name: normalizedName,
		criteria: activeCriteria.value,
		overwrite,
	})
	selectedFilter.value = ''
	closeSaveModal()
}

function onFilterChange() {
	const filter = safeFilters.value.find((item: SavedFilter) => item.id === Number(selectedFilter.value))
	if (filter) {
		applyFilter(filter)
	}
}

function onFilterSelect(value: string | number | null) {
	selectedFilter.value = value ? String(value) : ''
	onFilterChange()
}

function deleteSelectedFilter() {
	if (!selectedSavedFilter.value || selectedSavedFilter.value.isPredefined) {
		return
	}
	emit('delete', selectedSavedFilter.value.id)
	selectedFilter.value = ''
	closeDeleteModal()
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

function normalizeFilterName(name: string) {
	return name.trim().toLocaleLowerCase()
}

function getSuggestedSaveName() {
	const activeFilter = safeFilters.value.find((item: SavedFilter) => item.id === Number(selectedFilter.value))
	if (activeFilter) {
		return activeFilter.isPredefined ? `Copia de ${activeFilter.name}` : activeFilter.name
	}

	return 'Nuevo filtro'
}
</script>

<template>
	<section class="gi-filter-panel">
		<div class="gi-filter-toolbar">
			<label class="gi-field gi-filter-toolbar__field">
				<span class="gi-filter-toolbar__label">Filtros guardados</span>
				<SearchableSelect :model-value="selectedFilter || null" :options="filterOptions" placeholder="Selecciona un filtro" @update:modelValue="onFilterSelect" />
			</label>
			<button v-if="canDeleteSelectedFilter" class="gi-filter-toolbar__delete-button" type="button" @click="openDeleteModal">Eliminar filtro</button>
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
				<button class="gi-filter-chip-bar__add" type="button" aria-label="Anadir condicion" @click="openAddCriterionModal">+</button>
			</div>
			<div class="gi-filter-chip-bar__actions">
				<button class="gi-filter-chip-bar__icon-action" type="button" aria-label="Guardar filtro" title="Guardar filtro" @click="openSaveModal">
					<span aria-hidden="true">&#128190;</span>
				</button>
				<button class="gi-ghost-button gi-filter-chip-bar__text-action" type="button" @click="resetBuilder">Limpiar</button>
			</div>
		</div>

		<div v-if="modalOpen" class="gi-filter-modal-backdrop" @click.self="closeAddCriterionModal">
			<section class="gi-filter-modal" aria-label="Anadir filtro">
				<header class="gi-filter-modal__header">
					<h3>Anadir filtro</h3>
					<button class="gi-modal-close" type="button" aria-label="Cerrar ventana" @click="closeAddCriterionModal">x</button>
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

					<label v-else-if="modalCriterionKey === 'hasAttachments'" class="gi-switch-row gi-switch-row--modal">
						<input v-model="draftCriteria.hasAttachments" type="checkbox" />
						<span>Solo tickets con adjuntos o rutas URL</span>
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

		<div v-if="saveModalOpen" class="gi-filter-modal-backdrop" @click.self="closeSaveModal">
			<section class="gi-filter-save-modal" aria-label="Guardar filtro">
				<header class="gi-filter-modal__header">
					<h3>Guardar filtro</h3>
					<button class="gi-modal-close" type="button" aria-label="Cerrar ventana" @click="closeSaveModal">x</button>
				</header>

				<label class="gi-field">
					<span>Nombre del filtro</span>
					<input v-model="saveName" class="gi-input" placeholder="Nombre del filtro" />
				</label>

				<p v-if="saveModalError" class="gi-filter-save-modal__message">{{ saveModalError }}</p>

				<footer class="gi-filter-modal__footer">
					<button class="gi-ghost-button" type="button" @click="closeSaveModal">Cancelar</button>
					<button v-if="overwriteCandidateId !== null" class="gi-secondary-button" type="button" @click="saveCurrentFilter(true)">Sobrescribir</button>
					<button class="gi-primary-button" type="button" @click="saveCurrentFilter(false)">Guardar</button>
				</footer>
			</section>
		</div>

		<div v-if="deleteModalOpen" class="gi-filter-modal-backdrop" @click.self="closeDeleteModal">
			<section class="gi-filter-save-modal" aria-label="Eliminar filtro guardado">
				<header class="gi-filter-modal__header">
					<h3>Eliminar filtro</h3>
					<button class="gi-modal-close" type="button" aria-label="Cerrar ventana" @click="closeDeleteModal">x</button>
				</header>

				<p class="gi-filter-save-modal__message gi-filter-save-modal__message--neutral">
					¿Quieres eliminar <strong>{{ selectedSavedFilter?.name }}</strong>?
				</p>

				<footer class="gi-filter-modal__footer">
					<button class="gi-ghost-button" type="button" @click="closeDeleteModal">Cancelar</button>
					<button class="gi-secondary-button gi-filter-save-modal__danger" type="button" @click="deleteSelectedFilter">Eliminar</button>
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

.gi-filter-panel__actions,
.gi-filter-modal__header,
.gi-filter-modal__footer,
.gi-filter-chip,
.gi-filter-chip-bar {
	display: flex;
	gap: .75rem;
}

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
	display: flex;
	gap: .75rem;
	align-items: flex-end;
	flex-wrap: wrap;
}

.gi-filter-toolbar__field {
	min-width: 0;
	flex: 1 1 22rem;
}

.gi-filter-toolbar__delete-button {
	flex: 0 0 auto;
	min-height: 2.25rem;
	padding: .42rem .9rem;
	border: none;
	border-radius: 999px;
	background: rgba(148, 55, 31, .1);
	color: #8f391d;
	font: inherit;
	cursor: pointer;
}

.gi-filter-toolbar__label {
	margin: 0;
	font-size: .78rem;
	font-weight: 700;
	letter-spacing: .04em;
	text-transform: uppercase;
	color: #4b645d;
	white-space: nowrap;
}

.gi-filter-toolbar__field :deep(.gi-search-select) {
	flex: 1 1 auto;
}

.gi-filter-toolbar__field :deep(.gi-search-select__trigger) {
	min-height: 2.25rem;
	padding: .42rem .7rem;
	border-radius: 999px;
	font-size: .94rem;
}

.gi-filter-chip-bar {
	align-items: center;
	gap: .75rem;
	padding: .55rem .75rem;
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
	white-space: nowrap;
}

.gi-filter-chip-bar__items {
	display: flex;
	gap: .5rem;
	flex-wrap: wrap;
	align-items: center;
	min-width: 0;
	flex: 1;
}

.gi-filter-chip-bar__actions {
	display: flex;
	gap: .55rem;
	align-items: center;
	flex: none;
}

.gi-filter-chip {
	justify-content: flex-start;
	max-width: 100%;
	padding: .3rem .48rem .3rem .7rem;
	border-radius: 999px;
	background: rgba(49, 96, 91, .1);
	color: #214f45;
	min-height: 2rem;
	font-size: .88rem;
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

.gi-filter-chip-bar__add {
	width: 2rem;
	height: 2rem;
	border: 1px dashed rgba(33, 79, 69, .2);
	background: rgba(255, 255, 255, .86);
	color: #214f45;
	border-radius: 999px;
	cursor: pointer;
	font: inherit;
	font-size: 1.05rem;
	line-height: 1;
	padding: 0;
	flex: none;
}

.gi-filter-chip-bar__icon-action {
	width: 2rem;
	height: 2rem;
	display: inline-grid;
	place-items: center;
	border: 1px solid rgba(33, 79, 69, .18);
	background: rgba(255, 255, 255, .9);
	color: #214f45;
	border-radius: 999px;
	cursor: pointer;
	font: inherit;
	line-height: 1;
	padding: 0;
}

.gi-filter-chip-bar__text-action {
	white-space: nowrap;
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

.gi-modal-close {
	width: 2rem;
	height: 2rem;
	display: inline-grid;
	place-items: center;
	border: 1px solid rgba(33, 79, 69, .18);
	border-radius: 999px;
	background: rgba(255, 255, 255, .9);
	color: #255d52;
	font: inherit;
	line-height: 1;
	padding: 0;
	cursor: pointer;
	flex: none;
}

.gi-filter-modal-backdrop {
	position: fixed;
	inset: 0;
	background: rgba(24, 38, 34, .34);
	display: grid;
	place-items: center;
	padding: 1rem;
	z-index: 80;
}

.gi-filter-modal {
	width: min(40rem, 100%);
	min-height: min(32rem, calc(100vh - 2rem));
	max-height: calc(100vh - 2rem);
	overflow: auto;
	display: grid;
	gap: 1rem;
	padding: 1rem;
	border-radius: 22px;
	background: rgba(255, 255, 255, .99);
	box-shadow: 0 24px 64px rgba(20, 34, 30, .18);
}

.gi-filter-save-modal {
	width: min(28rem, 100%);
	min-height: min(20rem, calc(100vh - 2rem));
	max-height: calc(100vh - 2rem);
	overflow: auto;
	display: grid;
	gap: 1rem;
	padding: 1rem;
	border-radius: 22px;
	background: rgba(255, 255, 255, .99);
	box-shadow: 0 24px 64px rgba(20, 34, 30, .18);
}

.gi-filter-save-modal__message {
	margin: 0;
	color: #7b3d23;
	font-weight: 600;
}

.gi-filter-save-modal__message--neutral {
	color: #2b4c44;
}

.gi-filter-save-modal__danger {
	background: rgba(148, 55, 31, .12);
	color: #8f391d;
}

.gi-filter-modal__header h3 {
	margin: 0;
}

.gi-filter-modal__body {
	display: grid;
	gap: .8rem;
}

@media (max-width: 900px) {
	.gi-filter-toolbar {
		align-items: stretch;
	}

	.gi-filter-toolbar__field {
		flex: 1 1 auto;
	}

	.gi-filter-modal__header,
	.gi-filter-modal__footer,
	.gi-filter-chip-bar,
	.gi-filter-panel__actions {
		flex-direction: column;
		align-items: stretch;
	}

	.gi-filter-chip-bar__items {
		width: 100%;
	}

	.gi-filter-chip-bar__actions {
		width: 100%;
		justify-content: flex-end;
	}

}
</style>
