<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import SearchableSelect from '@/components/SearchableSelect.vue'
import TicketForm from '@/components/TicketForm.vue'
import TypeCascadeSelector from '@/components/TypeCascadeSelector.vue'
import { createDefaultTicketDraft, getTypeLabelsForPath } from '@/services/ticketDraft'
import { useBootstrapStore } from '@/store/bootstrap'
import { useTicketsStore } from '@/store/tickets'
import type { SearchableSelectOption } from '@/types'

const router = useRouter()
const bootstrapStore = useBootstrapStore()
const ticketsStore = useTicketsStore()

const types = computed(() => bootstrapStore.data.catalogs.types)
const fields = computed(() => bootstrapStore.data.catalogs.fields)
const urgencies = computed(() => bootstrapStore.data.catalogs.urgencies)
const provinceOptions = computed<SearchableSelectOption[]>(() => bootstrapStore.data.catalogs.provinces.map((province) => ({
	value: province,
	label: province,
})))
const draft = computed(() => ticketsStore.draft ?? createDefaultTicketDraft(bootstrapStore.data.personalConfig, urgencies.value))
const selectedPath = ref<number[]>([...(draft.value.selectedPath ?? [])])
const selectedProvince = ref<string | null>(draft.value.province ?? null)
const step = ref(selectedPath.value.length > 0 && selectedProvince.value !== null ? 'details' : 'type')
const typeSummary = computed(() => getTypeLabelsForPath(types.value, selectedPath.value))
const provinceSummary = computed(() => selectedProvince.value ?? 'Sin seleccionar')
const typeStepError = ref('')

if (!ticketsStore.draft) {
	ticketsStore.replaceDraft(draft.value)
}

watch(selectedProvince, () => {
	if (selectedProvince.value) {
		typeStepError.value = ''
	}
})

function continueToDetails() {
	if (selectedPath.value.length === 0) {
		return
	}

	if (!selectedProvince.value) {
		typeStepError.value = 'Debes seleccionar una provincia o anadir una nueva.'
		return
	}

	ticketsStore.mergeDraft({
		selectedPath: [...selectedPath.value],
		province: selectedProvince.value,
	})
	typeStepError.value = ''
	step.value = 'details'
}

function editType() {
	step.value = 'type'
}

function cancel() {
	ticketsStore.clearDraft()
	void router.push('/mis-incidencias')
}

async function submit(payload: Record<string, unknown>) {
	const finalPayload = {
		...payload,
		province: selectedProvince.value,
	}
	ticketsStore.mergeDraft(finalPayload as typeof draft.value)
	await ticketsStore.create(finalPayload)
	ticketsStore.clearDraft()
	await ticketsStore.load('user')
	await router.push('/mis-incidencias')
}
</script>

<template>
	<section class="gi-page">
		<header class="gi-page__header">
			<div>
				<h1>Nuevo ticket</h1>
			</div>
			<button class="gi-secondary-button" type="button" @click="cancel">Cancelar</button>
		</header>
		<section v-if="step === 'type'" class="gi-ticket-creation-card">
			<div class="gi-ticket-creation-card__header">
				<div>
					<h2>Seleccion de tipo</h2>
					<p>Usa la cascada para afinar el motivo del ticket y selecciona la provincia antes de continuar.</p>
				</div>
			</div>
			<TypeCascadeSelector v-model="selectedPath" :types="types" />
			<label class="gi-field gi-field--wide">
				<span>Provincia</span>
				<SearchableSelect v-model="selectedProvince" :options="provinceOptions" placeholder="Selecciona provincia" search-placeholder="Buscar provincia" clearable allow-create create-label="Anadir provincia" />
			</label>
			<p v-if="typeStepError" class="gi-ticket-creation-card__error">{{ typeStepError }}</p>
			<div class="gi-ticket-type-summary">
				<span class="gi-ticket-type-summary__label">Ruta elegida</span>
				<strong>{{ typeSummary.length > 0 ? typeSummary.join(' > ') : 'Todavia no has seleccionado un tipo' }}</strong>
			</div>
			<div class="gi-ticket-type-summary">
				<span class="gi-ticket-type-summary__label">Provincia</span>
				<strong>{{ provinceSummary }}</strong>
			</div>
			<div class="gi-actions">
				<button class="gi-primary-button" type="button" :disabled="selectedPath.length === 0" @click="continueToDetails">Continuar</button>
			</div>
		</section>
		<section v-else class="gi-ticket-creation-card gi-ticket-creation-card--details">
			<header class="gi-ticket-creation-card__header gi-ticket-creation-card__header--summary">
				<div class="gi-ticket-creation-card__summary-grid">
					<div class="gi-ticket-creation-card__type-line">
						<span class="gi-ticket-type-summary__label">Tipo seleccionado</span>
						<strong class="gi-ticket-creation-card__type-path">{{ typeSummary.join(' > ') }}</strong>
					</div>
					<div class="gi-ticket-creation-card__type-line">
						<span class="gi-ticket-type-summary__label">Provincia</span>
						<strong class="gi-ticket-creation-card__type-path">{{ provinceSummary }}</strong>
					</div>
				</div>
				<button class="gi-icon-button" type="button" aria-label="Modificar tipo de incidencia" title="Modificar tipo" @click="editType">
					<span aria-hidden="true">&#9998;</span>
				</button>
			</header>
			<TicketForm
				:key="`${selectedPath.join('-')}:${selectedProvince ?? 'sin-provincia'}:${draft.title ?? ''}:${draft.userDescription ?? ''}`"
				:types="types"
				:fields="fields"
				:urgencies="urgencies"
				:allowed-extensions="bootstrapStore.data.catalogs.attachmentConfig.allowedExtensions"
				:max-file-size-mb="bootstrapStore.data.catalogs.attachmentConfig.maxFileSizeMb"
				:initial-draft="draft"
				:locked-type-path="selectedPath"
				@submit="submit"
			/>
		</section>
	</section>
</template>

<style scoped>
.gi-ticket-creation-card {
	display: grid;
	gap: 1rem;
	padding: 1.25rem;
	border: 1px solid rgba(49, 96, 91, .12);
	border-radius: 22px;
	background: rgba(255, 255, 255, .94);
	box-shadow: 0 20px 48px rgba(34, 62, 55, .06);
}

.gi-ticket-creation-card__header h2,
.gi-ticket-creation-card__header p {
	margin: 0;
}

.gi-ticket-creation-card__header p {
	margin-top: .2rem;
	color: #5e706a;
}

.gi-ticket-creation-card__header--summary {
	display: flex;
	justify-content: space-between;
	gap: 1rem;
	align-items: center;
	flex-wrap: wrap;
}

.gi-ticket-creation-card__type-line {
	display: flex;
	align-items: center;
	gap: .65rem;
	min-width: 0;
	flex-wrap: wrap;
}

.gi-ticket-creation-card__summary-grid {
	display: grid;
	gap: .75rem;
}

.gi-ticket-creation-card__type-path {
	min-width: 0;
	word-break: break-word;
}

.gi-ticket-creation-card__error {
	margin: 0;
	color: #9a3d2d;
	font-weight: 600;
}

.gi-ticket-type-summary {
	display: grid;
	gap: .35rem;
	padding: .9rem 1rem;
	border-radius: 18px;
	background: rgba(239, 245, 241, .98);
	border: 1px solid rgba(49, 96, 91, .1);
}

.gi-ticket-type-summary__label {
	font-size: .74rem;
	text-transform: uppercase;
	letter-spacing: .06em;
	color: #60746d;
}

.gi-icon-button {
	width: 2.5rem;
	height: 2.5rem;
	display: inline-grid;
	place-items: center;
	border: 1px solid rgba(11, 110, 79, .16);
	border-radius: 999px;
	background: rgba(239, 245, 241, .98);
	color: #0b6e4f;
	cursor: pointer;
	font: inherit;
	font-size: 1rem;
	box-shadow: 0 10px 22px rgba(34, 62, 55, .06);
}

.gi-icon-button:hover {
	background: rgba(227, 238, 232, .98);
}
</style>