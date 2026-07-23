<script setup lang="ts">
import { computed, defineAsyncComponent, ref, watch } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate, useRoute, useRouter } from 'vue-router'
import { useBootstrapStore } from '@/store/bootstrap'
import { useTicketsStore } from '@/store/tickets'
import type { TicketAttachmentLinkDraft } from '@/types'

const TicketSidebarPanel = defineAsyncComponent(() => import('@/components/TicketSidebarPanel.vue'))

const route = useRoute()
const router = useRouter()
const bootstrapStore = useBootstrapStore()
const ticketsStore = useTicketsStore()
const panelRef = ref<{ confirmDiscardChanges: () => boolean | Promise<boolean> } | null>(null)
const ticketDeleteDialogOpen = ref(false)

function canLeaveTicket() {
	return panelRef.value?.confirmDiscardChanges() ?? true
}

onBeforeRouteLeave(() => canLeaveTicket())

onBeforeRouteUpdate((to) => {
	if (to.params.ticketId === route.params.ticketId) {
		return true
	}

	return canLeaveTicket()
})

const assignableUsers = computed(() => bootstrapStore.data.assignables.users)
const assignableGroups = computed(() => bootstrapStore.data.assignables.groups)
const statuses = computed(() => bootstrapStore.data.catalogs.statuses)
const urgencies = computed(() => bootstrapStore.data.catalogs.urgencies)

watch(() => route.params.ticketId, async(nextTicketId) => {
	const ticketId = Number(nextTicketId)
	if (ticketId) {
		await ticketsStore.select(ticketId)
	}
}, { immediate: true })

async function download(attachmentId: number) {
	const result = await ticketsStore.download(attachmentId)
	const binary = atob(result.content)
	const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0))
	const blob = new Blob([bytes], { type: String(result.meta.mimeType ?? 'application/octet-stream') })
	const link = document.createElement('a')
	link.href = URL.createObjectURL(blob)
	link.download = String(result.meta.originalName ?? 'adjunto')
	link.click()
	URL.revokeObjectURL(link.href)
}

async function downloadArchive(attachmentIds: number[]) {
	if (!ticketsStore.selected) {
		return
	}

	const result = await ticketsStore.downloadArchive(ticketsStore.selected.id, attachmentIds)
	const binary = atob(result.content)
	const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0))
	const link = document.createElement('a')
	link.href = URL.createObjectURL(new Blob([bytes], { type: result.mimeType }))
	link.download = result.filename
	link.click()
	URL.revokeObjectURL(link.href)
}

async function commentOnTicket(payload: { body: string, visibility: 'interno' | 'publico', files: File[], links: TicketAttachmentLinkDraft[], waitForUser?: boolean }) {
	if (!ticketsStore.selected) {
		return
	}

	await ticketsStore.comment(ticketsStore.selected.id, payload)
	if (payload.waitForUser) {
		await ticketsStore.update(ticketsStore.selected.id, { status: 'en_espera_usuario' })
	}
}

async function deleteCommentOnTicket(payload: { commentId: number, restoreAssignedStatus: boolean }) {
	if (!ticketsStore.selected) {
		return
	}

	await ticketsStore.deleteComment(ticketsStore.selected.id, payload.commentId, payload.restoreAssignedStatus)
}

async function editCommentOnTicket(payload: { commentId: number, body: string, visibility: 'interno' | 'publico' }) {
	if (!ticketsStore.selected) {
		return
	}

	await ticketsStore.editComment(ticketsStore.selected.id, payload.commentId, payload)
}

async function saveTicket(payload: Record<string, unknown>) {
	if (!ticketsStore.selected) {
		return
	}

	await ticketsStore.update(ticketsStore.selected.id, payload)
}

async function reopenTicket() {
	if (!ticketsStore.selected) {
		return
	}

	await ticketsStore.reopen(ticketsStore.selected.id)
}

async function assignToCurrentUser(payload?: { assignedUserUid: string | null, assignedGroupId: string | null }) {
	if (!ticketsStore.selected) {
		return
	}

	await ticketsStore.update(ticketsStore.selected.id, payload ?? {
		assignedUserUid: bootstrapStore.data.currentUser.uid,
		assignedGroupId: null,
	})
}

function backToIncident() {
	if (!ticketsStore.selected) {
		return
	}

	void router.push(`/soporte/${ticketsStore.selected.id}`)
}

function backToSupport() {
	void router.push('/soporte')
}

function requestTicketDeletion() {
	ticketDeleteDialogOpen.value = true
}

function closeTicketDeleteDialog() {
	ticketDeleteDialogOpen.value = false
}

async function confirmTicketDeletion() {
	if (!ticketsStore.selected) {
		closeTicketDeleteDialog()
		return
	}

	const ticketId = ticketsStore.selected.id
	closeTicketDeleteDialog()
	await ticketsStore.deleteTicket(ticketId)
	void router.push('/soporte')
}
</script>

<template>
	<section class="gi-page gi-page--ticket-full">
		<TicketSidebarPanel
			ref="panelRef"
			:ticket="ticketsStore.selected"
			:roles="bootstrapStore.data.roles"
			:users="assignableUsers"
			:groups="assignableGroups"
			:types="bootstrapStore.data.catalogs.types"
			:fields="bootstrapStore.data.catalogs.fields"
			:current-user-uid="bootstrapStore.data.currentUser.uid"
			:statuses="statuses"
			:urgencies="urgencies"
			:allowed-extensions="bootstrapStore.data.catalogs.attachmentConfig.allowedExtensions"
			:max-file-size-mb="bootstrapStore.data.catalogs.attachmentConfig.maxFileSizeMb"
			:initial-composer-visible="false"
			fullscreen
			initial-tab="comments"
			:read-only="!ticketsStore.selected?.canManage"
			:show-fullscreen="false"
			@comment="commentOnTicket"
			@edit-comment="editCommentOnTicket"
			@delete-comment="deleteCommentOnTicket"
			@save="saveTicket"
			@download="download"
			@download-archive="downloadArchive"
			@reopen="reopenTicket"
			@assign-to-me="assignToCurrentUser"
		>
			<template #actions>
				<button v-if="ticketsStore.selected?.canDelete" class="gi-ghost-button gi-dialog__danger" type="button" @click="requestTicketDeletion">Eliminar ticket</button>
				<button class="gi-secondary-button" type="button" @click="backToIncident">Volver al ticket</button>
				<button class="gi-secondary-button" type="button" @click="backToSupport">Volver a consola</button>
			</template>
		</TicketSidebarPanel>
		<div v-if="ticketDeleteDialogOpen" class="gi-app-dialog-backdrop gi-dialog-backdrop" @click.self="closeTicketDeleteDialog()">
			<section class="gi-app-dialog gi-dialog gi-dialog--compact" aria-label="Confirmar borrado del ticket">
				<header class="gi-dialog__header">
					<h3 class="gi-dialog__title">Eliminar ticket</h3>
					<button class="gi-modal-close" type="button" aria-label="Cerrar ventana" @click="closeTicketDeleteDialog()">x</button>
				</header>
				<p class="gi-dialog__message gi-dialog__message--neutral">Este borrado no se podrá deshacer.</p>
				<footer class="gi-dialog__footer">
					<button class="gi-ghost-button" type="button" @click="closeTicketDeleteDialog()">Cancelar</button>
					<button class="gi-secondary-button gi-dialog__danger" type="button" @click="confirmTicketDeletion">Eliminar ticket</button>
				</footer>
			</section>
		</div>
	</section>
</template>

<style scoped>
.gi-page {
	max-width: none;
}
</style>