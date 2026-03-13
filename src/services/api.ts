import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const ocsHeaders = {
	'OCS-APIRequest': 'true',
}

export async function apiGet<T>(path: string, params?: Record<string, unknown>): Promise<T> {
	const response = await axios.get(generateUrl(`/ocs/v2.php/apps/legal_advice${path}`), {
		headers: ocsHeaders,
		params,
	})
	return response.data.ocs.data as T
}

export async function apiPost<T>(path: string, data?: Record<string, unknown> | FormData): Promise<T> {
	const response = await axios.post(generateUrl(`/ocs/v2.php/apps/legal_advice${path}`), data, {
		headers: ocsHeaders,
	})
	return response.data.ocs.data as T
}

export async function apiPut<T>(path: string, data?: Record<string, unknown>): Promise<T> {
	const response = await axios.put(generateUrl(`/ocs/v2.php/apps/legal_advice${path}`), data, {
		headers: ocsHeaders,
	})
	return response.data.ocs.data as T
}

export async function apiDelete<T>(path: string): Promise<T> {
	const response = await axios.delete(generateUrl(`/ocs/v2.php/apps/legal_advice${path}`), {
		headers: ocsHeaders,
	})
	return response.data.ocs.data as T
}