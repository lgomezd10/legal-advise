<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import type { AssignableOption, CatalogField, SearchableSelectOption, StatusOption, Ticket, TicketAttachment, TicketAttachmentLinkDraft, TicketComment, TypeNode, UrgencyCatalogItem } from '@/types'
import { formatDateTime } from '@/utils/formatting'
import { formatHistoryEntries } from '@/utils/history'
import { excerptRichText, isRichTextEmpty, richTextToPlainText, sanitizeRichText } from '@/utils/richText'
import { getTicketPersonalDataRecord, getTypeLabel } from '@/services/ticketDraft'
import AttachmentPicker from './AttachmentPicker.vue'
import PlainTextEditor from './PlainTextEditor.vue'
import TicketCommentComposer from './TicketCommentComposer.vue'
import RichTextEditor from './RichTextEditor.vue'
import RichTextContent from './RichTextContent.vue'
import SearchableSelect from './SearchableSelect.vue'

let ticketSidebarPanelIdSequence = 0

type TicketSidebarTabId = 'comments' | 'detail' | 'attachments' | 'requester' | 'history'

const props = defineProps<{
	ticket: Ticket | null
	roles: string[]
	users?: AssignableOption[]
	groups?: AssignableOption[]
	types?: TypeNode[]
	fields?: CatalogField[]
	currentUserUid?: string
	statuses?: StatusOption[]
	urgencies?: UrgencyCatalogItem[]
	allowedExtensions?: string[]
	maxFileSizeMb?: number
	fullscreen?: boolean
	readOnly?: boolean
	showFullscreen?: boolean
	showRepeat?: boolean
	initialTab?: TicketSidebarTabId
	initialComposerVisible?: boolean
}>()

const emit = defineEmits<{
	(e: 'comment', payload: { body: string, visibility: 'interno' | 'publico', files: File[], links: TicketAttachmentLinkDraft[], waitForUser?: boolean }): void
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
const composerAttachmentsVisible = ref(false)
const composerError = ref('')
const replyTargetCommentId = ref<number | null>(null)
const commentsSearchText = ref('')
const commentsSortDirection = ref<'desc' | 'asc'>('desc')
const commentsDateFrom = ref('')
const commentsDateTo = ref('')
const commentsAuthorUid = ref<string | null>(null)
const commentsMobileMenuOpen = ref(false)
const expandedCommentIds = ref<number[]>([])
const discardChangesDialogOpen = ref(false)
const discardChangesResolver = ref<((confirmed: boolean) => void) | null>(null)
const closeReasonDialogOpen = ref(false)
const supportCommentDialogOpen = ref(false)
const externalAttachmentUrlDialogOpen = ref(false)
const pendingExternalAttachment = ref<TicketAttachment | null>(null)
const pendingSupportCommentAction = ref<{ body: string, visibility: 'interno' | 'publico', files: File[], links: TicketAttachmentLinkDraft[] } | null>(null)
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
const statusEditedManually = ref(false)
const commentComposerRef = ref<HTMLElement | { $el?: Element | null, openFileAttachment?: () => void } | null>(null)
const instanceId = `gi-ticket-sidebar-${++ticketSidebarPanelIdSequence}`

function getFieldId(suffix: string) {
	return `${instanceId}-${suffix}`
}

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

function normalizeFields(fields: CatalogField[] | Record<string, CatalogField> | undefined | null): CatalogField[] {
	if (Array.isArray(fields)) {
		return fields
	}

	if (fields && typeof fields === 'object') {
		return Object.values(fields)
	}

	return []
}

function normalizeTypes(types: TypeNode[] | Record<string, TypeNode> | undefined | null): TypeNode[] {
	if (Array.isArray(types)) {
		return types
	}

	if (types && typeof types === 'object') {
		return Object.values(types)
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
const defaultTab = computed<TicketSidebarTabId>(() => props.initialTab ?? 'comments')
const activeTab = ref<TicketSidebarTabId>(defaultTab.value)
const safeUsers = computed(() => normalizeAssignableOptions(props.users))
const safeGroups = computed(() => normalizeAssignableOptions(props.groups))
const safeTypes = computed(() => normalizeTypes(props.types))
const safeFields = computed(() => normalizeFields(props.fields)
	.filter((field: CatalogField) => field.active !== false)
	.slice()
	.sort((left: CatalogField, right: CatalogField) => left.sortOrder - right.sortOrder))
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
const userOptions = computed<SearchableSelectOption[]>(() => safeUsers.value
	.filter((user: AssignableOption) => !editableTicket.assignedGroupId || user.groupIds?.includes(editableTicket.assignedGroupId))
	.map((user: AssignableOption) => ({
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
	{ value: 'publico', label: 'Público' },
	{ value: 'interno', label: 'Interno' },
]
const supportTabs = computed(() => ([
	{ id: 'detail', label: 'Detalle' },
	{ id: 'comments', label: 'Comentarios' },
	{ id: 'requester', label: 'Solicitante' },
	{ id: 'history', label: 'Historial' },
	] as Array<{ id: TicketSidebarTabId, label: string }>))
const userTabs = computed(() => ([
	{ id: 'comments', label: 'Comentarios' },
	{ id: 'detail', label: 'Detalles' },
	{ id: 'attachments', label: 'Adjuntos' },
	] as Array<{ id: TicketSidebarTabId, label: string }>))
const visibleTabs = computed(() => showSupportTabs.value ? supportTabs.value : userTabs.value)
const shouldCollapseCommentOptions = computed(() => !showSupportTabs.value)
const requesterData = computed<Record<string, string>>(() => getTicketPersonalDataRecord(props.ticket))
const requesterContactEntries = computed(() => {
	const entries: Array<{ key: string, label: string, value: string }> = []
	const remainingKeys = new Set(Object.keys(requesterData.value))

	for (const field of safeFields.value) {
		const value = String(requesterData.value[field.fieldKey] ?? '').trim()
		remainingKeys.delete(field.fieldKey)
		if (value === '') {
			continue
		}
		entries.push({ key: field.fieldKey, label: field.label, value })
	}

	for (const key of remainingKeys) {
		const value = String(requesterData.value[key] ?? '').trim()
		if (value === '') {
			continue
		}
		entries.push({ key, label: formatPersonalDataLabel(key), value })
	}

	return entries
})
const ticketTypeLabel = computed(() => getTypeLabel(safeTypes.value, props.ticket?.typeId ?? null) || 'Sin tipo')
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

function resolveVisibilityLabel(value: 'interno' | 'publico') {
	return value === 'interno' ? 'Interno' : 'Público'
}

const currentEditPayload = computed<Record<string, unknown>>(() => ({
	title: editableTicket.title.trim(),
	status: editableTicket.status,
	urgencyId: editableTicket.urgencyId,
	assignedUserUid: editableTicket.assignedUserUid,
	assignedGroupId: editableTicket.assignedGroupId,
	supportDescription: editableTicket.supportDescription,
}))
const baseEditPayload = computed<Record<string, unknown>>(() => editableBaseSnapshot.value !== ''
	? JSON.parse(editableBaseSnapshot.value) as Record<string, unknown>
	: {})
const changedEditPayload = computed<Record<string, unknown>>(() => Object.entries(currentEditPayload.value).reduce<Record<string, unknown>>((changes, [key, value]) => {
	if (baseEditPayload.value[key] !== value) {
		changes[key] = value
	}

	return changes
}, {}))

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
const dirty = computed(() => canEditTicket.value && (Object.keys(changedEditPayload.value).length > 0 || closeReason.value.trim() !== ''))
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
const latestVisibleCommentId = computed(() => orderedComments.value.reduce<number | null>((latestId, item) => {
	if (!latestId) {
		return item.id
	}

	const latestComment = orderedComments.value.find((entry) => entry.id === latestId)
	if (!latestComment || item.createdAt > latestComment.createdAt) {
		return item.id
	}

	return latestId
}, null))
const replyTargetComment = computed(() => {
	if (!replyTargetCommentId.value) {
		return null
	}

	return (props.ticket?.comments ?? []).find((item: TicketComment) => item.id === replyTargetCommentId.value) ?? null
})
const commentComposerPlaceholder = computed(() => replyTargetComment.value
	? `Responde a ${resolveUserLabel(replyTargetComment.value.authorUid)}...`
	: 'Escribe una respuesta, pega una captura o inserta una imagen')
const visibleCommentIds = computed(() => orderedComments.value.map((item) => item.id))
const allVisibleCommentsExpanded = computed(() => visibleCommentIds.value.length > 0 && visibleCommentIds.value.every((id) => expandedCommentIds.value.includes(id)))

function hasUnsavedChanges() {
	return dirty.value
}

function confirmDiscardChanges() {
	if (!hasUnsavedChanges()) {
		return true
	}

	return new Promise<boolean>((resolve) => {
		discardChangesResolver.value = resolve
		discardChangesDialogOpen.value = true
	})
}

function resolveDiscardChangesDialog(confirmed: boolean) {
	discardChangesDialogOpen.value = false
	discardChangesResolver.value?.(confirmed)
	discardChangesResolver.value = null
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
	activeTab.value = nextTab ?? defaultTab.value
})

function resetTransientTicketPanelState() {
	comment.value = ''
	visibility.value = 'publico'
	attachmentsDraft.value = { files: [], links: [] }
	composerAttachmentsVisible.value = false
	composerError.value = ''
	replyTargetCommentId.value = null
	commentsSearchText.value = ''
	commentsSortDirection.value = 'desc'
	commentsDateFrom.value = ''
	commentsDateTo.value = ''
	commentsAuthorUid.value = null
	commentsMobileMenuOpen.value = false
	activeTab.value = defaultTab.value
	closeReason.value = ''
	closeReasonDialogOpen.value = false
	supportCommentDialogOpen.value = false
	pendingSupportCommentAction.value = null
}

watch(() => props.ticket?.id, () => {
	resetTransientTicketPanelState()
	if (discardChangesDialogOpen.value) {
		resolveDiscardChangesDialog(false)
	}
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
		statusEditedManually.value = false
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
	const nextPayload = {
		body: sanitizeRichText(comment.value),
		visibility: visibility.value,
		files: [...attachmentsDraft.value.files],
		links: [...attachmentsDraft.value.links],
	}
	if (showSupportTabs.value) {
		pendingSupportCommentAction.value = nextPayload
		supportCommentDialogOpen.value = true
		return
	}
	emit('comment', nextPayload)
	resetCommentComposerState()
}

function resetCommentComposerState() {
	comment.value = ''
	attachmentsDraft.value = { files: [], links: [] }
	composerAttachmentsVisible.value = false
	replyTargetCommentId.value = null
}

function closeSupportCommentDialog() {
	supportCommentDialogOpen.value = false
	pendingSupportCommentAction.value = null
}

function confirmSupportComment(waitForUser: boolean) {
	if (!pendingSupportCommentAction.value) {
		closeSupportCommentDialog()
		return
	}
	emit('comment', {
		...pendingSupportCommentAction.value,
		waitForUser,
	})
	closeSupportCommentDialog()
	resetCommentComposerState()
}

function closeExternalAttachmentUrlDialog() {
	externalAttachmentUrlDialogOpen.value = false
	pendingExternalAttachment.value = null
}

function confirmExternalAttachmentUrl() {
	if (!pendingExternalAttachment.value?.sourceUrl) {
		closeExternalAttachmentUrlDialog()
		return
	}

	window.open(pendingExternalAttachment.value.sourceUrl, '_blank', 'noopener,noreferrer')
	closeExternalAttachmentUrlDialog()
}

function onStatusChange(value: string | number | null) {
	if (!value) {
		return
	}

	statusEditedManually.value = true
	editableTicket.status = String(value)
	if (!requiresCloseReason.value) {
		closeReason.value = ''
	}
}

function onUrgencyChange(value: string | number | null) {
	editableTicket.urgencyId = value ? Number(value) : null
}

function hasAssignment(assignedUserUid: string | null, assignedGroupId: string | null) {
	return Boolean(assignedUserUid || assignedGroupId)
}

function syncDerivedStatusFromAssignment() {
	if (statusEditedManually.value) {
		return
	}

	const baseAssignedUserUid = typeof baseEditPayload.value.assignedUserUid === 'string' ? baseEditPayload.value.assignedUserUid : null
	const baseAssignedGroupId = typeof baseEditPayload.value.assignedGroupId === 'string' ? baseEditPayload.value.assignedGroupId : null
	const baseStatus = typeof baseEditPayload.value.status === 'string' ? baseEditPayload.value.status : ''
	const assignmentChanged = editableTicket.assignedUserUid !== baseAssignedUserUid || editableTicket.assignedGroupId !== baseAssignedGroupId

	if (!assignmentChanged) {
		editableTicket.status = baseStatus
	} else {
		editableTicket.status = hasAssignment(editableTicket.assignedUserUid, editableTicket.assignedGroupId) ? 'asignado' : 'nuevo'
	}

	if (!requiresCloseReason.value) {
		closeReason.value = ''
	}
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

	syncDerivedStatusFromAssignment()
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

	syncDerivedStatusFromAssignment()
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

function toggleCommentsMobileMenu() {
	commentsMobileMenuOpen.value = !commentsMobileMenuOpen.value
}

function closeCommentsMobileMenu() {
	commentsMobileMenuOpen.value = false
}

function openRequesterTab() {
	if (!showSupportTabs.value) {
		return
	}

	activeTab.value = 'requester'
}

function clearReplyTarget() {
	replyTargetCommentId.value = null
	composerAttachmentsVisible.value = false
}

function resolveCommentComposerElement() {
	if (commentComposerRef.value instanceof HTMLElement) {
		return commentComposerRef.value
	}

	const componentRoot = commentComposerRef.value?.$el
	return componentRoot instanceof HTMLElement ? componentRoot : null
}

function triggerCommentComposerAttachment() {
	if (commentComposerRef.value instanceof HTMLElement) {
		return
	}

	commentComposerRef.value?.openFileAttachment?.()
}

function focusCommentComposer() {
	nextTick(() => {
		const composerElement = resolveCommentComposerElement()
		composerElement?.scrollIntoView?.({ behavior: 'smooth', block: 'nearest' })
		const editable = composerElement?.querySelector('[contenteditable="true"]')
		if (editable instanceof HTMLElement) {
			editable.focus()
		}
	})
}

function replyToComment(item: TicketComment) {
	replyTargetCommentId.value = item.id
	composerAttachmentsVisible.value = false
	activeTab.value = 'comments'
	if (!expandedCommentIds.value.includes(item.id)) {
		expandedCommentIds.value = [...expandedCommentIds.value, item.id]
	}
	closeCommentsMobileMenu()
	focusCommentComposer()
}

function showComposerAttachments() {
	composerAttachmentsVisible.value = true
}

function commentExportText(item: TicketComment) {
	const text = richTextToPlainText(item.body)
	if (text !== '') {
		return text
	}

	return (item.attachments ?? []).map((attachment) => attachment.originalName).join(', ')
}

function commentAttachmentsExportText(item: TicketComment) {
	return (item.attachments ?? []).map((attachment) => attachment.originalName).join('|')
}

function csvEscape(value: string) {
	return `"${value.replace(/"/g, '""')}"`
}

function exportTimestamp() {
	const now = new Date()
	const year = String(now.getFullYear())
	const month = String(now.getMonth() + 1).padStart(2, '0')
	const day = String(now.getDate()).padStart(2, '0')
	const hours = String(now.getHours()).padStart(2, '0')
	const minutes = String(now.getMinutes()).padStart(2, '0')
	const seconds = String(now.getSeconds()).padStart(2, '0')

	return `${year}${month}${day}-${hours}${minutes}${seconds}`
}

function exportComments() {
	if (!props.ticket || filteredComments.value.length === 0) {
		return
	}

	const rows = [...filteredComments.value]
		.sort((left, right) => right.createdAt - left.createdAt)
		.map((item) => [
			formatDateTime(item.createdAt),
			resolveUserLabel(item.authorUid),
			commentExportText(item),
			commentAttachmentsExportText(item),
		].map((column) => csvEscape(column)).join(';'))
	const csv = [
		['Fecha', 'Usuario', 'Comentario', 'Adjuntos'].map((column) => csvEscape(column)).join(';'),
		...rows,
	].join('\r\n')
	const blob = new Blob([`\uFEFF${csv}`], { type: 'text/csv;charset=utf-8;' })
	const link = document.createElement('a')
	link.href = URL.createObjectURL(blob)
	link.download = `comentarios-${props.ticket.number}-${exportTimestamp()}.csv`
	link.click()
	URL.revokeObjectURL(link.href)
}

function openAttachment(attachment: TicketAttachment) {
	if (attachment.sourceUrl) {
		pendingExternalAttachment.value = attachment
		externalAttachmentUrlDialogOpen.value = true
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

function formatPersonalDataLabel(key: string) {
	if (key === 'email') {
		return 'Correo'
	}

	if (key === 'phone') {
		return 'Teléfono'
	}

	if (key === 'location') {
		return 'Dirección'
	}

	return key
		.replace(/[_-]+/g, ' ')
		.replace(/([a-z])([A-Z])/g, '$1 $2')
		.replace(/\s+/g, ' ')
		.trim()
		.replace(/^./, (char) => char.toUpperCase())
}

function saveChanges() {
	if (!dirty.value) {
		return
	}

	if (requiresCloseReason.value && closeReason.value.trim() === '') {
		closeReasonDialogOpen.value = true
		return
	}

	waitingForSaveSync.value = true
	emit('save', {
		...changedEditPayload.value,
		...(Object.prototype.hasOwnProperty.call(changedEditPayload.value, 'supportDescription') ? {
			supportDescription: sanitizeRichText(editableTicket.supportDescription),
		} : {}),
		closeReason: requiresCloseReason.value ? closeReason.value.trim() : undefined,
	})
}

function assignToCurrentUser() {
	if (!canEditTicket.value || !props.currentUserUid) {
		return
	}

	onAssignedUserChange(props.currentUserUid)
	waitingForSaveSync.value = true
	emit('assign-to-me')
}
</script>

<template>
	<div v-if="ticket" class="gi-sidebar-panel" :class="{ 'gi-sidebar-panel--fullscreen': fullscreen }">
		<header class="gi-sidebar-panel__header">
			<div class="gi-sidebar-panel__title-block">
				<div class="gi-sidebar-panel__ticket-line">
					<strong>{{ ticket.number }}</strong>
					<span v-if="showSupportTabs && ticket.creatorUid" class="gi-sidebar-panel__requester-inline">
						<span class="gi-sidebar-panel__requester-label">Creado por</span>
						<button class="gi-sidebar-panel__requester-button" type="button" @click="openRequesterTab">
							{{ resolveUserLabel(ticket.creatorUid) }}
						</button>
					</span>
				</div>
				<h2>{{ ticket.title }}</h2>
				<div class="gi-sidebar-panel__actions">
					<button v-if="showRepeat" class="gi-secondary-button gi-sidebar-panel__fullscreen-button" type="button" @click="emit('repeat')">
						Repetir ticket
					</button>
					<button v-if="showFullscreen && !fullscreen" class="gi-secondary-button gi-sidebar-panel__fullscreen-button" type="button" @click="emit('fullscreen')">
						Pantalla completa
					</button>
					<button v-if="canAssignToCurrentUser" class="gi-secondary-button gi-sidebar-panel__fullscreen-button" type="button" @click="assignToCurrentUser">
						Asignarme a mí
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
		<nav v-if="visibleTabs.length" class="gi-sidebar-panel__tabs" aria-label="Secciones del ticket">
			<button v-for="tab in visibleTabs" :key="tab.id" class="gi-sidebar-panel__tab" :class="{ 'gi-sidebar-panel__tab--active': activeTab === tab.id }" type="button" @click="activeTab = tab.id">
				{{ tab.label }}
			</button>
		</nav>
		<section v-if="activeTab === 'detail'" class="gi-sidebar-panel__block gi-sidebar-panel__detail-block">
			<div v-if="canEditTicket" class="gi-sidebar-panel__editor-form">
				<label class="gi-field gi-field--wide">
					<span>Título</span>
					<input :id="getFieldId('title')" v-model="editableTicket.title" :name="getFieldId('title')" class="gi-input" type="text" />
				</label>
				<div class="gi-sidebar-panel__compact-select-grid">
					<div class="gi-field gi-sidebar-panel__compact-field">
						<span>Estado</span>
						<SearchableSelect class="gi-search-select--compact" :model-value="editableTicket.status" :options="statusOptions" placeholder="Estado" @update:modelValue="onStatusChange" />
					</div>
					<div class="gi-field gi-sidebar-panel__compact-field">
						<span>Criticidad</span>
						<SearchableSelect class="gi-search-select--compact" :model-value="editableTicket.urgencyId" :options="urgencyOptions" placeholder="Sin criticidad" clearable @update:modelValue="onUrgencyChange" />
					</div>
					<div class="gi-field gi-sidebar-panel__compact-field">
						<span>Asignado a usuario</span>
						<SearchableSelect class="gi-search-select--compact" :model-value="editableTicket.assignedUserUid" :options="userOptions" placeholder="Sin usuario" clearable @update:modelValue="onAssignedUserChange" />
					</div>
					<div class="gi-field gi-sidebar-panel__compact-field">
						<span>Asignado a grupo</span>
						<SearchableSelect class="gi-search-select--compact" :model-value="editableTicket.assignedGroupId" :options="groupOptions" placeholder="Sin grupo" clearable @update:modelValue="onAssignedGroupChange" />
					</div>
				</div>
				<label v-if="requiresCloseReason" class="gi-field gi-field--wide gi-sidebar-panel__close-reason-field">
					<span>Motivo del cierre</span>
					<PlainTextEditor :input-id="getFieldId('close-reason')" v-model="closeReason" placeholder="Explica el motivo del cierre. Se publicará como un comentario." :min-height="120" />
				</label>
			</div>
			<div class="gi-sidebar-panel__description-stack">
				<div class="gi-field gi-field--wide">
					<span>Tipo</span>
					<div class="gi-sidebar-panel__type-chip">{{ ticketTypeLabel }}</div>
				</div>
				<div class="gi-field gi-field--wide">
					<span>Descripción del ticket</span>
					<RichTextContent :value="ticket.userDescription" surface />
				</div>
			</div>
			<div v-if="canEditTicket" class="gi-form-grid gi-sidebar-panel__support-editor-grid">
				<div class="gi-field gi-field--wide">
					<span>Descripción de soporte</span>
					<RichTextEditor v-model="editableTicket.supportDescription" placeholder="Añade contexto interno, pasos realizados o capturas" :min-height="220" />
				</div>
			</div>
			<div v-else-if="canManage" class="gi-sidebar-panel__closed-summary">
				<div class="gi-sidebar-panel__summary-grid">
					<div><span class="gi-sidebar-panel__summary-label">Tipo</span><strong>{{ ticketTypeLabel }}</strong></div>
					<div><span class="gi-sidebar-panel__summary-label">Estado</span><strong>{{ ticket.status }}</strong></div>
					<div><span class="gi-sidebar-panel__summary-label">Criticidad</span><strong>{{ ticket.urgencyId ?? 'Sin criticidad' }}</strong></div>
					<div><span class="gi-sidebar-panel__summary-label">Asignado a usuario</span><strong>{{ resolveUserLabel(ticket.assignedUserUid) }}</strong></div>
					<div><span class="gi-sidebar-panel__summary-label">Asignado a grupo</span><strong>{{ resolveGroupLabel(ticket.assignedGroupId) }}</strong></div>
				</div>
				<div>
					<h3>Descripción de soporte</h3>
					<RichTextContent :value="ticket.supportDescription" surface />
				</div>
			</div>
		</section>
		<section v-if="showSupportTabs ? activeTab === 'detail' : activeTab === 'attachments'" class="gi-sidebar-panel__block">
			<h3>Adjuntos</h3>
			<div class="gi-sidebar-panel__attachment-list">
				<button v-for="attachment in ticket.attachments || []" :key="attachment.id" class="gi-secondary-button gi-attachment-link" @click="openAttachment(attachment)">{{ attachment.originalName }}</button>
				<p v-if="!(ticket.attachments || []).length" class="gi-sidebar-panel__muted">No hay adjuntos publicados.</p>
			</div>
		</section>
		<section v-if="showSupportTabs && activeTab === 'requester'" class="gi-sidebar-panel__block gi-sidebar-panel__requester-block">
			<div class="gi-sidebar-panel__requester-header">
				<div>
					<p class="gi-sidebar-panel__section-kicker">Solicitante</p>
					<h3>{{ ticket.creatorUid ? resolveUserLabel(ticket.creatorUid) : 'Sin solicitante' }}</h3>
				</div>
			</div>
			<div v-if="requesterContactEntries.length" class="gi-sidebar-panel__requester-grid">
				<article v-for="entry in requesterContactEntries" :key="entry.key" class="gi-sidebar-panel__requester-card">
					<span class="gi-sidebar-panel__summary-label">{{ entry.label }}</span>
					<strong>{{ entry.value }}</strong>
				</article>
			</div>
			<p v-else class="gi-sidebar-panel__muted">No hay datos de contacto disponibles para este solicitante.</p>
		</section>
		<section v-if="activeTab === 'comments'" class="gi-sidebar-panel__block">
			<p v-if="!canPublishComment && isClosedTicket" class="gi-sidebar-panel__muted">Este ticket está cerrado. Reabre el ticket para volver a actuar sobre él.</p>
			<h3>Historial de comentarios</h3>
			<div class="gi-sidebar-panel__comments-toolbar">
				<label class="gi-field gi-field--wide gi-sidebar-panel__comments-search"><span>Buscar texto</span><input :id="getFieldId('comments-search')" v-model="commentsSearchText" :name="getFieldId('comments-search')" class="gi-input" type="search" /></label>
				<button class="gi-secondary-button gi-sidebar-panel__comments-mobile-toggle" :class="{ 'gi-sidebar-panel__comments-mobile-toggle--always': shouldCollapseCommentOptions }" type="button" :aria-expanded="commentsMobileMenuOpen ? 'true' : 'false'" @click="toggleCommentsMobileMenu">
					{{ commentsMobileMenuOpen ? 'Cerrar filtros' : 'Filtros y opciones' }}
				</button>
				<div v-if="showSupportTabs" class="gi-sidebar-panel__comments-toolbar-actions">
					<button v-if="filteredComments.length" class="gi-secondary-button" type="button" @click="exportComments">
						Exportar comentarios
					</button>
					<button class="gi-secondary-button" type="button" @click="toggleCommentsSortDirection">
						{{ commentsSortDirection === 'desc' ? 'Fecha: más recientes primero' : 'Fecha: más antiguas primero' }}
					</button>
					<button v-if="!fullscreen" class="gi-secondary-button" type="button" @click="emit('fullscreen')">Expandir comentarios</button>
					<button v-if="orderedComments.length" class="gi-secondary-button" type="button" @click="toggleAllVisibleComments">
						{{ allVisibleCommentsExpanded ? 'Ocultar todos' : 'Expandir todos' }}
					</button>
				</div>
			</div>
			<div v-if="commentsMobileMenuOpen" class="gi-sidebar-panel__comments-mobile-menu" :class="{ 'gi-sidebar-panel__comments-mobile-menu--always': shouldCollapseCommentOptions }">
				<div class="gi-sidebar-panel__comments-mobile-actions">
					<button v-if="filteredComments.length" class="gi-secondary-button" type="button" @click="exportComments(); closeCommentsMobileMenu()">
						Exportar comentarios
					</button>
					<button class="gi-secondary-button gi-sidebar-panel__sort-button" type="button" @click="toggleCommentsSortDirection(); closeCommentsMobileMenu()">
						{{ commentsSortDirection === 'desc' ? 'Fecha: más recientes primero' : 'Fecha: más antiguas primero' }}
					</button>
					<button v-if="!fullscreen" class="gi-secondary-button" type="button" @click="emit('fullscreen'); closeCommentsMobileMenu()">Expandir comentarios</button>
					<button v-if="orderedComments.length" class="gi-secondary-button" type="button" @click="toggleAllVisibleComments(); closeCommentsMobileMenu()">
						{{ allVisibleCommentsExpanded ? 'Ocultar todos' : 'Expandir todos' }}
					</button>
				</div>
				<div class="gi-form-grid gi-sidebar-panel__comments-mobile-filters">
					<label class="gi-field"><span>Desde</span><input :id="getFieldId('comments-date-from-mobile')" v-model="commentsDateFrom" :name="getFieldId('comments-date-from-mobile')" class="gi-input" type="date" /></label>
					<label class="gi-field"><span>Hasta</span><input :id="getFieldId('comments-date-to-mobile')" v-model="commentsDateTo" :name="getFieldId('comments-date-to-mobile')" class="gi-input" type="date" /></label>
					<div class="gi-field"><span>Usuario</span><SearchableSelect :model-value="commentsAuthorUid" :options="commentAuthorOptions" placeholder="Todos" clearable @update:modelValue="commentsAuthorUid = $event ? String($event) : null" /></div>
				</div>
			</div>
			<div v-if="showSupportTabs" class="gi-form-grid gi-sidebar-panel__comments-filters">
				<label class="gi-field"><span>Desde</span><input :id="getFieldId('comments-date-from')" v-model="commentsDateFrom" :name="getFieldId('comments-date-from')" class="gi-input" type="date" /></label>
				<label class="gi-field"><span>Hasta</span><input :id="getFieldId('comments-date-to')" v-model="commentsDateTo" :name="getFieldId('comments-date-to')" class="gi-input" type="date" /></label>
				<div class="gi-field"><span>Usuario</span><SearchableSelect :model-value="commentsAuthorUid" :options="commentAuthorOptions" placeholder="Todos" clearable @update:modelValue="commentsAuthorUid = $event ? String($event) : null" /></div>
			</div>
			<div class="gi-sidebar-panel__comments-accordion">
				<article v-for="item in orderedComments" :key="item.id" class="gi-sidebar-panel__accordion-item">
					<button class="gi-sidebar-panel__accordion-trigger" type="button" @click="toggleExpandedComment(item.id)">
						<span class="gi-sidebar-panel__accordion-trigger-content">
							<span class="gi-sidebar-panel__accordion-meta">
								<span>{{ formatDateTime(item.createdAt) }} · {{ resolveUserLabel(item.authorUid) }}</span>
								<span class="gi-badge gi-badge--success">{{ resolveVisibilityLabel(item.visibility) }}</span>
							</span>
						</span>
						<span class="gi-sidebar-panel__accordion-icon" aria-hidden="true">{{ expandedCommentIds.includes(item.id) ? '▾' : '▸' }}</span>
					</button>
					<div v-if="expandedCommentIds.includes(item.id)" class="gi-sidebar-panel__accordion-body">
						<RichTextContent :value="item.body" />
						<div v-if="item.attachments?.length" class="gi-comment__attachments">
							<button v-for="attachment in item.attachments" :key="attachment.id" class="gi-secondary-button gi-comment__attachment gi-attachment-link" @click="openAttachment(attachment)">{{ attachment.originalName }}</button>
						</div>
						<div v-if="canPublishComment && item.id === latestVisibleCommentId && replyTargetCommentId !== item.id" class="gi-sidebar-panel__comment-row-actions">
							<button class="gi-secondary-button gi-sidebar-panel__reply-button" type="button" @click="replyToComment(item)">Responder</button>
						</div>
						<TicketCommentComposer
							v-if="canPublishComment && item.id === latestVisibleCommentId"
							v-show="replyTargetCommentId === item.id"
							ref="commentComposerRef"
							:model-value="comment"
							:attachments-draft="attachmentsDraft"
							:allowed-extensions="allowedExtensions"
							:max-file-size-mb="maxFileSizeMb || 25"
							:composer-error="composerError"
							:placeholder="commentComposerPlaceholder"
							:visibility="visibility"
							:visibility-options="visibilityOptions"
							:show-visibility="canManage"
							:attachments-visible="composerAttachmentsVisible"
							dismissible
							class="gi-sidebar-panel__comment-composer gi-sidebar-panel__comment-composer--inline"
							@update:modelValue="comment = $event"
							@update:attachmentsDraft="attachmentsDraft = $event"
							@update:visibility="visibility = $event"
							@show-attachments="showComposerAttachments"
							@submit="sendComment"
							@close="clearReplyTarget"
						/>
					</div>
				</article>
				<p v-if="orderedComments.length === 0" class="gi-sidebar-panel__muted">No hay comentarios que coincidan con los filtros actuales.</p>
			</div>
			<TicketCommentComposer
				v-if="canPublishComment && orderedComments.length === 0"
				ref="commentComposerRef"
				:model-value="comment"
				:attachments-draft="attachmentsDraft"
				:allowed-extensions="allowedExtensions"
				:max-file-size-mb="maxFileSizeMb || 25"
				:composer-error="composerError"
				:placeholder="commentComposerPlaceholder"
				:visibility="visibility"
				:visibility-options="visibilityOptions"
				:show-visibility="canManage"
				:attachments-visible="composerAttachmentsVisible"
				class="gi-sidebar-panel__comment-composer gi-sidebar-panel__comment-composer--inline"
				@update:modelValue="comment = $event"
				@update:attachmentsDraft="attachmentsDraft = $event"
				@update:visibility="visibility = $event"
				@show-attachments="showComposerAttachments"
				@submit="sendComment"
			/>
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
				<p v-if="historyEntries.length === 0" class="gi-sidebar-panel__muted">No hay cambios registrados todavía.</p>
			</div>
		</section>
		<div v-if="supportCommentDialogOpen" class="gi-app-dialog-backdrop gi-dialog-backdrop" @click.self="closeSupportCommentDialog()">
			<section class="gi-app-dialog gi-dialog gi-dialog--compact" aria-label="Confirmar espera de usuario">
				<header class="gi-dialog__header">
					<h3 class="gi-dialog__title">Enviar comentario</h3>
					<button class="gi-modal-close" type="button" aria-label="Cerrar ventana" @click="closeSupportCommentDialog()">x</button>
				</header>
				<p class="gi-dialog__message gi-dialog__message--neutral">¿Quieres pasar el ticket a en espera de usuario al enviar este comentario?</p>
				<footer class="gi-dialog__footer">
					<button class="gi-ghost-button" type="button" @click="confirmSupportComment(false)">No</button>
					<button class="gi-primary-button" type="button" @click="confirmSupportComment(true)">Sí</button>
				</footer>
			</section>
		</div>
		<div v-if="discardChangesDialogOpen" class="gi-app-dialog-backdrop gi-dialog-backdrop" @click.self="resolveDiscardChangesDialog(false)">
			<section class="gi-app-dialog gi-dialog gi-dialog--compact" aria-label="Confirmar salida sin guardar">
				<header class="gi-dialog__header">
					<h3 class="gi-dialog__title">Cambios sin guardar</h3>
					<button class="gi-modal-close" type="button" aria-label="Cerrar ventana" @click="resolveDiscardChangesDialog(false)">x</button>
				</header>
				<p class="gi-dialog__message gi-dialog__message--neutral">Hay cambios sin guardar en esta incidencia. Si sales ahora, se perderán.</p>
				<footer class="gi-dialog__footer">
					<button class="gi-ghost-button" type="button" @click="resolveDiscardChangesDialog(false)">Seguir editando</button>
					<button class="gi-primary-button" type="button" @click="resolveDiscardChangesDialog(true)">Salir sin guardar</button>
				</footer>
			</section>
		</div>
		<div v-if="closeReasonDialogOpen" class="gi-app-dialog-backdrop gi-dialog-backdrop" @click.self="closeReasonDialogOpen = false">
			<section class="gi-app-dialog gi-dialog gi-dialog--compact" aria-label="Motivo del cierre obligatorio">
				<header class="gi-dialog__header">
					<h3 class="gi-dialog__title">Motivo del cierre obligatorio</h3>
					<button class="gi-modal-close" type="button" aria-label="Cerrar ventana" @click="closeReasonDialogOpen = false">x</button>
				</header>
				<p class="gi-dialog__message gi-dialog__message--neutral">Debes indicar el motivo del cierre antes de guardar.</p>
				<footer class="gi-dialog__footer">
					<button class="gi-primary-button" type="button" @click="closeReasonDialogOpen = false">Entendido</button>
				</footer>
			</section>
		</div>
		<div v-if="externalAttachmentUrlDialogOpen" class="gi-app-dialog-backdrop gi-dialog-backdrop" @click.self="closeExternalAttachmentUrlDialog()">
			<section class="gi-app-dialog gi-dialog gi-dialog--compact" aria-label="Confirmar apertura de enlace externo">
				<header class="gi-dialog__header">
					<h3 class="gi-dialog__title">Abrir enlace externo</h3>
					<button class="gi-modal-close" type="button" aria-label="Cerrar ventana" @click="closeExternalAttachmentUrlDialog()">x</button>
				</header>
				<p class="gi-dialog__message gi-dialog__message--neutral">Se va a abrir este adjunto en una página externa.</p>
				<p class="gi-dialog__message gi-dialog__message--neutral">{{ pendingExternalAttachment?.sourceUrl }}</p>
				<footer class="gi-dialog__footer">
					<button class="gi-ghost-button" type="button" @click="closeExternalAttachmentUrlDialog()">Cancelar</button>
					<button class="gi-primary-button" type="button" @click="confirmExternalAttachmentUrl()">Abrir enlace</button>
				</footer>
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

.gi-sidebar-panel__ticket-line {
	display: flex;
	align-items: center;
	gap: .75rem;
	flex-wrap: wrap;
}

.gi-sidebar-panel__requester-inline {
	display: inline-flex;
	align-items: center;
	gap: .45rem;
	flex-wrap: wrap;
	color: #48645d;
	font-size: .92rem;
}

.gi-sidebar-panel__requester-label {
	font-weight: 600;
}

.gi-sidebar-panel__requester-button {
	border: 0;
	background: transparent;
	padding: 0;
	font: inherit;
	font-weight: 700;
	color: #0b6e4f;
	text-decoration: underline;
	text-underline-offset: .15em;
	cursor: pointer;
}

.gi-sidebar-panel__requester-button:hover,
.gi-sidebar-panel__requester-button:focus-visible {
	color: #084f39;
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

.gi-sidebar-panel__editor-form {
	display: grid;
	gap: .85rem;
	padding: 1rem 0;
}

.gi-sidebar-panel__close-reason-field {
	width: 100%;
	min-width: 0;
}

.gi-sidebar-panel__close-reason-field :deep(.gi-plain-text-editor),
.gi-sidebar-panel__close-reason-field :deep(.gi-plain-text-editor__surface) {
	width: 100%;
	max-width: none;
}

.gi-sidebar-panel__compact-select-grid {
	display: grid;
	gap: .75rem;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	align-items: start;
}

.gi-sidebar-panel__compact-field {
	gap: .25rem;
}

.gi-sidebar-panel__compact-field > span {
	font-size: .73rem;
}

.gi-sidebar-panel__summary-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
	gap: .85rem;
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

.gi-sidebar-panel__requester-block,
.gi-sidebar-panel__requester-grid {
	display: grid;
	gap: 1rem;
}

.gi-sidebar-panel__requester-header h3 {
	margin: 0;
}

.gi-sidebar-panel__requester-grid {
	grid-template-columns: 1fr;
}

.gi-sidebar-panel__requester-card {
	padding: .95rem 1rem;
	border-radius: 16px;
	background: rgba(245, 249, 247, .96);
	border: 1px solid rgba(49, 96, 91, .12);
	display: grid;
	gap: .35rem;
	justify-items: start;
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

.gi-sidebar-panel__type-chip {
	padding: .8rem .95rem;
	border: 1px solid rgba(49, 96, 91, .12);
	border-radius: 14px;
	background: rgba(245, 249, 247, .96);
	font-weight: 600;
	color: #2f554c;
}

.gi-sidebar-panel__comments-mobile-toggle,
.gi-sidebar-panel__comments-mobile-menu {
	display: none;
}

.gi-sidebar-panel__comments-mobile-toggle--always {
	display: inline-flex;
	justify-content: center;
}

.gi-sidebar-panel__comments-mobile-menu--always {
	display: grid;
	gap: .85rem;
	padding: .9rem 1rem;
	border: 1px solid rgba(49, 96, 91, .12);
	border-radius: 16px;
	background: rgba(247, 250, 248, .95);
}

.gi-sidebar-panel__comment-composer--inline {
	margin-top: 1rem;
	margin-bottom: 0;
	margin-left: -1rem;
	margin-right: -1rem;
	width: calc(100% + 2rem);
}

.gi-sidebar-panel__composer-clear-button,
.gi-sidebar-panel__reply-button {
	white-space: nowrap;
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
	display: flex;
	align-items: center;
	justify-content: center;
	justify-self: stretch;
	width: 100%;
	min-height: 2.364rem;
	box-sizing: border-box;
	text-align: center;
}

.gi-sidebar-panel__comments-mobile-actions > .gi-secondary-button {
	width: 100%;
	justify-content: center;
	box-sizing: border-box;
}

.gi-sidebar-panel__selected-file {
	justify-content: space-between;
	padding: .65rem .8rem;
	border: 1px solid rgba(33, 53, 68, .12);
	border-radius: 12px;
	background: rgba(255, 255, 255, .7);
}

.gi-sidebar-panel__comments-filters,
.gi-sidebar-panel__comments-mobile-actions,
.gi-sidebar-panel__comments-mobile-filters,
.gi-sidebar-panel__comments-accordion {
	display: grid;
	gap: .75rem;
}

.gi-sidebar-panel__comment-row-actions {
	display: flex;
	justify-content: flex-start;
	align-items: center;
	gap: .65rem;
	padding-top: .1rem;
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
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: .45rem;
	font-size: .87rem;
	font-weight: 700;
	color: #385b53;
	line-height: 1.35;
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
	.gi-sidebar-panel__compact-select-grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}

	.gi-sidebar-panel__comment-meta,
	.gi-sidebar-panel__history-meta,
	.gi-sidebar-panel__comments-header,
	.gi-sidebar-panel__comments-header-actions,
	.gi-sidebar-panel__comments-toolbar-actions,
	.gi-sidebar-panel__comment-composer-actions {
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

@media (max-width: 640px) {
	.gi-sidebar-panel__compact-select-grid {
		grid-template-columns: 1fr;
	}

	.gi-sidebar-panel__comments-mobile-toggle {
		display: inline-flex;
		justify-content: center;
	}

	.gi-sidebar-panel__comments-toolbar-actions,
	.gi-sidebar-panel__comments-filters {
		display: none;
	}

	.gi-sidebar-panel__comments-mobile-menu {
		display: grid;
		gap: .85rem;
		padding: .9rem 1rem;
		border: 1px solid rgba(49, 96, 91, .12);
		border-radius: 16px;
		background: rgba(247, 250, 248, .95);
	}

}
</style>