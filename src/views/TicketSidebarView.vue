<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import TicketSidebarPanel from '@/components/TicketSidebarPanel.vue'
import { createRepeatTicketDraft } from '@/services/ticketDraft'
import { useBootstrapStore } from '@/store/bootstrap'
import { useTicketsStore } from '@/store/tickets'

const route = useRoute()
const router = useRouter()
const bootstrapStore = useBootstrapStore()
const ticketsStore = useTicketsStore()
const supportMode = computed(() => route.path.startsWith('/soporte'))
const panelRoles = computed(() => supportMode.value ? bootstrapStore.data.roles : ['usuario'])

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

async function commentOnTicket(payload: { body: string, visibility: 'interno' | 'publico', files: File[] }) {
	if (!ticketsStore.selected) {
		return
	}

	await ticketsStore.comment(ticketsStore.selected.id, payload)
}

async function updateTicket(payload: Record<string, unknown>) {
	if (!ticketsStore.selected) {
		return
	}

	await ticketsStore.update(ticketsStore.selected.id, payload)
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
		:ticket="ticketsStore.selected"
		:roles="panelRoles"
		:users="bootstrapStore.data.assignables.users"
		:groups="bootstrapStore.data.assignables.groups"
		:statuses="bootstrapStore.data.catalogs.statuses"
		:urgencies="bootstrapStore.data.catalogs.urgencies"
		:allowed-extensions="bootstrapStore.data.catalogs.attachmentConfig.allowedExtensions"
		:read-only="!supportMode"
		:show-fullscreen="true"
		:show-repeat="!supportMode"
		@comment="commentOnTicket"
		@update="updateTicket"
		@download="download"
		@fullscreen="openFullscreen"
		@repeat="repeatTicket"
	/>
</template>