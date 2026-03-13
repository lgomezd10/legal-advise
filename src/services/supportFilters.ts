import { apiDelete, apiGet, apiPost } from './api'
import type { SavedFilter } from '@/types'

export const fetchSupportFilters = async() => apiGet<{ items: SavedFilter[] }>('/api/v1/support/filters')
export const saveSupportFilter = async(payload: Record<string, unknown>) => apiPost<SavedFilter>('/api/v1/support/filters', payload)
export const deleteSupportFilter = async(id: number) => apiDelete<{ deleted: boolean }>(`/api/v1/support/filters/${id}`)