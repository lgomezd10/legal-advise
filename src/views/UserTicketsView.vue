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
const createdFrom = ref('')
const createdTo = ref('')
const openSections = ref({
	open: true,
	closed: true,
})
const filteredTickets = computed(() => {
	const term = searchText.value.trim().toLowerCase()
	return ticketsStore.items.filter((ticket: Ticket) => {
		const createdAt = new Date(ticket.createdAt * 1000)
		if (createdFrom.value) {
			const from = new Date(`${createdFrom.value}T00:00:00`)
			if (createdAt < from) {
				return false
			}
		}

		if (createdTo.value) {
			const to = new Date(`${createdTo.value}T23:59:59`)
			if (createdAt > to) {
				return false
			}
		}

		if (term === '') {
			return true
		}

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
const openTickets = computed(() => filteredTickets.value.filter((ticket: Ticket) => !isClosedTicket(ticket)))
const closedTickets = computed(() => filteredTickets.value.filter((ticket: Ticket) => isClosedTicket(ticket)))

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

function isClosedTicket(ticket: Ticket) {
	return ticket.status === 'resuelto' || ticket.status === 'cerrado'
}
</script>

<template>
	<section class="gi-page">
		<header class="gi-page__header gi-ticket-list-header">
			<div class="gi-ticket-list-header__top">
				<button class="gi-primary-button" type="button" @click="createTicket">Nuevo ticket</button>
			</div>
			<div class="gi-ticket-list-header__filters">
				<label class="gi-search-box gi-ticket-list-header__search">
					<span class="gi-search-box__label">Buscar</span>
					<input v-model="searchText" class="gi-search-box__input" type="search" placeholder="Buscar por numero, estado, titulo, descripcion, fecha, provincia, ciudad o asignacion" />
				</label>
				<label class="gi-field">
					<span>Desde</span>
					<input v-model="createdFrom" class="gi-input" type="date" />
				</label>
				<label class="gi-field">
					<span>Hasta</span>
					<input v-model="createdTo" class="gi-input" type="date" />
				</label>
			</div>
		</header>
		<div class="gi-ticket-sections">
			<section class="gi-ticket-section">
				<button class="gi-ticket-section__header" type="button" @click="openSections.open = !openSections.open">
					<span>Incidencias abiertas</span>
					<strong>{{ openTickets.length }}</strong>
				</button>
				<TicketList v-if="openSections.open" :tickets="openTickets" :empty-label="searchText.trim() === '' ? 'No hay incidencias abiertas' : 'No hay incidencias abiertas que coincidan con la busqueda'" @open="openTicket" />
			</section>
			<section class="gi-ticket-section">
				<button class="gi-ticket-section__header" type="button" @click="openSections.closed = !openSections.closed">
					<span>Incidencias cerradas</span>
					<strong>{{ closedTickets.length }}</strong>
				</button>
				<TicketList v-if="openSections.closed" :tickets="closedTickets" :empty-label="searchText.trim() === '' ? 'No hay incidencias cerradas' : 'No hay incidencias cerradas que coincidan con la busqueda'" @open="openTicket" />
			</section>
		</div>
	</section>
</template>

<style scoped>
.gi-ticket-list-header {
	display: grid;
	gap: .9rem;
	align-items: stretch;
}

.gi-ticket-list-header__top {
	display: flex;
	justify-content: space-between;
	align-items: flex-start;
	gap: 1rem;
	flex-wrap: wrap;
}

.gi-ticket-list-header__search {
	width: min(100%, 42rem);
	max-width: 100%;
	flex: none;
}

.gi-ticket-list-header__filters {
	display: flex;
	align-items: end;
	gap: .85rem;
	flex-wrap: wrap;
}

.gi-ticket-sections {
	display: grid;
	gap: 1rem;
}

.gi-ticket-section {
	display: grid;
	gap: .75rem;
	min-width: 0;
}

.gi-ticket-section__header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 1rem;
	padding: .85rem 1rem;
	border: 1px solid rgba(49, 96, 91, .12);
	border-radius: 18px;
	background: rgba(245, 249, 247, .96);
	color: #214f45;
	font: inherit;
	font-weight: 700;
	cursor: pointer;
	text-align: left;
}

@media (max-width: 900px) {
	.gi-ticket-list-header__top {
		flex-direction: column;
	}

	.gi-ticket-list-header__search {
		width: 100%;
	}

	.gi-ticket-list-header__filters {
		align-items: stretch;
	}
}
</style>