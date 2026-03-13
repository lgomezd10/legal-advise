<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import type { CatalogField, SearchableSelectOption, TicketDraft, TypeNode, UrgencyCatalogItem } from '@/types'
import SearchableSelect from './SearchableSelect.vue'
import TypeCascadeSelector from './TypeCascadeSelector.vue'

const props = defineProps<{
	types: TypeNode[]
	fields: CatalogField[]
	urgencies: UrgencyCatalogItem[]
	initialDraft?: TicketDraft | null
	lockedTypePath?: number[]
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

function applyDraft(draft: TicketDraft | null | undefined) {
	form.selectedPath = [...(props.lockedTypePath ?? draft?.selectedPath ?? [])]
	form.title = draft?.title ?? ''
	form.userDescription = draft?.userDescription ?? ''
	form.urgencyId = draft?.urgencyId ?? ''
	form.communicationChannel = draft?.communicationChannel ?? 'nextcloud_mail'
	form.personalData = { ...(draft?.personalData ?? {}) }
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
	})
}
</script>

<template>
	<div class="gi-form-shell">
		<TypeCascadeSelector v-if="!props.lockedTypePath?.length" v-model="form.selectedPath" :types="types" />
		<div class="gi-form-grid">
			<label class="gi-field gi-field--wide"><span>Titulo</span><input v-model="form.title" class="gi-input" /></label>
			<label class="gi-field"><span>Criticidad</span><SearchableSelect v-model="form.urgencyId" :options="urgencyOptions" placeholder="Selecciona" clearable /></label>
			<label class="gi-field"><span>Canal de comunicacion</span><SearchableSelect v-model="form.communicationChannel" :options="channelOptions" placeholder="Selecciona" /></label>
			<label class="gi-field gi-field--wide"><span>Descripcion</span><textarea v-model="form.userDescription" class="gi-textarea" rows="7" /></label>
			<label v-for="field in fields" :key="field.fieldKey" class="gi-field">
				<span>{{ field.label }}</span>
				<input v-model="form.personalData[field.fieldKey]" :type="field.fieldType" class="gi-input" :required="field.required" />
			</label>
		</div>
		<div class="gi-actions">
			<button class="gi-primary-button" :disabled="!selectedTypeId || !form.title || !form.userDescription" @click="submit">Crear ticket</button>
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