<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import type { SearchableSelectOption } from '@/types'

let searchableSelectIdSequence = 0

const props = withDefaults(defineProps<{
	modelValue?: string | number | null
	options: SearchableSelectOption[]
	placeholder?: string
	searchPlaceholder?: string
	emptyLabel?: string
	clearable?: boolean
	disabled?: boolean
	allowCreate?: boolean
	createLabel?: string
	inputId?: string
	inputName?: string
}>(), {
	modelValue: null,
	placeholder: 'Selecciona',
	searchPlaceholder: 'Buscar...',
	emptyLabel: 'Sin resultados',
	clearable: false,
	disabled: false,
	allowCreate: false,
	createLabel: 'Añadir',
	inputId: undefined,
	inputName: undefined,
})

const emit = defineEmits<{
	(e: 'update:modelValue', value: string | number | null): void
	(e: 'change', value: string | number | null): void
}>()

const rootRef = ref<HTMLElement | null>(null)
const searchInputRef = ref<HTMLInputElement | null>(null)
const open = ref(false)
const query = ref('')
const instanceId = `gi-search-select-${++searchableSelectIdSequence}`

function normalizeOptions(options: SearchableSelectOption[] | Record<string, SearchableSelectOption> | undefined | null): SearchableSelectOption[] {
	if (Array.isArray(options)) {
		return options
	}

	if (options && typeof options === 'object') {
		return Object.values(options)
	}

	return []
}

const safeOptions = computed(() => normalizeOptions(props.options))
const normalizedModelValue = computed(() => props.modelValue === null || props.modelValue === undefined ? '' : String(props.modelValue))
const selectedOption = computed(() => safeOptions.value.find((option: SearchableSelectOption) => String(option.value) === normalizedModelValue.value) ?? null)
const triggerLabel = computed(() => {
	if (selectedOption.value) {
		return selectedOption.value.label
	}

	if (props.allowCreate && normalizedModelValue.value.trim() !== '') {
		return normalizedModelValue.value
	}

	return props.placeholder
})
const filteredOptions = computed(() => {
	const term = query.value.trim().toLowerCase()
	if (!term) {
		return safeOptions.value
	}

	return safeOptions.value.filter((option: SearchableSelectOption) => `${option.label} ${option.searchText ?? ''}`.toLowerCase().includes(term))
})

const canCreateOption = computed(() => {
	if (!props.allowCreate) {
		return false
	}

	const trimmed = query.value.trim()
	if (trimmed === '') {
		return false
	}

	return !safeOptions.value.some((option: SearchableSelectOption) => option.label.trim().toLowerCase() === trimmed.toLowerCase())
})
const searchInputId = computed(() => props.inputId ?? `${instanceId}-search`)
const searchInputName = computed(() => props.inputName ?? searchInputId.value)

function closeDropdown() {
	open.value = false
	query.value = ''
}

async function openDropdown() {
	if (props.disabled) {
		return
	}

	open.value = true
	query.value = ''
	await nextTick()
	searchInputRef.value?.focus()
}

function toggleDropdown() {
	if (open.value) {
		closeDropdown()
		return
	}
	void openDropdown()
}

function selectOption(value: string | number | null) {
	emit('update:modelValue', value)
	emit('change', value)
	closeDropdown()
}

function handleDocumentPointerDown(event: Event) {
	if (!(event.target instanceof Node)) {
		return
	}

	if (rootRef.value?.contains(event.target)) {
		return
	}

	closeDropdown()
}

onMounted(() => {
	document.addEventListener('pointerdown', handleDocumentPointerDown)
})

onBeforeUnmount(() => {
	document.removeEventListener('pointerdown', handleDocumentPointerDown)
})
</script>

<template>
	<div ref="rootRef" class="gi-search-select" :class="{ 'gi-search-select--open': open, 'gi-search-select--disabled': disabled }">
		<button class="gi-search-select__trigger" type="button" :disabled="disabled" @click="toggleDropdown">
			<span class="gi-search-select__trigger-text" :class="{ 'gi-search-select__trigger-text--placeholder': !selectedOption && !(allowCreate && normalizedModelValue.trim() !== '') }">
				{{ triggerLabel }}
			</span>
			<span class="gi-search-select__trigger-icon">▾</span>
		</button>

		<div v-if="open" class="gi-search-select__panel">
			<input ref="searchInputRef" v-model="query" :id="searchInputId" :name="searchInputName" class="gi-input gi-search-select__search" :placeholder="searchPlaceholder" />
			<button v-if="clearable && normalizedModelValue" class="gi-search-select__clear" type="button" @click="selectOption(null)">
				Limpiar seleccion
			</button>
			<div class="gi-search-select__options">
				<button v-if="canCreateOption" class="gi-search-select__option gi-search-select__option--create" type="button" @click="selectOption(query.trim())">
					{{ createLabel }} "{{ query.trim() }}"
				</button>
				<button
					v-for="option in filteredOptions"
					:key="`${option.value}`"
					class="gi-search-select__option"
					:class="{ 'gi-search-select__option--active': String(option.value) === normalizedModelValue, 'gi-search-select__option--disabled': option.disabled }"
					type="button"
					:disabled="option.disabled"
					@click="selectOption(option.value)">
					{{ option.label }}
				</button>
				<div v-if="filteredOptions.length === 0" class="gi-search-select__empty">{{ emptyLabel }}</div>
			</div>
		</div>
	</div>
</template>

<style scoped>
.gi-search-select {
	position: relative;
	min-width: 0;
}

.gi-search-select__trigger {
	width: 100%;
	min-height: 2.7rem;
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: .75rem;
	padding: .68rem .85rem;
	border: 1px solid var(--gi-color-border-strong);
	border-radius: 12px;
	background: var(--gi-color-surface);
	color: var(--gi-color-text);
	font: inherit;
	font-weight: 400;
	text-align: left;
	cursor: pointer;
	box-sizing: border-box;
}

.gi-search-select__trigger:focus-visible {
	outline: 2px solid var(--gi-color-primary-soft);
	border-color: var(--gi-color-primary);
}

.gi-search-select__trigger-text {
	flex: 1;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	font-weight: 400;
}

.gi-search-select__trigger-text--placeholder {
	color: var(--gi-color-text-muted);
}

.gi-search-select__trigger-icon {
	flex: none;
	font-size: .8rem;
	color: var(--gi-color-text-muted);
}

.gi-search-select--compact .gi-search-select__trigger {
	min-height: 2.2rem;
	padding: .42rem .65rem;
	border-radius: 10px;
	font-size: .86rem;
	gap: .5rem;
}

.gi-search-select--compact .gi-search-select__trigger-icon {
	font-size: .72rem;
}

.gi-search-select--compact .gi-search-select__panel {
	top: calc(100% + .25rem);
	padding: .55rem;
	border-radius: 14px;
}

.gi-search-select__panel {
	position: absolute;
	left: 0;
	right: 0;
	top: calc(100% + .35rem);
	z-index: 20;
	display: grid;
	gap: .55rem;
	padding: .65rem;
	border-radius: 16px;
	background: var(--gi-color-surface-plain);
	border: 1px solid var(--gi-color-border-strong);
	box-shadow: 0 18px 42px var(--gi-color-shadow-medium);
}

.gi-search-select__search {
	font-weight: 400;
	min-height: 2.45rem;
	padding: .58rem .75rem;
}

.gi-search-select__clear {
	border: none;
	background: var(--gi-color-primary-soft);
	border-radius: 10px;
	padding: .45rem .6rem;
	font: inherit;
	font-weight: 400;
	text-align: left;
	cursor: pointer;
	color: var(--gi-color-primary-soft-text);
}

.gi-search-select__options {
	max-height: 15rem;
	overflow: auto;
	display: grid;
	gap: .3rem;
}

.gi-search-select__option,
.gi-search-select__empty {
	padding: .55rem .65rem;
	border-radius: 10px;
	font: inherit;
	font-weight: 400;
	text-align: left;
	line-height: 1.3;
}

.gi-search-select__option {
	border: none;
	background: transparent;
	cursor: pointer;
	color: var(--gi-color-text);
}

.gi-search-select__option:hover,
.gi-search-select__option--active {
	background: var(--gi-color-primary-soft);
	color: var(--gi-color-primary-soft-text);
}

.gi-search-select__option--create {
	font-weight: 600;
	border: 1px dashed var(--gi-color-border-strong);
	background: var(--gi-color-primary-soft-hover);
}

.gi-search-select__option--disabled {
	opacity: .5;
	cursor: not-allowed;
}

.gi-search-select__empty {
	color: var(--gi-color-text-muted);
	background: var(--gi-color-surface-subtle);
}

.gi-search-select--disabled .gi-search-select__trigger {
	opacity: .65;
	cursor: not-allowed;
}
</style>