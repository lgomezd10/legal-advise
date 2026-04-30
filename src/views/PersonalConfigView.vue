<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, watch } from 'vue'
import NotificationMatrix from '@/components/NotificationMatrix.vue'
import { restorePersonalConfig, updatePersonalConfig } from '@/services/personalConfig'
import { useBootstrapStore } from '@/store/bootstrap'
import { useNotificationsStore } from '@/store/notifications'
import type { NotificationMatrixItem } from '@/types'

const bootstrapStore = useBootstrapStore()
const notificationsStore = useNotificationsStore()
const fields = computed(() => bootstrapStore.data.catalogs.fields)
const form = reactive<Record<string, string>>({})
const state = reactive({
	saving: false,
	restoring: false,
	message: '',
	notificationMessage: '',
	readOnlyHintVisible: false,
})

let readOnlyHintTimer: ReturnType<typeof setTimeout> | null = null

function isReadOnlyField(fieldKey: string) {
	return fieldKey === 'email'
}

function clearReadOnlyHintTimer() {
	if (readOnlyHintTimer !== null) {
		clearTimeout(readOnlyHintTimer)
		readOnlyHintTimer = null
	}
}

function showReadOnlyHint(fieldKey: string) {
	if (!isReadOnlyField(fieldKey)) {
		return
	}

	clearReadOnlyHintTimer()
	state.readOnlyHintVisible = true
	readOnlyHintTimer = setTimeout(() => {
		state.readOnlyHintVisible = false
		readOnlyHintTimer = null
	}, 2200)
}

function hideReadOnlyHint() {
	clearReadOnlyHintTimer()
	state.readOnlyHintVisible = false
}

const hasPersonalConfigChanges = computed(() => fields.value.some((field) => {
	if (isReadOnlyField(field.fieldKey)) {
		return false
	}

	const currentValue = form[field.fieldKey] ?? ''
	const savedValue = bootstrapStore.data.personalConfig[field.fieldKey] ?? ''
	return currentValue !== savedValue
}))

const canRestore = computed(() => bootstrapStore.data.personalConfigHasStoredValues && !state.saving && !state.restoring)

const notificationOptions = computed<Array<{ value: NotificationMatrixItem['deliveryMode'], label: string }>>(() => {
	const options: Array<{ value: NotificationMatrixItem['deliveryMode'], label: string }> = []
	if (bootstrapStore.data.roles.includes('soporte') || bootstrapStore.data.roles.includes('administrador')) {
		options.push({ value: 'none', label: 'Ninguna' })
	}

	options.push(
		{ value: 'nextcloud', label: 'Nextcloud' },
		{ value: 'both', label: 'Nextcloud y correo' },
	)

	return options
})

onMounted(async() => {
	await notificationsStore.load()
})

onBeforeUnmount(() => {
	hideReadOnlyHint()
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
		if (isReadOnlyField(field.fieldKey)) {
			return result
		}

		result[field.fieldKey] = form[field.fieldKey] ?? ''
		return result
	}, {})
	const saved = await updatePersonalConfig(payload)
	bootstrapStore.setPersonalConfig(saved.values, saved.hasStoredValues)
	state.message = 'Configuración personal guardada.'
	state.saving = false
}

async function restore() {
	state.restoring = true
	state.message = ''
	const restored = await restorePersonalConfig()
	bootstrapStore.setPersonalConfig(restored.values, restored.hasStoredValues)
	state.message = 'Configuración personal restaurada desde tu perfil de Nextcloud.'
	state.restoring = false
}

async function saveNotifications(items: NotificationMatrixItem[]) {
	await notificationsStore.save(items)
	state.notificationMessage = 'Notificaciones personales guardadas.'
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
					<input
						:id="`personal-config-${field.fieldKey}`"
						v-model="form[field.fieldKey]"
						:name="`personal-config-${field.fieldKey}`"
						:type="field.fieldType"
						class="gi-input"
						:class="{ 'gi-input--readonly': isReadOnlyField(field.fieldKey) }"
						:required="field.required"
						:readonly="isReadOnlyField(field.fieldKey)"
						:aria-readonly="isReadOnlyField(field.fieldKey) ? 'true' : undefined"
						@click="showReadOnlyHint(field.fieldKey)"
						@focus="showReadOnlyHint(field.fieldKey)"
						@blur="hideReadOnlyHint"
					/>
					<small v-if="isReadOnlyField(field.fieldKey) && state.readOnlyHintVisible" class="gi-field__hint">Se sincroniza desde tu perfil de Nextcloud y no se puede editar aquí.</small>
				</label>
			</div>
			<footer class="gi-personal-config-card__footer">
				<p v-if="state.message" class="gi-personal-config-card__message">{{ state.message }}</p>
				<div class="gi-personal-config-card__actions">
					<button class="gi-secondary-button" type="button" :disabled="!canRestore" @click="restore">Restaurar</button>
					<button class="gi-primary-button" type="button" :disabled="state.saving || state.restoring || !hasPersonalConfigChanges" @click="save">Guardar cambios</button>
				</div>
			</footer>
		</section>
		<section class="gi-personal-config-card gi-surface-elevated">
			<div class="gi-admin-card__header">
				<div>
					<h2>Notificaciones personales</h2>
					<p>Solo aparecen los eventos para los que administración permite elegir entre Nextcloud y Nextcloud y correo.</p>
				</div>
			</div>
			<NotificationMatrix
				:items="notificationsStore.items"
				:delivery-options="notificationOptions"
				@toggle="saveNotifications"
			/>
			<p v-if="state.notificationMessage" class="gi-personal-config-card__message">{{ state.notificationMessage }}</p>
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

.gi-personal-config-card__actions {
	display: flex;
	gap: 0.75rem;
	align-items: center;
	margin-left: auto;
	flex-wrap: wrap;
}

.gi-input--readonly {
	background: var(--gi-color-surface-subtle);
	border-style: dashed;
	color: var(--gi-color-text-muted);
	cursor: not-allowed;
	box-shadow: inset 0 0 0 1px rgba(0, 0, 0, .02);
}

.gi-input--readonly:focus {
	outline: none;
	border-color: var(--gi-color-border-strong);
}

.gi-field__hint {
	margin: 0;
	color: var(--gi-color-text-muted);
	font-size: .78rem;
	line-height: 1.3;
}
</style>