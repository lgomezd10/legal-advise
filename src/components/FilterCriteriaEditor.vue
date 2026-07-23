<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import type { AssignableOption, SearchableSelectOption, StatusOption, TypeNode } from '@/types'
import SearchableSelect from './SearchableSelect.vue'

let filterCriteriaEditorIdSequence = 0

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
	modelValue: Record<string, unknown>
	statuses: StatusOption[]
	types: TypeNode[]
	users: AssignableOption[]
	groups: AssignableOption[]
}>()

const emit = defineEmits<{
	(e: 'update:modelValue', value: Record<string, unknown>): void
}>()

const criteria = reactive<FilterCriteriaState>({ status: [], assignedUser: '', assignedGroup: '', typeId: '', city: '', text: '', updatedWithinDays: '', unassigned: false, hasAttachments: false })
const enabledCriteria = reactive<Record<CriteriaKey, boolean>>({ status: false, assignedUser: false, assignedGroup: false, typeId: false, city: false, text: false, updatedWithinDays: false, unassigned: false, hasAttachments: false })
const draftCriteria = reactive<FilterCriteriaState>({ status: [], assignedUser: '', assignedGroup: '', typeId: '', city: '', text: '', updatedWithinDays: '', unassigned: false, hasAttachments: false })
const modalOpen = ref(false)
const modalCriterionKey = ref<CriteriaKey | ''>('')
const syncingFromModelValue = ref(false)
const instanceId = `gi-filter-criteria-${++filterCriteriaEditorIdSequence}`

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
const safeStatuses = computed<StatusOption[]>(() => normalizeCollection<StatusOption>(props.statuses))
const safeTypes = computed<TypeNode[]>(() => normalizeCollection<TypeNode>(props.types))
const safeUsers = computed<AssignableOption[]>(() => normalizeCollection<AssignableOption>(props.users))
const safeGroups = computed<AssignableOption[]>(() => normalizeCollection<AssignableOption>(props.groups))
const criterionTypeOptions = computed<SearchableSelectOption[]>(() => criterionOptions.map((option) => ({ value: option.key, label: option.label })))
const draftUserOptions = computed<SearchableSelectOption[]>(() => [{ value: '__me__', label: 'Asignadas a mi' }, ...safeUsers.value.map((user: AssignableOption) => ({ value: user.id, label: user.displayName }))])
const draftGroupOptions = computed<SearchableSelectOption[]>(() => [{ value: '__my_groups__', label: 'Mis grupos' }, ...safeGroups.value.map((group: AssignableOption) => ({ value: group.id, label: group.displayName }))])
const typeOptions = computed(() => flattenTypes(safeTypes.value))
const typeSelectOptions = computed<SearchableSelectOption[]>(() => typeOptions.value.map((item) => ({ value: String(item.id), label: item.label })))
const userLabelMap = computed(() => new Map(safeUsers.value.map((user: AssignableOption) => [user.id, user.displayName])))
const groupLabelMap = computed(() => new Map(safeGroups.value.map((group: AssignableOption) => [group.id, group.displayName])))
const statusLabelMap = computed(() => new Map(safeStatuses.value.map((status: StatusOption) => [status.id, status.label])))
const activeCriteria = computed<Record<string, unknown>>(() => {
	const next: Record<string, unknown> = {}
	if (enabledCriteria.status && criteria.status.length > 0) next.status = [...criteria.status]
	if (enabledCriteria.assignedUser && criteria.assignedUser) next.assignedUser = criteria.assignedUser
	if (enabledCriteria.assignedGroup && criteria.assignedGroup) next.assignedGroup = criteria.assignedGroup
	if (enabledCriteria.typeId && criteria.typeId) next.typeId = Number(criteria.typeId)
	if (enabledCriteria.city && criteria.city.trim()) next.city = criteria.city.trim()
	if (enabledCriteria.text && criteria.text.trim()) next.text = criteria.text.trim()
	if (enabledCriteria.updatedWithinDays && criteria.updatedWithinDays) next.updatedWithinDays = Number(criteria.updatedWithinDays)
	if (enabledCriteria.unassigned && criteria.unassigned) next.unassigned = true
	if (enabledCriteria.hasAttachments && criteria.hasAttachments) next.hasAttachments = true
	return next
})
const activeFilterChips = computed<FilterChip[]>(() => {
	const chips: FilterChip[] = []
	if (enabledCriteria.status && criteria.status.length > 0) chips.push({ key: 'status', label: `Estado: ${formatStatusList(criteria.status)}` })
	if (enabledCriteria.assignedUser && criteria.assignedUser) chips.push({ key: 'assignedUser', label: `Usuario: ${formatAssignedUser(criteria.assignedUser)}` })
	if (enabledCriteria.assignedGroup && criteria.assignedGroup) chips.push({ key: 'assignedGroup', label: `Grupo: ${formatAssignedGroup(criteria.assignedGroup)}` })
	if (enabledCriteria.typeId && criteria.typeId) chips.push({ key: 'typeId', label: `Tipo: ${formatType(criteria.typeId)}` })
	if (enabledCriteria.city && criteria.city.trim()) chips.push({ key: 'city', label: `Ciudad: ${criteria.city.trim()}` })
	if (enabledCriteria.text && criteria.text.trim()) chips.push({ key: 'text', label: `Texto: ${criteria.text.trim()}` })
	if (enabledCriteria.updatedWithinDays && criteria.updatedWithinDays) chips.push({ key: 'updatedWithinDays', label: `Ultimos ${criteria.updatedWithinDays} dias` })
	if (enabledCriteria.unassigned && criteria.unassigned) chips.push({ key: 'unassigned', label: 'Sin asignar' })
	if (enabledCriteria.hasAttachments && criteria.hasAttachments) chips.push({ key: 'hasAttachments', label: 'Con adjuntos' })
	return chips
})

watch(() => props.modelValue, (value) => {
	syncingFromModelValue.value = true
	resetAll()
	for (const [key, rawValue] of Object.entries(value ?? {})) {
		if (!(key in enabledCriteria)) continue
		enabledCriteria[key as CriteriaKey] = true
		if (key === 'status') criteria.status = Array.isArray(rawValue) ? rawValue.map(String) : []
		else if (key === 'assignedUser') criteria.assignedUser = String(rawValue)
		else if (key === 'assignedGroup') criteria.assignedGroup = String(rawValue)
		else if (key === 'typeId') criteria.typeId = String(rawValue)
		else if (key === 'city') criteria.city = String(rawValue)
		else if (key === 'text') criteria.text = String(rawValue)
		else if (key === 'updatedWithinDays') criteria.updatedWithinDays = String(rawValue)
		else if (key === 'unassigned') criteria.unassigned = Boolean(rawValue)
		else if (key === 'hasAttachments') criteria.hasAttachments = Boolean(rawValue)
	}
	syncingFromModelValue.value = false
}, { deep: true, immediate: true })

function flattenTypes(types: TypeNode[], prefix = ''): Array<{ id: number, label: string }> {
	return types.flatMap((item) => {
		const label = prefix ? `${prefix} > ${item.name}` : item.name
		return [{ id: item.id, label }, ...flattenTypes(item.children, label)]
	})
}

function resetAll() {
	criteria.status = []
	criteria.assignedUser = ''
	criteria.assignedGroup = ''
	criteria.typeId = ''
	criteria.city = ''
	criteria.text = ''
	criteria.updatedWithinDays = ''
	criteria.unassigned = false
	criteria.hasAttachments = false
	for (const key of Object.keys(enabledCriteria) as CriteriaKey[]) enabledCriteria[key] = false
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

function hasDraftValue(key: CriteriaKey) {
	if (key === 'status') return draftCriteria.status.length > 0
	if (key === 'assignedUser') return Boolean(draftCriteria.assignedUser)
	if (key === 'assignedGroup') return Boolean(draftCriteria.assignedGroup)
	if (key === 'typeId') return Boolean(draftCriteria.typeId)
	if (key === 'city') return Boolean(draftCriteria.city.trim())
	if (key === 'text') return Boolean(draftCriteria.text.trim())
	if (key === 'updatedWithinDays') return Boolean(draftCriteria.updatedWithinDays)
	if (key === 'hasAttachments') return draftCriteria.hasAttachments
	return draftCriteria.unassigned
}

function emitCurrentCriteria() {
	if (syncingFromModelValue.value) {
		return
	}

	emit('update:modelValue', activeCriteria.value)
}

function applyDraftCriterion() {
	if (!modalCriterionKey.value || !hasDraftValue(modalCriterionKey.value)) return
	const key = modalCriterionKey.value
	enabledCriteria[key] = true
	if (key === 'status') criteria.status = [...draftCriteria.status]
	else if (key === 'assignedUser') criteria.assignedUser = draftCriteria.assignedUser
	else if (key === 'assignedGroup') criteria.assignedGroup = draftCriteria.assignedGroup
	else if (key === 'typeId') criteria.typeId = draftCriteria.typeId
	else if (key === 'city') criteria.city = draftCriteria.city.trim()
	else if (key === 'text') criteria.text = draftCriteria.text.trim()
	else if (key === 'updatedWithinDays') criteria.updatedWithinDays = draftCriteria.updatedWithinDays
	else if (key === 'hasAttachments') criteria.hasAttachments = true
	else criteria.unassigned = true
	emitCurrentCriteria()
	modalOpen.value = false
	resetDraft()
}

function editCriterion(key: CriteriaKey) {
	loadDraftForCriterion(key)
	modalOpen.value = true
}

function removeCriterion(key: CriteriaKey) {
	enabledCriteria[key] = false
	if (key === 'status') criteria.status = []
	else if (key === 'assignedUser') criteria.assignedUser = ''
	else if (key === 'assignedGroup') criteria.assignedGroup = ''
	else if (key === 'typeId') criteria.typeId = ''
	else if (key === 'city') criteria.city = ''
	else if (key === 'text') criteria.text = ''
	else if (key === 'updatedWithinDays') criteria.updatedWithinDays = ''
	else if (key === 'hasAttachments') criteria.hasAttachments = false
	else criteria.unassigned = false
	emitCurrentCriteria()
}

function isStatusSelectable(statusId: string) {
	const status = safeStatuses.value.find((item: StatusOption) => item.id === statusId)
	if (!status) {
		return false
	}

	return status.active !== false || draftCriteria.status.includes(statusId)
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

function getFieldId(suffix: string) {
	return `${instanceId}-${suffix}`
}
</script>

<template>
	<div class="gi-filter-criteria-editor">
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
				<button class="gi-filter-chip-bar__add" type="button" aria-label="Añadir criterio" @click="modalOpen = true">
					+
				</button>
			</div>
		</div>
		<div v-if="modalOpen" class="gi-app-dialog-backdrop gi-dialog-backdrop" @click.self="modalOpen = false; resetDraft()">
			<section class="gi-app-dialog gi-dialog gi-dialog--wide">
				<header class="gi-dialog__header">
					<h3 class="gi-dialog__title">Añadir criterio</h3>
					<button class="gi-modal-close" type="button" aria-label="Cerrar ventana" @click="modalOpen = false; resetDraft()">x</button>
				</header>
				<label class="gi-field">
					<span>Tipo de filtro</span>
					<SearchableSelect :model-value="modalCriterionKey || null" :options="criterionTypeOptions" :input-id="getFieldId('criterion-type-search')" :input-name="getFieldId('criterion-type-search')" placeholder="Selecciona un criterio" @update:modelValue="modalCriterionKey = String($event ?? '') as CriteriaKey" />
				</label>
				<div v-if="modalCriterionKey" class="gi-dialog__body">
					<div v-if="modalCriterionKey === 'status'" class="gi-option-grid gi-option-grid--compact">
						<label v-for="status in safeStatuses" :key="status.id" class="gi-check-tile" :class="{ 'gi-check-tile--disabled': !isStatusSelectable(status.id) }">
							<input :id="getFieldId(`status-${status.id}`)" :name="getFieldId('status')" :checked="draftCriteria.status.includes(status.id)" :disabled="!isStatusSelectable(status.id)" type="checkbox" @change="draftCriteria.status = ($event.target as HTMLInputElement).checked ? [...new Set([...draftCriteria.status, status.id])] : draftCriteria.status.filter((item) => item !== status.id)" />
							<span>{{ status.label }}</span>
						</label>
					</div>
					<label v-else-if="modalCriterionKey === 'assignedUser'" class="gi-field"><span>Usuario asignado</span><SearchableSelect :model-value="draftCriteria.assignedUser || null" :options="draftUserOptions" :input-id="getFieldId('assigned-user-search')" :input-name="getFieldId('assigned-user-search')" placeholder="Selecciona un usuario" @update:modelValue="draftCriteria.assignedUser = String($event ?? '')" /></label>
					<label v-else-if="modalCriterionKey === 'assignedGroup'" class="gi-field"><span>Grupo asignado</span><SearchableSelect :model-value="draftCriteria.assignedGroup || null" :options="draftGroupOptions" :input-id="getFieldId('assigned-group-search')" :input-name="getFieldId('assigned-group-search')" placeholder="Selecciona un grupo" @update:modelValue="draftCriteria.assignedGroup = String($event ?? '')" /></label>
					<label v-else-if="modalCriterionKey === 'typeId'" class="gi-field"><span>Tipo</span><SearchableSelect :model-value="draftCriteria.typeId || null" :options="typeSelectOptions" :input-id="getFieldId('type-search')" :input-name="getFieldId('type-search')" placeholder="Selecciona un tipo" @update:modelValue="draftCriteria.typeId = String($event ?? '')" /></label>
					<label v-else-if="modalCriterionKey === 'city'" class="gi-field"><span>Ciudad</span><input :id="getFieldId('city')" :name="getFieldId('city')" v-model="draftCriteria.city" class="gi-input" /></label>
					<label v-else-if="modalCriterionKey === 'text'" class="gi-field"><span>Texto libre</span><input :id="getFieldId('text')" :name="getFieldId('text')" v-model="draftCriteria.text" class="gi-input" /></label>
					<label v-else-if="modalCriterionKey === 'updatedWithinDays'" class="gi-field"><span>Ultimos dias</span><input :id="getFieldId('updated-within-days')" :name="getFieldId('updated-within-days')" v-model="draftCriteria.updatedWithinDays" class="gi-input" /></label>
					<label v-else-if="modalCriterionKey === 'hasAttachments'" class="gi-switch-row"><input :id="getFieldId('has-attachments')" :name="getFieldId('has-attachments')" v-model="draftCriteria.hasAttachments" type="checkbox" /><span>Solo con adjuntos o rutas URL</span></label>
					<label v-else class="gi-switch-row"><input :id="getFieldId('unassigned')" :name="getFieldId('unassigned')" v-model="draftCriteria.unassigned" type="checkbox" /><span>Solo sin asignar</span></label>
				</div>
				<footer class="gi-dialog__footer">
					<button class="gi-primary-button" type="button" :disabled="!modalCriterionKey || !hasDraftValue(modalCriterionKey)" @click="applyDraftCriterion">Añadir</button>
				</footer>
			</section>
		</div>
	</div>
</template>

<style scoped>
.gi-filter-criteria-editor {
	display: grid;
	gap: .75rem;
}

.gi-filter-chip-bar {
	display: flex;
	align-items: center;
	gap: .75rem;
	padding: .55rem .75rem;
	border-radius: 18px;
	background: var(--gi-color-surface-subtle, rgba(242, 246, 243, .92));
	border: 1px solid var(--gi-color-border, rgba(49, 96, 91, .1));
}

.gi-filter-chip-bar__items {
	display: flex;
	gap: .5rem;
	flex-wrap: wrap;
	align-items: center;
	min-width: 0;
	flex: 1;
}

.gi-filter-chip {
	display: flex;
	gap: .75rem;
	align-items: center;
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

.gi-check-tile--disabled {
	opacity: .55;
}
</style>