<script setup lang="ts">
import { computed, defineAsyncComponent, reactive, watch } from 'vue'
import type { CatalogField, SearchableSelectOption, TicketAttachmentLinkDraft, TicketDraft, TypeNode, UrgencyCatalogItem } from '@/types'
import AttachmentPicker from './AttachmentPicker.vue'
import SearchableSelect from './SearchableSelect.vue'
import TypeCascadeSelector from './TypeCascadeSelector.vue'
import { isRichTextEmpty } from '@/utils/richText'

const RichTextEditor = defineAsyncComponent(() => import(/* webpackChunkName: "rich-text-editor" */ './RichTextEditor.vue'))

const props = defineProps<{
	types: TypeNode[]
	fields: CatalogField[]
	urgencies: UrgencyCatalogItem[]
	initialDraft?: TicketDraft | null
	lockedTypePath?: number[]
	allowedExtensions?: string[]
	maxFileSizeMb?: number
}>()

const emit = defineEmits<{
	(e: 'submit', payload: Record<string, unknown>): void
}>()

const form = reactive({
	selectedPath: [] as number[],
	title: '',
	userDescription: '',
	urgencyId: '',
	communicationChannel: 'nextcloud_mail',
	personalData: {} as Record<string, string>,
	attachments: { files: [] as File[], links: [] as TicketAttachmentLinkDraft[] },
})

const selectedTypeId = computed(() => form.selectedPath[form.selectedPath.length - 1] ?? null)
const urgencyOptions = computed<SearchableSelectOption[]>(() => props.urgencies.map((urgency: UrgencyCatalogItem) => ({
	value: String(urgency.id),
	label: String(urgency.name),
})))
const channelOptions: SearchableSelectOption[] = [
	{ value: 'nextcloud', label: 'Nextcloud' },
	{ value: 'mail', label: 'Correo' },
	{ value: 'nextcloud_mail', label: 'Nextcloud y correo' },
]
const visibleFields = computed(() => props.fields.filter((field: CatalogField) => field.fieldKey !== 'province'))

function applyDraft(draft: TicketDraft | null | undefined) {
	form.selectedPath = [...(props.lockedTypePath ?? draft?.selectedPath ?? [])]
	form.title = draft?.title ?? ''
	form.userDescription = draft?.userDescription ?? ''
	form.urgencyId = draft?.urgencyId ?? ''
	form.communicationChannel = draft?.communicationChannel ?? 'nextcloud_mail'
	form.personalData = { ...(draft?.personalData ?? {}) }
	form.attachments = {
		files: [...(draft?.attachments?.files ?? [])],
		links: [...(draft?.attachments?.links ?? [])],
	}
}

watch(() => props.initialDraft, (draft) => {
	applyDraft(draft)
}, { immediate: true, deep: true })

watch(() => props.lockedTypePath, (lockedTypePath) => {
	if (lockedTypePath) {
		form.selectedPath = [...lockedTypePath]
	}
}, { immediate: true, deep: true })

function submit() {
	emit('submit', {
		typeId: selectedTypeId.value,
		title: form.title,
		userDescription: form.userDescription,
		urgencyId: form.urgencyId ? Number(form.urgencyId) : null,
		communicationChannel: form.communicationChannel,
		personalData: form.personalData,
			attachments: {
				files: [...form.attachments.files],
				links: [...form.attachments.links],
			},
	})
}

const canSubmit = computed(() => Boolean(selectedTypeId.value) && form.title.trim() !== '' && !isRichTextEmpty(form.userDescription))
</script>

<template>
	<div class="gi-form-shell">
		<TypeCascadeSelector v-if="!props.lockedTypePath?.length" v-model="form.selectedPath" :types="types" />
		<div class="gi-form-grid">
			<label class="gi-field gi-field--wide"><span>Título</span><input v-model="form.title" class="gi-input" /></label>
			<label class="gi-field"><span>Criticidad</span><SearchableSelect v-model="form.urgencyId" :options="urgencyOptions" placeholder="Selecciona" clearable /></label>
			<label class="gi-field"><span>Canal de comunicación</span><SearchableSelect v-model="form.communicationChannel" :options="channelOptions" placeholder="Selecciona" /></label>
			<div class="gi-field gi-field--wide">
				<span>Descripción</span>
				<RichTextEditor v-model="form.userDescription" placeholder="Describe el ticket y, si lo necesitas, pega capturas o inserta imágenes" :min-height="220" />
			</div>
			<label class="gi-field gi-field--wide">
				<span>Adjuntos iniciales</span>
				<AttachmentPicker v-model="form.attachments" :allowed-extensions="allowedExtensions" :max-file-size-mb="maxFileSizeMb || 25" />
			</label>
			<label v-for="field in visibleFields" :key="field.fieldKey" class="gi-field">
				<span>{{ field.label }}</span>
				<input v-model="form.personalData[field.fieldKey]" :type="field.fieldType" class="gi-input" :required="field.required" />
			</label>
		</div>
		<div class="gi-actions">
			<button class="gi-primary-button" :disabled="!canSubmit" @click="submit">Crear ticket</button>
		</div>
	</div>
</template>

<style scoped>
.gi-form-intro {
	display: flex;
	justify-content: space-between;
	gap: 1rem;
	align-items: flex-start;
	margin-bottom: .8rem;
}

.gi-form-intro h2,
.gi-form-intro p {
	margin: 0;
}

.gi-form-intro__hint {
	max-width: 20rem;
	color: #5e706a;
}

@media (max-width: 900px) {
	.gi-form-intro {
		flex-direction: column;
	}
}
</style>