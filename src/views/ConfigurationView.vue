<script setup lang="ts">
import { computed, reactive } from 'vue'
import PersonalConfigView from '@/views/PersonalConfigView.vue'
import AdminConsoleView from '@/views/AdminConsoleView.vue'
import SupportSettingsPanel from '@/components/SupportSettingsPanel.vue'
import { useBootstrapStore } from '@/store/bootstrap'

const bootstrapStore = useBootstrapStore()
const openSections = reactive({
	personal: false,
	support: false,
	admin: false,
})

const showPersonal = computed(() => bootstrapStore.hasRole('usuario'))
const showSupport = computed(() => bootstrapStore.hasRole('soporte'))
const showAdmin = computed(() => bootstrapStore.hasRole('administrador'))
</script>

<template>
	<section class="gi-page gi-page--configuration">
		<header class="gi-page__header">
			<div>
				<h1>Configuración</h1>
				<p class="gi-page__subtitle">Centraliza aquí la configuración disponible para tu perfil.</p>
			</div>
		</header>
		<section class="gi-config-accordion">
			<article v-if="showPersonal" class="gi-config-accordion__item gi-surface-elevated">
				<button class="gi-config-accordion__trigger" type="button" @click="openSections.personal = !openSections.personal">
					<span>Configuración personal</span>
					<strong>{{ openSections.personal ? 'Ocultar' : 'Mostrar' }}</strong>
				</button>
				<div v-if="openSections.personal" class="gi-config-accordion__content">
					<PersonalConfigView />
				</div>
			</article>

			<article v-if="showSupport" class="gi-config-accordion__item gi-surface-elevated">
				<button class="gi-config-accordion__trigger" type="button" @click="openSections.support = !openSections.support">
					<span>Configuración de soporte</span>
					<strong>{{ openSections.support ? 'Ocultar' : 'Mostrar' }}</strong>
				</button>
				<div v-if="openSections.support" class="gi-config-accordion__content">
					<SupportSettingsPanel />
				</div>
			</article>

			<article v-if="showAdmin" class="gi-config-accordion__item gi-surface-elevated">
				<button class="gi-config-accordion__trigger" type="button" @click="openSections.admin = !openSections.admin">
					<span>Consola de administración</span>
					<strong>{{ openSections.admin ? 'Ocultar' : 'Mostrar' }}</strong>
				</button>
				<div v-if="openSections.admin" class="gi-config-accordion__content">
					<AdminConsoleView />
				</div>
			</article>
		</section>
	</section>
</template>

<style scoped>
.gi-page--configuration {
	display: grid;
	gap: 1rem;
}

.gi-config-accordion {
	display: grid;
	gap: 1rem;
}

.gi-config-accordion__item {
	border-radius: 24px;
	overflow: hidden;
}

.gi-config-accordion__trigger {
	width: 100%;
	display: flex;
	justify-content: space-between;
	align-items: center;
	gap: 1rem;
	padding: 1rem 1.2rem;
	border: none;
	background: rgba(238, 244, 242, .86);
	font: inherit;
	font-weight: 700;
	cursor: pointer;
}

.gi-config-accordion__content {
	padding: .5rem;
	background: rgba(250, 251, 250, .92);
}
</style>