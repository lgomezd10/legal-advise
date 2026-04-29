import { flushPromises, mount, shallowMount } from '@vue/test-utils'
import AccessRestrictedView from '@/views/AccessRestrictedView.vue'
import AdminConsoleView from '@/views/AdminConsoleView.vue'
import ConfigurationView from '@/views/ConfigurationView.vue'
import PersonalConfigView from '@/views/PersonalConfigView.vue'
import { adminConfigStoreMock, bootstrapStoreMock, notificationsStoreMock, restorePersonalConfigMock, updatePersonalConfigMock } from './helpers/mockState'
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

		expect(wrapper.text()).toContain('Configuración')
		expect(wrapper.text()).toContain('Configuración personal')
		expect(wrapper.text()).toContain('Configuración de soporte')
		expect(wrapper.text()).toContain('Consola de administración')

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

		const wrapper = mount(PersonalConfigView, {
			global: {
				stubs: {
					NotificationMatrix: NotificationMatrixStub,
				},
			},
		})

		expect((wrapper.get('button.gi-primary-button').element as HTMLButtonElement).disabled).toBe(true)

		expect(wrapper.text()).toContain('Guardar cambios')
		expect(wrapper.text()).toContain('Correo')
		expect(wrapper.text()).toContain('Ciudad')
		expect(wrapper.text()).toContain('Provincia')
		expect(wrapper.text()).toContain('Notificaciones personales')
		expect(notificationsStoreMock.load).toHaveBeenCalled()

		await wrapper.get('input[type="email"]').setValue('nuevo@example.com')
		expect((wrapper.get('button.gi-primary-button').element as HTMLButtonElement).disabled).toBe(false)
		await wrapper.get('button.gi-primary-button').trigger('click')
		await flushPromises()

		expect(updatePersonalConfigMock).toHaveBeenCalledWith({ email: 'nuevo@example.com', city: 'Madrid', province: 'Madrid' })
		expect(bootstrapStoreMock.setPersonalConfig).toHaveBeenCalledWith({ email: 'nuevo@example.com', city: 'Sevilla', province: 'Madrid' }, true)
		expect(wrapper.text()).toContain('Configuración personal guardada.')
	})

	it('mantiene guardar desactivado cuando el formulario coincide con lo cargado', async() => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['usuario'] })

		const wrapper = mount(PersonalConfigView, {
			global: {
				stubs: {
					NotificationMatrix: NotificationMatrixStub,
				},
			},
		})

		const saveButton = wrapper.get('button.gi-primary-button')
		expect((saveButton.element as HTMLButtonElement).disabled).toBe(true)

		await wrapper.get('input[type="email"]').setValue('nuevo@example.com')
		expect((saveButton.element as HTMLButtonElement).disabled).toBe(false)

		await wrapper.get('input[type="email"]').setValue('usuario@example.com')
		expect((saveButton.element as HTMLButtonElement).disabled).toBe(true)
	})

	it('restaura los datos desde Nextcloud cuando existe una configuración guardada', async() => {
		bootstrapStoreMock.data = createBootstrapData({
			roles: ['usuario'],
			personalConfig: { email: 'editado@example.com', city: 'Valencia', province: 'Valencia' },
			personalConfigHasStoredValues: true,
		})

		const wrapper = mount(PersonalConfigView, {
			global: {
				stubs: {
					NotificationMatrix: NotificationMatrixStub,
				},
			},
		})

		const restoreButton = wrapper.get('button.gi-secondary-button')
		expect((restoreButton.element as HTMLButtonElement).disabled).toBe(false)

		await restoreButton.trigger('click')
		await flushPromises()

		expect(restorePersonalConfigMock).toHaveBeenCalled()
		expect(bootstrapStoreMock.setPersonalConfig).toHaveBeenCalledWith({ email: 'usuario@example.com', city: 'Madrid', province: 'Madrid' }, false)
		expect(wrapper.text()).toContain('Configuración personal restaurada desde tu perfil de Nextcloud.')
	})

	it('mantiene desactivado restaurar cuando no hay datos guardados', () => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['usuario'], personalConfigHasStoredValues: false })

		const wrapper = mount(PersonalConfigView, {
			global: {
				stubs: {
					NotificationMatrix: NotificationMatrixStub,
				},
			},
		})

		expect((wrapper.get('button.gi-secondary-button').element as HTMLButtonElement).disabled).toBe(true)
	})

	it('muestra la opción Ninguna en notificaciones personales para soporte y administración', async() => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['soporte', 'administrador'] })
		notificationsStoreMock.items = [{ scopeId: 'soporte1', eventName: 'ticket_unassigned_created', deliveryMode: 'nextcloud' }]

		const wrapper = mount(PersonalConfigView)
		await flushPromises()

		const options = wrapper.findAll('select option').map((option) => option.text())
		expect(options).toContain('Ninguna')
	})

	it('no muestra la opción Ninguna en notificaciones personales para usuario normal', async() => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['usuario'] })
		notificationsStoreMock.items = [{ scopeId: 'usuario1', eventName: 'ticket_created', deliveryMode: 'nextcloud' }]

		const wrapper = mount(PersonalConfigView)
		await flushPromises()

		const options = wrapper.findAll('select option').map((option) => option.text())
		expect(options).not.toContain('Ninguna')
	})

	it('muestra las secciones y acciones clave de la consola de administración', async() => {
		bootstrapStoreMock.data = createBootstrapData({ roles: ['administrador', 'soporte'] })
		adminConfigStoreMock.data = createAdminConfigData()

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
		expect(wrapper.text()).toContain('Recargar')
		for (const section of ['Estados', 'Criticidades', 'Tipos', 'Campos', 'Filtros', 'Reglas', 'Perfiles', 'Adjuntos', 'Notificaciones', 'Tasks']) {
			expect(wrapper.text()).toContain(section)
		}

		await wrapper.get('button.gi-admin-topnav__item:nth-of-type(2)').trigger('click')
		expect(wrapper.text()).toContain('Añadir criticidad')

		await wrapper.get('button.gi-admin-topnav__item:nth-of-type(5)').trigger('click')
		expect(wrapper.text()).toContain('Filtros')

		await wrapper.get('button.gi-admin-topnav__item:nth-of-type(6)').trigger('click')
		expect(wrapper.text()).toContain('Añadir regla')

		await wrapper.get('button.gi-admin-topnav__item:nth-of-type(7)').trigger('click')
		expect(wrapper.text()).toContain('Usuario')
		expect(wrapper.text()).toContain('Soporte')
		expect(wrapper.text()).toContain('Administrador')
		expect(wrapper.text()).toContain('Añadir usuario o grupo')
		expect(wrapper.text()).toContain('Añadir')
		expect(wrapper.text()).not.toContain('Añadir perfil')

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