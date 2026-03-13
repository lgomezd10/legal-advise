import { defineStore } from 'pinia'
import { fetchAdminConfig, updateAdminConfig } from '@/services/admin'

export const useAdminConfigStore = defineStore('adminConfig', {
	state: () => ({
		data: null as Record<string, unknown> | null,
	}),
	actions: {
		async load() {
			this.data = await fetchAdminConfig()
		},
		async save(payload: Record<string, unknown>) {
			this.data = await updateAdminConfig(payload)
		},
	},
})