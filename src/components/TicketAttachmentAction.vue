<script setup lang="ts">
import { computed, ref } from 'vue'

const props = withDefaults(defineProps<{
	label?: string
	allowedExtensions?: string[]
	maxFileSizeMb?: number
}>(), {
	label: 'Adjuntar archivo',
	allowedExtensions: () => [],
	maxFileSizeMb: 25,
})

const emit = defineEmits<{
	(e: 'action'): void
}>()

const infoOpen = ref(false)
const allowedExtensionsLabel = computed(() => (props.allowedExtensions ?? [])
	.map((extension: string) => extension.trim().toLowerCase())
	.filter((extension: string) => extension !== '')
	.map((extension: string) => `.${extension}`)
	.join(', '))
const infoText = computed(() => {
	const base = `Tamano maximo del archivo: ${props.maxFileSizeMb} MB.`
	const extensions = allowedExtensionsLabel.value ? ` Tipos permitidos: ${allowedExtensionsLabel.value}.` : ''
	const fallback = ' Si lo supera, podras adjuntar una URL desde el aviso.'
	return `${base}${extensions}${fallback}`
})
</script>

<template>
	<div class="gi-ticket-attachment-action">
		<button class="gi-ticket-attachment-action__text" type="button" @click="emit('action')">{{ label }}</button>
		<div class="gi-ticket-attachment-action__info-wrap">
			<button class="gi-round-icon-button gi-ticket-attachment-action__info-button" type="button" aria-label="Ver informacion de adjuntos" :aria-expanded="infoOpen" @click="infoOpen = !infoOpen">
				<svg viewBox="0 0 20 20" aria-hidden="true">
					<path d="M10 1.5a8.5 8.5 0 1 0 0 17a8.5 8.5 0 0 0 0-17Zm0 12.3a1 1 0 1 1 0 2a1 1 0 0 1 0-2Zm1.2-2.7c-.65.42-.8.7-.8 1.2v.25H9v-.35c0-1.02.43-1.66 1.24-2.18c.73-.47 1.09-.81 1.09-1.43c0-.75-.6-1.2-1.46-1.2c-.84 0-1.49.34-2.07.95L6.9 7.3c.74-.9 1.8-1.45 3.23-1.45c1.77 0 3 .99 3 2.5c0 1.24-.7 1.93-1.93 2.75Z" fill="currentColor" />
				</svg>
			</button>
			<div v-if="infoOpen" class="gi-ticket-attachment-action__popover">{{ infoText }}</div>
		</div>
	</div>
</template>

<style scoped>
.gi-ticket-attachment-action {
	display: inline-flex;
	align-items: center;
	gap: .35rem;
	min-width: 0;
}

.gi-ticket-attachment-action__text {
	border: 0;
	background: transparent;
	padding: 0;
	font: inherit;
	font-weight: 600;
	color: #50635c;
	cursor: pointer;
	line-height: 1.2;
}

.gi-ticket-attachment-action__text:hover,
.gi-ticket-attachment-action__text:focus-visible {
	color: #244338;
	text-decoration: underline;
	text-underline-offset: .16em;
}

.gi-ticket-attachment-action__info-wrap {
	position: relative;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex: 0 0 auto;
}

.gi-ticket-attachment-action__info-button {
	width: 1.65rem;
	height: 1.65rem;
	min-width: 1.65rem;
	min-height: 1.65rem;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	padding: 0;
	vertical-align: middle;
}

.gi-ticket-attachment-action__info-button svg {
	width: .95rem;
	height: .95rem;
}

.gi-ticket-attachment-action__popover {
	position: absolute;
	top: calc(100% + .45rem);
	right: 0;
	min-width: 16rem;
	max-width: min(24rem, calc(100vw - 2rem));
	padding: .7rem .8rem;
	border: 1px solid rgba(49, 96, 91, .12);
	border-radius: 14px;
	background: rgba(255, 255, 255, .98);
	box-shadow: 0 18px 40px rgba(20, 34, 30, .16);
	color: #435852;
	z-index: 5;
	line-height: 1.4;
}
</style>