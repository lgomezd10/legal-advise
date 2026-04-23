<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'

let plainTextEditorIdSequence = 0

const props = withDefaults(defineProps<{
	modelValue?: string | null
	placeholder?: string
	disabled?: boolean
	minHeight?: number
	inputId?: string
}>(), {
	modelValue: '',
	placeholder: '',
	disabled: false,
	minHeight: 160,
	inputId: undefined,
})

const emit = defineEmits<{
	(e: 'update:modelValue', value: string): void
}>()

const instanceId = `gi-plain-text-editor-${++plainTextEditorIdSequence}`
const editorRef = ref<HTMLDivElement | null>(null)
const editorId = computed(() => props.inputId ?? `${instanceId}-surface`)
const editorStyle = computed(() => ({ '--gi-plain-text-editor-min-height': `${props.minHeight}px` }))

function normalizePlainText(value: string | null | undefined) {
	return (value ?? '').replace(/\r\n?/g, '\n')
}

function syncEditorValue() {
	const editor = editorRef.value
	if (!editor) {
		return
	}

	const nextValue = normalizePlainText(props.modelValue)
	if (editor.textContent !== nextValue) {
		editor.textContent = nextValue
	}
}

function onInput() {
	const editor = editorRef.value
	if (!editor) {
		return
	}

	emit('update:modelValue', normalizePlainText(editor.innerText))
}

onMounted(() => {
	syncEditorValue()
})

watch(() => props.modelValue, () => {
	syncEditorValue()
})
</script>

<template>
	<div class="gi-plain-text-editor" :class="{ 'gi-plain-text-editor--disabled': disabled }" :style="editorStyle">
		<div
			:id="editorId"
			ref="editorRef"
			class="gi-plain-text-editor__surface gi-textarea gi-textarea--plain"
			role="textbox"
			aria-multiline="true"
			:aria-disabled="disabled"
			:contenteditable="!disabled"
			:data-placeholder="placeholder"
			spellcheck="true"
			@input="onInput"
		></div>
	</div>
</template>

<style scoped>
.gi-plain-text-editor {
	position: relative;
}

.gi-plain-text-editor__surface {
	min-height: var(--gi-plain-text-editor-min-height);
	white-space: pre-wrap;
	word-break: break-word;
	overflow-wrap: anywhere;
	overflow: auto;
	outline: none;
	box-sizing: border-box;
}

.gi-plain-text-editor__surface:empty::before {
	content: attr(data-placeholder);
	color: var(--color-text-maxcontrast, #6b7280);
	pointer-events: none;
	white-space: normal;
}

.gi-plain-text-editor--disabled .gi-plain-text-editor__surface {
	opacity: .75;
	user-select: none;
}
</style>