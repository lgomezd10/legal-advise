import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { vi } from 'vitest'
import TicketForm from '@/components/TicketForm.vue'
import TicketSidebarPanel from '@/components/TicketSidebarPanel.vue'
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

		expect(wrapper.text()).toContain('Titulo')
		expect(wrapper.text()).toContain('Criticidad')
		expect(wrapper.text()).toContain('Canal de comunicacion')
		expect(wrapper.text()).toContain('Nextcloud')
		expect(wrapper.text()).toContain('Correo')
		expect(wrapper.text()).toContain('Nextcloud y correo')
		expect(wrapper.text()).toContain('Adjuntos iniciales')

		const submitButton = wrapper.get('button.gi-primary-button')
		expect(submitButton.attributes('disabled')).toBeDefined()

		await wrapper.get('input').setValue('Alta nueva')
		await wrapper.get('.rich-text-editor-stub').setValue('<p>Descripcion con contenido</p>')
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
		expect(wrapper.text()).toContain('Asignarme a mi')
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
		expect(wrapper.text()).toContain('Este ticket esta cerrado. Reabre el ticket para volver a actuar sobre el.')
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
})