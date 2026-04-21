<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import type { TicketAttachmentLinkDraft } from '@/types'

type AttachmentDraft = {
	files: File[]
	links: TicketAttachmentLinkDraft[]
}

const props = withDefaults(defineProps<{
	modelValue?: AttachmentDraft | null
	allowedExtensions?: string[]
	maxFileSizeMb?: number
}>(), {
	modelValue: null,
	allowedExtensions: () => [],
	maxFileSizeMb: 25,
})

const emit = defineEmits<{
	(e: 'update:modelValue', value: AttachmentDraft): void
}>()

const files = ref<File[]>([])
const links = ref<TicketAttachmentLinkDraft[]>([])
const errorMessage = ref('')
const oversizeModalOpen = ref(false)
const oversizeFileName = ref('')
const urlModalOpen = ref(false)
const urlDraft = ref<TicketAttachmentLinkDraft>({ url: '', label: '' })
const urlError = ref('')
const extensionsInfoOpen = ref(false)
const fileInputRef = ref<HTMLInputElement | null>(null)
let pendingFocusHandler: (() => void) | null = null

const normalizedAllowedExtensions = computed(() => (props.allowedExtensions ?? []).map((extension: string) => extension.trim().toLowerCase()).filter((extension: string) => extension !== ''))
const allowedExtensionsAccept = computed(() => normalizedAllowedExtensions.value.map((extension: string) => `.${extension}`).join(','))
const allowedExtensionsLabel = computed(() => normalizedAllowedExtensions.value.map((extension: string) => `.${extension}`).join(', '))
const maxFileSizeBytes = computed(() => Math.max(1, props.maxFileSizeMb) * 1024 * 1024)

watch(() => props.modelValue, (value) => {
	files.value = [...(value?.files ?? [])]
	links.value = [...(value?.links ?? [])]
}, { immediate: true, deep: true })

function emitValue() {
	emit('update:modelValue', { files: [...files.value], links: [...links.value] })
}

function refreshViewportAfterDialog() {
	window.requestAnimationFrame(() => {
		window.dispatchEvent(new Event('resize'))
	})
}

function clearPendingFocusHandler() {
	if (!pendingFocusHandler) {
		return
	}

	window.removeEventListener('focus', pendingFocusHandler)
	pendingFocusHandler = null
}

function openFileDialog() {
	clearPendingFocusHandler()
	pendingFocusHandler = () => {
		clearPendingFocusHandler()
		refreshViewportAfterDialog()
	}

	window.addEventListener('focus', pendingFocusHandler)
	fileInputRef.value?.click()
}

function onFileChange(event: Event) {
	const input = event.target as HTMLInputElement
	const nextFiles = Array.from(input.files ?? [])
	refreshViewportAfterDialog()
	if (nextFiles.length === 0) {
		return
	}

	const invalidFile = nextFiles.find((file) => !normalizedAllowedExtensions.value.includes(file.name.split('.').pop()?.toLowerCase() ?? ''))
	if (invalidFile) {
		errorMessage.value = `La extension de ${invalidFile.name} no esta permitida.`
		input.value = ''
		return
	}

	const oversizeFile = nextFiles.find((file) => file.size > maxFileSizeBytes.value)
	if (oversizeFile) {
		oversizeFileName.value = oversizeFile.name
		oversizeModalOpen.value = true
		errorMessage.value = ''
		input.value = ''
		return
	}

	const knownKeys = new Set(files.value.map((file) => `${file.name}:${file.size}:${file.lastModified}`))
	for (const file of nextFiles) {
		const key = `${file.name}:${file.size}:${file.lastModified}`
		if (!knownKeys.has(key)) {
			files.value.push(file)
			knownKeys.add(key)
		}
	}

	errorMessage.value = ''
	input.value = ''
	emitValue()
}

onBeforeUnmount(() => {
	clearPendingFocusHandler()
})

function removeFile(index: number) {
	files.value.splice(index, 1)
	emitValue()
}

function removeLink(index: number) {
	links.value.splice(index, 1)
	emitValue()
}

function closeOversizeModal() {
	oversizeModalOpen.value = false
	oversizeFileName.value = ''
}

function openUrlModal() {
	urlDraft.value = { url: '', label: '' }
	urlError.value = ''
	urlModalOpen.value = true
	closeOversizeModal()
}

function closeUrlModal() {
	urlModalOpen.value = false
	urlError.value = ''
	urlDraft.value = { url: '', label: '' }
}

function saveUrl() {
	const normalizedUrl = urlDraft.value.url.trim()
	if (normalizedUrl === '') {
		urlError.value = 'Debes indicar una ruta URL.'
		return
	}

	try {
		new URL(normalizedUrl)
	} catch {
		urlError.value = 'La ruta URL no es valida.'
		return
	}

	links.value.push({
		url: normalizedUrl,
		label: urlDraft.value.label.trim() || normalizedUrl,
	})
	closeUrlModal()
	emitValue()
	}
</script>

<template>
	<div class="gi-attachment-picker">
		<div class="gi-attachment-picker__toolbar">
			<button class="gi-secondary-button gi-attachment-picker__trigger" type="button" @click="openFileDialog">Adjuntar archivos</button>
			<input ref="fileInputRef" class="gi-attachment-picker__input" type="file" multiple :accept="allowedExtensionsAccept" @change="onFileChange" />
			<button class="gi-secondary-button" type="button" @click="openUrlModal">Adjuntar URL</button>
			<div v-if="allowedExtensionsLabel" class="gi-attachment-picker__helper-info">
				<button class="gi-round-icon-button gi-attachment-picker__helper-button" type="button" aria-label="Ver tipos de archivo permitidos" :aria-expanded="extensionsInfoOpen" @click="extensionsInfoOpen = !extensionsInfoOpen">
					<svg viewBox="0 0 20 20" aria-hidden="true">
						<path d="M10 1.5a8.5 8.5 0 1 0 0 17a8.5 8.5 0 0 0 0-17Zm0 12.3a1 1 0 1 1 0 2a1 1 0 0 1 0-2Zm1.2-2.7c-.65.42-.8.7-.8 1.2v.25H9v-.35c0-1.02.43-1.66 1.24-2.18c.73-.47 1.09-.81 1.09-1.43c0-.75-.6-1.2-1.46-1.2c-.84 0-1.49.34-2.07.95L6.9 7.3c.74-.9 1.8-1.45 3.23-1.45c1.77 0 3 .99 3 2.5c0 1.24-.7 1.93-1.93 2.75Z" />
					</svg>
				</button>
				<div v-if="extensionsInfoOpen" class="gi-attachment-picker__helper-popover">
					<strong>Tipos permitidos</strong>
					<span>{{ allowedExtensionsLabel }}</span>
				</div>
			</div>
			<span class="gi-attachment-picker__helper">Maximo: {{ maxFileSizeMb }} MB</span>
		</div>

		<ul v-if="files.length || links.length" class="gi-attachment-picker__list">
			<li v-for="(file, index) in files" :key="`${file.name}-${file.size}-${file.lastModified}`" class="gi-row-card gi-attachment-picker__item">
				<span>{{ file.name }}</span>
				<button class="gi-tertiary-button" type="button" @click="removeFile(index)">Quitar</button>
			</li>
			<li v-for="(link, index) in links" :key="`${link.url}-${index}`" class="gi-row-card gi-attachment-picker__item">
				<span>{{ link.label }}</span>
				<button class="gi-tertiary-button" type="button" @click="removeLink(index)">Quitar</button>
			</li>
		</ul>

		<p v-if="errorMessage" class="gi-form-error">{{ errorMessage }}</p>

		<div v-if="oversizeModalOpen" class="gi-app-dialog-backdrop gi-dialog-backdrop" @click.self="closeOversizeModal">
			<section class="gi-app-dialog gi-dialog gi-dialog--compact" aria-label="Archivo demasiado grande">
				<header class="gi-dialog__header">
					<h3 class="gi-dialog__title">Archivo demasiado grande</h3>
					<button class="gi-modal-close" type="button" aria-label="Cerrar ventana" @click="closeOversizeModal">x</button>
				</header>
				<p class="gi-dialog__message gi-dialog__message--neutral">
					{{ oversizeFileName }} supera el tamano maximo configurado. Para videos grandes, adjunta la ruta web del archivo.
				</p>
				<footer class="gi-dialog__footer">
					<button class="gi-ghost-button" type="button" @click="closeOversizeModal">Cancelar</button>
					<button class="gi-primary-button" type="button" @click="openUrlModal">Adjuntar URL</button>
				</footer>
			</section>
		</div>

		<div v-if="urlModalOpen" class="gi-app-dialog-backdrop gi-dialog-backdrop" @click.self="closeUrlModal">
			<section class="gi-app-dialog gi-dialog gi-dialog--compact" aria-label="Adjuntar URL">
				<header class="gi-dialog__header">
					<h3 class="gi-dialog__title">Adjuntar URL</h3>
					<button class="gi-modal-close" type="button" aria-label="Cerrar ventana" @click="closeUrlModal">x</button>
				</header>
				<label class="gi-field">
					<span>Ruta URL</span>
					<input v-model="urlDraft.url" class="gi-input" placeholder="https://..." />
				</label>
				<label class="gi-field">
					<span>Nombre visible</span>
					<input v-model="urlDraft.label" class="gi-input" placeholder="Video reunion.mp4" />
				</label>
				<p v-if="urlError" class="gi-form-error">{{ urlError }}</p>
				<footer class="gi-dialog__footer">
					<button class="gi-ghost-button" type="button" @click="closeUrlModal">Cancelar</button>
					<button class="gi-primary-button" type="button" @click="saveUrl">Guardar URL</button>
				</footer>
			</section>
		</div>
	</div>
</template>

<style scoped>
.gi-attachment-picker {
	display: grid;
	gap: .75rem;
	min-width: 0;
}

.gi-attachment-picker__toolbar {
	gap: .65rem;
	align-items: center;
	min-width: 0;
}

.gi-attachment-picker__trigger {
	cursor: pointer;
}

.gi-attachment-picker__input {
	position: absolute;
	width: 1px;
	height: 1px;
	opacity: 0;
	pointer-events: none;
}

.gi-attachment-picker__helper {
	color: #5f726b;
	font-size: .9rem;
}

.gi-attachment-picker__helper-info {
	position: relative;
	display: inline-flex;
	align-items: center;
}

.gi-attachment-picker__helper-popover {
	position: absolute;
	top: calc(100% + .45rem);
	left: 0;
	min-width: 16rem;
	max-width: min(28rem, calc(100vw - 2rem));
	display: grid;
	gap: .25rem;
	padding: .7rem .8rem;
	border: 1px solid rgba(49, 96, 91, .12);
	border-radius: 14px;
	background: rgba(255, 255, 255, .98);
	box-shadow: 0 18px 40px rgba(20, 34, 30, .16);
	color: #435852;
	z-index: 5;
}

.gi-attachment-picker__list {
	list-style: none;
	padding: 0;
	margin: 0;
	display: grid;
	gap: .45rem;
}

.gi-attachment-picker__item span {
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

</style>