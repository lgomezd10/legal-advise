<script setup lang="ts">
import { onMounted, ref } from 'vue'
import NotificationMatrix from '@/components/NotificationMatrix.vue'
import FilterCatalogEditor from '@/components/FilterCatalogEditor.vue'
import { fetchSupportFilterSettings, restoreSupportFilterSettings, updateSupportFilterSettings } from '@/services/supportFilters'
import { useBootstrapStore } from '@/store/bootstrap'
import { useNotificationsStore } from '@/store/notifications'
import type { SavedFilter } from '@/types'

const bootstrapStore = useBootstrapStore()
const notificationsStore = useNotificationsStore()
const filterSettings = ref<SavedFilter[]>([])
const statusMessage = ref('')

onMounted(async() => {
	filterSettings.value = (await fetchSupportFilterSettings()).items
	await notificationsStore.load()
})

async function saveFilters(nextFilters: SavedFilter[]) {
	filterSettings.value = (await updateSupportFilterSettings(nextFilters)).items
	statusMessage.value = 'Filtros de soporte guardados.'
}

async function restoreFilters() {
	filterSettings.value = (await restoreSupportFilterSettings()).items
	statusMessage.value = 'Se ha restaurado la configuracion global de filtros.'
}
</script>

<template>
	<section class="gi-support-settings">
		<FilterCatalogEditor
			:filters="filterSettings"
			:statuses="bootstrapStore.data.catalogs.statuses"
			:types="bootstrapStore.data.catalogs.types"
			:users="bootstrapStore.data.assignables.users"
			:groups="bootstrapStore.data.assignables.groups"
			title="Filtros de soporte"
			description="Puedes ajustar tu copia local de los filtros globales, cambiar cuál se aplica por defecto y restaurar en cualquier momento la configuración definida por administración."
			save-label="Guardar filtros"
			empty-label="No hay filtros disponibles para soporte."
			secondary-action-label="Restaurar configuracion global"
			lock-predefined-filters
			@save="saveFilters"
			@secondary-action="restoreFilters"
		/>
		<p v-if="statusMessage" class="gi-admin-feedback">{{ statusMessage }}</p>

		<section class="gi-admin-card gi-admin-card--fullwidth">
			<div class="gi-admin-card__header">
				<div>
					<h2>Notificaciones personales</h2>
					<p>Estas preferencias solo se aplican a tu usuario y complementan la política base del perfil.</p>
				</div>
			</div>
			<NotificationMatrix :items="notificationsStore.items" @toggle="notificationsStore.save" />
		</section>
	</section>
</template>

<style scoped>
.gi-support-settings {
	display: grid;
	gap: 1rem;
}
</style>