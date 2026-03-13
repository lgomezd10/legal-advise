import { defineStore } from 'pinia'
import type { Ticket, TicketDraft } from '@/types'
import { addComment, createTicket, downloadAttachment, exportTickets, fetchTicket, fetchTickets, updateTicket, uploadAttachment } from '@/services/tickets'

type CommentPayload = {
	body: string
	visibility: 'interno' | 'publico'
	files?: File[]
}

export const useTicketsStore = defineStore('tickets', {
	state: () => ({
		items: [] as Ticket[],
		selected: null as Ticket | null,
		draft: null as TicketDraft | null,
		loading: false,
	}),
	actions: {
		async load(scope: 'user' | 'support', criteria: Record<string, unknown> = {}) {
			this.loading = true
			this.items = (await fetchTickets(scope, criteria)).items
			this.loading = false
		},
		async select(ticketId: number) {
			this.selected = await fetchTicket(ticketId)
		},
		async create(payload: Record<string, unknown>) {
			this.selected = await createTicket(payload)
			return this.selected
		},
		async update(ticketId: number, payload: Record<string, unknown>) {
			this.selected = await updateTicket(ticketId, payload)
			this.items = this.items.map((item) => item.id === ticketId ? this.selected as Ticket : item)
			return this.selected
		},
		async comment(ticketId: number, payload: CommentPayload) {
			const comment = await addComment(ticketId, { body: payload.body, visibility: payload.visibility })

			for (const file of payload.files ?? []) {
				await uploadAttachment(ticketId, file, comment.id)
			}

			await this.select(ticketId)
		},
		async download(attachmentId: number) {
			return downloadAttachment(attachmentId)
		},
		async export(scope: 'user' | 'support', criteria: Record<string, unknown>, columns: string[] = []) {
			return exportTickets(scope, criteria, columns)
		},
		replaceDraft(draft: TicketDraft | null) {
			this.draft = draft ? {
				...draft,
				selectedPath: [...(draft.selectedPath ?? [])],
				personalData: { ...(draft.personalData ?? {}) },
			} : null
		},
		mergeDraft(partial: TicketDraft) {
			this.draft = {
				...(this.draft ?? {}),
				...partial,
				selectedPath: [...(partial.selectedPath ?? this.draft?.selectedPath ?? [])],
				personalData: {
					...(this.draft?.personalData ?? {}),
					...(partial.personalData ?? {}),
				},
			}
		},
		clearDraft() {
			this.draft = null
		},
	},
})