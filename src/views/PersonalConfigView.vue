<script setup lang="ts">
import { computed, reactive, watch } from 'vue'
import { updatePersonalConfig } from '@/services/personalConfig'
import { useBootstrapStore } from '@/store/bootstrap'

const bootstrapStore = useBootstrapStore()
const fields = computed(() => bootstrapStore.data.catalogs.fields)
const form = reactive<Record<string, string>>({})
const state = reactive({
	saving: false,
	message: '',
})

watch(() => [bootstrapStore.data.personalConfig, fields.value], () => {
	for (const field of fields.value) {
		form[field.fieldKey] = bootstrapStore.data.personalConfig[field.fieldKey] ?? ''
	}
}, { immediate: true, deep: true })

async function save() {
	state.saving = true
	state.message = ''
	const payload = fields.value.reduce<Record<string, string>>((result, field) => {
		result[field.fieldKey] = form[field.fieldKey] ?? ''
		return result
	}, {})
	const saved = await updatePersonalConfig(payload)
	bootstrapStore.setPersonalConfig(saved)
	state.message = 'Configuración personal guardada.'
	state.saving = false
}
</script>

<template>
	<section class="gi-page">
		<header class="gi-page__header">
			<div>
				<p class="gi-page__subtitle">Estos datos vienen de la configuración de tu perfil. Si los modifcas, será sólo para esta apliación.</p>
			</div>
		</header>
		<section class="gi-personal-config-card gi-surface-elevated">
			<div class="gi-form-grid">
				<label v-for="field in fields" :key="field.fieldKey" class="gi-field">
					<span>{{ field.label }}</span>
					<input v-model="form[field.fieldKey]" :type="field.fieldType" class="gi-input" :required="field.required" />
				</label>
			</div>
			<footer class="gi-personal-config-card__footer">
				<p v-if="state.message" class="gi-personal-config-card__message">{{ state.message }}</p>
				<button class="gi-primary-button" type="button" :disabled="state.saving" @click="save">Guardar cambios</button>
			</footer>
		</section>
	</section>
</template>

<style scoped>
.gi-personal-config-card {
	display: grid;
	gap: 1rem;
	padding: 1.25rem;
	border-radius: 22px;
}

.gi-personal-config-card__footer {
	display: flex;
	justify-content: space-between;
	gap: 1rem;
	align-items: center;
	flex-wrap: wrap;
}

.gi-personal-config-card__message {
	margin: 0;
	color: #2f5d53;
	font-weight: 600;
}
</style>