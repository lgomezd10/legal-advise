<script setup lang="ts">
import { computed, ref } from 'vue'
import type { AssignableOption, SearchableSelectOption, StatusOption, Ticket, UrgencyCatalogItem } from '@/types'
import SearchableSelect from './SearchableSelect.vue'

const props = defineProps<{
	ticket: Ticket | null
	roles: string[]
	users?: AssignableOption[]
	groups?: AssignableOption[]
	statuses?: StatusOption[]
	urgencies?: UrgencyCatalogItem[]
	allowedExtensions?: string[]
	fullscreen?: boolean
	readOnly?: boolean
	showFullscreen?: boolean
	showRepeat?: boolean
}>()

const emit = defineEmits<{
	(e: 'comment', payload: { body: string, visibility: 'interno' | 'publico', files: File[] }): void
	(e: 'update', payload: Record<string, unknown>): void
	(e: 'download', attachmentId: number): void
	(e: 'fullscreen'): void
	(e: 'repeat'): void
}>()

const comment = ref('')
const visibility = ref<'interno' | 'publico'>('publico')
const selectedFiles = ref<File[]>([])
const composerError = ref('')

function normalizeAssignableOptions(options: AssignableOption[] | Record<string, AssignableOption> | undefined | null): AssignableOption[] {
	if (Array.isArray(options)) {
		return options
	}

	if (options && typeof options === 'object') {
		return Object.values(options)
	}

	return []
}

function normalizeStatuses(statuses: StatusOption[] | Record<string, StatusOption> | undefined | null): StatusOption[] {
	if (Array.isArray(statuses)) {
		return statuses
	}

	if (statuses && typeof statuses === 'object') {
		return Object.values(statuses)
	}

	return []
}

function normalizeUrgencies(urgencies: UrgencyCatalogItem[] | Record<string, UrgencyCatalogItem> | undefined | null): UrgencyCatalogItem[] {
	if (Array.isArray(urgencies)) {
		return urgencies
	}

	if (urgencies && typeof urgencies === 'object') {
		return Object.values(urgencies)
	}

	return []
}

const selectedUserUid = computed(() => props.ticket?.assignedUserUid ?? '')
const selectedGroupId = computed(() => props.ticket?.assignedGroupId ?? '')
const canManage = computed(() => !props.readOnly && (props.roles.includes('soporte') || props.roles.includes('administrador')))
const safeUsers = computed(() => normalizeAssignableOptions(props.users))
const safeGroups = computed(() => normalizeAssignableOptions(props.groups))
const safeStatuses = computed(() => normalizeStatuses(props.statuses))
const safeUrgencies = computed(() => normalizeUrgencies(props.urgencies))
const filteredUsers = computed<AssignableOption[]>(() => {
	if (!selectedGroupId.value) {
		return safeUsers.value
	}

	return safeUsers.value.filter((user: AssignableOption) => user.groupIds?.includes(selectedGroupId.value))
})
const filteredGroups = computed<AssignableOption[]>(() => {
	if (selectedGroupId.value) {
		return safeGroups.value
	}

	if (!selectedUserUid.value) {
		return safeGroups.value
	}

	return safeGroups.value.filter((group: AssignableOption) => group.userIds?.includes(selectedUserUid.value))
})
const statusOptions = computed<SearchableSelectOption[]>(() => safeStatuses.value.map((status: StatusOption) => ({
	value: status.id,
	label: status.label,
})))
const urgencyOptions = computed<SearchableSelectOption[]>(() => safeUrgencies.value.map((urgency: UrgencyCatalogItem) => ({
	value: String(urgency.id),
	label: urgency.name,
})))
const userOptions = computed<SearchableSelectOption[]>(() => filteredUsers.value.map((user: AssignableOption) => ({
	value: user.id,
	label: user.displayName,
	searchText: [user.id, ...(user.groupIds ?? [])].join(' '),
})))
const groupOptions = computed<SearchableSelectOption[]>(() => filteredGroups.value.map((group: AssignableOption) => ({
	value: group.id,
	label: group.displayName,
	searchText: [group.id, ...(group.userIds ?? [])].join(' '),
})))
const visibilityOptions: SearchableSelectOption[] = [
	{ value: 'publico', label: 'Publico' },
	{ value: 'interno', label: 'Interno' },
]
const normalizedAllowedExtensions = computed(() => (props.allowedExtensions ?? []).map((extension: string) => extension.trim().toLowerCase()).filter((extension: string) => extension !== ''))
const allowedExtensionsAccept = computed(() => normalizedAllowedExtensions.value.map((extension: string) => `.${extension}`).join(','))
const allowedExtensionsLabel = computed(() => normalizedAllowedExtensions.value.map((extension: string) => `.${extension}`).join(', '))

function sendComment() {
	const body = comment.value.trim()
	if (!body) {
		composerError.value = 'Debes escribir un comentario para adjuntar archivos.'
		return
	}

	composerError.value = ''
	emit('comment', { body, visibility: visibility.value, files: [...selectedFiles.value] })
	comment.value = ''
	selectedFiles.value = []
}

function onFileChange(event: Event) {
	const input = event.target as HTMLInputElement
	const files = Array.from(input.files ?? [])
	if (files.length === 0) {
		return
	}

	const allowedExtensions = normalizedAllowedExtensions.value
	const invalidFile = files.find((file) => !allowedExtensions.includes(file.name.split('.').pop()?.toLowerCase() ?? ''))
	if (invalidFile) {
		composerError.value = `La extension de ${invalidFile.name} no esta permitida.`
		input.value = ''
		return
	}

	const knownKeys = new Set(selectedFiles.value.map((file) => `${file.name}:${file.size}:${file.lastModified}`))
	for (const file of files) {
		const key = `${file.name}:${file.size}:${file.lastModified}`
		if (!knownKeys.has(key)) {
			selectedFiles.value.push(file)
			knownKeys.add(key)
		}
	}

	composerError.value = ''
	input.value = ''
}

function removeSelectedFile(index: number) {
	selectedFiles.value.splice(index, 1)
}

function onStatusChange(value: string | number | null) {
	if (!value) {
		return
	}

	emit('update', { status: String(value) })
}

function onUrgencyChange(value: string | number | null) {
	emit('update', { urgencyId: value ? Number(value) : null })
}

function onAssignedUserChange(value: string | number | null) {
	const nextUserUid = value ? String(value) : null
	const payload: Record<string, unknown> = {
		assignedUserUid: nextUserUid,
	}

	if (selectedGroupId.value && nextUserUid) {
		const validForGroup = safeUsers.value.some((user: AssignableOption) => user.id === nextUserUid && user.groupIds?.includes(selectedGroupId.value))
		if (!validForGroup) {
			payload.assignedGroupId = null
		}
	}

	emit('update', payload)
}

function onAssignedGroupChange(value: string | number | null) {
	const nextGroupId = value ? String(value) : null
	const payload: Record<string, unknown> = {
		assignedGroupId: nextGroupId,
	}

	if (nextGroupId && selectedUserUid.value) {
		const validForUser = safeUsers.value.some((user: AssignableOption) => user.id === selectedUserUid.value && user.groupIds?.includes(nextGroupId))
		if (!validForUser) {
			payload.assignedUserUid = null
		}
	}

	emit('update', payload)
}
</script>

<template>
	<div v-if="ticket" class="gi-sidebar-panel">
		<header class="gi-sidebar-panel__header">
			<div class="gi-sidebar-panel__title-block">
				<strong>{{ ticket.number }}</strong>
				<h2>{{ ticket.title }}</h2>
				<div class="gi-sidebar-panel__actions">
					<button v-if="showRepeat" class="gi-secondary-button gi-sidebar-panel__fullscreen-button" type="button" @click="emit('repeat')">
						Repetir incidencia
					</button>
					<button v-if="showFullscreen && !fullscreen" class="gi-secondary-button gi-sidebar-panel__fullscreen-button" type="button" @click="emit('fullscreen')">
					Pantalla completa
					</button>
				</div>
			</div>
		</header>
		<section class="gi-sidebar-panel__block">
			<p>{{ ticket.userDescription }}</p>
		</section>
		<section v-if="canManage" class="gi-sidebar-panel__block">
			<div class="gi-form-grid">
				<label class="gi-field">
					<span>Estado</span>
					<SearchableSelect :model-value="ticket.status" :options="statusOptions" placeholder="Estado" @update:modelValue="onStatusChange" />
				</label>
				<label class="gi-field">
					<span>Criticidad</span>
					<SearchableSelect :model-value="ticket.urgencyId ?? null" :options="urgencyOptions" placeholder="Sin criticidad" clearable @update:modelValue="onUrgencyChange" />
				</label>
				<label class="gi-field">
					<span>Asignado a usuario</span>
					<SearchableSelect :model-value="ticket.assignedUserUid ?? null" :options="userOptions" placeholder="Sin usuario" clearable @update:modelValue="onAssignedUserChange" />
				</label>
				<label class="gi-field">
					<span>Asignado a grupo</span>
					<SearchableSelect :model-value="ticket.assignedGroupId ?? null" :options="groupOptions" placeholder="Sin grupo" clearable @update:modelValue="onAssignedGroupChange" />
				</label>
				<label class="gi-field gi-field--wide"><span>Descripcion de soporte</span><textarea class="gi-textarea" :value="ticket.supportDescription" @change="emit('update', { supportDescription: ($event.target as HTMLTextAreaElement).value })" /></label>
			</div>
		</section>
		<section class="gi-sidebar-panel__block">
			<h3>Adjuntos</h3>
			<button v-for="attachment in ticket.attachments || []" :key="attachment.id" class="gi-secondary-button gi-attachment-link" @click="emit('download', attachment.id)">{{ attachment.originalName }}</button>
		</section>
		<section class="gi-sidebar-panel__block">
			<h3>Comentarios</h3>
			<article v-for="item in ticket.comments || []" :key="item.id" class="gi-comment">
				<strong>{{ item.authorUid }}</strong>
				<p>{{ item.body }}</p>
				<div v-if="item.attachments?.length" class="gi-comment__attachments">
					<button v-for="attachment in item.attachments" :key="attachment.id" class="gi-secondary-button gi-comment__attachment gi-attachment-link" @click="emit('download', attachment.id)">{{ attachment.originalName }}</button>
				</div>
			</article>
			<textarea v-model="comment" class="gi-textarea" rows="4" placeholder="Añadir comentario" />
			<div class="gi-sidebar-panel__composer-tools">
				<label class="gi-secondary-button gi-sidebar-panel__file-trigger" for="ticket-comment-files">Adjuntar archivos</label>
				<input id="ticket-comment-files" class="gi-sidebar-panel__file-input" type="file" multiple :accept="allowedExtensionsAccept" @change="onFileChange" />
				<span v-if="allowedExtensionsLabel" class="gi-sidebar-panel__helper">Permitidos: {{ allowedExtensionsLabel }}</span>
			</div>
			<ul v-if="selectedFiles.length" class="gi-sidebar-panel__selected-files">
				<li v-for="(file, index) in selectedFiles" :key="`${file.name}-${file.size}-${file.lastModified}`" class="gi-sidebar-panel__selected-file">
					<span>{{ file.name }}</span>
					<button class="gi-tertiary-button" type="button" @click="removeSelectedFile(index)">Quitar</button>
				</li>
			</ul>
			<p v-if="composerError" class="gi-form-error">{{ composerError }}</p>
			<SearchableSelect v-if="canManage" v-model="visibility" :options="visibilityOptions" placeholder="Visibilidad" />
			<button class="gi-primary-button" @click="sendComment">Publicar</button>
		</section>
	</div>
</template>

<style scoped>
.gi-sidebar-panel__header {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	gap: 1rem;
}

.gi-sidebar-panel__title-block {
	display: grid;
	gap: .45rem;
	min-width: 0;
}

.gi-sidebar-panel__header h2 {
	margin: .3rem 0 0;
}

.gi-sidebar-panel__fullscreen-button {
	justify-self: flex-start;
}

.gi-sidebar-panel__actions {
	display: flex;
	gap: .6rem;
	flex-wrap: wrap;
}

.gi-comment__attachments,
.gi-sidebar-panel__composer-tools,
.gi-sidebar-panel__selected-file {
	display: flex;
	gap: .65rem;
	align-items: center;
	flex-wrap: wrap;
}

.gi-comment__attachments {
	margin-top: .65rem;
}

.gi-comment__attachment {
	max-width: 100%;
}

.gi-attachment-link {
	font-size: .82rem;
	font-weight: 400 !important;
	line-height: 1.25;
}

.gi-sidebar-panel__composer-tools {
	margin-top: .75rem;
}

.gi-sidebar-panel__file-trigger {
	cursor: pointer;
}

.gi-sidebar-panel__file-input {
	position: absolute;
	width: 1px;
	height: 1px;
	opacity: 0;
	pointer-events: none;
}

.gi-sidebar-panel__helper {
	color: #5f726b;
	font-size: .9rem;
}

.gi-sidebar-panel__selected-files {
	list-style: none;
	padding: 0;
	margin: .75rem 0 0;
	display: grid;
	gap: .45rem;
}

.gi-sidebar-panel__selected-file {
	justify-content: space-between;
	padding: .65rem .8rem;
	border: 1px solid rgba(33, 53, 68, .12);
	border-radius: 12px;
	background: rgba(255, 255, 255, .7);
}
</style>