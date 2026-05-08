import { apiDelete, apiGet, apiPut } from './api'

export interface PersonalConfigResponse {
	values: Record<string, string>
	hasStoredValues: boolean
}

export const fetchPersonalConfig = async() => apiGet<PersonalConfigResponse>('/api/v1/personal-config')

export const updatePersonalConfig = async(payload: Record<string, string>) => apiPut<PersonalConfigResponse>('/api/v1/personal-config', { values: payload })

export const restorePersonalConfig = async() => apiDelete<PersonalConfigResponse>('/api/v1/personal-config')