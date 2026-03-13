import { apiGet, apiPut } from './api'

export const fetchPersonalConfig = async() => apiGet<Record<string, string>>('/api/v1/personal-config')

export const updatePersonalConfig = async(payload: Record<string, string>) => apiPut<Record<string, string>>('/api/v1/personal-config', payload)