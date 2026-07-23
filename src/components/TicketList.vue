<script setup lang="ts">
import { computed } from 'vue'
import type { AssignableOption, StatusOption, Ticket, TypeNode } from '@/types'
import { getTypeLabel } from '@/services/ticketDraft'
import { formatDateTime, getStatusLabel } from '@/utils/formatting'
import { excerptRichText } from '@/utils/richText'


const props = defineProps<{
	tickets: Ticket[]
	emptyLabel: string
	statuses?: StatusOption[]
	types?: TypeNode[]
	users?: AssignableOption[]
	groups?: AssignableOption[]
}>()

const emit = defineEmits<{
	(e: 'open', id: number): void
}>()

const safeStatuses = computed<StatusOption[]>(() => props.statuses ?? [])
const safeTypes = computed<TypeNode[]>(() => props.types ?? [])

function resolveStatusLabel(statusId: string) {
	return getStatusLabel(statusId, safeStatuses.value)
}

function resolveDescription(value: string) {
	return excerptRichText(value, 180)
}

function resolveTypeLabel(typeId?: number | null) {
	return getTypeLabel(safeTypes.value, typeId) || 'Sin tipo'
}

</script>

<template>
	<div class="gi-list">
		<button v-for="ticket in tickets" :key="ticket.id" class="gi-ticket-card" :class="{ 'gi-ticket-card--waiting': ticket.status === 'en_espera_usuario' }" @click="emit('open', ticket.id)">
			<div class="gi-ticket-card__header">
				<h3 class="gi-ticket-card__title">{{ ticket.title }}</h3>
				<strong class="gi-ticket-card__number">{{ ticket.number }}</strong>
			</div>
			<div class="gi-ticket-card__meta">
				<span>Actualizada {{ formatDateTime(ticket.updatedAt) }}</span>
				<span>Creada {{ formatDateTime(ticket.createdAt) }}</span>
			</div>
			<p class="gi-ticket-card__description">{{ resolveDescription(ticket.userDescription) }}</p>
			<div class="gi-ticket-card__footer">
				<span class="gi-ticket-card__type">{{ resolveTypeLabel(ticket.typeId) }}</span>
				<span class="gi-badge gi-badge--success">{{ resolveStatusLabel(ticket.status) }}</span>
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
	display: grid;
	gap: .55rem;
	padding: 1rem 1.1rem;
	border: 1px solid var(--gi-color-border, rgba(49, 96, 91, .14));
	border-radius: 16px;
	background: var(--gi-color-surface-plain, rgba(250, 251, 249, .96));
	color: var(--gi-color-text, #222222);
	text-align: left;
	cursor: pointer;
	box-shadow: 0 12px 28px rgba(34, 62, 55, .06);
	min-width: 0;
}

.gi-ticket-card--waiting {
	background: linear-gradient(180deg, rgba(235, 248, 238, .98), rgba(221, 241, 226, .94));
	border-color: rgba(55, 128, 82, .24);
	box-shadow: 0 14px 32px rgba(49, 123, 75, .12);
}

.gi-ticket-card__header,
.gi-ticket-card__meta,
.gi-ticket-card__footer {
	display: flex;
	justify-content: space-between;
	gap: 1rem;
}

.gi-ticket-card__header {
	align-items: start;
}

.gi-ticket-card__meta {
	min-width: 0;
	flex-wrap: wrap;
	color: #61746d;
	font-size: .84rem;
}

.gi-ticket-card__description {
	color: var(--color-text-maxcontrast);
	display: -webkit-box;
	line-clamp: 2;
	-webkit-line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
	margin: 0;
}

.gi-ticket-card__title {
	margin: 0;
	font-size: 1rem;
	text-transform: uppercase;
	line-height: 1.2;
	flex: 1 1 auto;
	min-width: 0;
}

.gi-ticket-card__number {
	flex: none;
	white-space: nowrap;
}

.gi-ticket-card__footer {
	justify-content: space-between;
	align-items: center;
	flex-wrap: wrap;
}

.gi-ticket-card__type {
	color: #4f665f;
	font-size: .84rem;
	font-weight: 600;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
</style>