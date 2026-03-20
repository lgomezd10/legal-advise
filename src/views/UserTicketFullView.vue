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
				<p class="gi-kicker">Usuario</p>
				<h1>Detalle de incidencia</h1>
				<p class="gi-page__subtitle">Desde esta vista solo puedes consultar la incidencia y anadir comentarios o adjuntos.</p>
			</div>
			<button class="gi-secondary-button" type="button" @click="backToUserConsole">Volver a mis incidencias</button>
		</header>
		<TicketSidebarPanel
			:ticket="ticketsStore.selected"
			:roles="['usuario']"
			:allowed-extensions="bootstrapStore.data.catalogs.attachmentConfig.allowedExtensions"
			:max-file-size-mb="bootstrapStore.data.catalogs.attachmentConfig.maxFileSizeMb"
			fullscreen
			:read-only="true"
			:show-repeat="true"
			@comment="commentOnTicket"
			@download="download"
			@repeat="repeatTicket"
		/>
	</section>
</template>

<style scoped>
.gi-page {
	max-width: none;
}
</style>