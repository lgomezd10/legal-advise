import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { createComment, createTicket } from '../helpers/testData'

vi.unmock('@/store/tickets')

const createTicketMock = vi.fn()
const fetchTicketMock = vi.fn()
const addCommentMock = vi.fn()
const uploadAttachmentMock = vi.fn()
const uploadAttachmentUrlMock = vi.fn()

vi.mock('@/services/tickets', () => ({
	createTicket: (...args: unknown[]) => createTicketMock(...args),
	fetchTicket: (...args: unknown[]) => fetchTicketMock(...args),
	addComment: (...args: unknown[]) => addCommentMock(...args),
	uploadAttachment: (...args: unknown[]) => uploadAttachmentMock(...args),
	uploadAttachmentUrl: (...args: unknown[]) => uploadAttachmentUrlMock(...args),
	fetchTickets: vi.fn(),
	updateTicket: vi.fn(),
	reopenTicket: vi.fn(),
	downloadAttachment: vi.fn(),
	exportTickets: vi.fn(),
}))

describe('tickets store', () => {
	beforeEach(() => {
		vi.resetModules()
		setActivePinia(createPinia())
		createTicketMock.mockReset()
		fetchTicketMock.mockReset()
		addCommentMock.mockReset()
		uploadAttachmentMock.mockReset()
		uploadAttachmentUrlMock.mockReset()
	})

	async function createStore() {
		const { useTicketsStore } = await import('@/store/tickets')
		return useTicketsStore()
	}

	it('vincula los adjuntos iniciales al comentario de descripcion creado con el ticket', async() => {
		const store = await createStore()
		const initialComment = createComment({ id: 77, body: '<p>Descripcion inicial</p>', visibility: 'publico', createdAt: 1_700_000_000 })
		const createdTicket = createTicket({ id: 10, comments: [initialComment] })
		const refreshedTicket = createTicket({ id: 10, comments: [initialComment] })
		const file = new File(['hola'], 'evidencia.pdf', { type: 'application/pdf' })
		const link = { url: 'https://example.com/video', label: 'Video' }

		createTicketMock.mockResolvedValue(createdTicket)
		fetchTicketMock.mockResolvedValue(refreshedTicket)

		await store.create({
			title: 'Alta',
			userDescription: '<p>Descripcion inicial</p>',
			attachments: { files: [file], links: [link] },
		})

		expect(addCommentMock).not.toHaveBeenCalled()
		expect(uploadAttachmentMock).toHaveBeenCalledWith(10, file, 77)
		expect(uploadAttachmentUrlMock).toHaveBeenCalledWith(10, link, 77)
		expect(fetchTicketMock).toHaveBeenCalledWith(10)
	})

	it('mantiene el fallback a comentario vacio cuando la respuesta de creacion no trae comentario inicial', async() => {
		const store = await createStore()
		const createdTicket = createTicket({ id: 12, comments: [] })
		const file = new File(['hola'], 'evidencia.pdf', { type: 'application/pdf' })

		createTicketMock.mockResolvedValue(createdTicket)
		addCommentMock.mockResolvedValue({ id: 301 })

		await store.create({
			title: 'Alta',
			userDescription: '<p>Descripcion inicial</p>',
			attachments: { files: [file], links: [] },
		})

		expect(addCommentMock).toHaveBeenCalledWith(12, expect.objectContaining({
			body: '',
			visibility: 'publico',
			allowEmpty: true,
		}))
	})
})