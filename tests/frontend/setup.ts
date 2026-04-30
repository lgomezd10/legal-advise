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