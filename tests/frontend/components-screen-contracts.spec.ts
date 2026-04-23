import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { vi } from 'vitest'
import SupportTicketTable from '@/components/SupportTicketTable.vue'
import TicketList from '@/components/TicketList.vue'
import TicketForm from '@/components/TicketForm.vue'
import TicketSidebarPanel from '@/components/TicketSidebarPanel.vue'
import RichTextEditor from '@/components/RichTextEditor.vue'
import { AttachmentPickerStub, RichTextContentStub, RichTextEditorStub, SearchableSelectStub, TypeCascadeSelectorStub } from './helpers/stubs'
import { createBootstrapData, createComment, createTicket } from './helpers/testData'

describe('Contratos visibles de componentes de pantalla', () => {
	it('TicketForm muestra los canales definidos y el botón de crear ticket', async() => {
		const bootstrap = createBootstrapData()
		const wrapper = mount(TicketForm, {
			props: {
				types: bootstrap.catalogs.types,
				fields: bootstrap.catalogs.fields,
				urgencies: bootstrap.catalogs.urgencies,
				initialDraft: {
					selectedPath: [1, 11],
					title: '',
					userDescription: '',
					urgencyId: '1',
					communicationChannel: 'nextcloud_mail',
					personalData: { email: 'usuario@example.com', city: 'Madrid' },
					attachments: { files: [], links: [] },
				},
				lockedTypePath: [1, 11],
				allowedExtensions: ['pdf', 'png'],
				maxFileSizeMb: 25,
			},
			global: {
				stubs: {
					SearchableSelect: SearchableSelectStub,
					RichTextEditor: RichTextEditorStub,
					AttachmentPicker: AttachmentPickerStub,
					TypeCascadeSelector: TypeCascadeSelectorStub,
				},
			},
		})

		expect(wrapper.text()).toContain('Título')
		expect(wrapper.text()).toContain('Criticidad')
		expect(wrapper.text()).toContain('Canal de comunicación')
		expect(wrapper.text()).toContain('Nextcloud')
		expect(wrapper.text()).toContain('Correo')
		expect(wrapper.text()).toContain('Nextcloud y correo')
		expect(wrapper.text()).toContain('Adjuntos iniciales')

		const submitButton = wrapper.get('button.gi-primary-button')
		expect(submitButton.attributes('disabled')).toBeDefined()

		await wrapper.get('input').setValue('Alta nueva')
		await wrapper.get('.rich-text-editor-stub').setValue('<p>Descripción con contenido</p>')
		await nextTick()

		expect(wrapper.get('button.gi-primary-button').attributes('disabled')).toBeUndefined()
	})

	it('TicketSidebarPanel muestra acciones, pestañas y pide motivo de cierre al cerrar un ticket', async() => {
		const bootstrap = createBootstrapData({ roles: ['soporte'] })
		const wrapper = mount(TicketSidebarPanel, {
			props: {
				ticket: createTicket({ comments: [createComment()], canManage: true, canComment: true }),
				roles: ['soporte'],
				users: bootstrap.assignables.users,
				groups: bootstrap.assignables.groups,
				currentUserUid: 'usuario1',
				statuses: bootstrap.catalogs.statuses,
				urgencies: bootstrap.catalogs.urgencies,
				showFullscreen: true,
				showRepeat: true,
				readOnly: false,
			},
			global: {
				stubs: {
					SearchableSelect: SearchableSelectStub,
					AttachmentPicker: AttachmentPickerStub,
					RichTextEditor: RichTextEditorStub,
					RichTextContent: RichTextContentStub,
				},
			},
		})

		expect(wrapper.text()).toContain('Repetir ticket')
		expect(wrapper.text()).toContain('Pantalla completa')
		expect(wrapper.text()).toContain('Asignarme a mí')
		expect(wrapper.text()).toContain('Guardar')
		expect(wrapper.text()).toContain('Detalle')
		expect(wrapper.text()).toContain('Comentarios')
		expect(wrapper.text()).toContain('Historial')
		expect(wrapper.text()).toContain('Asignado a usuario')
		expect(wrapper.text()).toContain('Asignado a grupo')

		const selects = wrapper.findAllComponents(SearchableSelectStub)
		selects[0].vm.$emit('update:modelValue', 'cerrado')
		await nextTick()

		expect(wrapper.text()).toContain('Motivo del cierre')
		await wrapper.get('textarea.gi-textarea--plain').setValue('Cierre validado en prueba')
		await wrapper.get('button.gi-primary-button').trigger('click')

		expect(wrapper.emitted('save')).toBeTruthy()
		expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({ closeReason: 'Cierre validado en prueba' })
	})

	it('TicketSidebarPanel muestra reabrir cuando el ticket puede reabrirse', () => {
		const bootstrap = createBootstrapData({ roles: ['usuario'] })
		const wrapper = mount(TicketSidebarPanel, {
			props: {
				ticket: createTicket({ status: 'cerrado', canManage: false, canComment: false, canReopen: true }),
				roles: ['usuario'],
				users: bootstrap.assignables.users,
				groups: bootstrap.assignables.groups,
				currentUserUid: 'usuario1',
				statuses: bootstrap.catalogs.statuses,
				urgencies: bootstrap.catalogs.urgencies,
				readOnly: true,
			},
			global: {
				stubs: {
					SearchableSelect: SearchableSelectStub,
					AttachmentPicker: AttachmentPickerStub,
					RichTextEditor: RichTextEditorStub,
					RichTextContent: RichTextContentStub,
				},
			},
		})

		expect(wrapper.text()).toContain('Reabrir ticket')
		expect(wrapper.text()).toContain('Este ticket está cerrado. Reabre el ticket para volver a actuar sobre él.')
	})

	it('TicketSidebarPanel oculta por defecto el nuevo comentario en soporte y permite exportar comentarios', async() => {
		const bootstrap = createBootstrapData({ roles: ['soporte'] })
		const createObjectUrlMock = vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:comentarios')
		const revokeObjectUrlMock = vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => undefined)
		const clickMock = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined)

		const wrapper = mount(TicketSidebarPanel, {
			props: {
				ticket: createTicket({ comments: [createComment(), createComment({ id: 201, createdAt: 1_711_000_500, body: '<p>Segundo comentario</p>' })], canManage: true, canComment: true }),
				roles: ['soporte'],
				users: bootstrap.assignables.users,
				groups: bootstrap.assignables.groups,
				currentUserUid: 'usuario1',
				statuses: bootstrap.catalogs.statuses,
				urgencies: bootstrap.catalogs.urgencies,
				readOnly: false,
				initialTab: 'comments',
				initialComposerVisible: false,
			},
			global: {
				stubs: {
					SearchableSelect: SearchableSelectStub,
					AttachmentPicker: AttachmentPickerStub,
					RichTextEditor: RichTextEditorStub,
					RichTextContent: RichTextContentStub,
				},
			},
		})

		expect(wrapper.text()).toContain('Nuevo comentario')
		expect(wrapper.find('button[aria-label="Ocultar nuevo comentario"]').exists()).toBe(false)

		await wrapper.get('button.gi-sidebar-panel__composer-toggle-button').trigger('click')
		expect(wrapper.find('button[aria-label="Ocultar nuevo comentario"]').exists()).toBe(true)

		await wrapper.setProps({ ticket: createTicket({ id: 101, comments: [createComment({ id: 301 })], canManage: true, canComment: true }) })
		await nextTick()

		expect(wrapper.find('button[aria-label="Ocultar nuevo comentario"]').exists()).toBe(false)

		const exportButton = wrapper.findAll('button').find((button) => button.text() === 'Exportar comentarios')
		expect(exportButton).toBeDefined()
		await exportButton!.trigger('click')

		expect(createObjectUrlMock).toHaveBeenCalled()
		expect(clickMock).toHaveBeenCalled()
		expect(revokeObjectUrlMock).toHaveBeenCalledWith('blob:comentarios')

		createObjectUrlMock.mockRestore()
		revokeObjectUrlMock.mockRestore()
		clickMock.mockRestore()
	})

	it('TicketSidebarPanel muestra el tipo seleccionado del ticket', () => {
		const bootstrap = createBootstrapData({ roles: ['soporte', 'usuario'] })
		const wrapper = mount(TicketSidebarPanel, {
			props: {
				ticket: createTicket({ typeId: 11, canManage: true, canComment: true }),
				roles: ['soporte'],
				users: bootstrap.assignables.users,
				groups: bootstrap.assignables.groups,
				types: bootstrap.catalogs.types,
				currentUserUid: 'usuario1',
				statuses: bootstrap.catalogs.statuses,
				urgencies: bootstrap.catalogs.urgencies,
				readOnly: false,
			},
			global: {
				stubs: {
					SearchableSelect: SearchableSelectStub,
					AttachmentPicker: AttachmentPickerStub,
					RichTextEditor: RichTextEditorStub,
					RichTextContent: RichTextContentStub,
				},
			},
		})

		expect(wrapper.text()).toContain('Tipo')
		expect(wrapper.text()).toContain('Necesito asesoramiento > Solo Territorial')
	})

	it('SupportTicketTable muestra la provincia junto al tipo del ticket', () => {
		const bootstrap = createBootstrapData({ roles: ['soporte', 'usuario'] })
		const ticket = createTicket({ typeId: 11, province: 'A Coruña' })

		const userWrapper = mount(TicketList, {
			props: {
				tickets: [ticket],
				emptyLabel: 'Sin tickets',
				statuses: bootstrap.catalogs.statuses,
				types: bootstrap.catalogs.types,
			},
		})

		const supportWrapper = mount(SupportTicketTable, {
			props: {
				tickets: [ticket],
				emptyLabel: 'Sin tickets',
				visibleColumns: ['number', 'province', 'title'],
				types: bootstrap.catalogs.types,
			},
		})

		expect(userWrapper.text()).toContain('Necesito asesoramiento > Solo Territorial')
		expect(supportWrapper.text()).toContain('Provincia')
		expect(supportWrapper.text()).toContain('A Coruña')
		expect(supportWrapper.text()).toContain('A Coruña: Necesito asesoramiento > Solo Territorial')
	})

	it('TicketList y SupportTicketTable fijan en verde la chapa de estado', () => {
		const bootstrap = createBootstrapData({ roles: ['soporte', 'usuario'] })
		const ticket = createTicket({ status: 'en_espera_usuario' })

		const userWrapper = mount(TicketList, {
			props: {
				tickets: [ticket],
				emptyLabel: 'Sin tickets',
				statuses: bootstrap.catalogs.statuses,
				types: bootstrap.catalogs.types,
			},
		})

		const supportWrapper = mount(SupportTicketTable, {
			props: {
				tickets: [ticket],
				emptyLabel: 'Sin tickets',
				visibleColumns: ['status'],
				types: bootstrap.catalogs.types,
			},
		})

		expect(userWrapper.get('.gi-badge').classes()).toContain('gi-badge--success')
		expect(supportWrapper.get('.gi-badge').classes()).toContain('gi-badge--success')
	})

	it('TicketSidebarPanel muestra pestañas de usuario y agrupa opciones de comentarios en Mis tickets', async() => {
		const bootstrap = createBootstrapData({ roles: ['usuario'] })
		const wrapper = mount(TicketSidebarPanel, {
			props: {
				ticket: createTicket({ canManage: false, canComment: true }),
				roles: ['usuario'],
				users: bootstrap.assignables.users,
				groups: bootstrap.assignables.groups,
				types: bootstrap.catalogs.types,
				currentUserUid: 'usuario1',
				readOnly: true,
				showFullscreen: true,
			},
			global: {
				stubs: {
					SearchableSelect: SearchableSelectStub,
					AttachmentPicker: AttachmentPickerStub,
					RichTextEditor: RichTextEditorStub,
					RichTextContent: RichTextContentStub,
				},
			},
		})

		expect(wrapper.findAll('.gi-sidebar-panel__tab').map((tab) => tab.text())).toEqual(['Comentarios', 'Detalles', 'Adjuntos'])
		expect(wrapper.find('.gi-sidebar-panel__comments-toolbar-actions').exists()).toBe(false)

		await wrapper.get('.gi-sidebar-panel__comments-mobile-toggle').trigger('click')
		expect(wrapper.text()).toContain('Expandir comentarios')
	})

	it('RichTextEditor no activa botones de formato al iniciarse vacío', async() => {
		const wrapper = mount(RichTextEditor, {
			props: {
				modelValue: '',
				placeholder: 'Escribe',
			},
		})

		await nextTick()

		expect(wrapper.findAll('.gi-rich-text-editor__tool--active')).toHaveLength(0)
		expect(wrapper.find('.tiptap h2').exists()).toBe(false)
	})
})