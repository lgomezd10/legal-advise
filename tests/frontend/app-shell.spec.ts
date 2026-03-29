import { mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import App from '@/App.vue'
import { bootstrapStoreMock, routeState, routerPushMock, supportFiltersStoreMock } from './helpers/mockState'
import { createBootstrapData } from './helpers/testData'

describe('Shell de la aplicacion', () => {
	it('mantiene oculto el submenu de soporte por defecto y permite expandirlo', async() => {
		Object.defineProperty(window, 'innerWidth', {
			configurable: true,
			writable: true,
			value: 1400,
		})

		bootstrapStoreMock.data = createBootstrapData({
			roles: ['soporte'],
			navigation: [
				{ id: 'mis-incidencias', label: 'Mis tickets', route: '/mis-incidencias', visible: true },
				{ id: 'soporte', label: 'Consola de soporte', route: '/soporte', visible: true },
				{ id: 'configuracion', label: 'Configuracion', route: '/configuracion', visible: true },
			],
			supportFilters: [
				{ id: 10, name: 'Asignadas a mi', criteria: { assignedUser: '__me__' }, isPredefined: true, active: true, isDefault: true, sortOrder: 10 },
				{ id: 20, name: 'Madrid abiertas', criteria: { city: 'Madrid' }, isPredefined: false, active: true, isDefault: false, sortOrder: 20 },
			],
		})
		supportFiltersStoreMock.items = [...bootstrapStoreMock.data.supportFilters]
		routeState.path = '/soporte'
		routeState.query = { filterId: '10' }

		const wrapper = mount(App, {
			global: {
				stubs: {
					RouterView: defineComponent({
						name: 'RouterView',
						template: '<div class="router-view-stub" />',
					}),
				},
			},
		})

		expect(wrapper.text()).not.toContain('Asignadas a mi')
		expect(wrapper.text()).not.toContain('Madrid abiertas')

		const supportButton = wrapper.findAll('button').find((button) => button.text().includes('Consola de soporte'))
		expect(supportButton).toBeDefined()
		expect(supportButton!.attributes('aria-expanded')).toBe('false')

		await supportButton!.trigger('click')

		expect(wrapper.text()).toContain('Asignadas a mi')
		expect(wrapper.text()).toContain('Madrid abiertas')
		expect(supportButton!.attributes('aria-expanded')).toBe('true')

		const targetButton = wrapper.findAll('button').find((button) => button.text() === 'Madrid abiertas')
		expect(targetButton).toBeDefined()
		await targetButton!.trigger('click')
		expect(routerPushMock).toHaveBeenCalledWith({ path: '/soporte', query: { filterId: '20' } })

		await supportButton!.trigger('click')
		expect(supportButton!.attributes('aria-expanded')).toBe('false')
		expect(wrapper.text()).not.toContain('Asignadas a mi')
		expect(wrapper.text()).not.toContain('Madrid abiertas')
	})
})