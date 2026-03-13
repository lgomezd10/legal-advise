<script setup lang="ts">
import { computed } from 'vue'
import type { SupportColumnKey, Ticket } from '@/types'

type SupportColumn = {
	key: SupportColumnKey
	label: string
	defaultVisible: boolean
	sticky?: boolean
}

const props = defineProps<{
	tickets: Ticket[]
	emptyLabel: string
	visibleColumns: SupportColumnKey[]
}>()

const emit = defineEmits<{
	(e: 'open', id: number): void
}>()

const columns = computed<SupportColumn[]>(() => {
	const available: SupportColumn[] = [
		{ key: 'number', label: 'Ticket', defaultVisible: true, sticky: true },
		{ key: 'title', label: 'Titulo', defaultVisible: true },
		{ key: 'userDescription', label: 'Descripcion', defaultVisible: true },
		{ key: 'assignment', label: 'Asignacion', defaultVisible: true },
		{ key: 'status', label: 'Estado', defaultVisible: false },
		{ key: 'urgency', label: 'Criticidad', defaultVisible: false },
		{ key: 'createdAt', label: 'Fecha apertura', defaultVisible: false },
	]

	return available.filter((column) => props.visibleColumns.includes(column.key))
})

function formatDate(timestamp?: number | null) {
	if (!timestamp) {
		return 'Sin fecha'
	}
	return new Date(timestamp * 1000).toLocaleString()
}

function formatAssignment(ticket: Ticket) {
	const parts = [ticket.assignedUserUid, ticket.assignedGroupId ? `Grupo ${ticket.assignedGroupId}` : '']
		.filter(Boolean)
		.map(String)
	return parts.length > 0 ? parts.join(' / ') : 'Sin asignar'
}

function excerpt(text: string, maxLength = 140) {
	if (text.length <= maxLength) {
		return text
	}
	return `${text.slice(0, maxLength - 1)}...`
}
</script>

<template>
	<section class="gi-table-shell">
		<div v-if="tickets.length === 0" class="gi-empty-state">
			<p>{{ emptyLabel }}</p>
		</div>
		<div v-else class="gi-table-scroll">
			<table class="gi-support-table">
				<thead>
					<tr>
						<th v-for="column in columns" :key="column.key" :class="{ 'gi-support-table__cell--sticky': column.sticky }">
							{{ column.label }}
						</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="ticket in tickets" :key="ticket.id" class="gi-support-table__row" @click="emit('open', ticket.id)">
						<td v-for="column in columns" :key="column.key" :class="{ 'gi-support-table__cell--sticky': column.sticky }">
							<template v-if="column.key === 'number'">
								<strong>{{ ticket.number }}</strong>
							</template>
							<template v-else-if="column.key === 'title'">
								<div class="gi-support-table__title">{{ ticket.title }}</div>
							</template>
							<template v-else-if="column.key === 'userDescription'">
								<span class="gi-support-table__description">{{ excerpt(ticket.userDescription || '') }}</span>
							</template>
							<template v-else-if="column.key === 'assignment'">
								{{ formatAssignment(ticket) }}
							</template>
							<template v-else-if="column.key === 'status'">
								<span class="gi-badge">{{ ticket.status }}</span>
							</template>
							<template v-else-if="column.key === 'urgency'">
								{{ ticket.urgencyId ?? 'Sin criticidad' }}
							</template>
							<template v-else-if="column.key === 'createdAt'">
								{{ formatDate(ticket.createdAt) }}
							</template>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</section>
</template>

<style scoped>
.gi-table-shell {
	border: 1px solid rgba(49, 96, 91, .12);
	border-radius: 20px;
	background: rgba(255, 255, 255, .94);
	box-shadow: 0 20px 48px rgba(34, 62, 55, .06);
	overflow: hidden;
}

.gi-table-scroll {
	overflow: auto;
	max-width: 100%;
}

.gi-support-table {
	width: 100%;
	min-width: 72rem;
	border-collapse: separate;
	border-spacing: 0;
}

.gi-support-table th,
.gi-support-table td {
	padding: .95rem 1rem;
	text-align: left;
	vertical-align: top;
	border-bottom: 1px solid rgba(49, 96, 91, .08);
	background: rgba(255, 255, 255, .96);
}

.gi-support-table thead th {
	position: sticky;
	top: 0;
	z-index: 1;
	background: rgba(239, 245, 241, .98);
	font-size: .83rem;
	text-transform: uppercase;
	letter-spacing: .04em;
	color: #526861;
}

.gi-support-table__row {
	cursor: pointer;
}

.gi-support-table__row:hover td {
	background: rgba(244, 248, 246, .98);
}

.gi-support-table__cell--sticky {
	position: sticky;
	left: 0;
	z-index: 1;
}

.gi-support-table tbody .gi-support-table__cell--sticky {
	background: rgba(255, 255, 255, .98);
}

.gi-support-table__title {
	font-weight: 600;
	min-width: 12rem;
}

.gi-support-table__description {
	display: inline-block;
	max-width: 36rem;
	color: #596d66;
}

@media (max-width: 900px) {
	.gi-support-table {
		min-width: 56rem;
	}
}
</style>