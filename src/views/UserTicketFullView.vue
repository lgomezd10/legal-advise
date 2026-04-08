<script setup lang="ts">
import { watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import TicketSidebarPanel from '@/components/TicketSidebarPanel.vue'
import { createRepeatTicketDraft } from '@/services/ticketDraft'
import { useBootstrapStore } from '@/store/bootstrap'
import { useTicketsStore } from '@/store/tickets'
import type { TicketAttachmentLinkDraft } from '@/types'

const route = useRoute()
const router = useRouter()
const bootstrapStore = useBootstrapStore()
const ticketsStore = useTicketsStore()

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

async function reopenTicket() {
	if (!ticketsStore.selected) {
		return
	}

	await ticketsStore.reopen(ticketsStore.selected.id)
}

function backToUserConsole() {
	if (ticketsStore.selected) {
		void router.push(`/mis-incidencias/${ticketsStore.selected.id}`)
		return
	}

	void router.push('/mis-incidencias')
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
	<section class="gi-page">
		<header class="gi-page__header gi-page__header--dense">
			<div>
				<h1>Detalle del ticket</h1>			
			</div>
			<button class="gi-secondary-button" type="button" @click="backToUserConsole">Volver a mis tickets</button>
		</header>
		<TicketSidebarPanel
			:ticket="ticketsStore.selected"
			:roles="['usuario']"
			:users="bootstrapStore.data.assignables.users"
			:groups="bootstrapStore.data.assignables.groups"
			:fields="bootstrapStore.data.catalogs.fields"
			:current-user-uid="bootstrapStore.data.currentUser.uid"
			:allowed-extensions="bootstrapStore.data.catalogs.attachmentConfig.allowedExtensions"
			:max-file-size-mb="bootstrapStore.data.catalogs.attachmentConfig.maxFileSizeMb"
			fullscreen
			:read-only="true"
			:show-repeat="true"
			@comment="commentOnTicket"
			@download="download"
			@reopen="reopenTicket"
			@repeat="repeatTicket"
		/>
	</section>
</template>

<style scoped>
.gi-page {
	max-width: none;
}
</style>