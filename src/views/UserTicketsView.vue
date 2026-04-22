<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import TicketList from '@/components/TicketList.vue'
import { createDefaultTicketDraft } from '@/services/ticketDraft'
import { useBootstrapStore } from '@/store/bootstrap'
import { useTicketsStore } from '@/store/tickets'
import type { Ticket } from '@/types'
import { formatDateTime, getStatusLabel } from '@/utils/formatting'
import { richTextToPlainText } from '@/utils/richText'

const router = useRouter()
const bootstrapStore = useBootstrapStore()
const ticketsStore = useTicketsStore()
const urgencies = computed(() => bootstrapStore.data.catalogs.urgencies)
const statuses = computed(() => bootstrapStore.data.catalogs.statuses)
const assignableUsers = computed(() => bootstrapStore.data.assignables.users)
const assignableGroups = computed(() => bootstrapStore.data.assignables.groups)
const searchText = ref('')
const dateField = ref<'createdAt' | 'updatedAt'>('createdAt')
const createdFrom = ref('')
const createdTo = ref('')
const openSections = ref({
	pending: false,
	open: false,
	closed: false,
})
const initializedDefaultSection = ref(false)
const filteredTickets = computed(() => {
	const term = searchText.value.trim().toLowerCase()
	return ticketsStore.items.filter((ticket: Ticket) => {
		const ticketDate = new Date((dateField.value === 'updatedAt' ? ticket.updatedAt : ticket.createdAt) * 1000)
		if (createdFrom.value) {
			const from = new Date(`${createdFrom.value}T00:00:00`)
			if (ticketDate < from) {
				return false
			}
		}

		if (createdTo.value) {
			const to = new Date(`${createdTo.value}T23:59:59`)
			if (ticketDate > to) {
				return false
			}
		}

		if (term === '') {
			return true
		}

		const haystack = [
			ticket.number,
			ticket.status,
			getStatusLabel(ticket.status, statuses.value),
			ticket.title,
			richTextToPlainText(ticket.userDescription),
			ticket.publicCommentSearchText ?? '',
			ticket.province ?? '',
			ticket.city ?? '',
			formatDateTime(ticket.createdAt),
			formatDateTime(ticket.updatedAt),
		].join(' ').toLowerCase()

		return haystack.includes(term)
	})
})
const pendingTickets = computed(() => filteredTickets.value.filter((ticket: Ticket) => ticket.status === 'en_espera_usuario'))
const openTickets = computed(() => filteredTickets.value.filter((ticket: Ticket) => !isClosedTicket(ticket) && ticket.status !== 'en_espera_usuario'))
const closedTickets = computed(() => filteredTickets.value.filter((ticket: Ticket) => isClosedTicket(ticket)))

watch([searchText, pendingTickets, openTickets, closedTickets], ([term]) => {
	if (term.trim() === '') {
		return
	}

	openSections.value.pending = pendingTickets.value.length > 0
	openSections.value.open = openTickets.value.length > 0
	openSections.value.closed = closedTickets.value.length > 0
})

watch(pendingTickets, (tickets) => {
	if (tickets.length === 0 && openSections.value.pending) {
		openSections.value.pending = false
	}

	if (!ticketsStore.loading && !initializedDefaultSection.value) {
		openSections.value.pending = tickets.length > 0
		initializedDefaultSection.value = true
	}
}, { immediate: true })

onMounted(() => {
	initializedDefaultSection.value = false
	void ticketsStore.load('user').finally(() => {
		openSections.value.pending = pendingTickets.value.length > 0
		initializedDefaultSection.value = true
	})
})

function openTicket(ticketId: number) {
	void router.push(`/mis-incidencias/${ticketId}`)
}

function createTicket() {
	ticketsStore.replaceDraft(createDefaultTicketDraft(bootstrapStore.data.personalConfig, urgencies.value))
	void router.push('/mis-incidencias/nuevo')
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
					<input v-model="searchText" class="gi-search-box__input" type="search" placeholder="Buscar por número, estado, título, descripción, comentarios, fecha, provincia o ciudad" />
				</label>
				<label class="gi-field gi-ticket-list-header__date-field">
					<span>Fecha</span>
					<select v-model="dateField" class="gi-input gi-ticket-list-header__date-field-select">
						<option value="createdAt">Creación</option>
						<option value="updatedAt">Última modificación</option>
					</select>
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
				<button class="gi-ticket-section__header" type="button" @click="openSections.pending = !openSections.pending">
					<span>Pendiente de mi</span>
					<strong>{{ pendingTickets.length }}</strong>
				</button>
				<TicketList v-if="openSections.pending" :tickets="pendingTickets" :statuses="statuses" :types="bootstrapStore.data.catalogs.types" :users="assignableUsers" :groups="assignableGroups" :empty-label="searchText.trim() === '' ? 'No hay tickets pendientes de ti' : 'No hay tickets pendientes de ti que coincidan con la búsqueda'" @open="openTicket" />
			</section>
			<section class="gi-ticket-section">
				<button class="gi-ticket-section__header" type="button" @click="openSections.open = !openSections.open">
					<span>Tickets abiertos</span>
					<strong>{{ openTickets.length }}</strong>
				</button>
				<TicketList v-if="openSections.open" :tickets="openTickets" :statuses="statuses" :types="bootstrapStore.data.catalogs.types" :users="assignableUsers" :groups="assignableGroups" :empty-label="searchText.trim() === '' ? 'No hay tickets abiertos' : 'No hay tickets abiertos que coincidan con la búsqueda'" @open="openTicket" />
			</section>
			<section class="gi-ticket-section">
				<button class="gi-ticket-section__header" type="button" @click="openSections.closed = !openSections.closed">
					<span>Tickets cerrados</span>
					<strong>{{ closedTickets.length }}</strong>
				</button>
				<TicketList v-if="openSections.closed" :tickets="closedTickets" :statuses="statuses" :types="bootstrapStore.data.catalogs.types" :users="assignableUsers" :groups="assignableGroups" :empty-label="searchText.trim() === '' ? 'No hay tickets cerrados' : 'No hay tickets cerrados que coincidan con la búsqueda'" @open="openTicket" />
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

.gi-ticket-list-header__date-field {
	min-width: 12rem;
}

.gi-ticket-list-header__date-field-select {
	min-height: 2.7rem;
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