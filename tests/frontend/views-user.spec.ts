import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import NewTicketView from '@/views/NewTicketView.vue'
import UserTicketFullView from '@/views/UserTicketFullView.vue'
import UserTicketsView from '@/views/UserTicketsView.vue'
import { bootstrapStoreMock, routeState, routerPushMock, ticketsStoreMock } from './helpers/mockState'
import { SearchableSelectStub, TicketFormStub, TicketListStub, TicketSidebarPanelStub, TypeCascadeSelectorStub } from './helpers/stubs'
import { createBootstrapData, createTicket } from './helpers/testData'

describe('Pantallas de usuario', () => {
	it('busca en comentarios públicos pero no en comentarios internos', async() => {
		const TicketListResultStub = defineComponent({
			name: 'TicketList',
			props: {
				tickets: { type: Array, default: () => [] },
			},
			template: `<div class="ticket-list-result-stub">{{ tickets.map((ticket) => ticket.number).join(', ') }}</div>`,
		})

		bootstrapStoreMock.data = createBootstrapData({ roles: ['usuario'] })
		ticketsStoreMock.items = [
			createTicket({ id: 1, number: 'TK-PUBLICO', status: 'en_espera_usuario', publicCommentSearchText: 'seguimiento visible' }),
			createTicket({ id: 2, number: 'TK-INTERNO', status: 'en_espera_usuario', publicCommentSearchText: '' }),
		]

		const wrapper = mount(UserTicketsView, {
			global: {
				stubs: {
					TicketList: TicketListResultStub,
				},
			},
		})

		await flushPromises()
		await wrapper.get('input[type="search"]').setValue('seguimiento visible')
		expect(wrapper.text()).toContain('TK-PUBLICO')
		expect(wrapper.text()).not.toContain('TK-INTERNO')

		await wrapper.get('input[type="search"]').setValue('nota interna')
		expect(wrapper.text()).not.toContain('TK-PUBLICO')
		expect(wrapper.text()).not.toContain('TK-INTERNO')
	})

	it('permite filtrar por fecha de última modificación desde la consola de usuario', async() => {
		const TicketListResultStub = defineComponent({
			name: 'TicketList',
			props: {
				tickets: { type: Array, default: () => [] },
			},
			template: `<div class="ticket-list-result-stub">{{ tickets.map((ticket) => ticket.number).join(', ') }}</div>`,
		})

		bootstrapStoreMock.data = createBootstrapData({ roles: ['usuario'] })
		ticketsStoreMock.items = [
			createTicket({ id: 1, number: 'TK-CREADO-ANTES', createdAt: Math.floor(new Date('2024-01-10T10:00:00').getTime() / 1000), updatedAt: Math.floor(new Date('2024-01-12T10:00:00').getTime() / 1000), status: 'en_espera_usuario' }),
			createTicket({ id: 2, number: 'TK-ACTUALIZADO-DESPUES', createdAt: Math.floor(new Date('2024-01-10T10:00:00').getTime() / 1000), updatedAt: Math.floor(new Date('2024-07-15T10:00:00').getTime() / 1000), status: 'en_espera_usuario' }),
		]

		const wrapper = mount(UserTicketsView, {
			global: {
				stubs: {
					TicketList: TicketListResultStub,
				},
			},
		})

		await flushPromises()
		await wrapper.get('.gi-ticket-list-header__date-field-select').setValue('updatedAt')
		await wrapper.get('input[type="date"]').setValue('2024-06-01')

		expect(wrapper.text()).toContain('TK-ACTUALIZADO-DESPUES')
		expect(wrapper.text()).not.toContain('TK-CREADO-ANTES')
	})

	it('muestra la consola de Mis tickets con buscador y secciones principales', async() => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['usuario'] })
		ticketsStoreMock.items = [
			createTicket({ id: 1, status: 'en_espera_usuario', number: 'TK-1' }),
			createTicket({ id: 2, status: 'nuevo', number: 'TK-2' }),
			createTicket({ id: 3, status: 'cerrado', canManage: false, canComment: false, number: 'TK-3' }),
		]

		const wrapper = mount(UserTicketsView, {
			global: {
				stubs: {
					TicketList: TicketListStub,
				},
			},
		})

		await flushPromises()

		expect(ticketsStoreMock.load).toHaveBeenCalledWith('user')
		expect(wrapper.text()).toContain('Nuevo ticket')
		expect(wrapper.text()).toContain('Pendiente de mi')
		expect(wrapper.text()).toContain('Tickets abiertos')
		expect(wrapper.text()).toContain('Tickets cerrados')
		expect(wrapper.text()).toContain('Buscar')
	})

	it('expande Pendiente de mi al entrar si hay tickets pendientes tras la carga', async() => {
		const TicketListResultStub = defineComponent({
			name: 'TicketList',
			props: {
				tickets: { type: Array, default: () => [] },
			},
			template: `<div class="ticket-list-result-stub">{{ tickets.map((ticket) => ticket.number).join(', ') }}</div>`,
		})

		bootstrapStoreMock.data = createBootstrapData({ roles: ['usuario'] })
		ticketsStoreMock.items = []
		ticketsStoreMock.load.mockImplementation(async() => {
			ticketsStoreMock.items = [
				createTicket({ id: 1, number: 'TK-PENDIENTE', status: 'en_espera_usuario' }),
			]
		})

		const wrapper = mount(UserTicketsView, {
			global: {
				stubs: {
					TicketList: TicketListResultStub,
				},
			},
		})

		await flushPromises()

		expect(wrapper.text()).toContain('TK-PENDIENTE')
	})

	it('muestra el paso de seleccion de tipo y provincia al crear un ticket', () => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['usuario'] })

		const wrapper = mount(NewTicketView, {
			global: {
				stubs: {
					SearchableSelect: SearchableSelectStub,
					TypeCascadeSelector: TypeCascadeSelectorStub,
					TicketForm: TicketFormStub,
				},
			},
		})

		expect(wrapper.text()).toContain('Nuevo ticket')
		expect(wrapper.text()).toContain('Seleccion de tipo')
		expect(wrapper.text()).toContain('Provincia')
		expect(wrapper.text()).toContain('Madrid')
		expect(wrapper.text()).toContain('Ruta elegida')
		expect(wrapper.text()).toContain('Continuar')
		expect(wrapper.text()).toContain('Cancelar')
		expect(wrapper.text()).toContain('Añadir provincia')
	})

	it('guarda la última provincia seleccionada para reutilizarla en el siguiente ticket', async() => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['usuario'], personalConfig: { email: 'usuario@example.com', city: 'Madrid', province: 'Madrid' } })
		ticketsStoreMock.draft = {
			selectedPath: [1, 11],
			province: 'Sevilla',
			title: 'Borrador',
			userDescription: '<p>Texto</p>',
			urgencyId: '1',
			personalData: { city: 'Madrid', province: 'Madrid' },
			attachments: { files: [], links: [] },
		}

		const wrapper = mount(NewTicketView, {
			global: {
				stubs: {
					SearchableSelect: SearchableSelectStub,
					TypeCascadeSelector: TypeCascadeSelectorStub,
					TicketForm: TicketFormStub,
				},
			},
		})

		wrapper.getComponent(TicketFormStub).vm.$emit('submit', {
			title: 'Nuevo ticket',
			userDescription: '<p>Texto</p>',
			urgencyId: 1,
			personalData: { email: 'usuario@example.com', city: 'Madrid' },
		})
		await flushPromises()

		expect(ticketsStoreMock.create).toHaveBeenCalledWith(expect.objectContaining({
			province: 'Sevilla',
			personalData: expect.objectContaining({ province: 'Sevilla' }),
		}))
		expect(bootstrapStoreMock.setPersonalConfig).toHaveBeenCalledWith(expect.objectContaining({ province: 'Sevilla' }))
	})

	it('muestra el formulario de detalle cuando ya existe borrador con tipo y provincia', async() => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['usuario'] })
		ticketsStoreMock.draft = {
			selectedPath: [1, 11],
			province: 'Madrid',
			title: 'Borrador',
			userDescription: '<p>Texto</p>',
			urgencyId: '1',
			personalData: { city: 'Madrid' },
			attachments: { files: [], links: [] },
		}

		const wrapper = mount(NewTicketView, {
			global: {
				stubs: {
					SearchableSelect: SearchableSelectStub,
					TypeCascadeSelector: TypeCascadeSelectorStub,
					TicketForm: TicketFormStub,
				},
			},
		})
		await flushPromises()

		expect(wrapper.text()).toContain('Tipo seleccionado')
		expect(wrapper.text()).toContain('Solo Territorial')
		expect(wrapper.text()).toContain('Provincia')
		expect(wrapper.text()).toContain('Madrid')
		expect(wrapper.find('.ticket-form-stub').exists()).toBe(true)
		expect(wrapper.find('button[title="Modificar tipo"]').exists()).toBe(true)
	})

	it('muestra la pantalla completa de ticket de usuario y permite repetir', async() => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['usuario'] })
		routeState.path = '/mis-incidencias/100/completo'
		routeState.params = { ticketId: '100' }
		ticketsStoreMock.selected = createTicket({ id: 100 })

		const wrapper = mount(UserTicketFullView, {
			global: {
				stubs: {
					TicketSidebarPanel: TicketSidebarPanelStub,
				},
			},
		})

		await flushPromises()

		expect(ticketsStoreMock.select).toHaveBeenCalledWith(100)
		expect(wrapper.text()).toContain('Detalle del ticket')
		expect(wrapper.text()).toContain('Volver a mis tickets')

		wrapper.getComponent(TicketSidebarPanelStub).vm.$emit('repeat')
		await flushPromises()

		expect(ticketsStoreMock.replaceDraft).toHaveBeenCalled()
		expect(routerPushMock).toHaveBeenCalledWith('/mis-incidencias/nuevo')
	})
})