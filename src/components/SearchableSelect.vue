<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import type { SearchableSelectOption } from '@/types'

const props = withDefaults(defineProps<{
	modelValue?: string | number | null
	options: SearchableSelectOption[]
	placeholder?: string
	searchPlaceholder?: string
	emptyLabel?: string
	clearable?: boolean
	disabled?: boolean
}>(), {
	modelValue: null,
	placeholder: 'Selecciona',
	searchPlaceholder: 'Buscar...',
	emptyLabel: 'Sin resultados',
	clearable: false,
	disabled: false,
})

const emit = defineEmits<{
	(e: 'update:modelValue', value: string | number | null): void
	(e: 'change', value: string | number | null): void
}>()

const rootRef = ref<HTMLElement | null>(null)
const searchInputRef = ref<HTMLInputElement | null>(null)
const open = ref(false)
const query = ref('')

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
const filteredOptions = computed(() => {
	const term = query.value.trim().toLowerCase()
	if (!term) {
		return safeOptions.value
	}

	return safeOptions.value.filter((option: SearchableSelectOption) => `${option.label} ${option.searchText ?? ''}`.toLowerCase().includes(term))
})

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
			<span class="gi-search-select__trigger-text" :class="{ 'gi-search-select__trigger-text--placeholder': !selectedOption }">
				{{ selectedOption?.label ?? placeholder }}
			</span>
			<span class="gi-search-select__trigger-icon">▾</span>
		</button>

		<div v-if="open" class="gi-search-select__panel">
			<input ref="searchInputRef" v-model="query" class="gi-input gi-search-select__search" :placeholder="searchPlaceholder" />
			<button v-if="clearable && normalizedModelValue" class="gi-search-select__clear" type="button" @click="selectOption(null)">
				Limpiar seleccion
			</button>
			<div class="gi-search-select__options">
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
	border: 1px solid rgba(67, 104, 99, .22);
	border-radius: 12px;
	background: rgba(255, 255, 255, .96);
	color: var(--color-main-text, #222);
	font: inherit;
	font-weight: 400;
	text-align: left;
	cursor: pointer;
	box-sizing: border-box;
}

.gi-search-select__trigger:focus-visible {
	outline: 2px solid rgba(46, 118, 108, .22);
	border-color: rgba(46, 118, 108, .45);
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
	color: #6a7b75;
}

.gi-search-select__trigger-icon {
	flex: none;
	font-size: .8rem;
	color: #5c6f68;
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
	background: rgba(255, 255, 255, .99);
	border: 1px solid rgba(49, 96, 91, .14);
	box-shadow: 0 18px 42px rgba(34, 62, 55, .14);
}

.gi-search-select__search {
	font-weight: 400;
	min-height: 2.45rem;
	padding: .58rem .75rem;
}

.gi-search-select__clear {
	border: none;
	background: rgba(49, 96, 91, .08);
	border-radius: 10px;
	padding: .45rem .6rem;
	font: inherit;
	font-weight: 400;
	text-align: left;
	cursor: pointer;
	color: #214f45;
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
	color: #22342f;
}

.gi-search-select__option:hover,
.gi-search-select__option--active {
	background: rgba(49, 96, 91, .1);
	color: #214f45;
}

.gi-search-select__option--disabled {
	opacity: .5;
	cursor: not-allowed;
}

.gi-search-select__empty {
	color: #697b75;
	background: rgba(49, 96, 91, .04);
}

.gi-search-select--disabled .gi-search-select__trigger {
	opacity: .65;
	cursor: not-allowed;
}
</style>