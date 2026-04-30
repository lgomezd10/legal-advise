import { defineStore } from 'pinia'
import { loadState } from '@nextcloud/initial-state'
import { fetchBootstrap } from '@/services/bootstrap'
import type { BootstrapData } from '@/types'

const fallbackState: BootstrapData = {
		appInfo: {
			id: 'legal_advice',
			version: '',
			storageBytes: 0,
			storageLabel: '0 B',
			appDataBytes: 0,
			appDataLabel: '0 B',
			databaseBytes: 0,
			databaseLabel: '0 B',
			attachmentBytes: 0,
			attachmentLabel: '0 B',
		},
	currentUser: { uid: '', displayName: '' },
	roles: [],
	navigation: [],
	personalConfig: {},
	personalConfigHasStoredValues: false,
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
			ensureProvinceOption(province: string | null | undefined) {
				const trimmed = String(province ?? '').trim()
				if (trimmed === '') {
					return
				}

				const exists = this.data.catalogs.provinces.some((entry) => entry.trim().toLocaleLowerCase() === trimmed.toLocaleLowerCase())
				if (exists) {
					return
				}

				this.data = {
					...this.data,
					catalogs: {
						...this.data.catalogs,
						provinces: [...this.data.catalogs.provinces, trimmed],
					},
				}
			},
		setPersonalConfig(personalConfig: Record<string, string>, hasStoredValues?: boolean) {
			this.data = {
				...this.data,
				personalConfig: { ...personalConfig },
				personalConfigHasStoredValues: hasStoredValues ?? this.data.personalConfigHasStoredValues,
			}
		},
	},
})