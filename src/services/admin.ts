import { apiGet, apiPut } from './api'

export const fetchAdminConfig = async() => apiGet<Record<string, unknown>>('/api/v1/admin/config')
export const updateAdminConfig = async(payload: Record<string, unknown>) => apiPut<Record<string, unknown>>('/api/v1/admin/config', payload)