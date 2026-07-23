<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'
import type { SearchableSelectOption, TicketAttachmentLinkDraft } from '@/types'
import AttachmentPicker from './AttachmentPicker.vue'
import RichTextEditor from './RichTextEditor.vue'
import SearchableSelect from './SearchableSelect.vue'
import TicketAttachmentAction from './TicketAttachmentAction.vue'

type AttachmentDraft = {
	files: File[]
	links: TicketAttachmentLinkDraft[]
}

const props = withDefaults(defineProps<{
	modelValue?: string
	attachmentsDraft?: AttachmentDraft
	allowedExtensions?: string[]
	maxFileSizeMb?: number
	composerError?: string
	placeholder?: string
	visibility?: 'interno' | 'publico'
	visibilityOptions?: SearchableSelectOption[]
	showVisibility?: boolean
	attachmentsVisible?: boolean
	attachmentsEnabled?: boolean
	dismissible?: boolean
	submitLabel?: string
}>(), {
	modelValue: '',
	attachmentsDraft: () => ({ files: [], links: [] }),
	allowedExtensions: () => [],
	maxFileSizeMb: 25,
	composerError: '',
	placeholder: '',
	visibility: 'publico',
	visibilityOptions: () => [],
	showVisibility: false,
	attachmentsVisible: false,
	attachmentsEnabled: true,
	dismissible: false,
	submitLabel: 'Enviar',
})

const emit = defineEmits<{
	(e: 'update:modelValue', value: string): void
	(e: 'update:attachmentsDraft', value: AttachmentDraft): void
	(e: 'update:visibility', value: 'interno' | 'publico'): void
	(e: 'submit'): void
	(e: 'show-attachments'): void
	(e: 'close'): void
}>()

const attachmentPickerRef = ref<{ openFileDialog?: () => void } | null>(null)
const pendingFileDialogOpen = ref(false)

function flushPendingFileDialogOpen() {
	if (!pendingFileDialogOpen.value || !props.attachmentsVisible) {
		return
	}

	nextTick(() => {
		if (!pendingFileDialogOpen.value || !props.attachmentsVisible || !attachmentPickerRef.value?.openFileDialog) {
			return
		}

		attachmentPickerRef.value.openFileDialog()
		pendingFileDialogOpen.value = false
	})
}

function openFileAttachment() {
	pendingFileDialogOpen.value = true
	emit('show-attachments')
	flushPendingFileDialogOpen()
}

watch(() => props.attachmentsVisible, () => {
	flushPendingFileDialogOpen()
})

watch(attachmentPickerRef, () => {
	flushPendingFileDialogOpen()
})

defineExpose({
	openFileAttachment,
})
</script>

<template>
	<div class="gi-ticket-comment-composer">
		<RichTextEditor :model-value="modelValue" :placeholder="placeholder" :min-height="180" @update:modelValue="emit('update:modelValue', $event)" />
		<div v-if="showVisibility" class="gi-ticket-comment-composer__visibility-field">
			<span>Visibilidad</span>
			<SearchableSelect :model-value="visibility" :options="visibilityOptions" placeholder="Visibilidad" @update:modelValue="emit('update:visibility', $event === 'interno' ? 'interno' : 'publico')" />
		</div>
		<AttachmentPicker v-if="attachmentsEnabled && attachmentsVisible" ref="attachmentPickerRef" :model-value="attachmentsDraft" :allowed-extensions="allowedExtensions" :max-file-size-mb="maxFileSizeMb" :show-toolbar="false" :show-url-action="false" :show-helper-info="false" @update:modelValue="emit('update:attachmentsDraft', $event)" />
		<p v-if="composerError" class="gi-form-error">{{ composerError }}</p>
		<div class="gi-ticket-comment-composer__footer-actions">
			<TicketAttachmentAction v-if="attachmentsEnabled" :allowed-extensions="allowedExtensions" :max-file-size-mb="maxFileSizeMb" @action="openFileAttachment" />
			<div class="gi-ticket-comment-composer__footer-spacer" />
			<button class="gi-primary-button" type="button" @click="emit('submit')">{{ submitLabel }}</button>
			<button v-if="dismissible" class="gi-round-icon-button gi-ticket-comment-composer__close-button" type="button" aria-label="Cerrar respuesta" title="Cerrar respuesta" @click="emit('close')">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.4 5 12 10.6 17.6 5 19 6.4 13.4 12 19 17.6 17.6 19 12 13.4 6.4 19 5 17.6 10.6 12 5 6.4z" fill="currentColor" /></svg>
			</button>
		</div>
	</div>
</template>

<style scoped>
.gi-ticket-comment-composer {
	padding: 0;
	border: 1px solid var(--gi-color-border, rgba(49, 96, 91, .12));
	border-radius: 16px;
	background: var(--gi-color-surface-subtle, rgba(244, 248, 245, .98));
	color: var(--gi-color-text, #222222);
	box-shadow: inset 0 1px 0 rgba(255, 255, 255, .6);
	display: grid;
	gap: .9rem;
}

.gi-ticket-comment-composer__footer-actions {
	display: flex;
	align-items: center;
	gap: .65rem;
	flex-wrap: wrap;
	padding: 0 1rem 1rem;
}

.gi-ticket-comment-composer__footer-spacer {
	flex: 1 1 auto;
}

.gi-ticket-comment-composer__close-button {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex: 0 0 auto;
}

.gi-ticket-comment-composer__close-button svg {
	width: 1rem;
	height: 1rem;
}

.gi-ticket-comment-composer__visibility-field {
	display: grid;
	gap: .3rem;
	padding: 0 1rem;
}

.gi-ticket-comment-composer__visibility-field > span {
	font-size: .78rem;
	font-weight: 700;
	color: #5a6f68;
	text-transform: uppercase;
	letter-spacing: .04em;
}

@media (max-width: 900px) {
	.gi-ticket-comment-composer__footer-actions {
		align-items: stretch;
	}

	.gi-ticket-comment-composer__footer-spacer {
		display: none;
	}

	.gi-ticket-comment-composer__footer-actions > .gi-primary-button {
		flex: 1 1 10rem;
	}

	.gi-ticket-comment-composer__close-button {
		margin-left: auto;
	}

}
</style>