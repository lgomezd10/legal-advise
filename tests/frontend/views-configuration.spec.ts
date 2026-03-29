import { flushPromises, mount, shallowMount } from '@vue/test-utils'
import AccessRestrictedView from '@/views/AccessRestrictedView.vue'
import AdminConsoleView from '@/views/AdminConsoleView.vue'
import ConfigurationView from '@/views/ConfigurationView.vue'
import PersonalConfigView from '@/views/PersonalConfigView.vue'
import { adminConfigStoreMock, bootstrapStoreMock, notificationsStoreMock, updatePersonalConfigMock } from './helpers/mockState'
import { AdminConsoleViewStub, AdminTypeTreeEditorStub, FilterCatalogEditorStub, NotificationMatrixStub, PersonalConfigViewStub, SearchableSelectStub, SupportSettingsPanelStub } from './helpers/stubs'
import { createAdminConfigData, createBootstrapData } from './helpers/testData'

describe('Pantallas de configuración', () => {
	it('muestra los acordeones de configuración según los roles disponibles', async() => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['usuario', 'soporte', 'administrador'] })

		const wrapper = shallowMount(ConfigurationView, {
			global: {
				stubs: {
					PersonalConfigView: PersonalConfigViewStub,
					SupportSettingsPanel: SupportSettingsPanelStub,
					AdminConsoleView: AdminConsoleViewStub,
				},
			},
		})

		expect(wrapper.text()).toContain('Configuracion')
		expect(wrapper.text()).toContain('Configuracion personal')
		expect(wrapper.text()).toContain('Configuracion de soporte')
		expect(wrapper.text()).toContain('Consola de administracion')

		const triggers = wrapper.findAll('button.gi-config-accordion__trigger')
		await triggers[0].trigger('click')
		await triggers[1].trigger('click')
		await triggers[2].trigger('click')

		expect(wrapper.find('.personal-config-view-stub').exists()).toBe(true)
		expect(wrapper.find('.support-settings-panel-stub').exists()).toBe(true)
		expect(wrapper.find('.admin-console-view-stub').exists()).toBe(true)
	})

	it('muestra campos personales y guarda cambios en la configuración personal', async() => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['usuario'] })

		const wrapper = mount(PersonalConfigView)

		expect(wrapper.text()).toContain('Guardar cambios')
		expect(wrapper.text()).toContain('Correo')
		expect(wrapper.text()).toContain('Ciudad')

		await wrapper.get('input[type="email"]').setValue('nuevo@example.com')
		await wrapper.get('button').trigger('click')
		await flushPromises()

		expect(updatePersonalConfigMock).toHaveBeenCalledWith({ email: 'nuevo@example.com', city: 'Madrid' })
		expect(wrapper.text()).toContain('Configuracion personal guardada.')
	})

	it('muestra las secciones y acciones clave de la consola de administración', async() => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['administrador', 'soporte'] })
		adminConfigStoreMock.data = createAdminConfigData()
		notificationsStoreMock.items = [{ scopeId: 'usuario', eventName: 'ticket_created', channel: 'nextcloud', enabled: true }]

		const wrapper = mount(AdminConsoleView, {
			global: {
				stubs: {
					SearchableSelect: SearchableSelectStub,
					NotificationMatrix: NotificationMatrixStub,
					FilterCatalogEditor: FilterCatalogEditorStub,
					AdminTypeTreeEditor: AdminTypeTreeEditorStub,
				},
			},
		})

		await flushPromises()

		expect(adminConfigStoreMock.load).toHaveBeenCalled()
		expect(notificationsStoreMock.load).toHaveBeenCalled()
		expect(wrapper.text()).toContain('Recargar')
		for (const section of ['Estados', 'Criticidades', 'Tipos', 'Campos', 'Filtros', 'Reglas', 'Perfiles', 'Adjuntos', 'Notificaciones', 'Tasks']) {
			expect(wrapper.text()).toContain(section)
		}

		await wrapper.get('button.gi-admin-topnav__item:nth-of-type(2)').trigger('click')
		expect(wrapper.text()).toContain('Anadir criticidad')

		await wrapper.get('button.gi-admin-topnav__item:nth-of-type(5)').trigger('click')
		expect(wrapper.text()).toContain('Filtros')

		await wrapper.get('button.gi-admin-topnav__item:nth-of-type(6)').trigger('click')
		expect(wrapper.text()).toContain('Anadir regla')

		await wrapper.get('button.gi-admin-topnav__item:nth-of-type(8)').trigger('click')
		expect(wrapper.text()).toContain('Tamano maximo por fichero (MB)')

		await wrapper.get('button.gi-admin-topnav__item:nth-of-type(10)').trigger('click')
		expect(wrapper.text()).toContain('Integracion con Tasks')
	})

	it('muestra la pantalla de acceso restringido', () => {
		const wrapper = mount(AccessRestrictedView)

		expect(wrapper.text()).toContain('Consultas Legales')
		expect(wrapper.text()).toContain('Sin acceso')
		expect(wrapper.text()).toContain('Tu usuario no tiene acceso a ninguna seccion disponible de esta app.')
	})
})