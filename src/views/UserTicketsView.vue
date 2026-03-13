<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import TicketList from '@/components/TicketList.vue'
import { createDefaultTicketDraft } from '@/services/ticketDraft'
import { useBootstrapStore } from '@/store/bootstrap'
import { useTicketsStore } from '@/store/tickets'
import type { Ticket } from '@/types'

const router = useRouter()
const bootstrapStore = useBootstrapStore()
const ticketsStore = useTicketsStore()
const urgencies = computed(() => bootstrapStore.data.catalogs.urgencies)
const searchText = ref('')
const filteredTickets = computed(() => {
	const term = searchText.value.trim().toLowerCase()
	if (term === '') {
		return ticketsStore.items
	}

	return ticketsStore.items.filter((ticket: Ticket) => {
		const haystack = [
			ticket.number,
			ticket.status,
			ticket.title,
			ticket.userDescription,
			ticket.province ?? '',
			ticket.city ?? '',
			ticket.assignedUserUid ?? '',
			ticket.assignedGroupId ?? '',
			formatDate(ticket.createdAt),
		].join(' ').toLowerCase()

		return haystack.includes(term)
	})
})

onMounted(() => {
	void ticketsStore.load('user')
})

function openTicket(ticketId: number) {
	void router.push(`/mis-incidencias/${ticketId}`)
}

function createTicket() {
	ticketsStore.replaceDraft(createDefaultTicketDraft(bootstrapStore.data.personalConfig, urgencies.value))
	void router.push('/mis-incidencias/nuevo')
}

function formatDate(timestamp: number) {
	return new Date(timestamp * 1000).toLocaleString()
}
</script>

<template>
	<section class="gi-page">
		<header class="gi-page__header">
			<label class="gi-search-box">
				<span class="gi-search-box__label">Buscar</span>
				<input v-model="searchText" class="gi-search-box__input" type="search" placeholder="Buscar por numero, estado, titulo, descripcion, fecha, provincia, ciudad o asignacion" />
			</label>
			<button class="gi-primary-button" type="button" @click="createTicket">Nuevo ticket</button>
		</header>
		<TicketList :tickets="filteredTickets" :empty-label="searchText.trim() === '' ? 'No hay incidencias registradas' : 'No hay incidencias que coincidan con la busqueda'" @open="openTicket" />
	</section>
</template>