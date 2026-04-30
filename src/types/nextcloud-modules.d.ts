import type { AxiosStatic } from 'axios'
import type { DefineComponent } from 'vue'

declare module '@nextcloud/router' {
	export function generateUrl(path: string): string
}

declare module '@nextcloud/initial-state' {
	export function loadState<T>(app: string, key: string, fallback: T): T
}

declare module '@nextcloud/axios' {
	const axios: AxiosStatic
	export default axios
}

declare module '@nextcloud/vue' {
	export const NcAppContent: DefineComponent<Record<string, unknown>, Record<string, unknown>, unknown>
	export const NcAppNavigation: DefineComponent<Record<string, unknown>, Record<string, unknown>, unknown>
	export const NcAppNavigationItem: DefineComponent<Record<string, unknown>, Record<string, unknown>, unknown>
	export const NcAppSidebar: DefineComponent<Record<string, unknown>, Record<string, unknown>, unknown>
	export const NcContent: DefineComponent<Record<string, unknown>, Record<string, unknown>, unknown>
}