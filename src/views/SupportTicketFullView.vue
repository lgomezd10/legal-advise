<script setup lang="ts">
import { computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import TicketSidebarPanel from '@/components/TicketSidebarPanel.vue'
import { useBootstrapStore } from '@/store/bootstrap'
import { useTicketsStore } from '@/store/tickets'

const route = useRoute()
const router = useRouter()
const bootstrapStore = useBootstrapStore()
const ticketsStore = useTicketsStore()

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

function backToSupport() {
	if (ticketsStore.selected) {
		void router.push(`/soporte/${ticketsStore.selected.id}`)
		return
	}
	void router.push('/soporte')
}
</script>

<template>
	<section class="gi-page">
		<header class="gi-page__header gi-page__header--dense">
			<div>
				<p class="gi-kicker">Soporte</p>
				<h1>Incidencia a pantalla completa</h1>
			</div>
			<button class="gi-secondary-button" type="button" @click="backToSupport">Volver a consola</button>
		</header>
		<TicketSidebarPanel
			:ticket="ticketsStore.selected"
			:roles="bootstrapStore.data.roles"
			:users="assignableUsers"
			:groups="assignableGroups"
			:statuses="statuses"
			:urgencies="urgencies"
			:allowed-extensions="bootstrapStore.data.catalogs.attachmentConfig.allowedExtensions"
			fullscreen
			:show-fullscreen="false"
			@comment="commentOnTicket"
			@update="updateTicket"
			@download="download"
			@fullscreen="backToSupport"
		/>
	</section>
</template>

<style scoped>
.gi-page {
	max-width: none;
}
</style>