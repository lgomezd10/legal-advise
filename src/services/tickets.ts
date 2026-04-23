import { apiGet, apiPost, apiPut } from './api'
import type { Ticket, TicketAttachmentLinkDraft, TicketComment } from '@/types'

export const fetchTickets = async(scope: 'user' | 'support', criteria: Record<string, unknown> = {}) => apiGet<{ items: Ticket[] }>('/api/v1/tickets', { scope, criteria })
export const fetchTicket = async(id: number) => apiGet<Ticket>(`/api/v1/tickets/${id}`)
export const createTicket = async(payload: Record<string, unknown>) => apiPost<Ticket>('/api/v1/tickets', payload)
export const updateTicket = async(id: number, payload: Record<string, unknown>) => apiPut<Ticket>(`/api/v1/tickets/${id}`, payload)
export const reopenTicket = async(id: number) => apiPost<Ticket>(`/api/v1/tickets/${id}/reopen`)
export const addComment = async(id: number, payload: Record<string, unknown>) => apiPost<TicketComment>(`/api/v1/tickets/${id}/comments`, payload)
export const uploadAttachment = async(id: number, file: File, commentId: number) => {
	const formData = new FormData()
	formData.append('file', file)
	formData.append('commentId', String(commentId))
	return apiPost(`/api/v1/tickets/${id}/attachments`, formData)
}
export const uploadAttachmentUrl = async(id: number, link: TicketAttachmentLinkDraft, commentId: number) => {
	const formData = new FormData()
	formData.append('commentId', String(commentId))
	formData.append('sourceUrl', link.url)
	formData.append('originalName', link.label)
	return apiPost(`/api/v1/tickets/${id}/attachments`, formData)
}
export const downloadAttachment = async(id: number) => apiGet<{ meta: Record<string, unknown>, content: string }>(`/api/v1/attachments/${id}`)
export const exportTickets = async(scope: 'user' | 'support', criteria: Record<string, unknown>, columns: string[] = []) => apiGet<{ filename: string, mimeType: string, content: string }>('/api/v1/export/tickets', { scope, criteria, columns })