import { defineStore } from 'pinia'
import { loadState } from '@nextcloud/initial-state'
import { fetchBootstrap } from '@/services/bootstrap'
import type { BootstrapData } from '@/types'

const fallbackState: BootstrapData = {
	currentUser: { uid: '', displayName: '' },
	roles: [],
	navigation: [],
	personalConfig: {},
	catalogs: { statuses: [], urgencies: [], types: [], fields: [], provinces: [], attachmentConfig: { allowedExtensions: [], maxFileSizeMb: 25 } },
	supportFilters: [],
	assignables: { users: [], groups: [] },
	tasksIntegration: { available: false, config: {} },
}

export const useBootstrapStore = defineStore('bootstrap', {
	state: () => ({
		data: loadState<BootstrapData>('legal_advice', 'bootstrap', fallbackState),
		loading: false,
	}),
	getters: {
		hasRole: (state) => (role: string) => state.data.roles.includes(role),
	},
	actions: {
		async refresh() {
			this.loading = true
			this.data = await fetchBootstrap()
			this.loading = false
		},
		setPersonalConfig(personalConfig: Record<string, string>) {
			this.data = {
				...this.data,
				personalConfig: { ...personalConfig },
			}
		},
	},
})