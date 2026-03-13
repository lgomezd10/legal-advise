<script setup lang="ts">
import { computed } from 'vue'
import type { SearchableSelectOption, TypeNode } from '@/types'
import SearchableSelect from './SearchableSelect.vue'

const props = defineProps<{
	types: TypeNode[]
	modelValue: number[]
}>()

const emit = defineEmits<{
	(e: 'update:modelValue', value: number[]): void
}>()

const levels = computed<TypeNode[][]>(() => {
	const result: TypeNode[][] = []
	let options = props.types
	let level = 0
	while (options.length > 0) {
		result[level] = options
		const selectedId = props.modelValue[level]
		const selected = options.find((item: TypeNode) => item.id === selectedId)
		options = selected?.children ?? []
		level++
	}
	return result
})

function updateLevel(index: number | string, rawValue: string | number | null) {
	const value = Number(rawValue ?? '')
	const normalizedIndex = Number(index)
	const next = props.modelValue.slice(0, normalizedIndex)
	if (value > 0) {
		next[normalizedIndex] = value
	}
	emit('update:modelValue', next)
}

function optionsForLevel(options: TypeNode[]): SearchableSelectOption[] {
	return options.map((option) => ({
		value: option.id,
		label: option.name,
	}))
}
</script>

<template>
	<div class="gi-type-grid">
		<label v-for="(options, index) in levels" :key="index" class="gi-field">
			<span>Tipo {{ index + 1 }}</span>
			<SearchableSelect
				:model-value="props.modelValue[index] ?? null"
				:options="optionsForLevel(options)"
				placeholder="Selecciona"
				clearable
				@update:modelValue="updateLevel(index, $event)"
			/>
		</label>
	</div>
</template>

<style scoped>
.gi-type-grid {
	display: grid;
	gap: 1rem;
	grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
}
</style>