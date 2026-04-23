import { apiDelete, apiGet, apiPost, apiPut } from './api'
import type { SavedFilter } from '@/types'

export const fetchSupportFilters = async() => apiGet<{ items: SavedFilter[] }>('/api/v1/support/filters')
export const saveSupportFilter = async(payload: Record<string, unknown>) => apiPost<SavedFilter>('/api/v1/support/filters', payload)
export const deleteSupportFilter = async(id: number) => apiDelete<{ deleted: boolean }>(`/api/v1/support/filters/${id}`)
export const fetchSupportFilterSettings = async() => apiGet<{ items: SavedFilter[] }>('/api/v1/support/filter-settings')
export const updateSupportFilterSettings = async(items: SavedFilter[]) => apiPut<{ items: SavedFilter[] }>('/api/v1/support/filter-settings', { items })
export const restoreSupportFilterSettings = async() => apiDelete<{ items: SavedFilter[] }>('/api/v1/support/filter-settings')