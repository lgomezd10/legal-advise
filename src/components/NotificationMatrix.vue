<script setup lang="ts">
import type { NotificationMatrixItem } from '@/types'

const props = defineProps<{
	items: NotificationMatrixItem[]
}>()

const emit = defineEmits<{
	(e: 'toggle', items: NotificationMatrixItem[]): void
}>()

function toggle(index: number | string, event: Event) {
	const input = event.target as HTMLInputElement
	const normalizedIndex = Number(index)
	const next = props.items.map((item: NotificationMatrixItem, currentIndex: number) => currentIndex === normalizedIndex ? { ...item, enabled: input.checked } : item)
	emit('toggle', next)
}
</script>

<template>
	<div class="gi-matrix">
		<div v-for="(item, index) in props.items" :key="`${item.scopeId}-${item.eventName}-${item.channel}`" class="gi-matrix-row">
			<span>{{ item.scopeId }} · {{ item.eventName }}</span>
			<span>{{ item.channel }}</span>
			<label><input type="checkbox" :checked="Boolean(item.enabled)" @change="toggle(index, $event)" /> {{ item.enabled ? 'Activo' : 'Desactivado' }}</label>
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
	grid-template-columns: 2fr 1fr 1fr;
	gap: .75rem;
	padding: .75rem;
	border-radius: 12px;
	background: rgba(11, 110, 79, .05);
}
</style>