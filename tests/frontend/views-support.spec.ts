import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import SupportConsoleView from '@/views/SupportConsoleView.vue'
import SupportNewTicketView from '@/views/SupportNewTicketView.vue'
import SupportTicketFullView from '@/views/SupportTicketFullView.vue'
import TicketSidebarView from '@/views/TicketSidebarView.vue'
import { bootstrapStoreMock, routeState, routerPushMock, supportFiltersStoreMock, ticketsStoreMock } from './helpers/mockState'
import { SearchableSelectStub, SupportFilterBuilderStub, SupportTicketTableStub, TicketFormStub, TicketSidebarPanelStub } from './helpers/stubs'
import { createBootstrapData, createTicket } from './helpers/testData'

describe('Pantallas de soporte', () => {
	it('rehidrata en el builder los criterios persistidos sin filtro guardado', async() => {
		const SupportFilterBuilderPropsStub = defineComponent({
			name: 'SupportFilterBuilder',
			props: {
				initialFilterId: { type: Number, default: null },
				initialCriteria: { type: Object, default: () => ({}) },
			},
			template: `<div class="support-filter-builder-props-stub">{{ JSON.stringify(initialCriteria) }}</div>`,
		})

		bootstrapStoreMock.data = createBootstrapData({
			roles: ['soporte'],
			navigation: [{ id: 'soporte', label: 'Consola de soporte', route: '/soporte', visible: true }],
		})
		window.localStorage.setItem('legal_advice:support_console_state', JSON.stringify({
			visibleColumns: ['number', 'updatedAt', 'assignment', 'createdBy', 'title', 'userDescription'],
			columnEditorOrder: ['number', 'updatedAt', 'assignment', 'createdBy', 'province', 'title', 'userDescription', 'status', 'urgency', 'createdAt'],
			criteria: { province: 'Sevilla', text: 'seguimiento' },
			sortKey: 'updatedAt',
			sortDirection: 'desc',
			selectedFilterId: null,
		}))

		const wrapper = mount(SupportConsoleView, {
			global: {
				stubs: {
					SupportFilterBuilder: SupportFilterBuilderPropsStub,
					SupportTicketTable: SupportTicketTableStub,
				},
			},
		})

		await flushPromises()

		const builder = wrapper.getComponent(SupportFilterBuilderPropsStub)
		expect(builder.props('initialFilterId')).toBe(null)
		expect(builder.props('initialCriteria')).toEqual({ province: 'Sevilla', text: 'seguimiento' })
		expect(ticketsStoreMock.load).toHaveBeenCalledWith('support', { province: 'Sevilla', text: 'seguimiento' })
	})

	it('aplica un filtro de soporte indicado en la query de la ruta', async() => {
		const SupportFilterBuilderPropsStub = defineComponent({
			name: 'SupportFilterBuilder',
			props: {
				initialFilterId: { type: Number, default: null },
				initialCriteria: { type: Object, default: () => ({}) },
			},
			template: `<div class="support-filter-builder-props-stub">{{ initialFilterId }}</div>`,
		})

		bootstrapStoreMock.data = createBootstrapData({
			roles: ['soporte'],
			navigation: [{ id: 'soporte', label: 'Consola de soporte', route: '/soporte', visible: true }],
		})
		routeState.path = '/soporte'
		routeState.query = { filterId: '20' }
		supportFiltersStoreMock.items = [
			{ id: 10, name: 'Asignadas a mi', criteria: { assignedUser: '__me__' }, isPredefined: true, active: true, isDefault: true, sortOrder: 10 },
			{ id: 20, name: 'Madrid abiertas', criteria: { province: 'Madrid', status: ['nuevo'] }, isPredefined: false, active: true, isDefault: false, sortOrder: 20 },
		]

		const wrapper = mount(SupportConsoleView, {
			global: {
				stubs: {
					SupportFilterBuilder: SupportFilterBuilderPropsStub,
					SupportTicketTable: SupportTicketTableStub,
				},
			},
		})

		await flushPromises()

		const builder = wrapper.getComponent(SupportFilterBuilderPropsStub)
		expect(builder.props('initialFilterId')).toBe(20)
		expect(ticketsStoreMock.load).toHaveBeenCalledWith('support', { province: 'Madrid', status: ['nuevo'] })
	})

	it('muestra acciones principales y editor de columnas en la consola de soporte', async() => {
		bootstrapStoreMock.data = createBootstrapData({
			roles: ['soporte'],
			navigation: [{ id: 'soporte', label: 'Consola de soporte', route: '/soporte', visible: true }],
		})
		supportFiltersStoreMock.items = [{ id: 10, name: 'Asignadas a mi', criteria: { assignedUser: '__me__' }, isPredefined: true, active: true, isDefault: true, sortOrder: 10 }]
		ticketsStoreMock.items = [createTicket({ id: 100 })]

		const wrapper = mount(SupportConsoleView, {
			global: {
				stubs: {
					SupportFilterBuilder: SupportFilterBuilderStub,
					SupportTicketTable: SupportTicketTableStub,
				},
			},
		})

		await flushPromises()

		expect(supportFiltersStoreMock.load).toHaveBeenCalled()
		expect(ticketsStoreMock.load).toHaveBeenCalledWith('support', expect.any(Object))
		expect(wrapper.text()).toContain('Nuevo ticket')
		expect(wrapper.text()).toContain('Editar columnas')
		expect(wrapper.text()).toContain('Exportar CSV')

		await wrapper.get('button').trigger('click')
		await wrapper.get('button:nth-of-type(2)').trigger('click')
		expect(wrapper.text()).toContain('Número de ticket')
		expect(wrapper.text()).toContain('Última modificación')
		expect(wrapper.text()).toContain('Provincia')
		expect(wrapper.text()).toContain('Restaurar por defecto')
		expect(wrapper.text()).toContain('Listo')
	})

	it('migra el estado guardado antiguo a las nuevas columnas por defecto de soporte', async() => {
		bootstrapStoreMock.data = createBootstrapData({
			roles: ['soporte'],
			navigation: [{ id: 'soporte', label: 'Consola de soporte', route: '/soporte', visible: true }],
		})
		supportFiltersStoreMock.items = [{ id: 10, name: 'Asignadas a mi', criteria: { assignedUser: '__me__' }, isPredefined: true, active: true, isDefault: true, sortOrder: 10 }]
		ticketsStoreMock.items = [createTicket({ id: 100 })]
		window.localStorage.setItem('legal_advice:support_console_state', JSON.stringify({
			visibleColumns: ['number', 'updatedAt', 'assignment', 'createdBy', 'title', 'userDescription'],
			columnEditorOrder: ['number', 'updatedAt', 'assignment', 'createdBy', 'title', 'userDescription', 'status', 'urgency', 'createdAt'],
			criteria: {},
			sortKey: 'updatedAt',
			sortDirection: 'desc',
			selectedFilterId: null,
		}))

		const wrapper = mount(SupportConsoleView, {
			global: {
				stubs: {
					SupportFilterBuilder: SupportFilterBuilderStub,
					SupportTicketTable: SupportTicketTableStub,
				},
			},
		})

		await flushPromises()
		await wrapper.get('button:nth-of-type(2)').trigger('click')

		const provinceRow = wrapper.findAll('.gi-support-column-editor__item').find((row) => row.text().includes('Provincia'))
		expect(provinceRow).toBeTruthy()
		const provinceCheckbox = provinceRow!.get('input[type="checkbox"]')
		expect((provinceCheckbox.element as HTMLInputElement).checked).toBe(true)

		await provinceCheckbox.setValue(false)
		expect((provinceCheckbox.element as HTMLInputElement).checked).toBe(false)
		await provinceCheckbox.setValue(true)
		expect((provinceCheckbox.element as HTMLInputElement).checked).toBe(true)
	})

	it('muestra la pantalla de nuevo ticket de soporte con provincia y asignacion', async() => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['soporte'] })

		const wrapper = mount(SupportNewTicketView, {
			global: {
				stubs: {
					SearchableSelect: SearchableSelectStub,
					TicketForm: TicketFormStub,
				},
			},
		})
		await flushPromises()

		expect(wrapper.text()).toContain('Nuevo ticket')
		expect(wrapper.text()).toContain('Alta rápida desde soporte')
		expect(wrapper.text()).toContain('Provincia')
		expect(wrapper.text()).toContain('Asignado a grupo')
		expect(wrapper.text()).toContain('Asignado a usuario')
		expect(wrapper.text()).toContain('Cancelar')
		expect(wrapper.text()).toContain('Madrid')
		expect(wrapper.text()).toContain('Grupo Soporte')
		expect(wrapper.text()).toContain('Soporte Uno')
	})

	it('permite crear tickets de soporte sin provincia', async() => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['soporte'] })

		const wrapper = mount(SupportNewTicketView, {
			global: {
				stubs: {
					SearchableSelect: SearchableSelectStub,
					TicketForm: TicketFormStub,
				},
			},
		})

		wrapper.getComponent(TicketFormStub).vm.$emit('submit', {
			title: 'Ticket soporte',
			userDescription: '<p>Texto</p>',
			urgencyId: 1,
			personalData: { email: 'soporte@example.com' },
		})
		await flushPromises()

		expect(ticketsStoreMock.create).toHaveBeenCalledWith(expect.objectContaining({
			province: null,
		}))
	})

	it('muestra la pantalla completa de soporte con acciones de vuelta', async() => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['soporte'] })
		routeState.path = '/soporte/100/completo'
		routeState.params = { ticketId: '100' }
		ticketsStoreMock.selected = createTicket({ id: 100 })

		const wrapper = mount(SupportTicketFullView, {
			global: {
				stubs: {
					TicketSidebarPanel: TicketSidebarPanelStub,
				},
			},
		})

		await flushPromises()

		expect(ticketsStoreMock.select).toHaveBeenCalledWith(100)
		expect(wrapper.text()).toContain('Volver al ticket')
		expect(wrapper.text()).toContain('Volver a consola')
	})

	it('guarda cambios de estado desde la pantalla completa de soporte', async() => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['soporte'] })
		routeState.path = '/soporte/100/completo'
		routeState.params = { ticketId: '100' }
		ticketsStoreMock.selected = createTicket({ id: 100, canManage: true, status: 'nuevo' })

		const wrapper = mount(SupportTicketFullView, {
			global: {
				stubs: {
					TicketSidebarPanel: TicketSidebarPanelStub,
				},
			},
		})

		await flushPromises()

		const panel = wrapper.getComponent(TicketSidebarPanelStub)
		panel.vm.$emit('save', { status: 'en_espera_usuario' })
		await flushPromises()

		expect(ticketsStoreMock.update).toHaveBeenCalledWith(100, { status: 'en_espera_usuario' })
	})

	it('al comentar desde soporte completo puede pasar el ticket a en espera de usuario sin guardar aparte', async() => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['soporte'] })
		routeState.path = '/soporte/100/completo'
		routeState.params = { ticketId: '100' }
		ticketsStoreMock.selected = createTicket({ id: 100, canManage: true, status: 'nuevo' })

		const wrapper = mount(SupportTicketFullView, {
			global: {
				stubs: {
					TicketSidebarPanel: TicketSidebarPanelStub,
				},
			},
		})

		await flushPromises()

		const panel = wrapper.getComponent(TicketSidebarPanelStub)
		panel.vm.$emit('comment', { body: '<p>Seguimiento</p>', visibility: 'publico', files: [], links: [], waitForUser: true })
		await flushPromises()

		expect(ticketsStoreMock.comment).toHaveBeenCalledWith(100, expect.objectContaining({ body: '<p>Seguimiento</p>' }))
		expect(ticketsStoreMock.update).toHaveBeenCalledWith(100, { status: 'en_espera_usuario' })
	})

	it('al asignarse a si mismo desde soporte completo limpia el grupo anterior incompatible', async() => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['soporte', 'administrador'] })
		routeState.path = '/soporte/100/completo'
		routeState.params = { ticketId: '100' }
		ticketsStoreMock.selected = createTicket({ id: 100, canManage: true, assignedGroupId: 'grupo-soporte' })

		const wrapper = mount(SupportTicketFullView, {
			global: {
				stubs: {
					TicketSidebarPanel: TicketSidebarPanelStub,
				},
			},
		})

		await flushPromises()

		const panel = wrapper.getComponent(TicketSidebarPanelStub)
		panel.vm.$emit('assign-to-me', { assignedUserUid: 'usuario1', assignedGroupId: null })
		await flushPromises()

		expect(ticketsStoreMock.update).toHaveBeenCalledWith(100, { assignedUserUid: 'usuario1', assignedGroupId: null })
	})

	it('configura el panel lateral en modo soporte con edición habilitada', async() => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['soporte'] })
		routeState.path = '/soporte/100'
		routeState.params = { ticketId: '100' }
		ticketsStoreMock.selected = createTicket({ id: 100, canManage: true })

		const wrapper = mount(TicketSidebarView, {
			global: {
				stubs: {
					TicketSidebarPanel: TicketSidebarPanelStub,
				},
			},
		})

		await flushPromises()

		const panel = wrapper.getComponent(TicketSidebarPanelStub)
		expect(panel.props('readOnly')).toBe(false)
		expect(panel.props('showRepeat')).toBe(false)
		expect(panel.props('showFullscreen')).toBe(true)
		expect(panel.props('initialComposerVisible')).toBe(false)
	})

	it('guarda cambios de estado desde el panel lateral de soporte', async() => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['soporte'] })
		routeState.path = '/soporte/100'
		routeState.params = { ticketId: '100' }
		ticketsStoreMock.selected = createTicket({ id: 100, canManage: true, status: 'nuevo' })

		const wrapper = mount(TicketSidebarView, {
			global: {
				stubs: {
					TicketSidebarPanel: TicketSidebarPanelStub,
				},
			},
		})

		await flushPromises()

		const panel = wrapper.getComponent(TicketSidebarPanelStub)
		panel.vm.$emit('save', { status: 'en_espera_usuario' })
		await flushPromises()

		expect(ticketsStoreMock.update).toHaveBeenCalledWith(100, { status: 'en_espera_usuario' })
	})

	it('al comentar desde el panel lateral de soporte puede pasar el ticket a en espera de usuario sin guardar aparte', async() => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['soporte'] })
		routeState.path = '/soporte/100'
		routeState.params = { ticketId: '100' }
		ticketsStoreMock.selected = createTicket({ id: 100, canManage: true, status: 'nuevo' })

		const wrapper = mount(TicketSidebarView, {
			global: {
				stubs: {
					TicketSidebarPanel: TicketSidebarPanelStub,
				},
			},
		})

		await flushPromises()

		const panel = wrapper.getComponent(TicketSidebarPanelStub)
		panel.vm.$emit('comment', { body: '<p>Seguimiento</p>', visibility: 'publico', files: [], links: [], waitForUser: true })
		await flushPromises()

		expect(ticketsStoreMock.comment).toHaveBeenCalledWith(100, expect.objectContaining({ body: '<p>Seguimiento</p>' }))
		expect(ticketsStoreMock.update).toHaveBeenCalledWith(100, { status: 'en_espera_usuario' })
	})

	it('al asignarse a si mismo desde el panel lateral limpia el grupo anterior incompatible', async() => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['soporte', 'administrador'] })
		routeState.path = '/soporte/100'
		routeState.params = { ticketId: '100' }
		ticketsStoreMock.selected = createTicket({ id: 100, canManage: true, assignedGroupId: 'grupo-soporte' })

		const wrapper = mount(TicketSidebarView, {
			global: {
				stubs: {
					TicketSidebarPanel: TicketSidebarPanelStub,
				},
			},
		})

		await flushPromises()

		const panel = wrapper.getComponent(TicketSidebarPanelStub)
		panel.vm.$emit('assign-to-me', { assignedUserUid: 'usuario1', assignedGroupId: null })
		await flushPromises()

		expect(ticketsStoreMock.update).toHaveBeenCalledWith(100, { assignedUserUid: 'usuario1', assignedGroupId: null })
	})

	it('configura el panel lateral en modo usuario con repetición y solo lectura', async() => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['usuario'] })
		routeState.path = '/mis-incidencias/100'
		routeState.params = { ticketId: '100' }
		ticketsStoreMock.selected = createTicket({ id: 100, canManage: true })

		const wrapper = mount(TicketSidebarView, {
			global: {
				stubs: {
					TicketSidebarPanel: TicketSidebarPanelStub,
				},
			},
		})

		await flushPromises()

		const panel = wrapper.getComponent(TicketSidebarPanelStub)
		expect(panel.props('readOnly')).toBe(true)
		expect(panel.props('showRepeat')).toBe(true)
	})
})