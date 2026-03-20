<script setup lang="ts">
import { computed, ref } from 'vue'
import type { AssignableOption, SearchableSelectOption, StatusOption, Ticket, TicketAttachment, TicketAttachmentLinkDraft, TicketComment, UrgencyCatalogItem } from '@/types'
import AttachmentPicker from './AttachmentPicker.vue'
import SearchableSelect from './SearchableSelect.vue'

const props = defineProps<{
	ticket: Ticket | null
	roles: string[]
	users?: AssignableOption[]
	groups?: AssignableOption[]
	statuses?: StatusOption[]
	urgencies?: UrgencyCatalogItem[]
	allowedExtensions?: string[]
	maxFileSizeMb?: number
	fullscreen?: boolean
	readOnly?: boolean
	showFullscreen?: boolean
	showRepeat?: boolean
}>()

const emit = defineEmits<{
	(e: 'comment', payload: { body: string, visibility: 'interno' | 'publico', files: File[], links: TicketAttachmentLinkDraft[] }): void
	(e: 'update', payload: Record<string, unknown>): void
	(e: 'download', attachmentId: number): void
	(e: 'fullscreen'): void
	(e: 'repeat'): void
}>()

const comment = ref('')
const visibility = ref<'interno' | 'publico'>('publico')
const attachmentsDraft = ref<{ files: File[], links: TicketAttachmentLinkDraft[] }>({ files: [], links: [] })
const composerError = ref('')
const commentsModalOpen = ref(false)
const commentsSearchText = ref('')
const commentsDateFrom = ref('')
const commentsDateTo = ref('')
const commentsAuthorUid = ref<string | null>(null)
const expandedCommentIds = ref<number[]>([])

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
const canManage = computed(() => !props.readOnly && (props.ticket?.canManage ?? (props.roles.includes('soporte') || props.roles.includes('administrador'))))
const canComment = computed(() => props.ticket?.canComment ?? true)
const safeUsers = computed(() => normalizeAssignableOptions(props.users))
const safeGroups = computed(() => normalizeAssignableOptions(props.groups))
const safeStatuses = computed(() => normalizeStatuses(props.statuses))
const safeUrgencies = computed(() => normalizeUrgencies(props.urgencies))
const statusOptions = computed<SearchableSelectOption[]>(() => safeStatuses.value.map((status: StatusOption) => ({
	value: status.id,
	label: status.label,
})))
const urgencyOptions = computed<SearchableSelectOption[]>(() => safeUrgencies.value.map((urgency: UrgencyCatalogItem) => ({
	value: String(urgency.id),
	label: urgency.name,
})))
const userOptions = computed<SearchableSelectOption[]>(() => safeUsers.value.map((user: AssignableOption) => ({
	value: user.id,
	label: user.displayName,
	searchText: [user.id, ...(user.groupIds ?? [])].join(' '),
})))
const groupOptions = computed<SearchableSelectOption[]>(() => safeGroups.value.map((group: AssignableOption) => ({
	value: group.id,
	label: group.displayName,
	searchText: [group.id, ...(group.userIds ?? [])].join(' '),
})))
const visibilityOptions: SearchableSelectOption[] = [
	{ value: 'publico', label: 'Publico' },
	{ value: 'interno', label: 'Interno' },
]
const commentAuthorOptions = computed((): SearchableSelectOption[] => {
	const seen = new Set<string>()
	return (props.ticket?.comments ?? []).reduce((options: SearchableSelectOption[], item: TicketComment) => {
		if (!item.authorUid || seen.has(item.authorUid)) {
			return options
		}
		seen.add(item.authorUid)
		const user = safeUsers.value.find((entry) => entry.id === item.authorUid)
		options.push({ value: item.authorUid, label: user?.displayName ?? item.authorUid })
		return options
	}, [])
})
const filteredComments = computed(() => (props.ticket?.comments ?? []).filter((item: TicketComment) => {
	const term = commentsSearchText.value.trim().toLowerCase()
	if (term) {
		const haystack = `${item.authorUid} ${item.body} ${(item.attachments ?? []).map((attachment: TicketAttachment) => attachment.originalName).join(' ')}`.toLowerCase()
		if (!haystack.includes(term)) {
			return false
		}
	}

	if (commentsAuthorUid.value && item.authorUid !== commentsAuthorUid.value) {
		return false
	}

	const createdAt = new Date(item.createdAt * 1000)
	if (commentsDateFrom.value) {
		const from = new Date(`${commentsDateFrom.value}T00:00:00`)
		if (createdAt < from) {
			return false
		}
	}

	if (commentsDateTo.value) {
		const to = new Date(`${commentsDateTo.value}T23:59:59`)
		if (createdAt > to) {
			return false
		}
	}

	return true
}))

function sendComment() {
	const body = comment.value.trim()
	if (!body && attachmentsDraft.value.files.length === 0 && attachmentsDraft.value.links.length === 0) {
		composerError.value = 'Debes escribir un comentario o adjuntar al menos un archivo o una URL.'
		return
	}

	composerError.value = ''
	emit('comment', { body, visibility: visibility.value, files: [...attachmentsDraft.value.files], links: [...attachmentsDraft.value.links] })
	comment.value = ''
	attachmentsDraft.value = { files: [], links: [] }
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

function formatDate(timestamp?: number | null) {
	if (!timestamp) {
		return 'Sin fecha'
	}
	return new Date(timestamp * 1000).toLocaleString()
}

function commentSummary(item: TicketComment) {
	const excerpt = item.body.trim()
	if (excerpt.length > 0) {
		return excerpt.slice(0, 80)
	}
	return (item.attachments ?? []).map((attachment) => attachment.originalName).join(', ') || 'Sin texto'
}

function toggleExpandedComment(commentId: number) {
	if (expandedCommentIds.value.includes(commentId)) {
		expandedCommentIds.value = expandedCommentIds.value.filter((item) => item !== commentId)
		return
	}
	expandedCommentIds.value = [...expandedCommentIds.value, commentId]
}

function openAttachment(attachment: TicketAttachment) {
	if (attachment.sourceUrl) {
		window.open(attachment.sourceUrl, '_blank', 'noopener,noreferrer')
		return
	}

	emit('download', attachment.id)
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
		<section v-if="canComment" class="gi-sidebar-panel__block">
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
			<button v-for="attachment in ticket.attachments || []" :key="attachment.id" class="gi-secondary-button gi-attachment-link" @click="openAttachment(attachment)">{{ attachment.originalName }}</button>
		</section>
		<section class="gi-sidebar-panel__block">
			<div class="gi-sidebar-panel__comments-header">
				<h3>Comentarios</h3>
				<button class="gi-secondary-button" type="button" @click="commentsModalOpen = true">Expandir comentarios</button>
			</div>
			<article v-for="item in ticket.comments || []" :key="item.id" class="gi-comment">
				<strong>{{ item.authorUid }}</strong>
				<p>{{ item.body }}</p>
				<div v-if="item.attachments?.length" class="gi-comment__attachments">
					<button v-for="attachment in item.attachments" :key="attachment.id" class="gi-secondary-button gi-comment__attachment gi-attachment-link" @click="openAttachment(attachment)">{{ attachment.originalName }}</button>
				</div>
			</article>
			<textarea v-model="comment" class="gi-textarea" rows="4" placeholder="Añadir comentario" />
			<AttachmentPicker v-model="attachmentsDraft" :allowed-extensions="allowedExtensions" :max-file-size-mb="maxFileSizeMb || 25" />
			<p v-if="composerError" class="gi-form-error">{{ composerError }}</p>
			<SearchableSelect v-if="canManage" v-model="visibility" :options="visibilityOptions" placeholder="Visibilidad" />
			<button class="gi-primary-button" @click="sendComment">Publicar</button>
		</section>
		<div v-if="commentsModalOpen" class="gi-filter-modal-backdrop" @click.self="commentsModalOpen = false">
			<section class="gi-filter-modal gi-sidebar-panel__comments-modal" aria-label="Comentarios ampliados">
				<header class="gi-filter-modal__header">
					<h3>Comentarios</h3>
					<button class="gi-modal-close" type="button" aria-label="Cerrar ventana" @click="commentsModalOpen = false">x</button>
				</header>
				<div class="gi-form-grid gi-sidebar-panel__comments-filters">
					<label class="gi-field gi-field--wide"><span>Buscar texto</span><input v-model="commentsSearchText" class="gi-input" type="search" /></label>
					<label class="gi-field"><span>Desde</span><input v-model="commentsDateFrom" class="gi-input" type="date" /></label>
					<label class="gi-field"><span>Hasta</span><input v-model="commentsDateTo" class="gi-input" type="date" /></label>
					<label class="gi-field"><span>Usuario</span><SearchableSelect :model-value="commentsAuthorUid" :options="commentAuthorOptions" placeholder="Todos" clearable @update:modelValue="commentsAuthorUid = $event ? String($event) : null" /></label>
				</div>
				<div class="gi-sidebar-panel__comments-accordion">
					<article v-for="item in filteredComments" :key="item.id" class="gi-sidebar-panel__accordion-item">
						<button class="gi-sidebar-panel__accordion-trigger" type="button" @click="toggleExpandedComment(item.id)">
							<span>{{ formatDate(item.createdAt) }} · {{ item.authorUid }} · {{ commentSummary(item) }}</span>
						</button>
						<div v-if="expandedCommentIds.includes(item.id)" class="gi-sidebar-panel__accordion-body">
							<p>{{ item.body || 'Sin texto' }}</p>
							<div v-if="item.attachments?.length" class="gi-comment__attachments">
								<button v-for="attachment in item.attachments" :key="attachment.id" class="gi-secondary-button gi-comment__attachment gi-attachment-link" @click="openAttachment(attachment)">{{ attachment.originalName }}</button>
							</div>
						</div>
					</article>
				</div>
			</section>
		</div>
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
.gi-sidebar-panel__selected-file,
.gi-sidebar-panel__comments-header {
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


.gi-sidebar-panel__comments-header {
	justify-content: space-between;
}

.gi-sidebar-panel__selected-file {
	justify-content: space-between;
	padding: .65rem .8rem;
	border: 1px solid rgba(33, 53, 68, .12);
	border-radius: 12px;
	background: rgba(255, 255, 255, .7);
}

.gi-sidebar-panel__comments-modal {
	width: min(60rem, 100%);
}

.gi-sidebar-panel__comments-filters,
.gi-sidebar-panel__comments-accordion {
	display: grid;
	gap: .75rem;
}

.gi-sidebar-panel__accordion-item {
	border: 1px solid rgba(49, 96, 91, .12);
	border-radius: 16px;
	overflow: hidden;
	background: rgba(255, 255, 255, .9);
}

.gi-sidebar-panel__accordion-trigger {
	width: 100%;
	padding: .9rem 1rem;
	border: none;
	background: rgba(239, 245, 241, .98);
	text-align: left;
	font: inherit;
	cursor: pointer;
}

.gi-sidebar-panel__accordion-body {
	padding: .9rem 1rem 1rem;
	display: grid;
	gap: .75rem;
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

.gi-filter-modal__header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: .75rem;
}

.gi-filter-modal__header h3 {
	margin: 0;
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
</style>