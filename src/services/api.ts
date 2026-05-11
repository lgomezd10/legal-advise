import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const ocsHeaders = {
	'OCS-APIRequest': 'true',
	Accept: 'application/json',
}

function generateAppOcsUrl(path: string): string {
	return generateUrl(`/ocsapp/apps/legal_advice${path}`)
}

export async function apiGet<T>(path: string, params?: Record<string, unknown>): Promise<T> {
	const response = await axios.get(generateAppOcsUrl(path), {
		headers: ocsHeaders,
		params: {
			format: 'json',
			...params,
		},
	})
	return response.data.ocs.data as T
}

export async function apiPost<T>(path: string, data?: Record<string, unknown> | FormData): Promise<T> {
	const response = await axios.post(generateAppOcsUrl(path), data, {
		headers: ocsHeaders,
		params: {
			format: 'json',
		},
	})
	return response.data.ocs.data as T
}

export async function apiPut<T>(path: string, data?: Record<string, unknown>): Promise<T> {
	const response = await axios.put(generateAppOcsUrl(path), data, {
		headers: ocsHeaders,
		params: {
			format: 'json',
		},
	})
	return response.data.ocs.data as T
}

export async function apiDelete<T>(path: string): Promise<T> {
	const response = await axios.delete(generateAppOcsUrl(path), {
		headers: ocsHeaders,
		params: {
			format: 'json',
		},
	})
	return response.data.ocs.data as T
}