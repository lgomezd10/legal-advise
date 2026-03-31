<script setup lang="ts">
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import type { AssignableOption, SearchableSelectOption, StatusOption, Ticket, TicketAttachment, TicketAttachmentLinkDraft, TicketComment, UrgencyCatalogItem } from '@/types'
import { formatDateTime } from '@/utils/formatting'
import { formatHistoryEntries } from '@/utils/history'
import { excerptRichText, isRichTextEmpty, richTextToPlainText, sanitizeRichText } from '@/utils/richText'
import AttachmentPicker from './AttachmentPicker.vue'
import RichTextContent from './RichTextContent.vue'
import SearchableSelect from './SearchableSelect.vue'

const RichTextEditor = defineAsyncComponent(() => import(/* webpackChunkName: "rich-text-editor" */ './RichTextEditor.vue'))

type SupportTabId = 'detail' | 'comments' | 'history'

const props = defineProps<{
	ticket: Ticket | null
	roles: string[]
	users?: AssignableOption[]
	groups?: AssignableOption[]
	currentUserUid?: string
	statuses?: StatusOption[]
	urgencies?: UrgencyCatalogItem[]
	allowedExtensions?: string[]
	maxFileSizeMb?: number
	fullscreen?: boolean
	readOnly?: boolean
	showFullscreen?: boolean
	showRepeat?: boolean
	initialTab?: SupportTabId
	initialComposerVisible?: boolean
}>()

const emit = defineEmits<{
	(e: 'comment', payload: { body: string, visibility: 'interno' | 'publico', files: File[], links: TicketAttachmentLinkDraft[] }): void
	(e: 'save', payload: Record<string, unknown>): void
	(e: 'download', attachmentId: number): void
	(e: 'fullscreen'): void
	(e: 'reopen'): void
	(e: 'assign-to-me'): void
	(e: 'repeat'): void
}>()

const comment = ref('')
const visibility = ref<'interno' | 'publico'>('publico')
const attachmentsDraft = ref<{ files: File[], links: TicketAttachmentLinkDraft[] }>({ files: [], links: [] })
const composerError = ref('')
const commentsSearchText = ref('')
const commentsSortDirection = ref<'desc' | 'asc'>('desc')
const commentsDateFrom = ref('')
const commentsDateTo = ref('')
const commentsAuthorUid = ref<string | null>(null)
const composerVisible = ref(props.initialComposerVisible ?? true)
const expandedCommentIds = ref<number[]>([])
const activeTab = ref<SupportTabId>(props.initialTab ?? 'detail')
const editableTicket = reactive({
	title: '',
	status: '',
	urgencyId: null as number | null,
	assignedUserUid: null as string | null,
	assignedGroupId: null as string | null,
	supportDescription: '',
})
const closeReason = ref('')
const editableBaseSnapshot = ref('')
const syncedTicketId = ref<number | null>(null)
const waitingForSaveSync = ref(false)

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

const canManage = computed(() => !props.readOnly && (props.ticket?.canManage ?? (props.roles.includes('soporte') || props.roles.includes('administrador'))))
const canComment = computed(() => props.ticket?.canComment ?? true)
const isClosedTicket = computed(() => {
	if (!props.ticket) {
		return false
	}

	const matchedStatus = safeStatuses.value.find((item) => item.id === props.ticket?.status)
	if (matchedStatus) {
		return Boolean(matchedStatus.closed)
	}

	return props.ticket.status === 'resuelto' || props.ticket.status === 'cerrado'
})
const canEditTicket = computed(() => canManage.value && !isClosedTicket.value)
const canPublishComment = computed(() => canComment.value && !isClosedTicket.value)
const canAssignToCurrentUser = computed(() => Boolean(canEditTicket.value && props.currentUserUid && editableTicket.assignedUserUid !== props.currentUserUid))
const showSupportTabs = computed(() => canManage.value)
const safeUsers = computed(() => normalizeAssignableOptions(props.users))
const safeGroups = computed(() => normalizeAssignableOptions(props.groups))
const safeStatuses = computed(() => normalizeStatuses(props.statuses))
const safeUrgencies = computed(() => normalizeUrgencies(props.urgencies))
const statusOptions = computed<SearchableSelectOption[]>(() => safeStatuses.value.filter((status: StatusOption) => status.active !== false || status.id === props.ticket?.status).map((status: StatusOption) => ({
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
const supportTabs = computed(() => ([
	{ id: 'detail', label: 'Detalle' },
	{ id: 'comments', label: 'Comentarios' },
	{ id: 'history', label: 'Historial' },
] as Array<{ id: SupportTabId, label: string }>))
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
const historyEntries = computed(() => formatHistoryEntries(props.ticket?.history ?? [], {
	statuses: safeStatuses.value,
	users: safeUsers.value,
	groups: safeGroups.value,
	urgencies: safeUrgencies.value,
}))
const currentEditPayload = computed<Record<string, unknown>>(() => ({
	title: editableTicket.title.trim(),
	status: editableTicket.status,
	urgencyId: editableTicket.urgencyId,
	assignedUserUid: editableTicket.assignedUserUid,
	assignedGroupId: editableTicket.assignedGroupId,
	supportDescription: editableTicket.supportDescription,
}))
const targetStatusOption = computed(() => safeStatuses.value.find((item: StatusOption) => item.id === editableTicket.status) ?? null)
const requiresCloseReason = computed(() => {
	if (!canEditTicket.value || !props.ticket) {
		return false
	}

	const nextStatus = targetStatusOption.value
	if (!nextStatus) {
		return editableTicket.status === 'resuelto' || editableTicket.status === 'cerrado'
	}

	return Boolean(nextStatus.closed) && !isClosedTicket.value
})
const dirty = computed(() => canEditTicket.value && (JSON.stringify(currentEditPayload.value) !== editableBaseSnapshot.value || closeReason.value.trim() !== ''))
const filteredComments = computed(() => (props.ticket?.comments ?? []).filter((item: TicketComment) => {
	const term = commentsSearchText.value.trim().toLowerCase()
	if (term) {
		const haystack = `${item.authorUid} ${resolveUserLabel(item.authorUid)} ${richTextToPlainText(item.body)} ${(item.attachments ?? []).map((attachment: TicketAttachment) => attachment.originalName).join(' ')}`.toLowerCase()
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
const orderedComments = computed(() => [...filteredComments.value].sort((left, right) => {
	if (commentsSortDirection.value === 'asc') {
		return left.createdAt - right.createdAt
	}

	return right.createdAt - left.createdAt
}))
const visibleCommentIds = computed(() => orderedComments.value.map((item) => item.id))
const allVisibleCommentsExpanded = computed(() => visibleCommentIds.value.length > 0 && visibleCommentIds.value.every((id) => expandedCommentIds.value.includes(id)))

function hasUnsavedChanges() {
	return dirty.value
}

function confirmDiscardChanges() {
	if (!hasUnsavedChanges()) {
		return true
	}

	return window.confirm('Hay cambios sin guardar en esta incidencia. Si sales ahora, se perderan. Quieres continuar?')
}

function onBeforeWindowUnload(event: BeforeUnloadEvent) {
	if (!hasUnsavedChanges()) {
		return
	}

	event.preventDefault()
	event.returnValue = ''
}

defineExpose({
	hasUnsavedChanges,
	confirmDiscardChanges,
})

watch(() => props.initialTab, (nextTab) => {
	if (nextTab) {
		activeTab.value = nextTab
	}
})

watch(() => props.ticket?.id, () => {
	composerVisible.value = props.initialComposerVisible ?? true
	composerError.value = ''
	comment.value = ''
	attachmentsDraft.value = { files: [], links: [] }
})

watch(() => (props.ticket?.comments ?? []).map((item: TicketComment) => item.id).join(','), () => {
	expandedCommentIds.value = (props.ticket?.comments ?? []).map((item: TicketComment) => item.id)
}, { immediate: true })

watch(() => props.ticket ? JSON.stringify({
	id: props.ticket.id,
	title: props.ticket.title,
	status: props.ticket.status,
	urgencyId: props.ticket.urgencyId ?? null,
	assignedUserUid: props.ticket.assignedUserUid ?? null,
	assignedGroupId: props.ticket.assignedGroupId ?? null,
	supportDescription: props.ticket.supportDescription ?? '',
}) : '', () => {
	if (!props.ticket || !canManage.value) {
		return
	}

	const nextPayload = {
		title: props.ticket.title,
		status: props.ticket.status,
		urgencyId: props.ticket.urgencyId ?? null,
		assignedUserUid: props.ticket.assignedUserUid ?? null,
		assignedGroupId: props.ticket.assignedGroupId ?? null,
		supportDescription: props.ticket.supportDescription ?? '',
	}
	const nextSnapshot = JSON.stringify(nextPayload)
	if (syncedTicketId.value !== props.ticket.id || !dirty.value || waitingForSaveSync.value) {
		editableTicket.title = nextPayload.title
		editableTicket.status = nextPayload.status
		editableTicket.urgencyId = nextPayload.urgencyId
		editableTicket.assignedUserUid = nextPayload.assignedUserUid
		editableTicket.assignedGroupId = nextPayload.assignedGroupId
		editableTicket.supportDescription = nextPayload.supportDescription
		closeReason.value = ''
		editableBaseSnapshot.value = nextSnapshot
		syncedTicketId.value = props.ticket.id
		waitingForSaveSync.value = false
	}
}, { immediate: true })

onMounted(() => {
	window.addEventListener('beforeunload', onBeforeWindowUnload)
})

onBeforeUnmount(() => {
	window.removeEventListener('beforeunload', onBeforeWindowUnload)
})

function sendComment() {
	if (isRichTextEmpty(comment.value) && attachmentsDraft.value.files.length === 0 && attachmentsDraft.value.links.length === 0) {
		composerError.value = 'Debes escribir un comentario o adjuntar al menos un archivo o una URL.'
		return
	}

	composerError.value = ''
	emit('comment', { body: sanitizeRichText(comment.value), visibility: visibility.value, files: [...attachmentsDraft.value.files], links: [...attachmentsDraft.value.links] })
	comment.value = ''
	attachmentsDraft.value = { files: [], links: [] }
}

function onStatusChange(value: string | number | null) {
	if (!value) {
		return
	}

	editableTicket.status = String(value)
	if (!requiresCloseReason.value) {
		closeReason.value = ''
	}
}

function onUrgencyChange(value: string | number | null) {
	editableTicket.urgencyId = value ? Number(value) : null
}

function onAssignedUserChange(value: string | number | null) {
	const nextUserUid = value ? String(value) : null
	editableTicket.assignedUserUid = nextUserUid

	if (editableTicket.assignedGroupId && nextUserUid) {
		const validForGroup = safeUsers.value.some((user: AssignableOption) => user.id === nextUserUid && user.groupIds?.includes(editableTicket.assignedGroupId ?? ''))
		if (!validForGroup) {
			editableTicket.assignedGroupId = null
		}
	}
}

function onAssignedGroupChange(value: string | number | null) {
	const nextGroupId = value ? String(value) : null
	editableTicket.assignedGroupId = nextGroupId

	if (nextGroupId && editableTicket.assignedUserUid) {
		const validForUser = safeUsers.value.some((user: AssignableOption) => user.id === editableTicket.assignedUserUid && user.groupIds?.includes(nextGroupId))
		if (!validForUser) {
			editableTicket.assignedUserUid = null
		}
	}
}

function commentSummary(item: TicketComment) {
	const excerpt = richTextToPlainText(item.body)
	if (excerpt.length > 0) {
		return excerptRichText(item.body, 80)
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

function toggleAllVisibleComments() {
	if (allVisibleCommentsExpanded.value) {
		const visibleIds = new Set(visibleCommentIds.value)
		expandedCommentIds.value = expandedCommentIds.value.filter((item) => !visibleIds.has(item))
		return
	}

	expandedCommentIds.value = Array.from(new Set([...expandedCommentIds.value, ...visibleCommentIds.value]))
}

function toggleCommentsSortDirection() {
	commentsSortDirection.value = commentsSortDirection.value === 'desc' ? 'asc' : 'desc'
}

function hideComposer() {
	composerVisible.value = false
}

function showComposer() {
	composerVisible.value = true
}

function escapeHtml(value: string) {
	return value
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#39;')
}

function commentExportText(item: TicketComment) {
	const text = richTextToPlainText(item.body)
	if (text !== '') {
		return text
	}

	return (item.attachments ?? []).map((attachment) => attachment.originalName).join(', ')
}

function exportComments() {
	if (!props.ticket || filteredComments.value.length === 0) {
		return
	}

	const rows = [...filteredComments.value]
		.sort((left, right) => right.createdAt - left.createdAt)
		.map((item) => `<tr><td>${escapeHtml(formatDateTime(item.createdAt))}</td><td>${escapeHtml(resolveUserLabel(item.authorUid))}</td><td>${escapeHtml(commentExportText(item)).replace(/\n/g, '<br>')}</td></tr>`)
		.join('')
	const documentHtml = [
		'<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">',
		'<head><meta charset="utf-8"></head>',
		'<body>',
		'<table border="1">',
		'<thead><tr><th>Fecha</th><th>Usuario</th><th>Comentario</th></tr></thead>',
		`<tbody>${rows}</tbody>`,
		'</table>',
		'</body>',
		'</html>',
	].join('')
	const blob = new Blob([`\uFEFF${documentHtml}`], { type: 'application/vnd.ms-excel;charset=utf-8;' })
	const link = document.createElement('a')
	link.href = URL.createObjectURL(blob)
	link.download = `comentarios-${props.ticket.number}.xls`
	link.click()
	URL.revokeObjectURL(link.href)
}

function openAttachment(attachment: TicketAttachment) {
	if (attachment.sourceUrl) {
		window.open(attachment.sourceUrl, '_blank', 'noopener,noreferrer')
		return
	}

	emit('download', attachment.id)
}

function resolveUserLabel(userId: string | null | undefined) {
	if (!userId) {
		return 'Sin usuario'
	}

	return safeUsers.value.find((item) => item.id === userId)?.displayName ?? userId
}

function resolveGroupLabel(groupId: string | null | undefined) {
	if (!groupId) {
		return 'Sin grupo'
	}

	return safeGroups.value.find((item) => item.id === groupId)?.displayName ?? groupId
}

function saveChanges() {
	if (!dirty.value) {
		return
	}

	if (requiresCloseReason.value && closeReason.value.trim() === '') {
		window.alert('Debes indicar el motivo del cierre antes de guardar.')
		return
	}

	waitingForSaveSync.value = true
	emit('save', {
		...currentEditPayload.value,
		supportDescription: sanitizeRichText(editableTicket.supportDescription),
		closeReason: requiresCloseReason.value ? closeReason.value.trim() : undefined,
	})
}

function assignToCurrentUser() {
	if (!canEditTicket.value || !props.currentUserUid) {
		return
	}

	editableTicket.assignedUserUid = props.currentUserUid
	emit('assign-to-me')
}
</script>

<template>
	<div v-if="ticket" class="gi-sidebar-panel" :class="{ 'gi-sidebar-panel--fullscreen': fullscreen }">
		<header class="gi-sidebar-panel__header">
			<div class="gi-sidebar-panel__title-block">
				<strong>{{ ticket.number }}</strong>
				<h2>{{ ticket.title }}</h2>
				<div class="gi-sidebar-panel__actions">
					<button v-if="showRepeat" class="gi-secondary-button gi-sidebar-panel__fullscreen-button" type="button" @click="emit('repeat')">
						Repetir ticket
					</button>
					<button v-if="showFullscreen && !fullscreen" class="gi-secondary-button gi-sidebar-panel__fullscreen-button" type="button" @click="emit('fullscreen')">
						Pantalla completa
					</button>
					<button v-if="canAssignToCurrentUser" class="gi-secondary-button gi-sidebar-panel__fullscreen-button" type="button" @click="assignToCurrentUser">
						Asignarme a mi
					</button>
					<button v-if="ticket.canReopen" class="gi-secondary-button gi-sidebar-panel__fullscreen-button" type="button" @click="emit('reopen')">
						Reabrir ticket
					</button>
					<slot name="actions" />
					<button v-if="canManage" class="gi-primary-button gi-sidebar-panel__save-button" type="button" :disabled="!dirty" @click="saveChanges">
						Guardar
					</button>
				</div>
			</div>
		</header>
		<nav v-if="showSupportTabs" class="gi-sidebar-panel__tabs" aria-label="Secciones del ticket">
			<button v-for="tab in supportTabs" :key="tab.id" class="gi-sidebar-panel__tab" :class="{ 'gi-sidebar-panel__tab--active': activeTab === tab.id }" type="button" @click="activeTab = tab.id">
				{{ tab.label }}
			</button>
		</nav>
		<section v-if="!showSupportTabs || activeTab === 'detail'" class="gi-sidebar-panel__block gi-sidebar-panel__detail-block">
			<div v-if="canEditTicket" class="gi-form-grid">
				<label class="gi-field gi-field--wide">
					<span>Titulo</span>
					<input v-model="editableTicket.title" class="gi-input" type="text" />
				</label>
				<label class="gi-field">
					<span>Estado</span>
					<SearchableSelect :model-value="editableTicket.status" :options="statusOptions" placeholder="Estado" @update:modelValue="onStatusChange" />
				</label>
				<label v-if="requiresCloseReason" class="gi-field gi-field--wide">
					<span>Motivo del cierre</span>
					<textarea v-model="closeReason" class="gi-textarea gi-textarea--plain" rows="4" placeholder="Explica el motivo del cierre. Se publicara como un comentario." />
				</label>
				<label class="gi-field">
					<span>Criticidad</span>
					<SearchableSelect :model-value="editableTicket.urgencyId" :options="urgencyOptions" placeholder="Sin criticidad" clearable @update:modelValue="onUrgencyChange" />
				</label>
				<label class="gi-field">
					<span>Asignado a usuario</span>
					<SearchableSelect :model-value="editableTicket.assignedUserUid" :options="userOptions" placeholder="Sin usuario" clearable @update:modelValue="onAssignedUserChange" />
				</label>
				<label class="gi-field">
					<span>Asignado a grupo</span>
					<SearchableSelect :model-value="editableTicket.assignedGroupId" :options="groupOptions" placeholder="Sin grupo" clearable @update:modelValue="onAssignedGroupChange" />
				</label>
			</div>
			<div v-if="ticket.assignedUserUid || ticket.assignedGroupId" class="gi-sidebar-panel__assignment-summary">
				<div v-if="ticket.assignedUserUid"><span class="gi-sidebar-panel__summary-label">Asignado a usuario</span><strong>{{ resolveUserLabel(ticket.assignedUserUid) }}</strong></div>
				<div v-if="ticket.assignedGroupId"><span class="gi-sidebar-panel__summary-label">Asignado a grupo</span><strong>{{ resolveGroupLabel(ticket.assignedGroupId) }}</strong></div>
			</div>
			<div class="gi-sidebar-panel__description-stack">
				<div>
					<h3>Descripcion del ticket</h3>
					<RichTextContent :value="ticket.userDescription" surface />
				</div>
			</div>
			<div v-if="canEditTicket" class="gi-form-grid gi-sidebar-panel__support-editor-grid">
				<label class="gi-field gi-field--wide">
					<span>Descripcion de soporte</span>
					<RichTextEditor v-model="editableTicket.supportDescription" placeholder="Anade contexto interno, pasos realizados o capturas" :min-height="220" />
				</label>
			</div>
			<div v-else-if="canManage" class="gi-sidebar-panel__closed-summary">
				<div class="gi-sidebar-panel__summary-grid">
					<div><span class="gi-sidebar-panel__summary-label">Estado</span><strong>{{ ticket.status }}</strong></div>
					<div><span class="gi-sidebar-panel__summary-label">Criticidad</span><strong>{{ ticket.urgencyId ?? 'Sin criticidad' }}</strong></div>
					<div><span class="gi-sidebar-panel__summary-label">Asignado a usuario</span><strong>{{ resolveUserLabel(ticket.assignedUserUid) }}</strong></div>
					<div><span class="gi-sidebar-panel__summary-label">Asignado a grupo</span><strong>{{ resolveGroupLabel(ticket.assignedGroupId) }}</strong></div>
				</div>
				<div>
					<h3>Descripcion de soporte</h3>
					<RichTextContent :value="ticket.supportDescription" surface />
				</div>
			</div>
		</section>
		<section v-if="!showSupportTabs || activeTab === 'detail'" class="gi-sidebar-panel__block">
			<h3>Adjuntos</h3>
			<div class="gi-sidebar-panel__attachment-list">
				<button v-for="attachment in ticket.attachments || []" :key="attachment.id" class="gi-secondary-button gi-attachment-link" @click="openAttachment(attachment)">{{ attachment.originalName }}</button>
				<p v-if="!(ticket.attachments || []).length" class="gi-sidebar-panel__muted">No hay adjuntos publicados.</p>
			</div>
		</section>
		<section v-if="!showSupportTabs || activeTab === 'comments'" class="gi-sidebar-panel__block">
			<div class="gi-sidebar-panel__comments-header">				
				<div class="gi-sidebar-panel__comments-header-actions">
					<button v-if="!fullscreen" class="gi-secondary-button" type="button" @click="emit('fullscreen')">Expandir comentarios</button>
				</div>
			</div>
			<template v-if="canPublishComment">
				<div v-if="composerVisible" class="gi-sidebar-panel__comment-composer">
					<div class="gi-sidebar-panel__comment-composer-header">
						<strong>Nuevo comentario</strong>
						<button class="gi-round-icon-button gi-sidebar-panel__icon-button" type="button" aria-label="Ocultar nuevo comentario" title="Ocultar nuevo comentario" @click="hideComposer">
							<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.4 5 12 10.6 17.6 5 19 6.4 13.4 12 19 17.6 17.6 19 12 13.4 6.4 19 5 17.6 10.6 12 5 6.4z" fill="currentColor" /></svg>
						</button>
					</div>
					<RichTextEditor v-model="comment" placeholder="Anade un comentario, pega una captura o inserta una imagen" :min-height="180" />
					<AttachmentPicker v-model="attachmentsDraft" :allowed-extensions="allowedExtensions" :max-file-size-mb="maxFileSizeMb || 25" />
					<p v-if="composerError" class="gi-form-error">{{ composerError }}</p>
					<div class="gi-sidebar-panel__comment-composer-actions">
						<SearchableSelect v-if="canManage" v-model="visibility" :options="visibilityOptions" placeholder="Visibilidad" />
						<button class="gi-primary-button" @click="sendComment">Publicar</button>
					</div>
				</div>
				<div v-else class="gi-sidebar-panel__comment-composer-toggle-row">
					<button class="gi-secondary-button gi-sidebar-panel__composer-toggle-button" type="button" @click="showComposer">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5a1 1 0 0 1 1 1v5h5a1 1 0 1 1 0 2h-5v5a1 1 0 1 1-2 0v-5H6a1 1 0 1 1 0-2h5V6a1 1 0 0 1 1-1" fill="currentColor" /></svg>
						<span>Nuevo comentario</span>
					</button>
				</div>
			</template>
			<p v-else-if="isClosedTicket" class="gi-sidebar-panel__muted">Este ticket esta cerrado. Reabre el ticket para volver a actuar sobre el.</p>
			<h3>Histórico</h3>
			<div class="gi-sidebar-panel__comments-toolbar">
				<label class="gi-field gi-field--wide gi-sidebar-panel__comments-search"><span>Buscar texto</span><input v-model="commentsSearchText" class="gi-input" type="search" /></label>
				<div class="gi-sidebar-panel__comments-toolbar-actions">
					<button v-if="filteredComments.length" class="gi-secondary-button" type="button" @click="exportComments">
						Exportar comentarios
					</button>
					<button class="gi-secondary-button gi-sidebar-panel__sort-button" type="button" @click="toggleCommentsSortDirection">
						{{ commentsSortDirection === 'desc' ? 'Fecha: mas recientes primero' : 'Fecha: mas antiguas primero' }}
					</button>
					<button v-if="orderedComments.length" class="gi-secondary-button" type="button" @click="toggleAllVisibleComments">
						{{ allVisibleCommentsExpanded ? 'Ocultar todos' : 'Expandir todos' }}
					</button>
				</div>
			</div>
			<div class="gi-form-grid gi-sidebar-panel__comments-filters">
				<label class="gi-field"><span>Desde</span><input v-model="commentsDateFrom" class="gi-input" type="date" /></label>
				<label class="gi-field"><span>Hasta</span><input v-model="commentsDateTo" class="gi-input" type="date" /></label>
				<label class="gi-field"><span>Usuario</span><SearchableSelect :model-value="commentsAuthorUid" :options="commentAuthorOptions" placeholder="Todos" clearable @update:modelValue="commentsAuthorUid = $event ? String($event) : null" /></label>
			</div>
			<div class="gi-sidebar-panel__comments-accordion">
				<article v-for="item in orderedComments" :key="item.id" class="gi-sidebar-panel__accordion-item">
					<button class="gi-sidebar-panel__accordion-trigger" type="button" @click="toggleExpandedComment(item.id)">
						<span class="gi-sidebar-panel__accordion-trigger-content">
							<span class="gi-sidebar-panel__accordion-meta">{{ formatDateTime(item.createdAt) }} · {{ resolveUserLabel(item.authorUid) }}</span>
							<span class="gi-sidebar-panel__accordion-summary">{{ commentSummary(item) }}</span>
						</span>
						<span class="gi-sidebar-panel__accordion-icon" aria-hidden="true">{{ expandedCommentIds.includes(item.id) ? '▾' : '▸' }}</span>
					</button>
					<div v-if="expandedCommentIds.includes(item.id)" class="gi-sidebar-panel__accordion-body">
						<RichTextContent :value="item.body" />
						<div v-if="item.attachments?.length" class="gi-comment__attachments">
							<button v-for="attachment in item.attachments" :key="attachment.id" class="gi-secondary-button gi-comment__attachment gi-attachment-link" @click="openAttachment(attachment)">{{ attachment.originalName }}</button>
						</div>
					</div>
				</article>
				<p v-if="orderedComments.length === 0" class="gi-sidebar-panel__muted">No hay comentarios que coincidan con los filtros actuales.</p>
			</div>
		</section>
		<section v-if="showSupportTabs && activeTab === 'history'" class="gi-sidebar-panel__block">
			<div class="gi-sidebar-panel__history-list">
				<article v-for="entry in historyEntries" :key="entry.id" class="gi-sidebar-panel__history-item">
					<div class="gi-sidebar-panel__history-meta">
						<strong>{{ entry.title }}</strong>
						<span>{{ formatDateTime(entry.createdAt) }} · {{ entry.actor }}</span>
					</div>
					<ul v-if="entry.details.length" class="gi-sidebar-panel__history-details">
						<li v-for="detail in entry.details" :key="detail">{{ detail }}</li>
					</ul>
				</article>
				<p v-if="historyEntries.length === 0" class="gi-sidebar-panel__muted">No hay cambios registrados todavia.</p>
			</div>
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

.gi-sidebar-panel__tabs {
	display: flex;
	gap: .6rem;
	flex-wrap: wrap;
}

.gi-sidebar-panel__tab {
	border: 1px solid rgba(49, 96, 91, .14);
	border-radius: 999px;
	padding: .55rem .9rem;
	background: rgba(239, 245, 241, .96);
	color: #29594e;
	font: inherit;
	font-weight: 600;
	cursor: pointer;
}

.gi-sidebar-panel__tab--active {
	background: #0b6e4f;
	border-color: #0b6e4f;
	color: #fff;
}

.gi-sidebar-panel__fullscreen-button {
	justify-self: flex-start;
}

.gi-sidebar-panel__actions {
	display: flex;
	gap: .6rem;
	flex-wrap: wrap;
}

.gi-sidebar-panel__save-button:disabled {
	opacity: .55;
	cursor: not-allowed;
}

.gi-sidebar-panel__detail-block,
.gi-sidebar-panel__description-stack,
.gi-sidebar-panel__attachment-list,
.gi-sidebar-panel__history-list,
.gi-sidebar-panel__comment-list,
.gi-sidebar-panel__closed-summary {
	display: grid;
	gap: 1rem;
}

.gi-sidebar-panel__support-editor-grid {
	padding-top: 0;
}

.gi-sidebar-panel__summary-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
	gap: .85rem;
}

.gi-sidebar-panel__assignment-summary {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
	gap: .85rem;
	padding: .85rem 1rem;
	border: 1px solid rgba(49, 96, 91, .12);
	border-radius: 16px;
	background: rgba(242, 246, 243, .82);
}

.gi-sidebar-panel__summary-label {
	display: block;
	margin-bottom: .3rem;
	font-size: .78rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: .04em;
	color: #5a6f68;
}

.gi-textarea--plain {
	width: 100%;
	min-height: 6.5rem;
	resize: vertical;
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

.gi-sidebar-panel__comment-meta,
.gi-sidebar-panel__history-meta {
	display: flex;
	justify-content: flex-start;
	gap: .75rem;
	align-items: baseline;
	flex-wrap: wrap;
}

.gi-sidebar-panel__history-item {
	padding: .95rem 1rem;
	border-radius: 16px;
	background: rgba(245, 249, 247, .96);
	border: 1px solid rgba(49, 96, 91, .12);
	display: grid;
	gap: .7rem;
}

.gi-sidebar-panel__history-details {
	margin: 0;
	padding-left: 1rem;
	display: grid;
	gap: .35rem;
	color: #4b6058;
}

.gi-attachment-link {
	font-size: .82rem;
	font-weight: 400 !important;
	line-height: 1.25;
}


.gi-sidebar-panel__muted {
	margin: 0;
	color: #5f726b;
}

.gi-sidebar-panel__section-kicker {
	margin: .1rem 0 -.1rem;
	font-size: .8rem;
	font-weight: 700;
	line-height: 1.2;
	letter-spacing: .06em;
	text-transform: uppercase;
	color: #547068;
}

.gi-sidebar-panel__comments-header {
	justify-content: space-between;
}

.gi-sidebar-panel__comments-header-actions,
.gi-sidebar-panel__comment-composer-actions,
.gi-sidebar-panel__comments-toolbar-actions {
	display: flex;
	gap: .65rem;
	align-items: center;
	flex-wrap: wrap;
}

.gi-sidebar-panel__comment-composer,
.gi-sidebar-panel__comments-toolbar {
	display: grid;
	gap: .85rem;
}

.gi-sidebar-panel__comment-composer-toggle-row {
	display: flex;
	justify-content: flex-start;
	margin-bottom: .35rem;
}

.gi-sidebar-panel__comment-composer {
	padding: 1rem;
	border: 1px solid rgba(49, 96, 91, .12);
	border-radius: 16px;
	background: rgba(247, 250, 248, .95);
	box-shadow: inset 0 1px 0 rgba(255, 255, 255, .6);
	margin-bottom: 1rem;
}

.gi-sidebar-panel__comment-composer-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: .75rem;
}

.gi-sidebar-panel__icon-button,
.gi-sidebar-panel__composer-toggle-button {
	display: inline-flex;
	align-items: center;
	gap: .45rem;
}

.gi-sidebar-panel__icon-button svg,
.gi-sidebar-panel__composer-toggle-button svg {
	width: 1rem;
	height: 1rem;
}

.gi-sidebar-panel__comments-toolbar {
	grid-template-columns: minmax(0, 1fr) auto;
	align-items: end;
}

.gi-sidebar-panel__comments-toolbar-actions {
	justify-content: flex-start;
}

.gi-sidebar-panel__comments-search {
	margin: 0;
}

.gi-sidebar-panel__sort-button {
	justify-self: flex-start;
}

.gi-sidebar-panel__selected-file {
	justify-content: space-between;
	padding: .65rem .8rem;
	border: 1px solid rgba(33, 53, 68, .12);
	border-radius: 12px;
	background: rgba(255, 255, 255, .7);
}

.gi-sidebar-panel__comments-filters,
.gi-sidebar-panel__comments-accordion {
	display: grid;
	gap: .75rem;
}

.gi-sidebar-panel--fullscreen,
.gi-sidebar-panel--fullscreen .gi-sidebar-panel__block,
.gi-sidebar-panel--fullscreen .gi-sidebar-panel__comments-accordion,
.gi-sidebar-panel--fullscreen .gi-sidebar-panel__comment-list {
	width: 100%;
	max-width: none;
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
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: .75rem;
	text-align: left;
	font: inherit;
	cursor: pointer;
}

.gi-sidebar-panel__accordion-trigger-content {
	display: grid;
	gap: .35rem;
	min-width: 0;
}

.gi-sidebar-panel__accordion-meta {
	font-size: .87rem;
	font-weight: 700;
	color: #385b53;
	line-height: 1.35;
}

.gi-sidebar-panel__accordion-summary {
	color: #516862;
	line-height: 1.45;
	word-break: break-word;
}

.gi-sidebar-panel__accordion-icon {
	font-size: 1rem;
	line-height: 1;
	color: #4d6962;
	padding-top: .1rem;
}

.gi-sidebar-panel__accordion-body {
	padding: .9rem 1rem 1rem;
	display: grid;
	gap: .75rem;
}

@media (max-width: 900px) {
	.gi-sidebar-panel__comment-meta,
	.gi-sidebar-panel__history-meta,
	.gi-sidebar-panel__comments-header,
	.gi-sidebar-panel__comments-header-actions,
	.gi-sidebar-panel__comment-composer-actions,
	.gi-sidebar-panel__comments-toolbar-actions,
	.gi-sidebar-panel__comment-composer-header {
		flex-direction: column;
		align-items: flex-start;
	}

	.gi-sidebar-panel__sort-button,
	.gi-sidebar-panel__comments-toolbar-actions > .gi-secondary-button {
		width: 100%;
	}

	.gi-sidebar-panel__comments-toolbar {
		grid-template-columns: 1fr;
	}
}
</style>