import type { AdminStatusOption, AssignmentRule, BootstrapData, CatalogField, SavedFilter, StatusOption, Ticket, TicketAttachment, TicketComment, TypeNode, UrgencyCatalogItem } from '@/types'

export function createTypeTree(): TypeNode[] {
	return [
		{
			id: 1,
			name: 'Necesito asesoramiento',
			slug: 'neceisto-asesoramiento',
			level: 0,
			sortOrder: 10,
			active: true,
			children: [
				{ id: 11, parentId: 1, name: 'Solo Territorial', slug: 'solo-territorial', level: 1, sortOrder: 10, active: true, children: [] },
				{ id: 12, parentId: 1, name: 'Territorial y Legal', slug: 'territorial-legal', level: 1, sortOrder: 20, active: true, children: [] },
			],
		},
		{
			id: 2,
			name: 'Quiero informar',
			slug: 'quiero-informar',
			level: 0,
			sortOrder: 20,
			active: true,
			children: [],
		},
	]
}

export function createBootstrapData(overrides: Partial<BootstrapData> = {}): BootstrapData {
	const statuses: StatusOption[] = [
		{ id: 'nuevo', label: 'Nuevo', active: true, closed: false, fixed: true, toggleable: false },
		{ id: 'en_espera_usuario', label: 'En espera usuario', active: true, closed: false, fixed: true, toggleable: false },
		{ id: 'cerrado', label: 'Cerrado', active: true, closed: true, fixed: true, toggleable: false },
	]

	const base: BootstrapData = {
		appInfo: {
			id: 'legal_advice',
			version: '0.1.5',
			storageBytes: 1024,
			storageLabel: '1 KB',
			appDataBytes: 768,
			appDataLabel: '768 B',
			databaseBytes: 256,
			databaseLabel: '256 B',
			attachmentBytes: 512,
			attachmentLabel: '512 B',
		},
		currentUser: {
			uid: 'usuario1',
			displayName: 'Usuario Uno',
		},
		roles: ['usuario'],
		navigation: [
			{ id: 'mis-incidencias', label: 'Mis tickets', route: '/mis-incidencias', visible: true },
			{ id: 'configuracion', label: 'Configuración', route: '/configuracion', visible: true },
		],
		personalConfig: {
			email: 'usuario@example.com',
			city: 'Madrid',
			province: 'Madrid',
		},
		personalConfigHasStoredValues: false,
		catalogs: {
			statuses,
			urgencies: [
				{ id: 1, name: 'Baja', weight: 10, color: '#77aa55', active: true },
				{ id: 2, name: 'Alta', weight: 30, color: '#cc5533', active: true },
			],
			types: createTypeTree(),
			fields: [
				{ fieldKey: 'email', label: 'Correo', fieldType: 'email', required: true, sortOrder: 10, active: true },
				{ fieldKey: 'city', label: 'Ciudad', fieldType: 'text', required: false, sortOrder: 20, active: true },
				{ fieldKey: 'province', label: 'Provincia', fieldType: 'text', required: false, sortOrder: 30, active: true },
			],
			provinces: ['Madrid', 'Barcelona'],
			attachmentConfig: {
				allowedExtensions: ['pdf', 'png'],
				maxFileSizeMb: 25,
			},
		},
		supportFilters: [
			{ id: 10, name: 'Asignadas a mi', criteria: { assignedUser: '__me__', status: ['nuevo'] }, isPredefined: true, active: true, isDefault: true, sortOrder: 10 },
		],
		assignables: {
			users: [
				{ id: 'usuario1', displayName: 'Usuario Uno', groupIds: ['grupo-soporte'] },
				{ id: 'soporte1', displayName: 'Soporte Uno', groupIds: ['grupo-soporte'] },
			],
			groups: [
				{ id: 'grupo-soporte', displayName: 'Grupo Soporte', userIds: ['usuario1', 'soporte1'] },
			],
		},
		tasksIntegration: {
			available: true,
			config: { enabled: true },
		},
	}

	return {
		...base,
		...overrides,
		currentUser: {
			...base.currentUser,
			...(overrides.currentUser ?? {}),
		},
		catalogs: {
			...base.catalogs,
			...(overrides.catalogs ?? {}),
			attachmentConfig: {
				...base.catalogs.attachmentConfig,
				...(overrides.catalogs?.attachmentConfig ?? {}),
			},
		},
		assignables: {
			...base.assignables,
			...(overrides.assignables ?? {}),
		},
		tasksIntegration: {
			...base.tasksIntegration,
			...(overrides.tasksIntegration ?? {}),
		},
	}
}

export function createComment(overrides: Partial<TicketComment> = {}): TicketComment {
	return {
		id: 200,
		ticketId: 100,
		authorUid: 'soporte1',
		authorRole: 'soporte',
		body: '<p>Comentario de prueba</p>',
		visibility: 'publico',
		createdAt: 1_711_000_000,
		attachments: [],
		...overrides,
	}
}

export function createAttachment(overrides: Partial<TicketAttachment> = {}): TicketAttachment {
	return {
		id: 300,
		ticketId: 100,
		commentId: 200,
		originalName: 'informe.pdf',
		mimeType: 'application/pdf',
		size: 1024,
		createdAt: 1_711_000_000,
		sourceUrl: null,
		...overrides,
	}
}

export function createTicket(overrides: Partial<Ticket> = {}): Ticket {
	return {
		id: 100,
		number: 'TK-100',
		creatorUid: 'usuario1',
		createdAt: 1_711_000_000,
		updatedAt: 1_711_000_100,
		statusUpdatedAt: 1_711_000_100,
		status: 'nuevo',
		urgencyId: 1,
		typeId: 11,
		title: 'Ticket de prueba',
		userDescription: '<p>Descripción inicial</p>',
		supportDescription: '<p>Soporte</p>',
		assignedUserUid: 'soporte1',
		assignedGroupId: 'grupo-soporte',
		province: 'Madrid',
		city: 'Madrid',
		metadata: { communicationChannel: 'nextcloud_mail' },
		attachments: [],
		comments: [createComment()],
		history: [],
		personalData: [{ fieldKey: 'email', fieldValue: 'usuario@example.com' }],
		taskSync: null,
		canRead: true,
		canManage: true,
		canComment: true,
		canReopen: false,
		...overrides,
	}
}

export function createAdminConfigData() {
	const bootstrap = createBootstrapData({ roles: ['administrador', 'soporte', 'usuario'] })
	const statuses = bootstrap.catalogs.statuses as AdminStatusOption[]
	const urgencies = bootstrap.catalogs.urgencies as UrgencyCatalogItem[]
	const fields = bootstrap.catalogs.fields as CatalogField[]
	const filters = bootstrap.supportFilters as SavedFilter[]
	const rules: AssignmentRule[] = [{ typeId: 11, province: 'Madrid', assignedUserUid: 'soporte1', assignedGroupId: 'grupo-soporte', priority: 10 }]

	return {
		statuses,
		types: bootstrap.catalogs.types,
		urgencies,
		fields,
		filters,
		rules,
		notifications: [
			{ scopeId: 'usuario', eventName: 'ticket_created', deliveryMode: 'both' },
			{ scopeId: 'usuario', eventName: 'ticket_waiting_for_creator', deliveryMode: 'both' },
			{ scopeId: 'soporte', eventName: 'ticket_group_assigned', deliveryMode: 'both' },
		],
		profiles: [
			{ profile: 'usuario', principalType: 'user', principalId: 'usuario1' },
			{ profile: 'soporte', principalType: 'group', principalId: 'grupo-soporte' },
			{ profile: 'administrador', principalType: 'user', principalId: 'soporte1' },
		],
		attachmentConfig: { allowedExtensions: ['pdf', 'png'], maxFileSizeMb: 25 },
		tasksConfig: { enabled: true },
	}
}