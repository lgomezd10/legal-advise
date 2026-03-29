<script setup lang="ts">
import { computed, onErrorCaptured, onMounted, reactive, ref, watch } from 'vue'
import AdminTypeTreeEditor from '@/components/AdminTypeTreeEditor.vue'
import FilterCatalogEditor from '@/components/FilterCatalogEditor.vue'
import NotificationMatrix from '@/components/NotificationMatrix.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'
import { useAdminConfigStore } from '@/store/adminConfig'
import { useBootstrapStore } from '@/store/bootstrap'
import { useNotificationsStore } from '@/store/notifications'
import type { AdminStatusOption, AssignmentRule, CatalogField, EditableTypeNode, SavedFilter, SearchableSelectOption, TypeNode, UrgencyCatalogItem } from '@/types'

type AdminConfigData = {
	statuses: AdminStatusOption[]
	types: TypeNode[]
	urgencies: UrgencyCatalogItem[]
	fields: CatalogField[]
	filters: SavedFilter[]
	rules: AssignmentRule[]
	profiles: Array<{ id?: number, profile: string, principalType: string, principalId: string }>
	attachmentConfig: { allowedExtensions: string[], maxFileSizeMb: number }
	tasksConfig: Record<string, unknown>
}

type StatusDraft = AdminStatusOption & { clientId: string }
type UrgencyDraft = UrgencyCatalogItem & { clientId: string }
type FieldDraft = CatalogField & { clientId: string }
type FilterDraft = SavedFilter & { clientId: string }
type RuleDraft = AssignmentRule & { clientId: string }
type ProfileDraft = { id?: number, profile: string, principalType: 'user' | 'group', principalId: string, principalKey: string, clientId: string }

type AssignablesState = {
	users?: unknown[]
	groups?: unknown[]
}

const adminConfigStore = useAdminConfigStore()
const bootstrapStore = useBootstrapStore()
const notificationsStore = useNotificationsStore()
const taskConfig = reactive<{ enabled: boolean }>({ enabled: false })
const attachmentConfig = reactive<{ allowedExtensionsText: string, maxFileSizeMb: number }>({ allowedExtensionsText: '', maxFileSizeMb: 25 })
const statusDrafts = ref<StatusDraft[]>([])
const urgencyDrafts = ref<UrgencyDraft[]>([])
const typeDrafts = ref<EditableTypeNode[]>([])
const fieldDrafts = ref<FieldDraft[]>([])
const filterDrafts = ref<FilterDraft[]>([])
const ruleDrafts = ref<RuleDraft[]>([])
const profileDrafts = ref<ProfileDraft[]>([])
const activeSection = ref<'statuses' | 'urgencies' | 'types' | 'fields' | 'filters' | 'rules' | 'profiles' | 'attachments' | 'notifications' | 'tasks'>('statuses')
const loadState = reactive({
	loading: true,
	error: '',
	renderError: '',
})
const saveState = reactive({
	statuses: '',
	urgencies: '',
	types: '',
	fields: '',
	filters: '',
	rules: '',
	profiles: '',
	attachments: '',
	tasks: '',
})

const adminSections = [
	{ id: 'statuses', label: 'Estados' },
	{ id: 'urgencies', label: 'Criticidades' },
	{ id: 'types', label: 'Tipos' },
	{ id: 'fields', label: 'Campos' },
	{ id: 'filters', label: 'Filtros' },
	{ id: 'rules', label: 'Reglas' },
	{ id: 'profiles', label: 'Perfiles' },
	{ id: 'attachments', label: 'Adjuntos' },
	{ id: 'notifications', label: 'Notificaciones' },
	{ id: 'tasks', label: 'Tasks' },
] as const

const adminData = computed(() => adminConfigStore.data as AdminConfigData | null)
const statusItems = computed(() => statusDrafts.value)
const urgencyItems = computed(() => urgencyDrafts.value)
const fieldItems = computed(() => fieldDrafts.value)
const filterItems = computed(() => filterDrafts.value)
const ruleItems = computed(() => ruleDrafts.value)
const profileItems = computed(() => profileDrafts.value)
const typeLines = computed(() => flattenTypes(typeDrafts.value))
const typeOptions = computed<SearchableSelectOption[]>(() => flattenTypeOptions(typeDrafts.value))
const assignables = computed<AssignablesState>(() => isRecord(bootstrapStore.data.assignables) ? bootstrapStore.data.assignables as AssignablesState : { users: [], groups: [] })
const userOptions = computed<SearchableSelectOption[]>(() => toAssignableOptions(assignables.value.users))
const groupOptions = computed<SearchableSelectOption[]>(() => toAssignableOptions(assignables.value.groups))
const principalOptions = computed<SearchableSelectOption[]>(() => toPrincipalOptions(assignables.value.users, assignables.value.groups))
const provinceOptions = computed<SearchableSelectOption[]>(() => bootstrapStore.data.catalogs.provinces.map((province) => ({
	value: province,
	label: province,
})))
const preloadSourceOptions: SearchableSelectOption[] = [
	{ value: 'displayName', label: 'Nombre de Nextcloud' },
	{ value: 'email', label: 'Correo de Nextcloud' },
	{ value: 'phone', label: 'Telefono del perfil' },
	{ value: 'location', label: 'Direccion del perfil' },
	{ value: '', label: 'Sin precarga' },
]
const fieldTypeOptions: SearchableSelectOption[] = [
	{ value: 'text', label: 'Texto' },
	{ value: 'email', label: 'Correo' },
	{ value: 'tel', label: 'Telefono' },
]
const profileOptions: SearchableSelectOption[] = [
	{ value: 'usuario', label: 'Usuario' },
	{ value: 'soporte', label: 'Soporte' },
	{ value: 'administrador', label: 'Administrador' },
]
const requiredTypePaths = [
	'Neceisto asesoramiento > Solo Territorial',
	'Neceisto asesoramiento > Territorial y Legal',
	'Neceisto asesoramiento > Territorial y Comunicacion',
	'Neceisto asesoramiento > Territoral, Legal y Comunicacion',
	'Quiero informar',
]
const requiredTypeCoverage = computed(() => {
	const available = new Set(typeLines.value)
	return requiredTypePaths.map((path) => ({ path, present: available.has(path) }))
})

onMounted(async() => {
	await hydrateAdminView()
})

onErrorCaptured((error) => {
	loadState.renderError = error instanceof Error ? error.message : 'Se produjo un error al renderizar esta seccion.'
	activeSection.value = 'statuses'
	return false
})

watch(activeSection, () => {
	loadState.renderError = ''
})

function isRecord(value: unknown): value is Record<string, unknown> {
	return value !== null && typeof value === 'object'
}

function toAssignableOptions(entries: unknown[] | undefined): SearchableSelectOption[] {
	if (!Array.isArray(entries)) {
		return []
	}

	return entries.reduce<SearchableSelectOption[]>((options, entry) => {
		if (!isRecord(entry)) {
			return options
		}

		const id = typeof entry.id === 'string' ? entry.id.trim() : ''
		if (id === '') {
			return options
		}

		const displayName = typeof entry.displayName === 'string' && entry.displayName.trim() !== ''
			? entry.displayName.trim()
			: id

		options.push({
			value: id,
			label: `${displayName} (${id})`,
			searchText: `${displayName} ${id}`,
		})

		return options
	}, [])
}

function toPrincipalOptions(userEntries: unknown[] | undefined, groupEntries: unknown[] | undefined): SearchableSelectOption[] {
	return [
		...toAssignableOptions(userEntries).map((option) => ({
			...option,
			value: `user:${String(option.value)}`,
			label: `Usuario: ${option.label}`,
			searchText: `${option.searchText ?? ''} Usuario`,
		})),
		...toAssignableOptions(groupEntries).map((option) => ({
			...option,
			value: `group:${String(option.value)}`,
			label: `Grupo: ${option.label}`,
			searchText: `${option.searchText ?? ''} Grupo`,
		})),
	]
}

function buildPrincipalKey(principalType: string, principalId: string): string {
	if (principalId.trim() === '') {
		return ''
	}

	return `${principalType === 'group' ? 'group' : 'user'}:${principalId.trim()}`
}

function parsePrincipalKey(principalKey: string): { principalType: 'user' | 'group', principalId: string } {
	const trimmed = principalKey.trim()
	if (trimmed.startsWith('group:')) {
		return { principalType: 'group', principalId: trimmed.slice('group:'.length) }
	}

	if (trimmed.startsWith('user:')) {
		return { principalType: 'user', principalId: trimmed.slice('user:'.length) }
	}

	return { principalType: 'user', principalId: '' }
}

function ensurePrincipalOption(principalKey: string): SearchableSelectOption[] {
	if (principalKey === '' || principalOptions.value.some((option) => option.value === principalKey)) {
		return principalOptions.value
	}

	const { principalType, principalId } = parsePrincipalKey(principalKey)
	if (principalId === '') {
		return principalOptions.value
	}

	const kindLabel = principalType === 'group' ? 'Grupo' : 'Usuario'
	return [{
		value: principalKey,
		label: `${kindLabel}: ${principalId} (no disponible)`,
		searchText: principalId,
		disabled: true,
	}, ...principalOptions.value]
}

async function hydrateAdminView() {
	loadState.loading = true
	loadState.error = ''

	try {
		await Promise.all([
			bootstrapStore.refresh().catch(() => undefined),
			adminConfigStore.load(),
			notificationsStore.load(),
		])
		syncDrafts()
	} catch (error) {
		loadState.error = error instanceof Error ? error.message : 'No se pudo cargar la configuracion de administracion.'
	} finally {
		loadState.loading = false
	}
}

function flattenTypes(nodes: Array<TypeNode | EditableTypeNode>, prefix = ''): string[] {
	return nodes.filter(isTypeNodeLike).flatMap((node) => {
		const label = prefix ? `${prefix} > ${node.name}` : node.name
		return [label, ...flattenTypes(node.children, label)]
	})
}

function isTypeNodeLike(value: unknown): value is TypeNode | EditableTypeNode {
	return isRecord(value) && typeof value.name === 'string' && Array.isArray(value.children)
}

function syncDrafts() {
	taskConfig.enabled = Boolean(adminData.value?.tasksConfig?.enabled)
	attachmentConfig.allowedExtensionsText = (adminData.value?.attachmentConfig?.allowedExtensions ?? []).map((extension) => `.${extension}`).join(', ')
	attachmentConfig.maxFileSizeMb = Math.max(1, Number(adminData.value?.attachmentConfig?.maxFileSizeMb ?? 25))
	statusDrafts.value = normalizeStatuses(adminData.value?.statuses).map((item, index) => ({
		...item,
		clientId: buildClientId('status', item.id || index),
	}))
	urgencyDrafts.value = normalizeUrgencies(adminData.value?.urgencies).map((item, index) => ({
		...item,
		clientId: buildClientId('urgency', item.id ?? index),
	}))
	typeDrafts.value = cloneTypeNodes(normalizeTypes(adminData.value?.types))
	fieldDrafts.value = normalizeFields(adminData.value?.fields).map((item, index) => ({
		...item,
		clientId: buildClientId('field', item.id ?? item.fieldKey ?? index),
	}))
	filterDrafts.value = normalizeFilters(adminData.value?.filters).map((item, index) => ({
		...item,
		clientId: buildClientId('filter', item.id ?? `${item.name}-${index}`),
	}))
	ruleDrafts.value = normalizeRules(adminData.value?.rules).map((item, index) => ({
		...item,
		typeId: item.typeId ?? null,
		province: item.province ?? null,
		assignedUserUid: item.assignedUserUid ?? null,
		assignedGroupId: item.assignedGroupId ?? null,
		clientId: buildClientId('rule', item.id ?? `${item.typeId}-${index}`),
	}))
	profileDrafts.value = normalizeProfiles(adminData.value?.profiles).map((item, index) => ({
		...item,
		principalKey: buildPrincipalKey(item.principalType, item.principalId),
		clientId: buildClientId('profile', item.id ?? `${item.profile}-${index}`),
	}))
	saveState.statuses = ''
	saveState.urgencies = ''
	saveState.types = ''
	saveState.fields = ''
	saveState.filters = ''
	saveState.rules = ''
	saveState.profiles = ''
	saveState.attachments = ''
	saveState.tasks = ''
}

function buildClientId(prefix: string, seed: number | string): string {
	return `${prefix}-${seed}-${Math.random().toString(36).slice(2, 8)}`
}

function normalizeStatuses(entries: unknown): AdminStatusOption[] {
	if (!Array.isArray(entries)) {
		return []
	}

	return entries
		.filter(isRecord)
		.filter((entry) => typeof entry.id === 'string' && typeof entry.label === 'string')
		.map((entry) => ({
			id: String(entry.id),
			label: String(entry.label),
			active: Boolean(entry.active ?? true),
			closed: Boolean(entry.closed ?? false),
			fixed: Boolean(entry.fixed ?? true),
			toggleable: Boolean(entry.toggleable ?? false),
			description: typeof entry.description === 'string' ? entry.description : '',
		}))
}

function flattenTypeOptions(nodes: Array<TypeNode | EditableTypeNode>, prefix = ''): SearchableSelectOption[] {
	return nodes.filter(isTypeNodeLike).flatMap((node) => {
		const label = prefix ? `${prefix} > ${node.name}` : node.name
		const current = typeof node.id === 'number'
			? [{ value: node.id, label, searchText: `${node.slug ?? ''} ${label}`.trim() }]
			: []
		return [...current, ...flattenTypeOptions(node.children, label)]
	})
}

function cloneTypeNodes(nodes: TypeNode[]): EditableTypeNode[] {
	return nodes.filter(isTypeNodeLike).map((node, index) => ({
		id: node.id,
		parentId: node.parentId,
		name: node.name,
		slug: node.slug,
		level: node.level,
		sortOrder: node.sortOrder || (index + 1) * 10,
		active: node.active,
		clientId: buildClientId('type', node.id),
		children: cloneTypeNodes(node.children),
	}))
}

function normalizeUrgencies(entries: unknown): UrgencyCatalogItem[] {
	if (!Array.isArray(entries)) {
		return []
	}

	return entries.filter(isRecord).filter((entry) => typeof entry.name === 'string').map((entry) => ({
		id: typeof entry.id === 'number' ? entry.id : undefined,
		name: String(entry.name),
		weight: typeof entry.weight === 'number' ? entry.weight : Number(entry.weight ?? 0),
		color: typeof entry.color === 'string' && entry.color !== '' ? entry.color : '#5E8B7E',
		restrictions: isRecord(entry.restrictions) ? entry.restrictions : null,
		active: Boolean(entry.active ?? true),
	}))
}

function normalizeFields(entries: unknown): CatalogField[] {
	if (!Array.isArray(entries)) {
		return []
	}

	return entries.filter(isRecord).filter((entry) => typeof entry.fieldKey === 'string' && typeof entry.label === 'string').map((entry) => ({
		id: typeof entry.id === 'number' ? entry.id : undefined,
		fieldKey: String(entry.fieldKey),
		label: String(entry.label),
		fieldType: typeof entry.fieldType === 'string' ? entry.fieldType : 'text',
		required: Boolean(entry.required ?? false),
		preloadSource: typeof entry.preloadSource === 'string' ? entry.preloadSource : '',
		sortOrder: typeof entry.sortOrder === 'number' ? entry.sortOrder : Number(entry.sortOrder ?? 0),
		active: Boolean(entry.active ?? true),
	}))
}

function normalizeFilters(entries: unknown): SavedFilter[] {
	if (!Array.isArray(entries)) {
		return []
	}

	return entries.filter(isRecord).filter((entry) => typeof entry.name === 'string').map((entry, index) => ({
		id: typeof entry.id === 'number' ? entry.id : -(index + 1),
		ownerUid: typeof entry.ownerUid === 'string' ? entry.ownerUid : null,
		scopeType: typeof entry.scopeType === 'string' ? entry.scopeType : 'global',
		name: String(entry.name),
		criteria: isRecord(entry.criteria) ? entry.criteria : {},
		isPredefined: Boolean(entry.isPredefined ?? true),
		active: Boolean(entry.active ?? true),
		isDefault: Boolean(entry.isDefault ?? false),
		sortOrder: typeof entry.sortOrder === 'number' ? entry.sortOrder : (index + 1) * 10,
	}))
}

function normalizeRules(entries: unknown): AssignmentRule[] {
	if (!Array.isArray(entries)) {
		return []
	}

	return entries.filter(isRecord).map((entry) => ({
		id: typeof entry.id === 'number' ? entry.id : undefined,
		typeId: typeof entry.typeId === 'number' ? entry.typeId : Number.isFinite(Number(entry.typeId)) && Number(entry.typeId) > 0 ? Number(entry.typeId) : null,
		province: typeof entry.province === 'string' && entry.province.trim() !== '' ? entry.province.trim() : null,
		assignedUserUid: typeof entry.assignedUserUid === 'string' && entry.assignedUserUid !== '' ? entry.assignedUserUid : null,
		assignedGroupId: typeof entry.assignedGroupId === 'string' && entry.assignedGroupId !== '' ? entry.assignedGroupId : null,
		priority: typeof entry.priority === 'number' ? entry.priority : Number(entry.priority ?? 0),
	}))
}

function normalizeProfiles(entries: unknown): Array<{ id?: number, profile: string, principalType: 'user' | 'group', principalId: string }> {
	if (!Array.isArray(entries)) {
		return []
	}

	return entries.filter(isRecord).filter((entry) => typeof entry.profile === 'string').map((entry) => ({
		id: typeof entry.id === 'number' ? entry.id : undefined,
		profile: String(entry.profile),
		principalType: entry.principalType === 'group' ? 'group' : 'user',
		principalId: typeof entry.principalId === 'string' ? entry.principalId : '',
	}))
}

function normalizeTypes(entries: unknown): TypeNode[] {
	if (!Array.isArray(entries)) {
		return []
	}

	return entries.filter(isRecord).filter((entry) => typeof entry.name === 'string').map((entry, index) => ({
		id: typeof entry.id === 'number' ? entry.id : -(index + 1),
		parentId: typeof entry.parentId === 'number' ? entry.parentId : null,
		name: String(entry.name),
		slug: typeof entry.slug === 'string' ? entry.slug : '',
		level: typeof entry.level === 'number' ? entry.level : 0,
		sortOrder: typeof entry.sortOrder === 'number' ? entry.sortOrder : (index + 1) * 10,
		active: Boolean(entry.active ?? true),
		children: normalizeTypes(entry.children),
	}))
}

function addField() {
	fieldDrafts.value.push({
		fieldKey: '',
		label: '',
		fieldType: 'text',
		required: false,
		preloadSource: '',
		sortOrder: (fieldDrafts.value.length + 1) * 10,
		active: true,
		clientId: buildClientId('field', fieldDrafts.value.length + 1),
	})
	saveState.fields = 'Tienes cambios sin guardar.'
}

async function saveFields() {
	const payload = fieldDrafts.value
		.filter((field) => field.fieldKey.trim() !== '' && field.label.trim() !== '')
		.map(({ clientId, ...field }) => ({
			...field,
			fieldKey: field.fieldKey.trim(),
			label: field.label.trim(),
		}))

	await adminConfigStore.save({ fields: payload })
	syncDrafts()
	saveState.fields = 'Campos guardados.'
}

async function saveFilters(nextFilters: SavedFilter[]) {
	const payload = nextFilters.map((filter, index) => ({
		id: filter.id > 0 ? filter.id : undefined,
		name: filter.name.trim(),
		criteria: filter.criteria,
		active: Boolean(filter.active ?? true),
		isDefault: Boolean(filter.isDefault ?? false),
		sortOrder: (index + 1) * 10,
	}))

	await adminConfigStore.save({ filters: payload })
	syncDrafts()
	saveState.filters = 'Filtros guardados.'
}

async function saveStatuses() {
	const payload = statusDrafts.value.map(({ clientId, ...status }) => ({
		id: status.id,
		label: status.label.trim(),
		active: status.toggleable ? Boolean(status.active) : true,
	}))

	await adminConfigStore.save({ statuses: payload })
	syncDrafts()
	saveState.statuses = 'Estados guardados.'
}

function addRule() {
	loadState.renderError = ''
	ruleDrafts.value.push({
		typeId: null,
		province: null,
		assignedUserUid: null,
		assignedGroupId: null,
		priority: 100,
		clientId: buildClientId('rule', ruleDrafts.value.length + 1),
	})
	saveState.rules = 'Tienes cambios sin guardar.'
}

async function saveRules() {
	const payload = ruleDrafts.value
		.filter((rule) => (rule.typeId ?? 0) > 0)
		.map(({ clientId, ...rule }) => ({
			...rule,
			typeId: Number(rule.typeId),
			province: rule.province?.trim() ? rule.province.trim() : null,
		}))

	await adminConfigStore.save({ rules: payload })
	syncDrafts()
	saveState.rules = 'Reglas guardadas.'
}

function addProfile() {
	loadState.renderError = ''
	profileDrafts.value.push({
		profile: 'usuario',
		principalType: 'user',
		principalId: '',
		principalKey: '',
		clientId: buildClientId('profile', profileDrafts.value.length + 1),
	})
	saveState.profiles = 'Tienes cambios sin guardar.'
}

function updateProfilePrincipal(profile: ProfileDraft, principalKey: string | number | null) {
	const key = typeof principalKey === 'string' ? principalKey : ''
	const parsed = parsePrincipalKey(key)
	profile.principalKey = key
	profile.principalType = parsed.principalType
	profile.principalId = parsed.principalId
}

async function saveProfiles() {
	const payload = profileDrafts.value
		.filter((profile) => profile.profile !== '' && profile.principalId !== '')
		.map(({ clientId, principalKey, ...profile }) => profile)

	await adminConfigStore.save({ profiles: payload })
	syncDrafts()
	saveState.profiles = 'Perfiles guardados.'
}
function addUrgency() {
	urgencyDrafts.value.push({
		name: '',
		weight: urgencyDrafts.value.length + 1,
		color: '#5E8B7E',
		active: true,
		restrictions: null,
		clientId: buildClientId('urgency', urgencyDrafts.value.length + 1),
	})
	if (saveState.urgencies === '') {
		saveState.urgencies = 'Tienes cambios sin guardar.'
	}
}

function addRootType() {
	typeDrafts.value.push(createEmptyType(typeDrafts.value.length, 0))
	saveState.types = 'Tienes cambios sin guardar.'
}

function addChildType(parent: EditableTypeNode) {
	parent.children.push(createEmptyType(parent.children.length, parent.level + 1))
	saveState.types = 'Tienes cambios sin guardar.'
}

function createEmptyType(index: number, level: number): EditableTypeNode {
	return {
		name: '',
		slug: '',
		level,
		sortOrder: (index + 1) * 10,
		active: true,
		children: [],
		clientId: buildClientId('type', `${level}-${index}`),
	}
}

async function saveUrgencies() {
	const payload = urgencyDrafts.value
		.map(({ clientId, ...item }) => item)
		.filter((item) => item.name.trim() !== '')

	await adminConfigStore.save({ urgencies: payload })
	syncDrafts()
	saveState.urgencies = 'Criticidades guardadas.'
}

async function saveTypes() {
	await adminConfigStore.save({
		types: serializeTypeNodes(typeDrafts.value),
	})
	syncDrafts()
	saveState.types = 'Tipos guardados.'
}

function serializeTypeNodes(nodes: EditableTypeNode[]): Array<Record<string, unknown>> {
	return nodes
		.filter((node) => node.name.trim() !== '')
		.map((node, index) => ({
			id: node.id,
			name: node.name.trim(),
			slug: node.slug,
			level: node.level,
			sortOrder: node.sortOrder || (index + 1) * 10,
			active: node.active,
			children: serializeTypeNodes(node.children),
		}))
}

async function saveTasksConfig() {
	if (!adminData.value) {
		return
	}
	await adminConfigStore.save({ tasksConfig: { ...adminData.value.tasksConfig, enabled: taskConfig.enabled } })
	syncDrafts()
	saveState.tasks = 'Configuracion de Tasks guardada.'
}

function parseAllowedExtensions(rawValue: string): string[] {
	return Array.from(new Set(
		rawValue
			.split(/[\s,;\n\r]+/)
			.map((extension) => extension.trim().toLowerCase().replace(/^\./, ''))
			.filter((extension) => extension !== ''),
	))
}

async function saveAttachmentConfig() {
	const allowedExtensions = parseAllowedExtensions(attachmentConfig.allowedExtensionsText)
	await adminConfigStore.save({ attachmentConfig: { allowedExtensions, maxFileSizeMb: attachmentConfig.maxFileSizeMb } })
	syncDrafts()
	saveState.attachments = 'Configuracion de adjuntos guardada.'
}
</script>

<template>
	<section class="gi-page">
		<div v-if="loadState.error" class="gi-admin-feedback gi-admin-feedback--error">{{ loadState.error }}</div>
		<div v-else-if="loadState.loading" class="gi-admin-feedback">Cargando configuracion de administracion...</div>
		<template v-else>
		<header class="gi-page__header">
			<div class="gi-admin-header-actions">
				<button class="gi-secondary-button" type="button" @click="syncDrafts">Recargar</button>
			</div>
		</header>
		<nav class="gi-admin-topnav" aria-label="Secciones de administracion">
			<button
				v-for="section in adminSections"
				:key="section.id"
				class="gi-admin-topnav__item"
				:class="{ 'gi-admin-topnav__item--active': activeSection === section.id }"
				type="button"
				@click="activeSection = section.id">
				{{ section.label }}
			</button>
		</nav>

		<section v-if="activeSection === 'statuses'" class="gi-admin-panel">
			<section class="gi-admin-card gi-admin-card--fullwidth">
				<div class="gi-admin-card__header">
					<div>
						<h2>Estados</h2>
						<p>Los estados base no se eliminan. Puedes cambiar la etiqueta visible y activar o desactivar solo los estados permitidos.</p>
					</div>
					<div class="gi-admin-card__toolbar">
						<button class="gi-primary-button" type="button" @click="saveStatuses">Guardar</button>
					</div>
				</div>
				<ul class="gi-admin-list">
					<li v-for="status in statusItems" :key="status.clientId" class="gi-admin-row gi-admin-row--status">
						<div class="gi-admin-row__status-meta">
							<strong>{{ status.id }}</strong>
							<p>{{ status.description }}</p>
						</div>
						<label class="gi-field gi-field--wide">
							<span>Etiqueta visible</span>
							<input v-model="status.label" class="gi-input" type="text" placeholder="Etiqueta del estado" />
						</label>
						<label class="gi-field gi-admin-row__toggle">
							<span>Activo</span>
							<input v-model="status.active" type="checkbox" :disabled="!status.toggleable" />
						</label>
					</li>
				</ul>
				<p v-if="saveState.statuses" class="gi-admin-feedback">{{ saveState.statuses }}</p>
			</section>
		</section>

		<section v-if="activeSection === 'urgencies'" class="gi-admin-panel">
			<section class="gi-admin-card gi-admin-card--fullwidth">
				<div class="gi-admin-card__header">
					<div>
						<h2>Criticidades</h2>
						<p>Escala base editable para priorizar tickets desde el arranque.</p>
					</div>
					<div class="gi-admin-card__toolbar">
						<button class="gi-secondary-button" type="button" @click="addUrgency">Anadir criticidad</button>
						<button class="gi-primary-button" type="button" @click="saveUrgencies">Guardar</button>
					</div>
				</div>
				<ul class="gi-admin-list">
					<li v-for="urgency in urgencyItems" :key="urgency.clientId" class="gi-admin-row gi-admin-row--form">
						<label class="gi-field">
							<span>Nombre</span>
							<input v-model="urgency.name" class="gi-input" type="text" placeholder="Alta" />
						</label>
						<label class="gi-field gi-admin-row__weight">
							<span>Peso</span>
							<input v-model.number="urgency.weight" class="gi-input" type="number" min="1" />
						</label>
						<label class="gi-field gi-admin-row__color">
							<span>Color</span>
							<input v-model="urgency.color" class="gi-input gi-input--color" type="color" />
						</label>
						<label class="gi-field gi-admin-row__toggle">
							<span>Activa</span>
							<input v-model="urgency.active" type="checkbox" />
						</label>
					</li>
				</ul>
				<p v-if="saveState.urgencies" class="gi-admin-feedback">{{ saveState.urgencies }}</p>
			</section>
		</section>

		<section v-if="activeSection === 'types'" class="gi-admin-panel">
			<section class="gi-admin-card gi-admin-card--fullwidth">
				<div class="gi-admin-card__header">
					<div>
						<h2>Tipos y subtipos</h2>
						<p>Editor jerarquico para mantener la seleccion en cascada y las reglas de enrutado.</p>
					</div>
					<div class="gi-admin-card__toolbar">
						<button class="gi-secondary-button" type="button" @click="addRootType">Anadir tipo raiz</button>
						<button class="gi-primary-button" type="button" @click="saveTypes">Guardar</button>
					</div>
				</div>
				<AdminTypeTreeEditor :nodes="typeDrafts" @add-child="addChildType" />
				<ul class="gi-admin-list gi-admin-list--dense">
					<li v-for="item in requiredTypeCoverage" :key="item.path" class="gi-admin-row">
						<div class="gi-admin-row__title">{{ item.path }}</div>
						<span class="gi-meta-pill" :class="{ 'gi-meta-pill--ok': item.present, 'gi-meta-pill--warn': !item.present }">
							{{ item.present ? 'Disponible' : 'Falta' }}
						</span>
					</li>
				</ul>
				<p v-if="saveState.types" class="gi-admin-feedback">{{ saveState.types }}</p>
			</section>
		</section>

		<section v-if="activeSection === 'fields'" class="gi-admin-panel">
			<section class="gi-admin-card gi-admin-card--fullwidth">
				<div class="gi-admin-card__header">
					<div>
						<h2>Campos personales</h2>
						<p>Define los campos que puede editar el usuario en su configuracion personal y reutilizar en nuevos tickets.</p>
					</div>
					<div class="gi-admin-card__toolbar">
						<button class="gi-secondary-button" type="button" @click="addField">Anadir campo</button>
						<button class="gi-primary-button" type="button" @click="saveFields">Guardar</button>
					</div>
				</div>
				<ul class="gi-admin-list">
					<li v-for="field in fieldItems" :key="field.clientId" class="gi-admin-row gi-admin-row--form gi-admin-row--stacked">
						<label class="gi-field">
							<span>Clave</span>
							<input v-model="field.fieldKey" class="gi-input" type="text" placeholder="city" />
						</label>
						<label class="gi-field">
							<span>Etiqueta</span>
							<input v-model="field.label" class="gi-input" type="text" placeholder="Ciudad" />
						</label>
						<label class="gi-field">
							<span>Tipo</span>
							<SearchableSelect v-model="field.fieldType" :options="fieldTypeOptions" placeholder="Tipo de campo" />
						</label>
						<label class="gi-field">
							<span>Precarga</span>
							<SearchableSelect v-model="field.preloadSource" :options="preloadSourceOptions" placeholder="Origen de precarga" clearable />
						</label>
						<label class="gi-field gi-admin-row__weight">
							<span>Orden</span>
							<input v-model.number="field.sortOrder" class="gi-input" type="number" min="0" />
						</label>
						<label class="gi-field gi-admin-row__toggle">
							<span>Obligatorio</span>
							<input v-model="field.required" type="checkbox" />
						</label>
						<label class="gi-field gi-admin-row__toggle">
							<span>Activo</span>
							<input v-model="field.active" type="checkbox" />
						</label>
					</li>
				</ul>
				<p v-if="saveState.fields" class="gi-admin-feedback">{{ saveState.fields }}</p>
			</section>
		</section>

		<section v-if="activeSection === 'filters'" class="gi-admin-panel">
			<FilterCatalogEditor
				:filters="filterItems"
				:statuses="bootstrapStore.data.catalogs.statuses"
				:types="bootstrapStore.data.catalogs.types"
				:users="bootstrapStore.data.assignables.users"
				:groups="bootstrapStore.data.assignables.groups"
				title="Filtros"
				description="Gestiona los filtros globales de soporte, qué filtros están activos y cuál se aplica por defecto al abrir la consola."
				save-label="Guardar filtros"
				empty-label="No hay filtros globales configurados."
				@save="saveFilters"
			/>
			<p v-if="saveState.filters" class="gi-admin-feedback">{{ saveState.filters }}</p>
		</section>

		<section v-if="activeSection === 'tasks'" class="gi-admin-panel">
			<section class="gi-admin-card gi-admin-card--fullwidth">
				<div class="gi-admin-card__header">
					<div>
						<h2>Integracion con Tasks</h2>
						<p>Esta integracion es opcional y no bloquea el flujo principal.</p>
					</div>
				</div>
				<label class="gi-switch-row">
					<input v-model="taskConfig.enabled" type="checkbox" />
					<span>Activar sincronizacion con Tasks</span>
				</label>
				<button class="gi-secondary-button gi-admin-card__action" @click="saveTasksConfig">Guardar</button>
				<p v-if="saveState.tasks" class="gi-admin-feedback">{{ saveState.tasks }}</p>
			</section>
		</section>

		<section v-if="activeSection === 'attachments'" class="gi-admin-panel">
			<section class="gi-admin-card gi-admin-card--fullwidth">
				<div class="gi-admin-card__header">
					<div>
						<h2>Adjuntos</h2>
						<p>Define las extensiones permitidas y el tamano maximo. Si un fichero supera el limite, la UI ofrecera adjuntarlo como ruta URL.</p>
					</div>
					<div class="gi-admin-card__toolbar">
						<button class="gi-primary-button" type="button" @click="saveAttachmentConfig">Guardar</button>
					</div>
				</div>
				<label class="gi-field">
					<span>Tamano maximo por fichero (MB)</span>
					<input v-model.number="attachmentConfig.maxFileSizeMb" class="gi-input" type="number" min="1" />
				</label>
				<label class="gi-field gi-field--wide">
					<span>Extensiones permitidas</span>
					<textarea v-model="attachmentConfig.allowedExtensionsText" class="gi-textarea" rows="5" placeholder=".pdf, .doc, .docx, .xls, .xlsx, .csv, .jpg, .png, .mp3, .mp4, .mov" />
				</label>
				<p class="gi-admin-feedback">Introduce las extensiones separadas por comas, espacios o saltos de linea.</p>
				<p v-if="saveState.attachments" class="gi-admin-feedback">{{ saveState.attachments }}</p>
			</section>
		</section>

		<section v-if="activeSection === 'rules'" class="gi-admin-panel">
			<section class="gi-admin-card gi-admin-card--fullwidth">
				<div class="gi-admin-card__header">
					<div>
						<h2>Reglas de asignacion</h2>
						<p>Asocia tipos de ticket con usuarios o grupos reales de Nextcloud y permite excepciones por provincia.</p>
					</div>
					<div class="gi-admin-card__toolbar">
						<button class="gi-secondary-button" type="button" @click="addRule">Anadir regla</button>
						<button class="gi-primary-button" type="button" @click="saveRules">Guardar</button>
					</div>
				</div>
				<ul class="gi-admin-list">
					<li v-for="rule in ruleItems" :key="rule.clientId" class="gi-admin-row gi-admin-row--form gi-admin-row--stacked">
						<label class="gi-field gi-field--wide">
							<span>Tipo</span>
							<SearchableSelect v-model="rule.typeId" :options="typeOptions" placeholder="Selecciona tipo" />
						</label>
						<label class="gi-field">
							<span>Provincia</span>
							<SearchableSelect v-model="rule.province" :options="provinceOptions" placeholder="Todas las provincias" search-placeholder="Buscar provincia" clearable />
						</label>
						<label class="gi-field">
							<span>Usuario asignado</span>
							<SearchableSelect v-model="rule.assignedUserUid" :options="userOptions" placeholder="Sin usuario" clearable />
						</label>
						<label class="gi-field">
							<span>Grupo asignado</span>
							<SearchableSelect v-model="rule.assignedGroupId" :options="groupOptions" placeholder="Sin grupo" clearable />
						</label>
						<label class="gi-field gi-admin-row__weight">
							<span>Prioridad</span>
							<input v-model.number="rule.priority" class="gi-input" type="number" min="0" />
						</label>
					</li>
				</ul>
				<p v-if="saveState.rules" class="gi-admin-feedback">{{ saveState.rules }}</p>
			</section>
		</section>

		<section v-if="activeSection === 'profiles'" class="gi-admin-panel">
			<section class="gi-admin-card gi-admin-card--fullwidth">
				<div class="gi-admin-card__header">
					<div>
						<h2>Perfiles</h2>
						<p>Mapea perfiles funcionales de la app con usuarios o grupos reales de Nextcloud.</p>
					</div>
					<div class="gi-admin-card__toolbar">
						<button class="gi-secondary-button" type="button" @click="addProfile">Anadir perfil</button>
						<button class="gi-primary-button" type="button" @click="saveProfiles">Guardar</button>
					</div>
				</div>
				<ul class="gi-admin-list">
					<li v-for="profile in profileItems" :key="profile.clientId" class="gi-admin-row gi-admin-row--form gi-admin-row--stacked">
						<label class="gi-field">
							<span>Perfil</span>
							<SearchableSelect v-model="profile.profile" :options="profileOptions" placeholder="Selecciona perfil" />
						</label>
						<label class="gi-field gi-field--wide">
							<span>Usuario o grupo de Nextcloud</span>
							<SearchableSelect :model-value="profile.principalKey" :options="ensurePrincipalOption(profile.principalKey)" placeholder="Selecciona usuario o grupo" @update:model-value="updateProfilePrincipal(profile, $event)" />
						</label>
					</li>
				</ul>
				<p v-if="saveState.profiles" class="gi-admin-feedback">{{ saveState.profiles }}</p>
			</section>
		</section>

		<section v-if="activeSection === 'notifications'" class="gi-admin-panel">
			<section class="gi-admin-card gi-admin-card--fullwidth">
			<h2>Preferencias de notificacion</h2>
			<NotificationMatrix :items="notificationsStore.items" @toggle="notificationsStore.save" />
			</section>
		</section>
		<div v-if="loadState.renderError" class="gi-admin-feedback gi-admin-feedback--error">
			{{ loadState.renderError }}
		</div>
		</template>
	</section>
</template>

<style scoped>
.gi-admin-panel {
	width: 100%;
	min-width: 0;
}

.gi-admin-panel--stacked {
	display: grid;
	gap: 1rem;
}

.gi-admin-topnav {
	display: flex;
	gap: .65rem;
	overflow: auto;
	padding: .2rem 0 1rem;
	margin-bottom: 1rem;
	position: sticky;
	top: 0;
	z-index: 2;
	background: linear-gradient(180deg, rgba(244, 242, 231, .96), rgba(244, 242, 231, .78));
	backdrop-filter: blur(8px);
}

.gi-admin-topnav__item {
	border: 1px solid rgba(11, 110, 79, .12);
	background: rgba(255, 255, 255, .76);
	color: #23453d;
	border-radius: 999px;
	padding: .7rem 1rem;
	font: inherit;
	font-weight: 600;
	white-space: nowrap;
	cursor: pointer;
}

.gi-admin-topnav__item--active {
	background: rgba(11, 110, 79, .12);
	border-color: rgba(11, 110, 79, .18);
	color: #0b6e4f;
}

.gi-admin-header-actions,
.gi-admin-card__toolbar,
.gi-admin-card__header {
	display: flex;
	gap: .75rem;
}

.gi-admin-card__header {
	justify-content: space-between;
	align-items: flex-start;
	margin-bottom: 1rem;
	flex-wrap: wrap;
}

.gi-admin-card__header h2,
.gi-admin-card__header p {
	margin: 0;
}

.gi-admin-card__header p {
	margin-top: .3rem;
	color: #5f726b;
	max-width: 36rem;
}

.gi-admin-card__toolbar {
	align-items: center;
	flex-wrap: wrap;
}

.gi-admin-overview {
	display: grid;
	gap: .85rem;
	grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
	margin-bottom: 1rem;
}

.gi-stat-card,
.gi-admin-row,
.gi-admin-row__badges {
	display: flex;
	gap: .65rem;
}

.gi-stat-card,
.gi-admin-row {
	justify-content: space-between;
	align-items: center;
}

.gi-stat-card {
	padding: .9rem 1rem;
	border-radius: 18px;
	background: linear-gradient(135deg, rgba(232, 241, 238, .95), rgba(255, 255, 255, .96));
	border: 1px solid rgba(11, 110, 79, .1);
}

.gi-admin-card {
	padding: 1rem;
	border-radius: 22px;
	background: rgba(255, 255, 255, .92);
	border: 1px solid rgba(11, 110, 79, .12);
	overflow: auto;
	min-width: 0;
}

.gi-admin-card--fullwidth {
	width: 100%;
	max-width: none;
	min-height: calc(100vh - 17rem);
}

.gi-admin-card--highlight {
	margin-bottom: 1rem;
	background: linear-gradient(135deg, rgba(236, 242, 239, .94), rgba(255, 255, 255, .95));
}

.gi-admin-card__action {
	margin-top: .9rem;
}

.gi-admin-list {
	list-style: none;
	padding: 0;
	margin: 0;
	display: grid;
	gap: .65rem;
}

.gi-admin-list--dense {
	gap: .45rem;
}

.gi-admin-row {
	padding: .7rem .8rem;
	border-radius: 14px;
	background: rgba(236, 242, 239, .76);
}

.gi-admin-row--form {
	display: grid;
	grid-template-columns: minmax(0, 2.2fr) repeat(3, minmax(0, 1.25fr)) 7rem;
	gap: .75rem;
	align-items: end;
}

.gi-admin-row--status {
	display: grid;
	grid-template-columns: minmax(15rem, 1fr) minmax(0, 1.5fr);
	gap: .9rem;
	align-items: start;
}

.gi-admin-row__status-meta {
	display: grid;
	gap: .35rem;
}

.gi-admin-row__status-meta strong,
.gi-admin-row__status-meta p {
	margin: 0;
}

.gi-admin-row__status-meta p {
	color: #5f726b;
}

.gi-admin-row__title {
	display: flex;
	align-items: center;
	gap: .55rem;
	font-weight: 600;
}

.gi-admin-row__badges {
	flex-wrap: wrap;
	justify-content: flex-end;
}

.gi-admin-row__weight,
.gi-admin-row__color,
.gi-admin-row__toggle {
	min-width: 0;
}

.gi-admin-row__toggle {
	justify-items: center;
}

.gi-color-dot {
	width: .8rem;
	height: .8rem;
	border-radius: 999px;
	box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .08);
}

.gi-admin-feedback {
	margin: .85rem 0 0;
	color: #1f574c;
	font-weight: 600;
}

.gi-input--color {
	padding: .35rem;
	height: 2.9rem;
}

.gi-meta-pill--ok {
	background: rgba(11, 110, 79, .14);
	color: #0b6e4f;
}

.gi-meta-pill--warn {
	background: rgba(196, 118, 37, .14);
	color: #8b5d18;
}

@media (max-width: 900px) {
	.gi-admin-topnav {
		position: static;
		padding-top: 0;
	}

	.gi-admin-row--form {
		grid-template-columns: 1fr;
	}

	.gi-admin-row__toggle {
		justify-items: flex-start;
	}

	.gi-admin-card__header {
		flex-direction: column;
	}
}
</style>