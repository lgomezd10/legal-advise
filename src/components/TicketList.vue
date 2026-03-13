<script setup lang="ts">
import type { Ticket } from '@/types'

defineProps<{
	tickets: Ticket[]
	emptyLabel: string
}>()

const emit = defineEmits<{
	(e: 'open', id: number): void
}>()

function formatDate(timestamp: number) {
	return new Date(timestamp * 1000).toLocaleString()
}
</script>

<template>
	<div class="gi-list">
		<button v-for="ticket in tickets" :key="ticket.id" class="gi-ticket-card" @click="emit('open', ticket.id)">
			<div class="gi-ticket-card__header">
				<strong>{{ ticket.number }}</strong>
				<span class="gi-badge">{{ ticket.status }}</span>
			</div>
			<h3>{{ ticket.title }}</h3>
			<p>{{ ticket.userDescription }}</p>
			<div class="gi-ticket-card__meta">
				<span>{{ formatDate(ticket.createdAt) }}</span>
				<span>{{ ticket.province || 'Sin provincia' }}</span>
				<span>{{ ticket.city || 'Sin ciudad' }}</span>
				<span v-if="ticket.assignedUserUid || ticket.assignedGroupId">{{ ticket.assignedUserUid || ticket.assignedGroupId }}</span>
			</div>
		</button>
		<div v-if="tickets.length === 0" class="gi-empty-state">
			<p>{{ emptyLabel }}</p>
		</div>
	</div>
</template>

<style scoped>
.gi-list {
	display: grid;
	gap: .85rem;
}

.gi-ticket-card {
	padding: 1rem 1.1rem;
	border: 1px solid rgba(49, 96, 91, .14);
	border-radius: 16px;
	background: linear-gradient(180deg, rgba(250, 251, 249, .96), rgba(240, 245, 242, .92));
	text-align: left;
	cursor: pointer;
	box-shadow: 0 12px 28px rgba(34, 62, 55, .06);
}

.gi-ticket-card__header,
.gi-ticket-card__meta {
	display: flex;
	justify-content: space-between;
	gap: 1rem;
}

.gi-ticket-card p {
	color: var(--color-text-maxcontrast);
	display: -webkit-box;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
}

.gi-ticket-card h3 {
	margin: .7rem 0 .45rem;
}

.gi-ticket-card__meta {
	flex-wrap: wrap;
	color: #61746d;
	font-size: .84rem;
	margin-top: .8rem;
}
</style>