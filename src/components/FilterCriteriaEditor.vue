<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import type { AssignableOption, SearchableSelectOption, StatusOption, TypeNode } from '@/types'
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

watch(() => props.modelValue, (value) => {
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
}, { deep: true, immediate: true })

watch(activeCriteria, (value) => emit('update:modelValue', value), { deep: true })

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
	modalOpen.value = false
	resetDraft()
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
	if (Object.keys(activeCriteria.value).length === 0) emit('update:modelValue', {})
}
</script>

<template>
	<div class="gi-filter-criteria-editor">
		<div class="gi-filter-criteria-editor__actions">
			<button class="gi-secondary-button" type="button" @click="modalOpen = true">Anadir criterio</button>
		</div>
		<div class="gi-filter-criteria-editor__remove-grid">
			<button v-for="option in criterionOptions.filter((item) => enabledCriteria[item.key])" :key="option.key" class="gi-filter-remove-button" type="button" @click="removeCriterion(option.key)">
				Quitar {{ option.label }}
			</button>
		</div>
		<div v-if="modalOpen" class="gi-filter-modal-backdrop" @click.self="modalOpen = false; resetDraft()">
			<section class="gi-filter-modal">
				<header class="gi-filter-modal__header">
					<h3>Anadir criterio</h3>
					<button class="gi-modal-close" type="button" aria-label="Cerrar ventana" @click="modalOpen = false; resetDraft()">x</button>
				</header>
				<label class="gi-field">
					<span>Tipo de filtro</span>
					<SearchableSelect :model-value="modalCriterionKey || null" :options="criterionTypeOptions" placeholder="Selecciona un criterio" @update:modelValue="modalCriterionKey = String($event ?? '') as CriteriaKey" />
				</label>
				<div v-if="modalCriterionKey" class="gi-filter-modal__body">
					<div v-if="modalCriterionKey === 'status'" class="gi-option-grid gi-option-grid--compact">
						<label v-for="status in safeStatuses" :key="status.id" class="gi-check-tile">
							<input :checked="draftCriteria.status.includes(status.id)" type="checkbox" @change="draftCriteria.status = ($event.target as HTMLInputElement).checked ? [...draftCriteria.status, status.id] : draftCriteria.status.filter((item) => item !== status.id)" />
							<span>{{ status.label }}</span>
						</label>
					</div>
					<label v-else-if="modalCriterionKey === 'assignedUser'" class="gi-field"><span>Usuario asignado</span><SearchableSelect :model-value="draftCriteria.assignedUser || null" :options="draftUserOptions" placeholder="Selecciona un usuario" @update:modelValue="draftCriteria.assignedUser = String($event ?? '')" /></label>
					<label v-else-if="modalCriterionKey === 'assignedGroup'" class="gi-field"><span>Grupo asignado</span><SearchableSelect :model-value="draftCriteria.assignedGroup || null" :options="draftGroupOptions" placeholder="Selecciona un grupo" @update:modelValue="draftCriteria.assignedGroup = String($event ?? '')" /></label>
					<label v-else-if="modalCriterionKey === 'typeId'" class="gi-field"><span>Tipo</span><SearchableSelect :model-value="draftCriteria.typeId || null" :options="typeSelectOptions" placeholder="Selecciona un tipo" @update:modelValue="draftCriteria.typeId = String($event ?? '')" /></label>
					<label v-else-if="modalCriterionKey === 'city'" class="gi-field"><span>Ciudad</span><input v-model="draftCriteria.city" class="gi-input" /></label>
					<label v-else-if="modalCriterionKey === 'text'" class="gi-field"><span>Texto libre</span><input v-model="draftCriteria.text" class="gi-input" /></label>
					<label v-else-if="modalCriterionKey === 'updatedWithinDays'" class="gi-field"><span>Ultimos dias</span><input v-model="draftCriteria.updatedWithinDays" class="gi-input" /></label>
					<label v-else-if="modalCriterionKey === 'hasAttachments'" class="gi-switch-row"><input v-model="draftCriteria.hasAttachments" type="checkbox" /><span>Solo con adjuntos o rutas URL</span></label>
					<label v-else class="gi-switch-row"><input v-model="draftCriteria.unassigned" type="checkbox" /><span>Solo sin asignar</span></label>
				</div>
				<footer class="gi-filter-modal__footer">
					<button class="gi-primary-button" type="button" :disabled="!modalCriterionKey || !hasDraftValue(modalCriterionKey)" @click="applyDraftCriterion">Anadir</button>
				</footer>
			</section>
		</div>
	</div>
</template>

<style scoped>
.gi-filter-criteria-editor,
.gi-filter-criteria-editor__actions,
.gi-filter-criteria-editor__remove-grid {
	display: grid;
	gap: .75rem;
}

.gi-filter-remove-button {
	border: 1px dashed rgba(15, 36, 51, .16);
	background: rgba(255, 255, 255, .82);
	border-radius: 999px;
	padding: .5rem .75rem;
	font: inherit;
	cursor: pointer;
}

.gi-filter-modal-backdrop {
	position: fixed;
	inset: 0;
	display: grid;
	place-items: center;
	background: rgba(24, 38, 34, .34);
	z-index: 95;
	padding: 1rem;
}

.gi-filter-modal {
	width: min(42rem, 100%);
	min-height: min(32rem, calc(100vh - 2rem));
	max-height: calc(100vh - 2rem);
	overflow: auto;
	display: grid;
	gap: .9rem;
	padding: 1rem;
	border-radius: 22px;
	background: rgba(255, 255, 255, .99);
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

.gi-filter-modal__header,
.gi-filter-modal__footer {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: .75rem;
}
</style>