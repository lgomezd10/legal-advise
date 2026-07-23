import { beforeEach, vi } from 'vitest'
import {
	adminConfigStoreMock,
	bootstrapStoreMock,
	notificationsStoreMock,
	onBeforeRouteLeaveMock,
	onBeforeRouteUpdateMock,
	resetFrontendMocks,
	routeState,
	routerPushMock,
	supportFiltersStoreMock,
	ticketsStoreMock,
	restorePersonalConfigMock,
	updatePersonalConfigMock,
} from './helpers/mockState'

if (typeof window.localStorage === 'undefined' || typeof window.localStorage.clear !== 'function') {
	const store = new Map<string, string>()
	const polyfill: Storage = {
		get length() { return store.size },
		clear() { store.clear() },
		getItem(key: string) { return store.get(key) ?? null },
		setItem(key: string, value: string) { store.set(key, String(value)) },
		removeItem(key: string) { store.delete(key) },
		key(index: number) { return [...store.keys()][index] ?? null },
	}
	Object.defineProperty(window, 'localStorage', { value: polyfill, writable: true, configurable: true })
}

vi.mock('vue-router', () => ({
	useRoute: () => routeState,
	useRouter: () => ({ push: routerPushMock }),
	onBeforeRouteLeave: (guard: unknown) => onBeforeRouteLeaveMock(guard),
	onBeforeRouteUpdate: (guard: unknown) => onBeforeRouteUpdateMock(guard),
}))

vi.mock('@/store/bootstrap', () => ({
	useBootstrapStore: () => bootstrapStoreMock,
}))

vi.mock('@/store/tickets', () => ({
	useTicketsStore: () => ticketsStoreMock,
}))

vi.mock('@/store/supportFilters', () => ({
	useSupportFiltersStore: () => supportFiltersStoreMock,
}))

vi.mock('@/store/adminConfig', () => ({
	useAdminConfigStore: () => adminConfigStoreMock,
}))

vi.mock('@/store/notifications', () => ({
	useNotificationsStore: () => notificationsStoreMock,
}))

vi.mock('@/services/personalConfig', () => ({
	restorePersonalConfig: restorePersonalConfigMock,
	updatePersonalConfig: updatePersonalConfigMock,
}))

beforeEach(() => {
	resetFrontendMocks()
	vi.stubGlobal('alert', vi.fn())
	vi.stubGlobal('confirm', vi.fn(() => true))
	vi.stubGlobal('open', vi.fn())
	vi.stubGlobal('atob', (value: string) => Buffer.from(value, 'base64').toString('binary'))
	vi.stubGlobal('URL', URL)
	vi.stubGlobal('Blob', Blob)
	Object.defineProperty(URL, 'createObjectURL', {
		configurable: true,
		writable: true,
		value: vi.fn(() => 'blob:mock'),
	})
	Object.defineProperty(URL, 'revokeObjectURL', {
		configurable: true,
		writable: true,
		value: vi.fn(() => undefined),
	})
})