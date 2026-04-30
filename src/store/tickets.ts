import { defineStore } from 'pinia'
import type { Ticket, TicketAttachmentLinkDraft, TicketDraft } from '@/types'
import { addComment, createTicket, downloadAttachment, exportTickets, fetchTicket, fetchTickets, reopenTicket, updateTicket, uploadAttachment, uploadAttachmentUrl } from '@/services/tickets'

type CommentPayload = {
	body: string
	visibility: 'interno' | 'publico'
	files?: File[]
	links?: TicketAttachmentLinkDraft[]
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
			const attachments = isAttachmentDraft(payload.attachments) ? payload.attachments : { files: [], links: [] }
			const { attachments: _ignoredAttachments, ...ticketPayload } = payload
			this.selected = await createTicket(ticketPayload)
			if (this.selected && (attachments.files.length > 0 || attachments.links.length > 0)) {
				await this.comment(this.selected.id, { body: '', visibility: 'publico', files: attachments.files, links: attachments.links })
			}
			return this.selected
		},
		async update(ticketId: number, payload: Record<string, unknown>) {
			this.selected = await updateTicket(ticketId, payload)
			this.items = this.items.map((item) => item.id === ticketId ? this.selected as Ticket : item)
			return this.selected
		},
		async comment(ticketId: number, payload: CommentPayload) {
			const comment = await addComment(ticketId, {
				body: payload.body,
				visibility: payload.visibility,
				allowEmpty: payload.body.trim() === '' && ((payload.files?.length ?? 0) > 0 || (payload.links?.length ?? 0) > 0),
			})

			for (const file of payload.files ?? []) {
				await uploadAttachment(ticketId, file, comment.id)
			}

			for (const link of payload.links ?? []) {
				await uploadAttachmentUrl(ticketId, link, comment.id)
			}

			await this.select(ticketId)
			this.items = this.items.map((item) => item.id === ticketId && this.selected ? this.selected : item)
		},
		async reopen(ticketId: number) {
			this.selected = await reopenTicket(ticketId)
			this.items = this.items.map((item) => item.id === ticketId ? this.selected as Ticket : item)
			return this.selected
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

function isAttachmentDraft(value: unknown): value is { files: File[], links: TicketAttachmentLinkDraft[] } {
	if (!value || typeof value !== 'object') {
		return false
	}

	const maybe = value as { files?: unknown, links?: unknown }
	return Array.isArray(maybe.files) && Array.isArray(maybe.links)
}