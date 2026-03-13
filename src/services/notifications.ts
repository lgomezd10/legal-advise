import { apiGet, apiPut } from './api'
import type { NotificationMatrixItem } from '@/types'

export const fetchNotificationPreferences = async() => apiGet<{ items: NotificationMatrixItem[] }>('/api/v1/notifications/preferences')
export const updateNotificationPreferences = async(items: NotificationMatrixItem[]) => apiPut<{ items: NotificationMatrixItem[] }>('/api/v1/notifications/preferences', { items })