import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, nextTick, ref } from 'vue'
import { vi } from 'vitest'
import SupportTicketTable from '@/components/SupportTicketTable.vue'
import TicketList from '@/components/TicketList.vue'
import TicketForm from '@/components/TicketForm.vue'
import TicketSidebarPanel from '@/components/TicketSidebarPanel.vue'
import TicketCommentComposer from '@/components/TicketCommentComposer.vue'
import RichTextEditor from '@/components/RichTextEditor.vue'
import { AttachmentPickerStub, RichTextContentStub, RichTextEditorStub, SearchableSelectStub, TypeCascadeSelectorStub } from './helpers/stubs'
import { createAttachment, createBootstrapData, createComment, createTicket } from './helpers/testData'

describe('Contratos visibles de componentes de pantalla', () => {
	it('TicketForm muestra los campos principales y el botón de crear ticket', async() => {
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
				initialTab: 'detail',
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
		const closeReasonEditor = wrapper.get('.gi-textarea--plain')
		;(closeReasonEditor.element as HTMLDivElement).innerText = 'Cierre validado en prueba'
		await closeReasonEditor.trigger('input')
		await nextTick()
		await wrapper.get('button.gi-primary-button').trigger('click')

		expect(wrapper.emitted('save')).toBeTruthy()
		expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({ status: 'cerrado', closeReason: 'Cierre validado en prueba' })
	})

	it('TicketSidebarPanel emite el nuevo estado al guardar cambios de soporte', async() => {
		const bootstrap = createBootstrapData({ roles: ['soporte'] })
		const wrapper = mount(TicketSidebarPanel, {
			props: {
				ticket: createTicket({ status: 'nuevo', comments: [createComment()], canManage: true, canComment: true }),
				roles: ['soporte'],
				users: bootstrap.assignables.users,
				groups: bootstrap.assignables.groups,
				currentUserUid: 'usuario1',
				statuses: bootstrap.catalogs.statuses,
				urgencies: bootstrap.catalogs.urgencies,
				initialTab: 'detail',
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

		const selects = wrapper.findAllComponents(SearchableSelectStub)
		selects[0].vm.$emit('update:modelValue', 'en_espera_usuario')
		await nextTick()
		await wrapper.get('button.gi-primary-button').trigger('click')

		expect(wrapper.emitted('save')).toBeTruthy()
		expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({ status: 'en_espera_usuario' })
	})

	it('TicketSidebarPanel guarda cuando solo cambia el estado usando el selector real', async() => {
		const bootstrap = createBootstrapData({ roles: ['soporte'] })
		const wrapper = mount(TicketSidebarPanel, {
			props: {
				ticket: createTicket({ status: 'nuevo', comments: [createComment()], canManage: true, canComment: true }),
				roles: ['soporte'],
				users: bootstrap.assignables.users,
				groups: bootstrap.assignables.groups,
				currentUserUid: 'usuario1',
				statuses: bootstrap.catalogs.statuses,
				urgencies: bootstrap.catalogs.urgencies,
				initialTab: 'detail',
				readOnly: false,
			},
			global: {
				stubs: {
					AttachmentPicker: AttachmentPickerStub,
					RichTextEditor: RichTextEditorStub,
					RichTextContent: RichTextContentStub,
				},
			},
		})

		const statusTrigger = wrapper.findAll('.gi-search-select__trigger')[0]
		await statusTrigger.trigger('click')
		await nextTick()

		const waitStatusOption = wrapper.findAll('.gi-search-select__option').find((option) => option.text().includes('En espera usuario'))
		expect(waitStatusOption).toBeTruthy()
		await waitStatusOption!.trigger('click')
		await nextTick()

		await wrapper.get('button.gi-primary-button').trigger('click')

		expect(wrapper.emitted('save')).toBeTruthy()
		expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({ status: 'en_espera_usuario' })
	})

	it('TicketSidebarPanel asigna estado asignado en frontend cuando solo cambia la asignación', async() => {
		const bootstrap = createBootstrapData({ roles: ['soporte'] })
		const wrapper = mount(TicketSidebarPanel, {
			props: {
				ticket: createTicket({
					status: 'en_progreso',
					assignedUserUid: 'soporte1',
					assignedGroupId: 'grupo-soporte',
					comments: [createComment()],
					canManage: true,
					canComment: true,
				}),
				roles: ['soporte'],
				users: bootstrap.assignables.users,
				groups: bootstrap.assignables.groups,
				currentUserUid: 'usuario1',
				statuses: bootstrap.catalogs.statuses,
				urgencies: bootstrap.catalogs.urgencies,
				initialTab: 'detail',
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

		const selects = wrapper.findAllComponents(SearchableSelectStub)
		selects[2].vm.$emit('update:modelValue', 'soporte2')
		await nextTick()
		await wrapper.get('button.gi-primary-button').trigger('click')

		expect(wrapper.emitted('save')).toBeTruthy()
		expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({ assignedUserUid: 'soporte2', status: 'asignado' })
	})

	it('TicketSidebarPanel envía status cuando cambia asignación y estado de forma explícita', async() => {
		const bootstrap = createBootstrapData({ roles: ['soporte'] })
		const wrapper = mount(TicketSidebarPanel, {
			props: {
				ticket: createTicket({
					status: 'nuevo',
					assignedUserUid: 'soporte1',
					assignedGroupId: 'grupo-soporte',
					comments: [createComment()],
					canManage: true,
					canComment: true,
				}),
				roles: ['soporte'],
				users: bootstrap.assignables.users,
				groups: bootstrap.assignables.groups,
				currentUserUid: 'usuario1',
				statuses: bootstrap.catalogs.statuses,
				urgencies: bootstrap.catalogs.urgencies,
				initialTab: 'detail',
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

		const selects = wrapper.findAllComponents(SearchableSelectStub)
		selects[2].vm.$emit('update:modelValue', 'soporte2')
		selects[0].vm.$emit('update:modelValue', 'en_espera_usuario')
		await nextTick()
		await wrapper.get('button.gi-primary-button').trigger('click')

		expect(wrapper.emitted('save')).toBeTruthy()
		expect(wrapper.emitted('save')?.[0]?.[0]).toMatchObject({ assignedUserUid: 'soporte2', status: 'en_espera_usuario' })
	})

	it('TicketSidebarPanel revierte el estado y desactiva guardar al restaurar la asignación original', async() => {
		const bootstrap = createBootstrapData({ roles: ['soporte'] })
		const wrapper = mount(TicketSidebarPanel, {
			props: {
				ticket: createTicket({
					status: 'en_progreso',
					assignedUserUid: 'soporte1',
					assignedGroupId: null,
					comments: [createComment()],
					canManage: true,
					canComment: true,
				}),
				roles: ['soporte'],
				users: bootstrap.assignables.users,
				groups: bootstrap.assignables.groups,
				currentUserUid: 'usuario1',
				statuses: bootstrap.catalogs.statuses,
				urgencies: bootstrap.catalogs.urgencies,
				initialTab: 'detail',
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

		const selects = wrapper.findAllComponents(SearchableSelectStub)
		selects[2].vm.$emit('update:modelValue', 'soporte2')
		await nextTick()
		expect(wrapper.findAllComponents(SearchableSelectStub)[0].props('modelValue')).toBe('asignado')
		expect(wrapper.get('button.gi-primary-button').attributes('disabled')).toBeUndefined()

		selects[2].vm.$emit('update:modelValue', 'soporte1')
		await nextTick()

		expect(wrapper.findAllComponents(SearchableSelectStub)[0].props('modelValue')).toBe('en_progreso')
		expect(wrapper.get('button.gi-primary-button').attributes('disabled')).toBeDefined()
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

	it('TicketSidebarPanel oculta editar y eliminar comentarios en Mis tickets aunque el backend mande capacidades de soporte', async() => {
		const bootstrap = createBootstrapData({ roles: ['administrador', 'soporte', 'usuario'] })
		const wrapper = mount(TicketSidebarPanel, {
			props: {
				ticket: createTicket({
					canManage: true,
					canComment: true,
					comments: [createComment({ canEdit: true, canDelete: true })],
				}),
				roles: ['usuario'],
				users: bootstrap.assignables.users,
				groups: bootstrap.assignables.groups,
				currentUserUid: 'usuario1',
				statuses: bootstrap.catalogs.statuses,
				urgencies: bootstrap.catalogs.urgencies,
				readOnly: true,
				initialTab: 'comments',
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

		await nextTick()

		expect(wrapper.text()).not.toContain('Editar comentario')
		expect(wrapper.text()).not.toContain('Eliminar comentario')
		expect(wrapper.findAll('.gi-sidebar-panel__comment-icon-button').length).toBe(1)
		expect(wrapper.text()).not.toContain('Solicitante')
		expect(wrapper.find('.gi-sidebar-panel__save-button').exists()).toBe(false)
	})

	it('TicketSidebarPanel muestra responder en soporte y permite exportar comentarios', async() => {
		const bootstrap = createBootstrapData({ roles: ['soporte'] })
		const createObjectUrlMock = vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:comentarios')
		const revokeObjectUrlMock = vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => undefined)
		const clickMock = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined)
		const originalBlob = globalThis.Blob
		let exportedCsv = ''
		class BlobCapture {
			public readonly parts: unknown[]

			public constructor(parts: unknown[]) {
				this.parts = parts
				exportedCsv = parts.map((part) => typeof part === 'string' ? part : String(part)).join('')
			}
		}
		vi.stubGlobal('Blob', BlobCapture as unknown as typeof Blob)
		const exportedComment = createComment({
			id: 201,
			createdAt: 1_711_000_500,
			body: '<p>Segundo comentario</p>',
			attachments: [
				createAttachment({ id: 901, originalName: 'informe.pdf' }),
				createAttachment({ id: 902, originalName: 'captura.png' }),
			],
		})

		const wrapper = mount(TicketSidebarPanel, {
			props: {
				ticket: createTicket({ comments: [createComment(), exportedComment], canManage: true, canComment: true }),
				roles: ['soporte'],
				users: bootstrap.assignables.users,
				groups: bootstrap.assignables.groups,
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

		expect(wrapper.text()).toContain('Responder')
		expect(wrapper.find('.gi-sidebar-panel__reply-button').exists()).toBe(true)
		expect(wrapper.find('.gi-sidebar-panel__accordion-summary').exists()).toBe(false)

		await wrapper.get('button.gi-sidebar-panel__reply-button').trigger('click')
		await nextTick()
		expect(wrapper.find('.gi-sidebar-panel__reply-button').exists()).toBe(false)
		expect(wrapper.findAll('button').some((button) => button.text() === 'Enviar')).toBe(true)
		expect(wrapper.findAll('button').some((button) => button.text() === 'Adjuntar archivo')).toBe(true)

		await wrapper.setProps({ ticket: createTicket({ id: 101, comments: [createComment({ id: 301 }), exportedComment], canManage: true, canComment: true }) })
		await nextTick()

		expect(wrapper.find('.gi-sidebar-panel__reply-button').exists()).toBe(true)
		await wrapper.get('button.gi-sidebar-panel__comments-mobile-toggle').trigger('click')
		await nextTick()

		const exportButton = wrapper.findAll('button').find((button) => button.text() === 'Exportar comentarios')
		expect(exportButton).toBeDefined()
		await exportButton!.trigger('click')

		expect(createObjectUrlMock).toHaveBeenCalled()
		expect(exportedCsv).toContain('"Adjuntos"')
		expect(exportedCsv).toContain('"informe.pdf|captura.png"')
		expect(clickMock).toHaveBeenCalled()
		expect(revokeObjectUrlMock).toHaveBeenCalledWith('blob:comentarios')

		vi.stubGlobal('Blob', originalBlob)
		createObjectUrlMock.mockRestore()
		revokeObjectUrlMock.mockRestore()
		clickMock.mockRestore()
	})

	it('TicketSidebarPanel confirma antes de abrir un adjunto por URL', async() => {
		const bootstrap = createBootstrapData({ roles: ['usuario'] })
		const openMock = vi.spyOn(window, 'open').mockImplementation(() => null)
		const urlAttachment = createAttachment({
			id: 950,
			originalName: 'video externo',
			sourceUrl: 'https://example.com/evidencia',
		})
		const wrapper = mount(TicketSidebarPanel, {
			props: {
				ticket: createTicket({
					attachments: [urlAttachment],
					canManage: false,
					canComment: true,
				}),
				roles: ['usuario'],
				users: bootstrap.assignables.users,
				groups: bootstrap.assignables.groups,
				currentUserUid: 'usuario1',
				statuses: bootstrap.catalogs.statuses,
				urgencies: bootstrap.catalogs.urgencies,
				readOnly: true,
				initialTab: 'attachments',
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

		await wrapper.get('button.gi-attachment-link').trigger('click')
		await nextTick()

		expect(wrapper.text()).toContain('Abrir enlace externo')
		expect(wrapper.text()).toContain('https://example.com/evidencia')
		await wrapper.get('.gi-dialog__footer .gi-primary-button').trigger('click')
		expect(openMock).toHaveBeenCalledWith('https://example.com/evidencia', '_blank', 'noopener,noreferrer')

		openMock.mockRestore()
	})

	it('TicketSidebarPanel pregunta en soporte si quiere pasar el ticket a espera de usuario al enviar comentario', async() => {
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
				readOnly: false,
				initialTab: 'comments',
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

		await wrapper.get('.gi-sidebar-panel__reply-button').trigger('click')
		await wrapper.get('.rich-text-editor-stub').setValue('<p>Seguimiento</p>')
		await nextTick()
		const sendButton = wrapper.findAll('button').find((button) => button.text() === 'Enviar')
		expect(sendButton).toBeDefined()
		await sendButton!.trigger('click')

		expect(wrapper.text()).toContain('¿Quieres pasar el ticket a en espera de usuario al enviar este comentario?')
		const yesButton = wrapper.findAll('button').find((button) => button.text() === 'Sí')
		expect(yesButton).toBeDefined()
		await yesButton!.trigger('click')

		expect(wrapper.emitted('comment')?.[0]?.[0]).toMatchObject({ waitForUser: true })
	})

	it('TicketSidebarPanel no muestra el dialogo de espera de usuario para comentarios internos', async() => {
		const bootstrap = createBootstrapData({ roles: ['soporte', 'usuario'] })
		const wrapper = mount(TicketSidebarPanel, {
			props: {
				ticket: createTicket({
					canManage: true,
					canComment: true,
					comments: [createComment({ id: 1, body: '<p>Comentario inicial</p>' })],
				}),
				roles: ['soporte'],
				users: bootstrap.assignables.users,
				groups: bootstrap.assignables.groups,
				types: bootstrap.catalogs.types,
				currentUserUid: 'usuario1',
				statuses: bootstrap.catalogs.statuses,
				urgencies: bootstrap.catalogs.urgencies,
				readOnly: false,
				initialTab: 'comments',
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

		await wrapper.get('.gi-sidebar-panel__reply-button').trigger('click')
		await wrapper.get('.rich-text-editor-stub').setValue('<p>Nota interna</p>')
		await nextTick()
		const visibilitySelect = wrapper.findAllComponents(SearchableSelectStub).find((component) => component.props('placeholder') === 'Visibilidad')
		expect(visibilitySelect).toBeDefined()
		await visibilitySelect!.vm.$emit('update:modelValue', 'interno')
		await nextTick()
		const sendButton = wrapper.findAll('button').find((button) => button.text() === 'Enviar')
		expect(sendButton).toBeDefined()
		await sendButton!.trigger('click')

		expect(wrapper.text()).not.toContain('¿Quieres pasar el ticket a en espera de usuario al enviar este comentario?')
		expect(wrapper.emitted('comment')?.[0]?.[0]).toMatchObject({ visibility: 'interno' })
		expect(wrapper.emitted('comment')?.[0]?.[0]).not.toHaveProperty('waitForUser')
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
				initialTab: 'detail',
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
		expect(wrapper.text()).toContain('Responder')
		expect(wrapper.text()).toContain('Adjuntar archivo')

		await wrapper.get('.gi-sidebar-panel__comments-mobile-toggle').trigger('click')
		expect(wrapper.text()).toContain('Expandir comentarios')
	})

	it('TicketCommentComposer abre el selector de archivo aunque el picker se monte después de pedirlo', async() => {
		const openFileDialog = vi.fn()
		const DeferredAttachmentPickerStub = {
			name: 'AttachmentPicker',
			props: {
				modelValue: { type: Object, default: () => ({ files: [], links: [] }) },
				allowedExtensions: { type: Array, default: () => [] },
				maxFileSizeMb: { type: Number, default: 25 },
				showToolbar: { type: Boolean, default: true },
				showUrlAction: { type: Boolean, default: true },
				showHelperInfo: { type: Boolean, default: true },
			},
			setup() {
				return { openFileDialog }
			},
			template: '<div class="attachment-picker-stub">Adjuntar archivos</div>',
		}

		const Harness = defineComponent({
			components: { TicketCommentComposer },
			setup() {
				const attachmentsVisible = ref(false)
				const attachmentsDraft = ref({ files: [] as File[], links: [] })

				function handleShowAttachments() {
					attachmentsVisible.value = true
				}

				return {
					attachmentsVisible,
					attachmentsDraft,
					handleShowAttachments,
				}
			},
			template: `
				<TicketCommentComposer
					ref="composer"
					:model-value="''"
					:attachments-draft="attachmentsDraft"
					:attachments-visible="attachmentsVisible"
					@show-attachments="handleShowAttachments"
				/>
			`,
		})

		const wrapper = mount(Harness, {
			global: {
				stubs: {
					AttachmentPicker: DeferredAttachmentPickerStub,
					RichTextEditor: RichTextEditorStub,
					SearchableSelect: SearchableSelectStub,
				},
			},
		})

		;(wrapper.getComponent(TicketCommentComposer).vm as { openFileAttachment: () => void }).openFileAttachment()
		await nextTick()
		await nextTick()

		expect(openFileDialog).toHaveBeenCalledTimes(1)
	})

	it('RichTextEditor no activa botones de formato al iniciarse vacío', async() => {
		const wrapper = mount(RichTextEditor, {
			props: {
				modelValue: '',
				placeholder: 'Escribe',
			},
		})

		await nextTick()
		await flushPromises()

		expect(wrapper.findAll('.gi-rich-text-editor__tool--active')).toHaveLength(0)
		expect(wrapper.find('.tiptap h2').exists()).toBe(false)
	})
})