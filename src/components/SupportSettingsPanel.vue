<script setup lang="ts">
import { onMounted, ref } from 'vue'
import FilterCatalogEditor from '@/components/FilterCatalogEditor.vue'
import { fetchSupportFilterSettings, restoreSupportFilterSettings, updateSupportFilterSettings } from '@/services/supportFilters'
import { useBootstrapStore } from '@/store/bootstrap'
import type { SavedFilter } from '@/types'

const bootstrapStore = useBootstrapStore()
const filterSettings = ref<SavedFilter[]>([])
const statusMessage = ref('')

onMounted(async() => {
	filterSettings.value = (await fetchSupportFilterSettings()).items
})

async function saveFilters(nextFilters: SavedFilter[]) {
	filterSettings.value = (await updateSupportFilterSettings(nextFilters)).items
	statusMessage.value = 'Filtros de soporte guardados.'
}

async function restoreFilters() {
	filterSettings.value = (await restoreSupportFilterSettings()).items
	statusMessage.value = 'Se ha restaurado la configuración global de filtros.'
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
			secondary-action-label="Restaurar configuración global"
			lock-predefined-filters
			@save="saveFilters"
			@secondary-action="restoreFilters"
		/>
		<p v-if="statusMessage" class="gi-admin-feedback">{{ statusMessage }}</p>
	</section>
</template>

<style scoped>
.gi-support-settings {
	display: grid;
	gap: 1rem;
}
</style>