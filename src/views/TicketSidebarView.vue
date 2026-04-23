<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate, useRoute, useRouter } from 'vue-router'
import TicketSidebarPanel from '@/components/TicketSidebarPanel.vue'
import { createRepeatTicketDraft } from '@/services/ticketDraft'
import { useBootstrapStore } from '@/store/bootstrap'
import { useTicketsStore } from '@/store/tickets'
import type { TicketAttachmentLinkDraft } from '@/types'

const route = useRoute()
const router = useRouter()
const bootstrapStore = useBootstrapStore()
const ticketsStore = useTicketsStore()
const supportMode = computed(() => route.path.startsWith('/soporte'))
const panelRoles = computed(() => supportMode.value ? bootstrapStore.data.roles : ['usuario'])
const panelRef = ref<{ confirmDiscardChanges: () => boolean | Promise<boolean> } | null>(null)

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

async function commentOnTicket(payload: { body: string, visibility: 'interno' | 'publico', files: File[], links: TicketAttachmentLinkDraft[] }) {
	if (!ticketsStore.selected) {
		return
	}

	await ticketsStore.comment(ticketsStore.selected.id, payload)
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

async function assignToCurrentUser() {
	if (!ticketsStore.selected || !supportMode.value) {
		return
	}

	await ticketsStore.update(ticketsStore.selected.id, { assignedUserUid: bootstrapStore.data.currentUser.uid })
}

function openFullscreen() {
	if (!ticketsStore.selected) {
		return
	}

	void router.push(supportMode.value ? `/soporte/${ticketsStore.selected.id}/completo` : `/mis-incidencias/${ticketsStore.selected.id}/completo`)
}

function repeatTicket() {
	if (!ticketsStore.selected) {
		return
	}

	ticketsStore.replaceDraft(createRepeatTicketDraft(
		ticketsStore.selected,
		bootstrapStore.data.catalogs.types,
		bootstrapStore.data.personalConfig,
		bootstrapStore.data.catalogs.urgencies,
	))
	void router.push('/mis-incidencias/nuevo')
}
</script>

<template>
	<TicketSidebarPanel
		ref="panelRef"
		:ticket="ticketsStore.selected"
		:roles="panelRoles"
		:users="bootstrapStore.data.assignables.users"
		:groups="bootstrapStore.data.assignables.groups"
		:types="bootstrapStore.data.catalogs.types"
		:fields="bootstrapStore.data.catalogs.fields"
		:current-user-uid="bootstrapStore.data.currentUser.uid"
		:statuses="bootstrapStore.data.catalogs.statuses"
		:urgencies="bootstrapStore.data.catalogs.urgencies"
		:allowed-extensions="bootstrapStore.data.catalogs.attachmentConfig.allowedExtensions"
		:max-file-size-mb="bootstrapStore.data.catalogs.attachmentConfig.maxFileSizeMb"
		:initial-composer-visible="!supportMode"
		:read-only="!supportMode || !ticketsStore.selected?.canManage"
		:show-fullscreen="true"
		:show-repeat="!supportMode"
		@comment="commentOnTicket"
		@save="saveTicket"
		@download="download"
		@fullscreen="openFullscreen"
		@reopen="reopenTicket"
		@assign-to-me="assignToCurrentUser"
		@repeat="repeatTicket"
	/>
</template>