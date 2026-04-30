<script setup lang="ts">
import { computed, onErrorCaptured, onMounted, reactive, ref, watch } from 'vue'
import AdminTypeTreeEditor from '@/components/AdminTypeTreeEditor.vue'
import FilterCatalogEditor from '@/components/FilterCatalogEditor.vue'
import NotificationMatrix from '@/components/NotificationMatrix.vue'
import SearchableSelect from '@/components/SearchableSelect.vue'
import { useAdminConfigStore } from '@/store/adminConfig'
import { useBootstrapStore } from '@/store/bootstrap'
import type { AdminStatusOption, AssignmentRule, CatalogField, EditableTypeNode, NotificationMatrixItem, SavedFilter, SearchableSelectOption, TypeNode, UrgencyCatalogItem } from '@/types'

type AdminConfigData = {
	statuses: AdminStatusOption[]
	types: TypeNode[]
	urgencies: UrgencyCatalogItem[]
	fields: CatalogField[]
	filters: SavedFilter[]
	rules: AssignmentRule[]
	profiles: Array<{ id?: number, profile: string, principalType: string, principalId: string }>
	notifications: NotificationMatrixItem[]
	attachmentConfig: { allowedExtensions: string[], maxFileSizeMb: number }
	tasksConfig: Record<string, unknown>
}

type StatusDraft = AdminStatusOption & { clientId: string }
type UrgencyDraft = UrgencyCatalogItem & { clientId: string }
type FieldDraft = CatalogField & { clientId: string }
type FilterDraft = SavedFilter & { clientId: string }
type RuleDraft = AssignmentRule & { clientId: string }
type FixedProfileId = 'usuario' | 'soporte' | 'administrador'
type ProfileDraft = { id?: number, profile: FixedProfileId, principalType: 'user' | 'group', principalId: string, principalKey: string, clientId: string }
type ProfileSection = { id: FixedProfileId, label: string, description: string, items: ProfileDraft[] }
type TypeDeleteDialogState = {
	open: boolean
	targetClientId: string | null
	targetPath: string
	affectedRuleSummaries: string[]
	affectedRuleCount: number
}

type AssignablesState = {
	users?: unknown[]
	groups?: unknown[]
}

type AdminSectionId = 'info' | 'statuses' | 'urgencies' | 'types' | 'fields' | 'filters' | 'rules' | 'profiles' | 'attachments' | 'tasks' | 'notifications'

const adminConfigStore = useAdminConfigStore()
const bootstrapStore = useBootstrapStore()
const taskConfig = reactive<{ enabled: boolean }>({ enabled: false })
const attachmentConfig = reactive<{ allowedExtensionsText: string, maxFileSizeMb: number }>({ allowedExtensionsText: '', maxFileSizeMb: 25 })
const statusDrafts = ref<StatusDraft[]>([])
const urgencyDrafts = ref<UrgencyDraft[]>([])
const typeDrafts = ref<EditableTypeNode[]>([])
const fieldDrafts = ref<FieldDraft[]>([])
const filterDrafts = ref<FilterDraft[]>([])
const ruleDrafts = ref<RuleDraft[]>([])
const profileDrafts = ref<ProfileDraft[]>([])
const notificationDrafts = ref<NotificationMatrixItem[]>([])
const profileSelectionState = reactive<Record<FixedProfileId, string | number | null>>({
	usuario: null,
	soporte: null,
	administrador: null,
})
const activeSection = ref<AdminSectionId>('info')
const typeDeleteDialog = reactive<TypeDeleteDialogState>({
	open: false,
	targetClientId: null,
	targetPath: '',
	affectedRuleSummaries: [],
	affectedRuleCount: 0,
})
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
	{ id: 'info', label: 'Información' },
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

const adminTopNavSections = adminSections.filter((section) => section.id !== 'info')

const adminData = computed(() => adminConfigStore.data as AdminConfigData | null)
const statusItems = computed(() => statusDrafts.value)
const urgencyItems = computed(() => urgencyDrafts.value)
const fieldItems = computed(() => fieldDrafts.value)
const filterItems = computed(() => filterDrafts.value)
const ruleItems = computed(() => ruleDrafts.value)
const typeLines = computed(() => flattenTypes(typeDrafts.value))
const typeOptions = computed<SearchableSelectOption[]>(() => flattenTypeOptions(typeDrafts.value))
const assignables = computed<AssignablesState>(() => isRecord(bootstrapStore.data.assignables) ? bootstrapStore.data.assignables as AssignablesState : { users: [], groups: [] })
const userOptions = computed<SearchableSelectOption[]>(() => toAssignableOptions(assignables.value.users))
const groupOptions = computed<SearchableSelectOption[]>(() => toAssignableOptions(assignables.value.groups))
const principalOptions = computed<SearchableSelectOption[]>(() => toPrincipalOptions(assignables.value.users, assignables.value.groups))
const appInfo = computed(() => bootstrapStore.data.appInfo)
const provinceOptions = computed<SearchableSelectOption[]>(() => bootstrapStore.data.catalogs.provinces.map((province) => ({
	value: province,
	label: province,
})))
const preloadSourceOptions: SearchableSelectOption[] = [
	{ value: 'displayName', label: 'Nombre de Nextcloud' },
	{ value: 'email', label: 'Correo de Nextcloud' },
	{ value: 'phone', label: 'Teléfono del perfil' },
	{ value: 'location', label: 'Dirección del perfil' },
	{ value: '', label: 'Sin precarga' },
]
const fieldTypeOptions: SearchableSelectOption[] = [
	{ value: 'text', label: 'Texto' },
	{ value: 'email', label: 'Correo' },
	{ value: 'tel', label: 'Teléfono' },
]
const profileDefinitions: Array<{ id: FixedProfileId, label: string, description: string }> = [
	{ id: 'usuario', label: 'Usuario', description: 'Usuarios finales que pueden crear y consultar sus propios tickets.' },
	{ id: 'soporte', label: 'Soporte', description: 'Equipos que atienden incidencias y trabajan sobre la consola operativa.' },
	{ id: 'administrador', label: 'Administrador', description: 'Responsables de configuración y administración completa de la app.' },
]
const profileSections = computed<ProfileSection[]>(() => profileDefinitions.map((definition) => ({
	...definition,
	items: profileDrafts.value.filter((profile) => profile.profile === definition.id),
})))
const adminGroupAssignmentsCount = computed(() => profileDrafts.value.filter((profile) => profile.profile === 'administrador' && profile.principalType === 'group').length)
const requiredTypePaths = [
	'Necesito asesoramiento > Solo Territorial',
	'Necesito asesoramiento > Territorial y Legal',
	'Necesito asesoramiento > Territorial y Comunicación',
	'Necesito asesoramiento > Territorial, Legal y Comunicación',
	'Quiero informar',
]
const requiredTypeCoverage = computed(() => {
	const available = new Set(typeLines.value)
	return requiredTypePaths.map((path) => ({ path, present: available.has(path) }))
})
const typePathById = computed(() => {
	const paths = new Map<number, string>()
	collectTypePaths(typeDrafts.value, '', paths)
	return paths
})

onMounted(async() => {
	await hydrateAdminView()
})

onErrorCaptured((error) => {
	loadState.renderError = error instanceof Error ? error.message : 'Se produjo un error al renderizar esta seccion.'
	activeSection.value = 'info'
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

function getPrincipalLabel(principalKey: string): string {
	const option = ensurePrincipalOption(principalKey).find((entry) => String(entry.value) === principalKey)
	return option?.label ?? principalKey
}

async function hydrateAdminView() {
	loadState.loading = true
	loadState.error = ''

	try {
		await Promise.all([
			bootstrapStore.refresh().catch(() => undefined),
			adminConfigStore.load(),
		])
		syncDrafts()
	} catch (error) {
		loadState.error = error instanceof Error ? error.message : 'No se pudo cargar la configuración de administración.'
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

function collectTypePaths(nodes: Array<TypeNode | EditableTypeNode>, prefix: string, paths: Map<number, string>) {
	for (const node of nodes) {
		if (!isTypeNodeLike(node)) {
			continue
		}

		const label = prefix ? `${prefix} > ${node.name}` : node.name
		if (typeof node.id === 'number') {
			paths.set(node.id, label)
		}
		collectTypePaths(node.children, label, paths)
	}
}

function isTypeNodeLike(value: unknown): value is TypeNode | EditableTypeNode {
	return isRecord(value) && typeof value.name === 'string' && Array.isArray(value.children)
}

function syncDrafts() {
	profileSelectionState.usuario = null
	profileSelectionState.soporte = null
	profileSelectionState.administrador = null
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
	notificationDrafts.value = normalizeNotifications(adminData.value?.notifications)
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

function normalizeNotifications(entries: unknown): NotificationMatrixItem[] {
	if (!Array.isArray(entries)) {
		return []
	}

	const profileOrder: Record<string, number> = { usuario: 0, soporte: 1, administrador: 2 }
	const eventOrder: Record<string, number> = { ticket_created: 0, ticket_assigned: 1, ticket_waiting_for_creator: 2, ticket_group_assigned: 3, ticket_status_changed: 4, ticket_public_reply: 5, ticket_resolved: 6 }
	const normalizeDeliveryMode = (value: unknown): NotificationMatrixItem['deliveryMode'] => value === 'none' || value === 'nextcloud' || value === 'both'
		? value
		: 'nextcloud'

	return entries
		.filter(isRecord)
		.filter((entry) => typeof entry.scopeId === 'string' && typeof entry.eventName === 'string')
		.map((entry): NotificationMatrixItem => ({
			scopeId: String(entry.scopeId),
			eventName: String(entry.eventName),
			deliveryMode: normalizeDeliveryMode(entry.deliveryMode),
		}))
		.sort((left, right) => {
			const profileDiff = (profileOrder[left.scopeId] ?? 99) - (profileOrder[right.scopeId] ?? 99)
			if (profileDiff !== 0) {
				return profileDiff
			}

			return (eventOrder[left.eventName] ?? 99) - (eventOrder[right.eventName] ?? 99)
		})
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

function normalizeProfiles(entries: unknown): Array<{ id?: number, profile: FixedProfileId, principalType: 'user' | 'group', principalId: string }> {
	if (!Array.isArray(entries)) {
		return []
	}

	return entries
		.filter(isRecord)
		.filter((entry) => entry.profile === 'usuario' || entry.profile === 'soporte' || entry.profile === 'administrador')
		.map((entry) => ({
			id: typeof entry.id === 'number' ? entry.id : undefined,
			profile: entry.profile as FixedProfileId,
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
		.map(({ clientId: _clientId, ...field }) => ({
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
	const payload = statusDrafts.value.map(({ clientId: _clientId, ...status }) => ({
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
		.map(({ clientId: _clientId, ...rule }) => ({
			...rule,
			typeId: Number(rule.typeId),
			province: rule.province?.trim() ? rule.province.trim() : null,
		}))

	await adminConfigStore.save({ rules: payload })
	syncDrafts()
	saveState.rules = 'Reglas guardadas.'
}

function removeRule(clientId: string) {
	ruleDrafts.value = ruleDrafts.value.filter((rule) => rule.clientId !== clientId)
	saveState.rules = 'Tienes cambios sin guardar.'
}

function addProfileAssignment(profile: FixedProfileId) {
	loadState.renderError = ''
	const selectedKey = typeof profileSelectionState[profile] === 'string' ? profileSelectionState[profile] : ''
	const parsed = parsePrincipalKey(selectedKey)
	if (parsed.principalId === '') {
		return
	}

	const principalKey = buildPrincipalKey(parsed.principalType, parsed.principalId)
	const alreadyExists = profileDrafts.value.some((item) => item.profile === profile && item.principalKey === principalKey)
	if (alreadyExists) {
		profileSelectionState[profile] = null
		return
	}

	profileDrafts.value.push({
		profile,
		principalType: parsed.principalType,
		principalId: parsed.principalId,
		principalKey,
		clientId: buildClientId('profile', `${profile}-${profileDrafts.value.length + 1}`),
	})
	profileSelectionState[profile] = null
	saveState.profiles = 'Tienes cambios sin guardar.'
}

function removeProfileAssignment(clientId: string) {
	const profile = profileDrafts.value.find((item) => item.clientId === clientId)
	if (profile && !canRemoveProfileAssignment(profile)) {
		saveState.profiles = 'El perfil Administrador debe conservar al menos un grupo.'
		return
	}

	profileDrafts.value = profileDrafts.value.filter((profile) => profile.clientId !== clientId)
	saveState.profiles = 'Tienes cambios sin guardar.'
}

function canRemoveProfileAssignment(profile: ProfileDraft) {
	if (profile.profile !== 'administrador' || profile.principalType !== 'group') {
		return true
	}

	return adminGroupAssignmentsCount.value > 1
}

async function saveProfiles() {
	const payload = profileDrafts.value
		.filter((profile) => profile.principalId !== '')
		.map(({ clientId: _clientId, principalKey: _principalKey, ...profile }) => profile)

	const hasAdminGroup = payload.some((profile) => profile.profile === 'administrador' && profile.principalType === 'group')
	if (!hasAdminGroup) {
		saveState.profiles = 'El perfil Administrador debe conservar al menos un grupo.'
		return
	}

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

function buildEditableTypePath(node: EditableTypeNode, prefix = ''): string {
	const name = node.name.trim() || 'Nuevo tipo'
	return prefix ? `${prefix} > ${name}` : name
}

function findTypeNodeEntry(nodes: EditableTypeNode[], clientId: string, prefix = ''): { node: EditableTypeNode, index: number, siblings: EditableTypeNode[], path: string } | null {
	for (const [index, node] of nodes.entries()) {
		const path = buildEditableTypePath(node, prefix)
		if (node.clientId === clientId) {
			return { node, index, siblings: nodes, path }
		}

		const nested = findTypeNodeEntry(node.children, clientId, path)
		if (nested) {
			return nested
		}
	}

	return null
}

function collectTypeBranchIds(node: EditableTypeNode): number[] {
	const ids: number[] = []
	if (typeof node.id === 'number') {
		ids.push(node.id)
	}

	for (const child of node.children) {
		ids.push(...collectTypeBranchIds(child))
	}

	return ids
}

function describeRule(rule: RuleDraft): string {
	const typeLabel = typeof rule.typeId === 'number'
		? (typePathById.value.get(rule.typeId) ?? `Tipo ${rule.typeId}`)
		: 'Tipo sin definir'
	const provinceLabel = rule.province?.trim() ? `Provincia: ${rule.province.trim()}` : 'Todas las provincias'
	const assignmentLabel = rule.assignedUserUid
		? `Usuario: ${rule.assignedUserUid}`
		: rule.assignedGroupId
			? `Grupo: ${rule.assignedGroupId}`
			: 'Sin asignación'

	return `${typeLabel} · ${provinceLabel} · ${assignmentLabel}`
}

function requestTypeRemoval(clientId: string) {
	const entry = findTypeNodeEntry(typeDrafts.value, clientId)
	if (!entry) {
		return
	}

	const branchTypeIds = new Set(collectTypeBranchIds(entry.node))
	const affectedRules = ruleDrafts.value.filter((rule) => typeof rule.typeId === 'number' && branchTypeIds.has(rule.typeId))
	typeDeleteDialog.open = true
	typeDeleteDialog.targetClientId = clientId
	typeDeleteDialog.targetPath = entry.path
	typeDeleteDialog.affectedRuleSummaries = affectedRules.map(describeRule)
	typeDeleteDialog.affectedRuleCount = affectedRules.length
	loadState.renderError = ''
}

function closeTypeDeleteDialog() {
	typeDeleteDialog.open = false
	typeDeleteDialog.targetClientId = null
	typeDeleteDialog.targetPath = ''
	typeDeleteDialog.affectedRuleSummaries = []
	typeDeleteDialog.affectedRuleCount = 0
}

function confirmTypeRemoval() {
	const targetClientId = typeDeleteDialog.targetClientId
	if (!targetClientId) {
		closeTypeDeleteDialog()
		return
	}

	const entry = findTypeNodeEntry(typeDrafts.value, targetClientId)
	if (!entry) {
		closeTypeDeleteDialog()
		return
	}

	const branchTypeIds = new Set(collectTypeBranchIds(entry.node))
	entry.siblings.splice(entry.index, 1)
	ruleDrafts.value = ruleDrafts.value.filter((rule) => !(typeof rule.typeId === 'number' && branchTypeIds.has(rule.typeId)))
	saveState.types = 'Tienes cambios sin guardar.'
	if (typeDeleteDialog.affectedRuleCount > 0) {
		saveState.rules = 'Tienes cambios sin guardar.'
	}
	closeTypeDeleteDialog()
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
		.map(({ clientId: _clientId, ...item }) => item)
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
	saveState.tasks = 'Configuración de Tasks guardada.'
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
	saveState.attachments = 'Configuración de adjuntos guardada.'
}

async function saveNotifications(items: NotificationMatrixItem[]) {
	notificationDrafts.value = [...items]
	await adminConfigStore.save({ notifications: items })
	syncDrafts()
}
</script>

<template>
	<section class="gi-page">
		<div v-if="loadState.error" class="gi-admin-feedback gi-admin-feedback--error">{{ loadState.error }}</div>
		<div v-else-if="loadState.loading" class="gi-admin-feedback">Cargando configuración de administración...</div>
		<template v-else>
		<header class="gi-page__header">
			<div class="gi-admin-header-actions">
				<button class="gi-secondary-button" type="button" @click="syncDrafts">Recargar</button>
			</div>
		</header>
		<label class="gi-field gi-admin-mobile-menu" aria-label="Sección de administración">
			<span class="gi-filter-toolbar__label">Sección</span>
			<select id="admin-active-section" v-model="activeSection" name="admin-active-section" class="gi-input gi-admin-mobile-menu__select">
				<option v-for="section in adminSections" :key="section.id" :value="section.id">{{ section.label }}</option>
			</select>
		</label>
		<nav class="gi-admin-topnav" aria-label="Secciones de administracion">
			<button
				v-for="section in adminTopNavSections"
				:key="section.id"
				class="gi-admin-topnav__item"
				:class="{ 'gi-admin-topnav__item--active': activeSection === section.id }"
				type="button"
				@click="activeSection = section.id">
				{{ section.label }}
			</button>
		</nav>

		<section v-if="activeSection === 'info'" class="gi-admin-panel gi-admin-panel--stacked">
			<section class="gi-admin-card gi-admin-card--fullwidth">
				<div class="gi-admin-card__header">
					<div>
						<h2>Información de la aplicación</h2>
						<p>Resumen operativo de la instalación actual de Consultas Legales.</p>
					</div>
				</div>
				<div class="gi-admin-overview gi-admin-overview--info">
					<article class="gi-stat-card gi-stat-card--stacked">
						<span class="gi-stat-card__label">Espacio ocupado</span>
						<strong class="gi-stat-card__value">{{ appInfo.storageLabel }}</strong>
						<p class="gi-stat-card__detail">{{ appInfo.storageBytes.toLocaleString('es-ES') }} bytes almacenados por la aplicación.</p>
					</article>
					<article class="gi-stat-card gi-stat-card--stacked">
						<span class="gi-stat-card__label">Versión de la aplicación</span>
						<strong class="gi-stat-card__value">{{ appInfo.version || 'No disponible' }}</strong>
						<p class="gi-stat-card__detail">Identificador interno: {{ appInfo.id }}</p>
					</article>
				</div>
			</section>
		</section>

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
						<label class="gi-field gi-admin-row__toggle gi-admin-row__toggle--status">
							<span>Activo</span>
							<input :id="`status-active-${status.clientId}`" v-model="status.active" :name="`status-active-${status.id}`" type="checkbox" :disabled="!status.toggleable" />
						</label>
						<label class="gi-field gi-field--wide">
							<span>Etiqueta visible</span>
							<input :id="`status-label-${status.clientId}`" v-model="status.label" :name="`status-label-${status.id}`" class="gi-input" type="text" placeholder="Etiqueta del estado" />
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
						<button class="gi-secondary-button" type="button" @click="addUrgency">Añadir criticidad</button>
						<button class="gi-primary-button" type="button" @click="saveUrgencies">Guardar</button>
					</div>
				</div>
				<ul class="gi-admin-list">
					<li v-for="urgency in urgencyItems" :key="urgency.clientId" class="gi-admin-row gi-admin-row--form">
						<label class="gi-field">
							<span>Nombre</span>
							<input :id="`urgency-name-${urgency.clientId}`" v-model="urgency.name" :name="`urgency-name-${urgency.clientId}`" class="gi-input" type="text" placeholder="Alta" />
						</label>
						<label class="gi-field gi-admin-row__weight">
							<span>Peso</span>
							<input :id="`urgency-weight-${urgency.clientId}`" v-model.number="urgency.weight" :name="`urgency-weight-${urgency.clientId}`" class="gi-input" type="number" min="1" />
						</label>
						<label class="gi-field gi-admin-row__color">
							<span>Color</span>
							<input :id="`urgency-color-${urgency.clientId}`" v-model="urgency.color" :name="`urgency-color-${urgency.clientId}`" class="gi-input gi-input--color" type="color" />
						</label>
						<label class="gi-field gi-admin-row__toggle">
							<span>Activa</span>
							<input :id="`urgency-active-${urgency.clientId}`" v-model="urgency.active" :name="`urgency-active-${urgency.clientId}`" type="checkbox" />
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
						<button class="gi-secondary-button" type="button" @click="addRootType">Añadir tipo raiz</button>
						<button class="gi-primary-button" type="button" @click="saveTypes">Guardar</button>
					</div>
				</div>
				<AdminTypeTreeEditor :nodes="typeDrafts" @add-child="addChildType" @request-remove="requestTypeRemoval" />
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
						<p>Define los campos que puede editar el usuario en su configuración personal y reutilizar en nuevos tickets.</p>
					</div>
					<div class="gi-admin-card__toolbar">
						<button class="gi-secondary-button" type="button" @click="addField">Añadir campo</button>
						<button class="gi-primary-button" type="button" @click="saveFields">Guardar</button>
					</div>
				</div>
				<ul class="gi-admin-list">
					<li v-for="field in fieldItems" :key="field.clientId" class="gi-admin-row gi-admin-row--form gi-admin-row--stacked">
						<label class="gi-field">
							<span>Clave</span>
							<input :id="`field-key-${field.clientId}`" v-model="field.fieldKey" :name="`field-key-${field.clientId}`" class="gi-input" type="text" placeholder="city" />
						</label>
						<label class="gi-field">
							<span>Etiqueta</span>
							<input :id="`field-label-${field.clientId}`" v-model="field.label" :name="`field-label-${field.clientId}`" class="gi-input" type="text" placeholder="Ciudad" />
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
							<input :id="`field-order-${field.clientId}`" v-model.number="field.sortOrder" :name="`field-order-${field.clientId}`" class="gi-input" type="number" min="0" />
						</label>
						<label class="gi-field gi-admin-row__toggle">
							<span>Obligatorio</span>
							<input :id="`field-required-${field.clientId}`" v-model="field.required" :name="`field-required-${field.clientId}`" type="checkbox" />
						</label>
						<label class="gi-field gi-admin-row__toggle">
							<span>Activo</span>
							<input :id="`field-active-${field.clientId}`" v-model="field.active" :name="`field-active-${field.clientId}`" type="checkbox" />
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
				<div class="gi-admin-attachment-config">
					<label class="gi-field">
						<span>Tamano maximo por fichero (MB)</span>
						<input id="attachment-max-file-size" v-model.number="attachmentConfig.maxFileSizeMb" name="attachment-max-file-size" class="gi-input" type="number" min="1" />
					</label>
					<label class="gi-field gi-admin-attachment-config__extensions">
						<span>Extensiones permitidas</span>
						<textarea
							id="attachment-allowed-extensions"
							v-model="attachmentConfig.allowedExtensionsText"
							name="attachment-allowed-extensions"
							class="gi-textarea gi-admin-attachment-config__textarea"
							rows="3"
							placeholder=".pdf, .doc, .docx, .xls, .xlsx, .csv, .jpg, .png, .mp3, .mp4, .mov"
						/>
					</label>
				</div>
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
						<button class="gi-secondary-button" type="button" @click="addRule">Añadir regla</button>
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
							<input :id="`rule-priority-${rule.clientId}`" v-model.number="rule.priority" :name="`rule-priority-${rule.clientId}`" class="gi-input" type="number" min="0" />
						</label>
						<div class="gi-admin-row__inline-actions gi-admin-row__inline-actions--end">
							<button class="gi-round-icon-button gi-admin-row__danger-button" type="button" :aria-label="`Eliminar regla ${rule.id ?? rule.clientId}`" title="Eliminar regla" @click="removeRule(rule.clientId)">×</button>
						</div>
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
						<p>Los perfiles son fijos. Añade tantos usuarios o grupos de Nextcloud como necesites dentro de cada perfil.</p>
					</div>
					<div class="gi-admin-card__toolbar">
						<button class="gi-primary-button" type="button" @click="saveProfiles">Guardar</button>
					</div>
				</div>
				<div class="gi-profile-grid">
					<section v-for="section in profileSections" :key="section.id" class="gi-profile-card">
						<div class="gi-profile-card__header">
							<div>
								<h3>{{ section.label }}</h3>
								<p>{{ section.description }}</p>
							</div>
						</div>
						<div class="gi-profile-card__body">
							<div class="gi-profile-editor">
								<div class="gi-profile-editor__chips" :class="{ 'gi-profile-editor__chips--empty': section.items.length === 0 }">
									<p v-if="section.items.length === 0" class="gi-profile-card__empty">No hay usuarios o grupos asignados a este perfil.</p>
									<button
										v-for="profile in section.items"
										:key="profile.clientId"
										class="gi-profile-chip"
										type="button"
										:disabled="!canRemoveProfileAssignment(profile)"
										:title="!canRemoveProfileAssignment(profile) ? 'El perfil Administrador debe conservar al menos un grupo.' : undefined"
										@click="removeProfileAssignment(profile.clientId)">
										<span class="gi-profile-chip__label">{{ getPrincipalLabel(profile.principalKey) }}</span>
										<span class="gi-profile-chip__remove">×</span>
									</button>
								</div>
								<div class="gi-profile-editor__controls">
									<div class="gi-profile-editor__select">
										<SearchableSelect
											:model-value="profileSelectionState[section.id]"
											:options="principalOptions"
											placeholder="Añadir usuario o grupo"
											search-placeholder="Buscar usuario o grupo"
											clearable
											@update:model-value="profileSelectionState[section.id] = $event" />
									</div>
									<button class="gi-primary-button gi-profile-editor__add" type="button" @click="addProfileAssignment(section.id)">Añadir</button>
								</div>
							</div>
						</div>
					</section>
				</div>
				<p v-if="saveState.profiles" class="gi-admin-feedback">{{ saveState.profiles }}</p>
			</section>
		</section>

		<section v-if="activeSection === 'tasks'" class="gi-admin-panel">
			<section class="gi-admin-card gi-admin-card--fullwidth">
				<div class="gi-admin-card__header">
					<div>
						<h2>Integracion con Tasks</h2>
						<p>Activa o desactiva la sincronización best-effort con Nextcloud Tasks sin bloquear el flujo principal de tickets.</p>
					</div>
					<div class="gi-admin-card__toolbar">
						<button class="gi-primary-button" type="button" @click="saveTasksConfig">Guardar</button>
					</div>
				</div>
				<label class="gi-field gi-admin-row__toggle gi-admin-row__toggle--status">
					<span>Integración habilitada</span>
					<input id="tasks-enabled" v-model="taskConfig.enabled" name="tasks-enabled" type="checkbox" />
				</label>
				<p class="gi-admin-feedback">Si Tasks no está disponible, la app seguirá funcionando y registrará el estado de sincronización cuando proceda.</p>
				<p v-if="saveState.tasks" class="gi-admin-feedback">{{ saveState.tasks }}</p>
			</section>
		</section>

		<section v-if="activeSection === 'notifications'" class="gi-admin-panel">
			<section class="gi-admin-card gi-admin-card--fullwidth">
			<h2>Preferencias de notificacion</h2>
			<p>Esta configuración define la política base para cada perfil. Las preferencias del ticket y del usuario solo pueden limitar estos canales, no ampliarlos.</p>
			<NotificationMatrix
				:items="notificationDrafts"
				:scope-labels="{ usuario: 'Usuario', soporte: 'Soporte', administrador: 'Administración' }"
				:delivery-options="[
					{ value: 'none', label: 'Ninguna' },
					{ value: 'nextcloud', label: 'Nextcloud' },
					{ value: 'both', label: 'Nextcloud y correo' },
				]"
				@toggle="saveNotifications"
			/>
			</section>
		</section>
		<div v-if="loadState.renderError" class="gi-admin-feedback gi-admin-feedback--error">
			{{ loadState.renderError }}
		</div>
		<div v-if="typeDeleteDialog.open" class="gi-app-dialog-backdrop gi-dialog-backdrop" @click.self="closeTypeDeleteDialog">
			<section class="gi-app-dialog gi-dialog gi-dialog--compact" aria-label="Eliminar tipo o subtipo">
				<header class="gi-dialog__header">
					<h3 class="gi-dialog__title">Eliminar tipo</h3>
					<button class="gi-modal-close" type="button" aria-label="Cerrar ventana" @click="closeTypeDeleteDialog">x</button>
				</header>
				<p class="gi-dialog__message gi-dialog__message--neutral">
					¿Quieres eliminar <strong>{{ typeDeleteDialog.targetPath }}</strong>?
				</p>
				<p v-if="typeDeleteDialog.affectedRuleCount > 0" class="gi-dialog__message gi-dialog__message--neutral">
					Esta rama tiene {{ typeDeleteDialog.affectedRuleCount }} regla<span v-if="typeDeleteDialog.affectedRuleCount !== 1">s</span> de asignación asociada<span v-if="typeDeleteDialog.affectedRuleCount !== 1">s</span>. Si aceptas, esos flujos también se quitarán de la configuración.
				</p>
				<ul v-if="typeDeleteDialog.affectedRuleSummaries.length > 0" class="gi-admin-delete-impact-list">
					<li v-for="summary in typeDeleteDialog.affectedRuleSummaries" :key="summary">{{ summary }}</li>
				</ul>
				<footer class="gi-dialog__footer">
					<button class="gi-ghost-button" type="button" @click="closeTypeDeleteDialog">Cancelar</button>
					<button class="gi-secondary-button gi-dialog__danger" type="button" @click="confirmTypeRemoval">Eliminar</button>
				</footer>
			</section>
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

.gi-admin-delete-impact-list {
	margin: 0;
	padding-left: 1.1rem;
	color: var(--gi-color-text-muted);
	display: grid;
	gap: .35rem;
}

.gi-admin-row__inline-actions {
	display: flex;
	align-items: flex-end;
	justify-content: flex-end;
}

.gi-admin-row__inline-actions--end {
	margin-left: auto;
}

.gi-admin-row__danger-button {
	width: 2rem;
	height: 2rem;
	color: var(--gi-color-danger, #b42318);
	font-size: 1.15rem;
	line-height: 1;
}

.gi-admin-topnav {
	display: flex;
	gap: .65rem;
	overflow: auto;
	padding: .6rem .85rem .9rem 1rem;
	margin-left: .65rem;
	margin-bottom: 1rem;
	position: sticky;
	top: 0;
	z-index: 2;
	background: var(--gi-color-surface-muted);
	border: 1px solid var(--gi-color-border);
	border-radius: 18px;
	backdrop-filter: blur(8px);
	align-items: center;
	min-height: 4rem;
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

.gi-admin-mobile-menu {
	display: none;
	margin-bottom: 1rem;
	max-width: 24rem;
}

.gi-admin-mobile-menu__select {
	min-height: 2.9rem;
	border-radius: 16px;
}

.gi-admin-card__header {
	display: flex;
	gap: .75rem;
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

.gi-stat-card--stacked {
	align-items: flex-start;
	justify-content: flex-start;
	flex-direction: column;
	gap: .35rem;
}

.gi-stat-card__label,
.gi-stat-card__detail {
	margin: 0;
}

.gi-stat-card__label {
	font-size: .8rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: .04em;
	color: #5f726b;
}

.gi-stat-card__value {
	font-size: 1.35rem;
	line-height: 1.1;
	color: #173a33;
}

.gi-stat-card__detail {
	color: #5f726b;
}

.gi-admin-overview--info {
	grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
	margin-bottom: 0;
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

.gi-admin-attachment-config {
	display: grid;
	grid-template-columns: minmax(16rem, 24rem) minmax(0, 1fr);
	gap: 1rem;
	align-items: start;
}

.gi-admin-attachment-config__extensions {
	min-width: 0;
	grid-column: 1 / -1;
}

.gi-admin-attachment-config__textarea {
	width: 100%;
	min-height: 0;
	resize: vertical;
}

.gi-profile-grid {
	display: grid;
	grid-template-columns: 1fr;
	gap: 1rem;
}

.gi-profile-card {
	display: grid;
	gap: .85rem;
	padding: .95rem;
	border-radius: 18px;
	background: linear-gradient(180deg, rgba(236, 242, 239, .72), rgba(255, 255, 255, .92));
	border: 1px solid rgba(11, 110, 79, .1);
	align-content: start;
}

.gi-profile-card__header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	gap: .75rem;
	flex-wrap: wrap;
}

.gi-profile-card__header h3,
.gi-profile-card__header p {
	margin: 0;
}

.gi-profile-card__header p,
.gi-profile-card__empty {
	color: #5f726b;
}

.gi-profile-card__body {
	display: grid;
	grid-template-columns: minmax(0, 1fr);
	gap: .8rem;
	align-items: start;
}

.gi-profile-card__empty {
	margin: 0;
	padding: .7rem .85rem;
	border-radius: 14px;
	background: rgba(255, 255, 255, .72);
	border: 1px dashed rgba(11, 110, 79, .18);
}

.gi-profile-editor {
	display: grid;
	gap: .75rem;
	min-width: 0;
}

.gi-profile-editor__chips {
	display: flex;
	flex-wrap: wrap;
	gap: .55rem;
	padding: .25rem 0;
	min-height: 2.6rem;
	align-items: flex-start;
}

.gi-profile-editor__chips--empty {
	padding-top: 0;
	padding-bottom: 0;
}

.gi-profile-chip {
	display: inline-flex;
	align-items: center;
	gap: .55rem;
	max-width: 100%;
	padding: .45rem .7rem;
	border-radius: 999px;
	border: 1px solid rgba(55, 94, 89, .16);
	background: linear-gradient(180deg, rgba(234, 241, 238, .98), rgba(248, 250, 249, .98));
	color: #21463f;
	font: inherit;
	cursor: pointer;
}

.gi-profile-chip__label {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.gi-profile-chip__remove {
	font-size: 1rem;
	line-height: 1;
	color: #48645d;
}

.gi-profile-editor__controls {
	display: grid;
	grid-template-columns: minmax(0, 1fr) auto;
	gap: .75rem;
	align-items: end;
}

.gi-profile-editor__select {
	min-width: 0;
}

.gi-profile-editor__add {
	min-height: 2.7rem;
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
	grid-template-columns: minmax(15rem, 1fr) auto minmax(0, 1.5fr);
	gap: .9rem;
	align-items: center;
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

.gi-admin-row__toggle--status {
	min-width: 5.5rem;
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
	.gi-admin-attachment-config {
		grid-template-columns: 1fr;
	}

	.gi-admin-mobile-menu {
		display: grid;
	}

	.gi-admin-topnav {
		display: none;
	}

	.gi-admin-row--form {
		grid-template-columns: 1fr;
	}

	.gi-admin-row--status {
		grid-template-columns: 1fr;
		align-items: start;
	}

	.gi-admin-row__toggle {
		justify-items: flex-start;
	}

	.gi-admin-card__header {
		flex-direction: column;
	}

	.gi-profile-grid {
		grid-template-columns: 1fr;
	}

	.gi-profile-card__body,
	.gi-profile-editor__controls {
		grid-template-columns: 1fr;
	}
}
</style>