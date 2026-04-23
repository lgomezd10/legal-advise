<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import SearchableSelect from '@/components/SearchableSelect.vue'
import TicketForm from '@/components/TicketForm.vue'
import { createDefaultTicketDraft } from '@/services/ticketDraft'
import { useBootstrapStore } from '@/store/bootstrap'
import { useTicketsStore } from '@/store/tickets'
import type { AssignableOption, SearchableSelectOption } from '@/types'

const router = useRouter()
const bootstrapStore = useBootstrapStore()
const ticketsStore = useTicketsStore()

const types = computed(() => bootstrapStore.data.catalogs.types)
const fields = computed(() => bootstrapStore.data.catalogs.fields)
const urgencies = computed(() => bootstrapStore.data.catalogs.urgencies)
const assignableUsers = computed(() => bootstrapStore.data.assignables.users)
const assignableGroups = computed(() => bootstrapStore.data.assignables.groups)
const provinceOptions = computed<SearchableSelectOption[]>(() => bootstrapStore.data.catalogs.provinces.map((province) => ({
	value: province,
	label: province,
})))
const userOptions = computed<SearchableSelectOption[]>(() => assignableUsers.value
	.filter((user: AssignableOption) => !assignedGroupId.value || user.groupIds?.includes(assignedGroupId.value))
	.map((user: AssignableOption) => ({
	value: user.id,
	label: user.displayName,
	searchText: [user.id, ...(user.groupIds ?? [])].join(' '),
})))
const groupOptions = computed<SearchableSelectOption[]>(() => assignableGroups.value.map((group: AssignableOption) => ({
	value: group.id,
	label: group.displayName,
	searchText: [group.id, ...(group.userIds ?? [])].join(' '),
})))
const initialDraft = computed(() => createDefaultTicketDraft(bootstrapStore.data.personalConfig, urgencies.value))

const selectedProvince = ref<string | null>(null)
const assignedUserUid = ref<string | null>(null)
const assignedGroupId = ref<string | null>(null)
const submitError = ref('')

watch(selectedProvince, () => {
	if (selectedProvince.value) {
		bootstrapStore.ensureProvinceOption(selectedProvince.value)
	}
})

function onAssignedUserSelect(value: string | number | null) {
	const nextUserUid = value ? String(value) : null
	assignedUserUid.value = nextUserUid

	if (assignedGroupId.value && nextUserUid) {
		const validForGroup = assignableUsers.value.some((user: AssignableOption) => user.id === nextUserUid && user.groupIds?.includes(assignedGroupId.value as string))
		if (!validForGroup) {
			assignedGroupId.value = null
		}
	}
}

function onAssignedGroupSelect(value: string | number | null) {
	const nextGroupId = value ? String(value) : null
	assignedGroupId.value = nextGroupId

	if (nextGroupId && assignedUserUid.value) {
		const validForUser = assignableUsers.value.some((user: AssignableOption) => user.id === assignedUserUid.value && user.groupIds?.includes(nextGroupId))
		if (!validForUser) {
			assignedUserUid.value = null
		}
	}
}

function cancel() {
	void router.push('/soporte')
}

async function submit(payload: Record<string, unknown>) {
	submitError.value = ''
	const finalPayload = {
		...payload,
		province: selectedProvince.value,
		assignedUserUid: assignedUserUid.value,
		assignedGroupId: assignedGroupId.value,
	}
	const created = await ticketsStore.create(finalPayload)
	await router.push(`/soporte/${created.id}`)
}
</script>

<template>
	<section class="gi-page">
		<header class="gi-page__header">
			<div>
				<h1>Nuevo ticket</h1>
				<p class="gi-page__subtitle">Alta rápida desde soporte, con asignación manual opcional y reglas automáticas cuando no se indica destinatario.</p>
			</div>
			<button class="gi-secondary-button" type="button" @click="cancel">Cancelar</button>
		</header>

		<section class="gi-support-new-ticket-card">
			<div class="gi-form-grid gi-support-new-ticket-card__meta-grid">
				<div class="gi-field">
					<span>Provincia</span>
					<SearchableSelect :model-value="selectedProvince" :options="provinceOptions" placeholder="Selecciona provincia" search-placeholder="Buscar provincia" clearable allow-create create-label="Añadir provincia" @update:modelValue="selectedProvince = $event ? String($event) : null" />
				</div>
				<div class="gi-field">
					<span>Asignado a grupo</span>
					<SearchableSelect :model-value="assignedGroupId" :options="groupOptions" placeholder="Sin grupo" clearable @update:modelValue="onAssignedGroupSelect" />
				</div>
				<div class="gi-field">
					<span>Asignado a usuario</span>
					<SearchableSelect :model-value="assignedUserUid" :options="userOptions" placeholder="Sin usuario" clearable @update:modelValue="onAssignedUserSelect" />
				</div>
			</div>
			<p v-if="submitError" class="gi-support-new-ticket-card__error">{{ submitError }}</p>
			<TicketForm :types="types" :fields="fields" :urgencies="urgencies" :initial-draft="initialDraft" :allowed-extensions="bootstrapStore.data.catalogs.attachmentConfig.allowedExtensions" :max-file-size-mb="bootstrapStore.data.catalogs.attachmentConfig.maxFileSizeMb" @submit="submit" />
		</section>
	</section>
</template>

<style scoped>
.gi-support-new-ticket-card {
	display: grid;
	gap: 1rem;
	padding: 1.25rem;
	border: 1px solid rgba(49, 96, 91, .12);
	border-radius: 22px;
	background: rgba(255, 255, 255, .94);
	box-shadow: 0 20px 48px rgba(34, 62, 55, .06);
}

.gi-support-new-ticket-card__meta-grid {
	padding: 0;
}

.gi-support-new-ticket-card__error {
	margin: 0;
	color: #9a3d2d;
	font-weight: 600;
}
</style>