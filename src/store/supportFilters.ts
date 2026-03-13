import { defineStore } from 'pinia'
import type { SavedFilter } from '@/types'
import { deleteSupportFilter, fetchSupportFilters, saveSupportFilter } from '@/services/supportFilters'

export const useSupportFiltersStore = defineStore('supportFilters', {
	state: () => ({
		items: [] as SavedFilter[],
	}),
	actions: {
		async load() {
			this.items = (await fetchSupportFilters()).items
		},
		async save(payload: Record<string, unknown>) {
			await saveSupportFilter(payload)
			await this.load()
		},
		async remove(id: number) {
			await deleteSupportFilter(id)
			await this.load()
		},
	},
})