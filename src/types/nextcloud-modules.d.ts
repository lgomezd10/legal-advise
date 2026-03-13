declare module '@nextcloud/router' {
	export function generateUrl(path: string): string
}

declare module '@nextcloud/initial-state' {
	export function loadState<T>(app: string, key: string, fallback: T): T
}

declare module '@nextcloud/axios' {
	const axios: any
	export default axios
}

declare module '@nextcloud/vue' {
	export const NcAppContent: any
	export const NcAppNavigation: any
	export const NcAppNavigationItem: any
	export const NcAppSidebar: any
	export const NcContent: any
}