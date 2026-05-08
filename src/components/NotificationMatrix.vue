<script setup lang="ts">
import type { NotificationMatrixItem } from '@/types'

const props = defineProps<{
	items: NotificationMatrixItem[]
	scopeLabels?: Record<string, string>
	deliveryOptions?: Array<{ value: NotificationMatrixItem['deliveryMode'], label: string }>
	}>()

const deliveryOptions = props.deliveryOptions ?? [
	{ value: 'none', label: 'Ninguna' },
	{ value: 'nextcloud', label: 'Nextcloud' },
	{ value: 'both', label: 'Nextcloud y correo' },
]

const emit = defineEmits<{
	(e: 'toggle', items: NotificationMatrixItem[]): void
}>()

function updateDeliveryMode(index: number | string, event: Event) {
	const select = event.target as HTMLSelectElement
	const normalizedIndex = Number(index)
	const next = props.items.map((item: NotificationMatrixItem, currentIndex: number) => currentIndex === normalizedIndex
		? { ...item, deliveryMode: normalizeDeliveryMode(select.value) }
		: item)
	emit('toggle', next)
}

function normalizeDeliveryMode(value: string): NotificationMatrixItem['deliveryMode'] {
	if (value === 'none' || value === 'nextcloud' || value === 'both') {
		return value
	}

	return 'nextcloud'
}

function getEventLabel(eventName: string) {
	return {
		ticket_created: 'Creación del ticket',
		ticket_unassigned_created: 'Ticket nuevo sin asignación',
		ticket_assigned: 'Asignación del ticket a mi',
		ticket_waiting_for_creator: 'Pendiente de mi respuesta',
		ticket_group_assigned: 'Asignación a uno de mis grupos',
		ticket_status_changed: 'Cambio de estado',
		ticket_resolved: 'Cierre o resolución',
		ticket_public_reply: 'Comentario público añadido',
	}[eventName] ?? eventName
}

function getScopeLabel(scopeId: string) {
	return props.scopeLabels?.[scopeId] ?? scopeId
}
</script>

<template>
	<div class="gi-matrix">
		<div v-for="(item, index) in props.items" :key="`${item.scopeId}-${item.eventName}`" class="gi-matrix-row">
			<div>
				<strong>{{ getEventLabel(item.eventName) }}</strong>
				<p class="gi-matrix-row__meta">{{ getScopeLabel(item.scopeId) }}</p>
			</div>
			<label class="gi-matrix-row__select">
				<span>Canal</span>
				<select :id="`notification-mode-${index}`" :name="`notification-mode-${item.scopeId}-${item.eventName}`" :value="item.deliveryMode" @change="updateDeliveryMode(index, $event)">
					<option v-for="option in deliveryOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
				</select>
			</label>
		</div>
	</div>
</template>

<style scoped>
.gi-matrix {
	display: grid;
	gap: .65rem;
}

.gi-matrix-row {
	display: grid;
	grid-template-columns: minmax(0, 2fr) minmax(14rem, 18rem);
	gap: .75rem;
	padding: .75rem;
	border-radius: 12px;
	background: rgba(11, 110, 79, .05);
	align-items: center;
}

.gi-matrix-row__meta {
	margin: .25rem 0 0;
	color: var(--color-text-maxcontrast, #5b5b5b);
	font-size: .9rem;
}

.gi-matrix-row__select {
	display: grid;
	gap: .35rem;
}

.gi-matrix-row__select select {
	width: 100%;
	min-height: 2.5rem;
	padding: .45rem .7rem;
	border-radius: 10px;
	border: 1px solid var(--color-border, #d0d7de);
	background: var(--color-main-background, #fff);
	color: var(--color-main-text, #222);
}

@media (max-width: 720px) {
	.gi-matrix-row {
		grid-template-columns: 1fr;
	}
}
</style>