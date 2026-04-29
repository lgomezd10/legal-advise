import { reactive } from 'vue'
import { vi } from 'vitest'
import type { BootstrapData, NotificationMatrixItem, Ticket, TicketDraft } from '@/types'
import { createAdminConfigData, createBootstrapData } from './testData'

export const routeState = reactive({
	path: '/mis-incidencias',
	query: {} as Record<string, string | string[] | undefined>,
	params: {} as Record<string, string>,
})

export const routerPushMock = vi.fn()
export const onBeforeRouteLeaveMock = vi.fn()
export const onBeforeRouteUpdateMock = vi.fn()
export const updatePersonalConfigMock = vi.fn(async(payload: Record<string, string>) => ({ values: payload, hasStoredValues: true }))
export const restorePersonalConfigMock = vi.fn(async() => ({ values: { email: 'usuario@example.com', city: 'Madrid', province: 'Madrid' }, hasStoredValues: false }))

export const bootstrapStoreMock = reactive({
	data: createBootstrapData(),
	loading: false,
	hasRole: (role: string) => false,
	refresh: vi.fn(async() => undefined),
	ensureProvinceOption: vi.fn((province: string | null | undefined) => {
		const trimmed = String(province ?? '').trim()
		if (trimmed === '') {
			return
		}
		if (bootstrapStoreMock.data.catalogs.provinces.some((entry) => entry.trim().toLocaleLowerCase() === trimmed.toLocaleLowerCase())) {
			return
		}
		bootstrapStoreMock.data = {
			...bootstrapStoreMock.data,
			catalogs: {
				...bootstrapStoreMock.data.catalogs,
				provinces: [...bootstrapStoreMock.data.catalogs.provinces, trimmed],
			},
		}
	}),
	setPersonalConfig: vi.fn((personalConfig: Record<string, string>, hasStoredValues = bootstrapStoreMock.data.personalConfigHasStoredValues) => {
		bootstrapStoreMock.data = {
			...bootstrapStoreMock.data,
			personalConfig: { ...personalConfig },
			personalConfigHasStoredValues: hasStoredValues,
		}
	}),
}) as {
	data: BootstrapData
	loading: boolean
	hasRole: (role: string) => boolean
	refresh: ReturnType<typeof vi.fn>
	ensureProvinceOption: ReturnType<typeof vi.fn>
	setPersonalConfig: ReturnType<typeof vi.fn>
}

bootstrapStoreMock.hasRole = (role: string) => bootstrapStoreMock.data.roles.includes(role)

export const ticketsStoreMock = reactive({
	items: [] as Ticket[],
	selected: null as Ticket | null,
	draft: null as TicketDraft | null,
	loading: false,
	load: vi.fn(async() => undefined),
	select: vi.fn(async() => undefined),
	create: vi.fn(async(payload: Record<string, unknown>) => ({ id: 999, ...payload })),
	update: vi.fn(async() => undefined),
	comment: vi.fn(async() => undefined),
	reopen: vi.fn(async() => undefined),
	download: vi.fn(async() => ({ meta: { originalName: 'adjunto.txt', mimeType: 'text/plain' }, content: 'YQ==' })),
	export: vi.fn(async() => ({ filename: 'tickets.csv', mimeType: 'text/csv', content: 'YQ==' })),
	replaceDraft: vi.fn((draft: TicketDraft | null) => {
		ticketsStoreMock.draft = draft
	}),
	mergeDraft: vi.fn((partial: TicketDraft) => {
		ticketsStoreMock.draft = {
			...(ticketsStoreMock.draft ?? {}),
			...partial,
		}
	}),
	clearDraft: vi.fn(() => {
		ticketsStoreMock.draft = null
	}),
})

export const supportFiltersStoreMock = reactive({
	items: [...createBootstrapData().supportFilters],
	defaultFilterId: 10 as number | null,
	load: vi.fn(async() => undefined),
	save: vi.fn(async() => undefined),
	remove: vi.fn(async() => undefined),
})

export const adminConfigStoreMock = reactive({
	data: createAdminConfigData() as Record<string, unknown> | null,
	load: vi.fn(async() => undefined),
	save: vi.fn(async() => undefined),
})

export const notificationsStoreMock = reactive({
	items: [{ scopeId: 'usuario', eventName: 'ticket_created', deliveryMode: 'both' }] as NotificationMatrixItem[],
	load: vi.fn(async() => undefined),
	save: vi.fn(async() => undefined),
})

export function resetFrontendMocks() {
	routeState.path = '/mis-incidencias'
	routeState.query = {}
	routeState.params = {}
	routerPushMock.mockReset()
	onBeforeRouteLeaveMock.mockReset()
	onBeforeRouteUpdateMock.mockReset()
	updatePersonalConfigMock.mockReset()
	updatePersonalConfigMock.mockResolvedValue({ values: { email: 'nuevo@example.com', city: 'Sevilla', province: 'Madrid' }, hasStoredValues: true })
	restorePersonalConfigMock.mockReset()
	restorePersonalConfigMock.mockResolvedValue({ values: { email: 'usuario@example.com', city: 'Madrid', province: 'Madrid' }, hasStoredValues: false })

	bootstrapStoreMock.data = createBootstrapData()
	bootstrapStoreMock.loading = false
	bootstrapStoreMock.refresh.mockReset()
	bootstrapStoreMock.refresh.mockResolvedValue(undefined)
	bootstrapStoreMock.ensureProvinceOption.mockClear()
	bootstrapStoreMock.setPersonalConfig.mockClear()

	ticketsStoreMock.items = []
	ticketsStoreMock.selected = null
	ticketsStoreMock.draft = null
	ticketsStoreMock.loading = false
	ticketsStoreMock.load.mockReset()
	ticketsStoreMock.load.mockResolvedValue(undefined)
	ticketsStoreMock.select.mockReset()
	ticketsStoreMock.select.mockResolvedValue(undefined)
	ticketsStoreMock.create.mockReset()
	ticketsStoreMock.create.mockImplementation(async(payload: Record<string, unknown>) => ({ id: 999, ...payload }))
	ticketsStoreMock.update.mockReset()
	ticketsStoreMock.update.mockResolvedValue(undefined)
	ticketsStoreMock.comment.mockReset()
	ticketsStoreMock.comment.mockResolvedValue(undefined)
	ticketsStoreMock.reopen.mockReset()
	ticketsStoreMock.reopen.mockResolvedValue(undefined)
	ticketsStoreMock.download.mockReset()
	ticketsStoreMock.download.mockResolvedValue({ meta: { originalName: 'adjunto.txt', mimeType: 'text/plain' }, content: 'YQ==' })
	ticketsStoreMock.export.mockReset()
	ticketsStoreMock.export.mockResolvedValue({ filename: 'tickets.csv', mimeType: 'text/csv', content: 'YQ==' })
	ticketsStoreMock.replaceDraft.mockClear()
	ticketsStoreMock.mergeDraft.mockClear()
	ticketsStoreMock.clearDraft.mockClear()

	supportFiltersStoreMock.items = [...createBootstrapData().supportFilters]
	supportFiltersStoreMock.defaultFilterId = 10
	supportFiltersStoreMock.load.mockReset()
	supportFiltersStoreMock.load.mockResolvedValue(undefined)
	supportFiltersStoreMock.save.mockReset()
	supportFiltersStoreMock.save.mockResolvedValue(undefined)
	supportFiltersStoreMock.remove.mockReset()
	supportFiltersStoreMock.remove.mockResolvedValue(undefined)

	adminConfigStoreMock.data = createAdminConfigData()
	adminConfigStoreMock.load.mockReset()
	adminConfigStoreMock.load.mockResolvedValue(undefined)
	adminConfigStoreMock.save.mockReset()
	adminConfigStoreMock.save.mockResolvedValue(undefined)

	notificationsStoreMock.items = [{ scopeId: 'usuario', eventName: 'ticket_created', deliveryMode: 'both' }]
	notificationsStoreMock.load.mockReset()
	notificationsStoreMock.load.mockResolvedValue(undefined)
	notificationsStoreMock.save.mockReset()
	notificationsStoreMock.save.mockResolvedValue(undefined)

	window.localStorage.clear()
}