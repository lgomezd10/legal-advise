import { defineStore } from 'pinia'
import { fetchNotificationPreferences, updateNotificationPreferences } from '@/services/notifications'
import type { NotificationMatrixItem } from '@/types'

export const useNotificationsStore = defineStore('notifications', {
	state: () => ({
		items: [] as NotificationMatrixItem[],
	}),
	actions: {
		async load() {
			this.items = (await fetchNotificationPreferences()).items
		},
		async save(items: NotificationMatrixItem[]) {
			this.items = (await updateNotificationPreferences(items)).items
		},
	},
})