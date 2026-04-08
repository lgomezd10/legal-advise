<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import type { AssignableOption, SavedFilter, SearchableSelectOption, StatusOption, TypeNode } from '@/types'
import SearchableSelect from './SearchableSelect.vue'

type CriteriaKey = 'status' | 'createdBy' | 'assignedUser' | 'assignedGroup' | 'typeId' | 'city' | 'text' | 'updatedWithinDays' | 'createdAt' | 'updatedAt' | 'unassigned' | 'hasAttachments'

type FilterCriteriaState = {
	status: string[]
	createdBy: string
	assignedUser: string
	assignedGroup: string
	typeId: string
	city: string
	text: string
	updatedWithinDays: string
	createdAtFrom: string
	createdAtTo: string
	updatedAtFrom: string
	updatedAtTo: string
	unassigned: boolean
	hasAttachments: boolean
}

type FilterChip = {
	key: CriteriaKey
	label: string
}

type CriteriaNegationState = Record<CriteriaKey, boolean>

const props = defineProps<{
	filters: SavedFilter[]
	statuses: StatusOption[]
	types: TypeNode[]
	users: AssignableOption[]
	groups: AssignableOption[]
	initialFilterId?: number | null
	initialCriteria?: Record<string, unknown> | null
}>()

const emit = defineEmits<{
	(e: 'apply', criteria: Record<string, unknown>, selectedFilterId?: number | null): void
	(e: 'save', payload: Record<string, unknown>): void
	(e: 'delete', id: number): void
}>()

const criteria = reactive<FilterCriteriaState>({
	status: [],
	createdBy: '',
	assignedUser: '',
	assignedGroup: '',
	typeId: '',
	city: '',
	text: '',
	updatedWithinDays: '',
	createdAtFrom: '',
	createdAtTo: '',
	updatedAtFrom: '',
	updatedAtTo: '',
	unassigned: false,
	hasAttachments: false,
})

const enabledCriteria = reactive<Record<CriteriaKey, boolean>>({
	status: false,
	createdBy: false,
	assignedUser: false,
	assignedGroup: false,
	typeId: false,
	city: false,
	text: false,
	updatedWithinDays: false,
	createdAt: false,
	updatedAt: false,
	unassigned: false,
	hasAttachments: false,
})

const draftCriteria = reactive<FilterCriteriaState>({
	status: [],
	createdBy: '',
	assignedUser: '',
	assignedGroup: '',
	typeId: '',
	city: '',
	text: '',
	updatedWithinDays: '',
	createdAtFrom: '',
	createdAtTo: '',
	updatedAtFrom: '',
	updatedAtTo: '',
	unassigned: false,
	hasAttachments: false,
})

const criteriaNegation = reactive<CriteriaNegationState>({
	status: false,
	createdBy: false,
	assignedUser: false,
	assignedGroup: false,
	typeId: false,
	city: false,
	text: false,
	updatedWithinDays: false,
	createdAt: false,
	updatedAt: false,
	unassigned: false,
	hasAttachments: false,
})

const draftNegation = reactive<CriteriaNegationState>({
	status: false,
	createdBy: false,
	assignedUser: false,
	assignedGroup: false,
	typeId: false,
	city: false,
	text: false,
	updatedWithinDays: false,
	createdAt: false,
	updatedAt: false,
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
const initializedStateKey = ref('')
const textSearchInput = ref('')
const suppressApply = ref(false)

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
	{ key: 'createdBy', label: 'Creado por' },
	{ key: 'assignedUser', label: 'Usuario asignado' },
	{ key: 'assignedGroup', label: 'Grupo asignado' },
	{ key: 'typeId', label: 'Tipo' },
	{ key: 'city', label: 'Ciudad' },
	{ key: 'text', label: 'Texto libre' },
	{ key: 'updatedWithinDays', label: 'Fecha reciente' },
	{ key: 'createdAt', label: 'Fecha de creacion' },
	{ key: 'updatedAt', label: 'Fecha de ultima modificacion' },
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
const createdByOptions = computed<SearchableSelectOption[]>(() => safeUsers.value.map((user: AssignableOption) => ({ value: user.id, label: user.displayName, searchText: [user.id, ...(user.groupIds ?? [])].join(' ') })))
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
	if (enabledCriteria.createdBy && criteria.createdBy) {
		next.createdBy = criteria.createdBy
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
	if (enabledCriteria.createdAt) {
		if (criteria.createdAtFrom) {
			next.createdAtFrom = criteria.createdAtFrom
		}
		if (criteria.createdAtTo) {
			next.createdAtTo = criteria.createdAtTo
		}
	}
	if (enabledCriteria.updatedAt) {
		if (criteria.updatedAtFrom) {
			next.updatedAtFrom = criteria.updatedAtFrom
		}
		if (criteria.updatedAtTo) {
			next.updatedAtTo = criteria.updatedAtTo
		}
	}
	if (enabledCriteria.unassigned && criteria.unassigned) {
		next.unassigned = true
	}
	if (enabledCriteria.hasAttachments && criteria.hasAttachments) {
		next.hasAttachments = true
	}
	const negatedCriteria = (Object.keys(criteriaNegation) as CriteriaKey[]).filter((key) => enabledCriteria[key] && criteriaNegation[key])
	if (negatedCriteria.length > 0) {
		next.negatedCriteria = negatedCriteria
	}
	return next
})
const activeFilterChips = computed<FilterChip[]>(() => {
	const chips: FilterChip[] = []
	if (enabledCriteria.status && criteria.status.length > 0) {
		chips.push({ key: 'status', label: withNegationLabel('status', `Estado: ${formatStatusList(criteria.status)}`) })
	}
	if (enabledCriteria.createdBy && criteria.createdBy) {
		chips.push({ key: 'createdBy', label: withNegationLabel('createdBy', `Creado por: ${formatAssignedUser(criteria.createdBy)}`) })
	}
	if (enabledCriteria.assignedUser && criteria.assignedUser) {
		chips.push({ key: 'assignedUser', label: withNegationLabel('assignedUser', `Usuario: ${formatAssignedUser(criteria.assignedUser)}`) })
	}
	if (enabledCriteria.assignedGroup && criteria.assignedGroup) {
		chips.push({ key: 'assignedGroup', label: withNegationLabel('assignedGroup', `Grupo: ${formatAssignedGroup(criteria.assignedGroup)}`) })
	}
	if (enabledCriteria.typeId && criteria.typeId) {
		chips.push({ key: 'typeId', label: withNegationLabel('typeId', `Tipo: ${formatType(criteria.typeId)}`) })
	}
	if (enabledCriteria.city && criteria.city.trim()) {
		chips.push({ key: 'city', label: withNegationLabel('city', `Ciudad: ${criteria.city.trim()}`) })
	}
	if (enabledCriteria.text && criteria.text.trim()) {
		chips.push({ key: 'text', label: withNegationLabel('text', `Texto: ${criteria.text.trim()}`) })
	}
	if (enabledCriteria.updatedWithinDays && criteria.updatedWithinDays) {
		chips.push({ key: 'updatedWithinDays', label: withNegationLabel('updatedWithinDays', `Ultimos ${criteria.updatedWithinDays} dias`) })
	}
	if (enabledCriteria.createdAt && (criteria.createdAtFrom || criteria.createdAtTo)) {
		chips.push({ key: 'createdAt', label: withNegationLabel('createdAt', `Creacion: ${formatDateRange(criteria.createdAtFrom, criteria.createdAtTo)}`) })
	}
	if (enabledCriteria.updatedAt && (criteria.updatedAtFrom || criteria.updatedAtTo)) {
		chips.push({ key: 'updatedAt', label: withNegationLabel('updatedAt', `Ultima modificacion: ${formatDateRange(criteria.updatedAtFrom, criteria.updatedAtTo)}`) })
	}
	if (enabledCriteria.unassigned && criteria.unassigned) {
		chips.push({ key: 'unassigned', label: withNegationLabel('unassigned', 'Sin asignar') })
	}
	if (enabledCriteria.hasAttachments && criteria.hasAttachments) {
		chips.push({ key: 'hasAttachments', label: withNegationLabel('hasAttachments', 'Con adjuntos') })
	}
	return chips
})

watch(activeCriteria, (value) => {
	if (suppressApply.value) {
		return
	}

	emit('apply', value, selectedFilter.value ? Number(selectedFilter.value) : null)
}, { deep: true, immediate: true })

watch(textSearchInput, (value) => {
	criteria.text = value
	enabledCriteria.text = value.trim() !== ''
	selectedFilter.value = ''
})

watch(() => [props.initialFilterId, props.initialCriteria, safeFilters.value] as const, ([initialFilterId, initialCriteria]) => {
	const normalizedFilterId = initialFilterId ? Number(initialFilterId) : null
	const normalizedCriteria = normalizeCriteria(initialCriteria ?? {})
	const criteriaKey = JSON.stringify(normalizedCriteria)
	const nextStateKey = normalizedFilterId !== null
		? `filter:${normalizedFilterId}`
		: `criteria:${criteriaKey}`

	if (initializedStateKey.value === nextStateKey) {
		return
	}

	if (normalizedFilterId !== null) {
		const filter = safeFilters.value.find((item: SavedFilter) => item.id === normalizedFilterId)
		if (!filter) {
			return
		}

		initializedStateKey.value = nextStateKey
		applyFilter(filter, false)
		return
	}

	if (Object.keys(normalizedCriteria).length === 0) {
		initializedStateKey.value = nextStateKey
		return
	}

	initializedStateKey.value = nextStateKey
	applyCriteria(normalizedCriteria, null, '', false)
}, { deep: true, immediate: true })

function flattenTypes(types: TypeNode[], prefix = ''): Array<{ id: number, label: string }> {
	return types.flatMap((item) => {
		const label = prefix ? `${prefix} > ${item.name}` : item.name
		return [{ id: item.id, label }, ...flattenTypes(item.children, label)]
	})
}

function toggleDraftStatus(statusId: string, checked: boolean) {
	if (checked && !isDraftStatusSelectable(statusId)) {
		return
	}

	draftCriteria.status = checked
		? Array.from(new Set([...draftCriteria.status, statusId]))
		: draftCriteria.status.filter((item) => item !== statusId)
}

function clearCriterionValue(key: CriteriaKey) {
	if (key === 'status') {
		criteria.status = []
	} else if (key === 'createdBy') {
		criteria.createdBy = ''
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
	} else if (key === 'createdAt') {
		criteria.createdAtFrom = ''
		criteria.createdAtTo = ''
	} else if (key === 'updatedAt') {
		criteria.updatedAtFrom = ''
		criteria.updatedAtTo = ''
	} else if (key === 'hasAttachments') {
		criteria.hasAttachments = false
	} else {
		criteria.unassigned = false
	}
}

function resetDraft() {
	draftCriteria.status = []
	draftCriteria.createdBy = ''
	draftCriteria.assignedUser = ''
	draftCriteria.assignedGroup = ''
	draftCriteria.typeId = ''
	draftCriteria.city = ''
	draftCriteria.text = ''
	draftCriteria.updatedWithinDays = ''
	draftCriteria.createdAtFrom = ''
	draftCriteria.createdAtTo = ''
	draftCriteria.updatedAtFrom = ''
	draftCriteria.updatedAtTo = ''
	draftCriteria.unassigned = false
	draftCriteria.hasAttachments = false
	for (const key of Object.keys(draftNegation) as CriteriaKey[]) {
		draftNegation[key] = false
	}
	modalCriterionKey.value = ''
}

function loadDraftForCriterion(key: CriteriaKey) {
	draftCriteria.status = key === 'status' ? [...criteria.status] : []
	draftCriteria.createdBy = key === 'createdBy' ? criteria.createdBy : ''
	draftCriteria.assignedUser = key === 'assignedUser' ? criteria.assignedUser : ''
	draftCriteria.assignedGroup = key === 'assignedGroup' ? criteria.assignedGroup : ''
	draftCriteria.typeId = key === 'typeId' ? criteria.typeId : ''
	draftCriteria.city = key === 'city' ? criteria.city : ''
	draftCriteria.text = key === 'text' ? criteria.text : ''
	draftCriteria.updatedWithinDays = key === 'updatedWithinDays' ? criteria.updatedWithinDays : ''
	draftCriteria.createdAtFrom = key === 'createdAt' ? criteria.createdAtFrom : ''
	draftCriteria.createdAtTo = key === 'createdAt' ? criteria.createdAtTo : ''
	draftCriteria.updatedAtFrom = key === 'updatedAt' ? criteria.updatedAtFrom : ''
	draftCriteria.updatedAtTo = key === 'updatedAt' ? criteria.updatedAtTo : ''
	draftCriteria.unassigned = key === 'unassigned' ? criteria.unassigned : true
	draftCriteria.hasAttachments = key === 'hasAttachments' ? criteria.hasAttachments : true
	for (const draftKey of Object.keys(draftNegation) as CriteriaKey[]) {
		draftNegation[draftKey] = false
	}
	draftNegation[key] = criteriaNegation[key]
	modalCriterionKey.value = key
}

function openAddCriterionModal() {
	resetDraft()
	modalOpen.value = true
}

function editCriterion(key: CriteriaKey) {
	loadDraftForCriterion(key)
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
	if (key === 'createdBy') {
		return Boolean(draftCriteria.createdBy)
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
	if (key === 'createdAt') {
		return Boolean(draftCriteria.createdAtFrom || draftCriteria.createdAtTo)
	}
	if (key === 'updatedAt') {
		return Boolean(draftCriteria.updatedAtFrom || draftCriteria.updatedAtTo)
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
	} else if (key === 'createdBy') {
		criteria.createdBy = draftCriteria.createdBy
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
	} else if (key === 'createdAt') {
		criteria.createdAtFrom = draftCriteria.createdAtFrom
		criteria.createdAtTo = draftCriteria.createdAtTo
	} else if (key === 'updatedAt') {
		criteria.updatedAtFrom = draftCriteria.updatedAtFrom
		criteria.updatedAtTo = draftCriteria.updatedAtTo
	} else if (key === 'hasAttachments') {
		criteria.hasAttachments = true
	} else {
		criteria.unassigned = true
	}
	criteriaNegation[key] = draftNegation[key]

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
	criteriaNegation[key] = false
	clearCriterionValue(key)
	selectedFilter.value = ''
}

function resetBuilder() {
	criteria.status = []
	criteria.createdBy = ''
	criteria.assignedUser = ''
	criteria.assignedGroup = ''
	criteria.typeId = ''
	criteria.city = ''
	criteria.text = ''
	textSearchInput.value = ''
	criteria.updatedWithinDays = ''
	criteria.createdAtFrom = ''
	criteria.createdAtTo = ''
	criteria.updatedAtFrom = ''
	criteria.updatedAtTo = ''
	criteria.unassigned = false
	criteria.hasAttachments = false
	for (const key of Object.keys(enabledCriteria) as CriteriaKey[]) {
		enabledCriteria[key] = false
		criteriaNegation[key] = false
	}
	saveName.value = ''
	selectedFilter.value = ''
	closeAddCriterionModal()
	resetDraft()
}

function normalizeCriteria(source: Record<string, unknown>) {
	const normalized: Record<string, unknown> = {}
	for (const [key, value] of Object.entries(source)) {
		normalized[key] = Array.isArray(value) ? [...value] : value
	}

	return normalized
}

function applyCriteria(nextCriteria: Record<string, unknown>, filterId: number | null, saveLabel: string, shouldEmit: boolean) {
	suppressApply.value = true
	resetBuilder()
	const negatedCriteria = Array.isArray(nextCriteria.negatedCriteria)
		? nextCriteria.negatedCriteria.map(String).filter((key): key is CriteriaKey => key in criteriaNegation)
		: []
	for (const [key, rawValue] of Object.entries(nextCriteria)) {
		if (key === 'negatedCriteria') {
			continue
		}
		if (key === 'createdAtFrom' || key === 'createdAtTo') {
			enabledCriteria.createdAt = true
			if (key === 'createdAtFrom') {
				criteria.createdAtFrom = String(rawValue)
			} else {
				criteria.createdAtTo = String(rawValue)
			}
			continue
		}
		if (key === 'updatedAtFrom' || key === 'updatedAtTo') {
			enabledCriteria.updatedAt = true
			if (key === 'updatedAtFrom') {
				criteria.updatedAtFrom = String(rawValue)
			} else {
				criteria.updatedAtTo = String(rawValue)
			}
			continue
		}
		if (!(key in enabledCriteria)) {
			continue
		}
		enabledCriteria[key as CriteriaKey] = true
		if (key === 'status') {
			criteria.status = Array.isArray(rawValue) ? rawValue.map(String) : String(rawValue).split(',').map((item: string) => item.trim()).filter(Boolean)
		} else if (key === 'createdBy') {
			criteria.createdBy = String(rawValue)
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
			textSearchInput.value = criteria.text
		} else if (key === 'updatedWithinDays') {
			criteria.updatedWithinDays = String(rawValue)
		} else if (key === 'hasAttachments') {
			criteria.hasAttachments = Boolean(rawValue)
		} else if (key === 'unassigned') {
			criteria.unassigned = Boolean(rawValue)
		}
	}
	for (const key of negatedCriteria) {
		criteriaNegation[key] = true
	}
	saveName.value = saveLabel
	selectedFilter.value = filterId === null ? '' : String(filterId)
	suppressApply.value = false
	if (shouldEmit) {
		emit('apply', activeCriteria.value, filterId)
	}
}

function applyFilter(filter: SavedFilter, shouldEmit = true) {
	applyCriteria(filter.criteria ?? {}, filter.id, filter.isPredefined ? `Copia de ${filter.name}` : filter.name, shouldEmit)
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

function syncDraftDateRange(key: 'createdAt' | 'updatedAt', changedBoundary: 'from' | 'to') {
	const fromKey = key === 'createdAt' ? 'createdAtFrom' : 'updatedAtFrom'
	const toKey = key === 'createdAt' ? 'createdAtTo' : 'updatedAtTo'
	const fromValue = draftCriteria[fromKey]
	const toValue = draftCriteria[toKey]

	if (!fromValue || !toValue) {
		return
	}

	if (fromValue <= toValue) {
		return
	}

	if (changedBoundary === 'from') {
		draftCriteria[toKey] = fromValue
		return
	}

	draftCriteria[fromKey] = toValue
}

function formatStatusList(statusIds: string[]) {
	return statusIds.map((statusId) => statusLabelMap.value.get(statusId) ?? statusId).join(', ')
}

function withNegationLabel(key: CriteriaKey, label: string) {
	return criteriaNegation[key] ? `Excluir ${label}` : label
}

function getDraftNegation(key: CriteriaKey | '') {
	return key ? draftNegation[key] : false
}

function setDraftNegation(key: CriteriaKey | '', value: boolean) {
	if (!key) {
		return
	}

	draftNegation[key] = value
}

function isDraftStatusSelectable(statusId: string) {
	const status = safeStatuses.value.find((item: StatusOption) => item.id === statusId)
	if (!status) {
		return false
	}

	return status.active !== false || draftCriteria.status.includes(statusId)
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

function formatDateLabel(value: string) {
	if (!value) {
		return ''
	}

	const [year, month, day] = value.split('-')
	if (!year || !month || !day) {
		return value
	}

	return `${day}/${month}/${year}`
}

function formatDateRange(from: string, to: string) {
	if (from && to) {
		return `del ${formatDateLabel(from)} al ${formatDateLabel(to)}`
	}

	if (from) {
		return `desde ${formatDateLabel(from)}`
	}

	if (to) {
		return `hasta ${formatDateLabel(to)}`
	}

	return 'sin definir'
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
	<section class="gi-filter-panel gi-surface-elevated gi-surface-elevated--soft">
		<div class="gi-filter-toolbar">
			<label class="gi-field gi-filter-toolbar__field gi-filter-toolbar__field--search">
				<span class="gi-filter-toolbar__label">Buscar</span>
				<input v-model="textSearchInput" class="gi-input gi-filter-toolbar__search-input" type="search" placeholder="Buscar en cualquier campo del ticket y en comentarios" />
			</label>
			<label class="gi-field gi-filter-toolbar__field">
				<span class="gi-filter-toolbar__label">Filtros guardados</span>
				<SearchableSelect :model-value="selectedFilter || null" :options="filterOptions" placeholder="Selecciona un filtro" @update:modelValue="onFilterSelect" />
			</label>
			<button v-if="canDeleteSelectedFilter" class="gi-filter-toolbar__delete-button" type="button" @click="openDeleteModal">Eliminar filtro</button>
		</div>

		<div class="gi-filter-chip-group">
			<span class="gi-filter-toolbar__label gi-filter-chip-group__label">Aplicando</span>
			<div class="gi-filter-chip-bar">
			<div class="gi-filter-chip-bar__items">
				<div v-if="activeFilterChips.length === 0" class="gi-filter-chip gi-filter-chip--empty">
					<span>Sin filtros activos</span>
				</div>
				<button v-for="chip in activeFilterChips" :key="chip.key" class="gi-filter-chip gi-filter-chip--action" type="button" @click="editCriterion(chip.key)">
					<span class="gi-filter-chip__text">{{ chip.label }}</span>
					<span class="gi-filter-chip__edit">Editar</span>
					<span class="gi-filter-chip__remove" role="button" :aria-label="`Quitar ${chip.label}`" @click.stop="removeCriterion(chip.key)">x</span>
				</button>
				<button class="gi-filter-chip-bar__add" type="button" aria-label="Anadir condicion" @click="openAddCriterionModal">+</button>
			</div>
			<div class="gi-filter-chip-bar__actions">
				<button class="gi-round-icon-button gi-filter-chip-bar__icon-action" type="button" aria-label="Guardar filtro" title="Guardar filtro" @click="openSaveModal">
					<span aria-hidden="true">&#128190;</span>
				</button>
				<button class="gi-ghost-button gi-filter-chip-bar__text-action" type="button" @click="resetBuilder">Limpiar</button>
			</div>
		</div>
		</div>

		<div v-if="modalOpen" class="gi-dialog-backdrop" @click.self="closeAddCriterionModal">
			<section class="gi-dialog gi-dialog--wide" aria-label="Añadir filtro">
				<header class="gi-dialog__header">
					<h3 class="gi-dialog__title">Añadir filtro</h3>
					<button class="gi-modal-close" type="button" aria-label="Cerrar ventana" @click="closeAddCriterionModal">x</button>
				</header>

				<label class="gi-field">
					<span>Tipo de filtro</span>
					<SearchableSelect :model-value="modalCriterionKey || null" :options="criterionTypeOptions" placeholder="Selecciona un criterio" @update:modelValue="onModalCriterionChange(String($event ?? ''))" />
				</label>

				<div v-if="modalCriterionKey" class="gi-dialog__body">
					<div v-if="modalCriterionKey === 'status'" class="gi-option-grid gi-option-grid--compact">
						<label v-for="status in safeStatuses" :key="status.id" class="gi-check-tile" :class="{ 'gi-check-tile--disabled': !isDraftStatusSelectable(status.id) }">
							<input :checked="draftCriteria.status.includes(status.id)" :disabled="!isDraftStatusSelectable(status.id)" type="checkbox" @change="toggleDraftStatus(status.id, ($event.target as HTMLInputElement).checked)" />
							<span>{{ status.label }}</span>
						</label>
					</div>

					<label v-else-if="modalCriterionKey === 'assignedUser'" class="gi-field">
						<span>Usuario asignado</span>
						<SearchableSelect :model-value="draftCriteria.assignedUser || null" :options="draftUserOptions" placeholder="Selecciona un usuario" @update:modelValue="onDraftAssignedUserSelect" />
					</label>

					<label v-else-if="modalCriterionKey === 'createdBy'" class="gi-field">
						<span>Creado por</span>
						<SearchableSelect :model-value="draftCriteria.createdBy || null" :options="createdByOptions" placeholder="Selecciona un usuario" @update:modelValue="draftCriteria.createdBy = $event ? String($event) : ''" />
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

					<div v-else-if="modalCriterionKey === 'createdAt'" class="gi-filter-modal__date-range">
						<label class="gi-field">
							<span>Fecha de inicio</span>
							<input v-model="draftCriteria.createdAtFrom" class="gi-input" type="date" @input="syncDraftDateRange('createdAt', 'from')" />
						</label>
						<label class="gi-field">
							<span>Fecha de fin</span>
							<input v-model="draftCriteria.createdAtTo" class="gi-input" type="date" @input="syncDraftDateRange('createdAt', 'to')" />
						</label>
					</div>

					<div v-else-if="modalCriterionKey === 'updatedAt'" class="gi-filter-modal__date-range">
						<label class="gi-field">
							<span>Fecha de inicio</span>
							<input v-model="draftCriteria.updatedAtFrom" class="gi-input" type="date" @input="syncDraftDateRange('updatedAt', 'from')" />
						</label>
						<label class="gi-field">
							<span>Fecha de fin</span>
							<input v-model="draftCriteria.updatedAtTo" class="gi-input" type="date" @input="syncDraftDateRange('updatedAt', 'to')" />
						</label>
					</div>

					<label v-else-if="modalCriterionKey === 'hasAttachments'" class="gi-switch-row gi-switch-row--modal">
						<input v-model="draftCriteria.hasAttachments" type="checkbox" />
						<span>Solo tickets con adjuntos o rutas URL</span>
					</label>

					<label v-else class="gi-switch-row gi-switch-row--modal">
						<input v-model="draftCriteria.unassigned" type="checkbox" />
						<span>Mostrar solo tickets sin usuario ni grupo asignado</span>
					</label>

					<label class="gi-switch-row gi-switch-row--modal">
						<input :checked="getDraftNegation(modalCriterionKey)" type="checkbox" @change="setDraftNegation(modalCriterionKey, ($event.target as HTMLInputElement).checked)" />
						<span>Negar este filtro</span>
					</label>
				</div>

				<footer class="gi-dialog__footer">
					<button class="gi-ghost-button" type="button" @click="closeAddCriterionModal">Cancelar</button>
					<button class="gi-primary-button" type="button" :disabled="!modalCriterionKey || !hasDraftValue(modalCriterionKey)" @click="applyDraftCriterion">Anadir</button>
				</footer>
			</section>
		</div>

		<div v-if="saveModalOpen" class="gi-dialog-backdrop" @click.self="closeSaveModal">
			<section class="gi-dialog gi-dialog--compact" aria-label="Guardar filtro">
				<header class="gi-dialog__header">
					<h3 class="gi-dialog__title">Guardar filtro</h3>
					<button class="gi-modal-close" type="button" aria-label="Cerrar ventana" @click="closeSaveModal">x</button>
				</header>

				<label class="gi-field">
					<span>Nombre del filtro</span>
					<input v-model="saveName" class="gi-input" placeholder="Nombre del filtro" />
				</label>

				<p v-if="saveModalError" class="gi-dialog__message">{{ saveModalError }}</p>

				<footer class="gi-dialog__footer">
					<button class="gi-ghost-button" type="button" @click="closeSaveModal">Cancelar</button>
					<button v-if="overwriteCandidateId !== null" class="gi-secondary-button" type="button" @click="saveCurrentFilter(true)">Sobrescribir</button>
					<button class="gi-primary-button" type="button" @click="saveCurrentFilter(false)">Guardar</button>
				</footer>
			</section>
		</div>

		<div v-if="deleteModalOpen" class="gi-dialog-backdrop" @click.self="closeDeleteModal">
			<section class="gi-dialog gi-dialog--compact" aria-label="Eliminar filtro guardado">
				<header class="gi-dialog__header">
					<h3 class="gi-dialog__title">Eliminar filtro</h3>
					<button class="gi-modal-close" type="button" aria-label="Cerrar ventana" @click="closeDeleteModal">x</button>
				</header>

				<p class="gi-dialog__message gi-dialog__message--neutral">
					¿Quieres eliminar <strong>{{ selectedSavedFilter?.name }}</strong>?
				</p>

				<footer class="gi-dialog__footer">
					<button class="gi-ghost-button" type="button" @click="closeDeleteModal">Cancelar</button>
					<button class="gi-secondary-button gi-dialog__danger" type="button" @click="deleteSelectedFilter">Eliminar</button>
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
	margin-bottom: 1.2rem;
}

.gi-filter-panel__actions,
.gi-filter-chip,
.gi-filter-chip-bar {
	display: flex;
	gap: .75rem;
}

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
	align-items: flex-end;
}

.gi-filter-toolbar__field {
	min-width: 0;
	flex: 1 1 22rem;
}

.gi-filter-toolbar__field--search {
	flex: 2 1 28rem;
}

.gi-filter-toolbar__search-input {
	min-height: 2.9rem;
	border-radius: 16px;
}

.gi-filter-chip-group {
	display: grid;
	gap: .45rem;
}

.gi-filter-chip-group__label {
	padding-left: .15rem;
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

.gi-filter-modal__date-range {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
	gap: .75rem;
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

.gi-filter-chip--action {
	border: none;
	font: inherit;
	cursor: pointer;
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

.gi-filter-chip__edit {
	font-size: .78rem;
	color: #45675e;
	white-space: nowrap;
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

.gi-filter-chip-bar__text-action {
	white-space: nowrap;
}

.gi-filter-chip__remove {
	display: inline-grid;
	place-items: center;
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

.gi-check-tile--disabled {
	opacity: .55;
}

.gi-switch-row--modal {
	margin-top: .35rem;
}

.gi-inline-action {
	border: none;
	background: transparent;
	color: #255d52;
	font: inherit;
	cursor: pointer;
	padding: 0;
}

@media (max-width: 900px) {
	.gi-filter-toolbar {
		align-items: stretch;
	}

	.gi-filter-toolbar__field {
		flex: 1 1 auto;
	}

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
