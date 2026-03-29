export interface NavigationItem {
	id: string
	label: string
	route: string
	visible: boolean
}

export interface CatalogField {
	id?: number
	fieldKey: string
	label: string
	fieldType: string
	required: boolean
	preloadSource?: string
	sortOrder: number
	active: boolean
}

export interface UrgencyCatalogItem {
	id?: number
	name: string
	weight: number
	color: string
	restrictions?: Record<string, unknown> | null
	active: boolean
}

export interface TypeNode {
	id: number
	parentId?: number | null
	name: string
	slug: string
	level: number
	sortOrder: number
	active: boolean
	children: TypeNode[]
}

export interface EditableTypeNode {
	id?: number
	parentId?: number | null
	name: string
	slug?: string
	level: number
	sortOrder: number
	active: boolean
	children: EditableTypeNode[]
	clientId: string
}

export type SupportColumnKey = 'number' | 'createdBy' | 'title' | 'userDescription' | 'assignment' | 'status' | 'urgency' | 'createdAt' | 'updatedAt'

export interface TicketAttachmentLinkDraft {
	url: string
	label: string
}

export interface TicketComment {
	id: number
	ticketId: number
	authorUid: string
	authorRole: string
	body: string
	visibility: 'interno' | 'publico'
	createdAt: number
	attachments?: TicketAttachment[]
}

export interface TicketHistoryEntry {
	id: number
	ticketId: number
	actorUid?: string | null
	actorRole?: string | null
	eventType: string
	visibility: 'interno' | 'publico'
	payload?: Record<string, unknown> | null
	createdAt: number
}

export interface TicketAttachment {
	id: number
	ticketId: number
	commentId?: number | null
	originalName: string
	mimeType: string
	size: number
	createdAt: number
	sourceUrl?: string | null
}

export interface Ticket {
	id: number
	number: string
	creatorUid: string
	createdAt: number
	updatedAt: number
	statusUpdatedAt: number
	status: string
	urgencyId?: number | null
	typeId?: number | null
	title: string
	userDescription: string
	supportDescription: string
	publicCommentSearchText?: string
	assignedUserUid?: string | null
	assignedGroupId?: string | null
	province?: string | null
	city?: string | null
	metadata?: Record<string, unknown>
	attachments?: TicketAttachment[]
	comments?: TicketComment[]
	history?: TicketHistoryEntry[]
	personalData?: Array<Record<string, unknown>>
	taskSync?: Record<string, unknown> | null
	canRead?: boolean
	canManage?: boolean
	canComment?: boolean
	canReopen?: boolean
}

export interface AssignableOption {
	id: string
	displayName: string
	groupIds?: string[]
	userIds?: string[]
}

export interface SearchableSelectOption {
	value: string | number
	label: string
	searchText?: string
	disabled?: boolean
}

export interface AssignmentRule {
	id?: number
	typeId: number | null
	province?: string | null
	assignedUserUid?: string | null
	assignedGroupId?: string | null
	priority: number
}

export interface NotificationMatrixItem {
	scopeId: string
	eventName: string
	channel: string
	enabled?: boolean
	[key: string]: unknown
}

export interface StatusOption {
	id: string
	label: string
	active?: boolean
	closed?: boolean
	fixed?: boolean
	toggleable?: boolean
}

export interface AdminStatusOption extends StatusOption {
	fixed?: boolean
	description?: string
}

export interface SavedFilter {
	id: number
	ownerUid?: string | null
	scopeType?: string | null
	name: string
	criteria: Record<string, unknown>
	isPredefined: boolean
	active?: boolean
	isDefault?: boolean
	sortOrder: number
}

export interface BootstrapData {
	currentUser: {
		uid: string
		displayName: string
	}
	roles: string[]
	navigation: NavigationItem[]
	personalConfig: Record<string, string>
	catalogs: {
		statuses: StatusOption[]
		urgencies: UrgencyCatalogItem[]
		types: TypeNode[]
		fields: CatalogField[]
		provinces: string[]
		attachmentConfig: {
			allowedExtensions: string[]
			maxFileSizeMb: number
		}
	}
	supportFilters: SavedFilter[]
	assignables: {
		users: AssignableOption[]
		groups: AssignableOption[]
	}
	tasksIntegration: {
		available: boolean
		config: Record<string, unknown>
	}
}

export interface TicketDraft {
	selectedPath?: number[]
	typeId?: number | null
	province?: string | null
	title?: string
	userDescription?: string
	urgencyId?: string | null
	communicationChannel?: string
	personalData?: Record<string, string>
	attachments?: {
		files: File[]
		links: TicketAttachmentLinkDraft[]
	}
}