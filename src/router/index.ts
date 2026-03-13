import { createRouter, createWebHashHistory } from 'vue-router'
import UserTicketsView from '@/views/UserTicketsView.vue'
import NewTicketView from '@/views/NewTicketView.vue'
import PersonalConfigView from '@/views/PersonalConfigView.vue'
import SupportConsoleView from '@/views/SupportConsoleView.vue'
import SupportTicketFullView from '@/views/SupportTicketFullView.vue'
import UserTicketFullView from '@/views/UserTicketFullView.vue'
import AdminConsoleView from '@/views/AdminConsoleView.vue'
import TicketSidebarView from '@/views/TicketSidebarView.vue'

export const router = createRouter({
	history: createWebHashHistory(),
	routes: [
		{ path: '/', redirect: '/mis-incidencias' },
		{ path: '/mis-incidencias', components: { default: UserTicketsView } },
		{ path: '/mis-incidencias/nuevo', components: { default: NewTicketView } },
		{ path: '/mis-incidencias/:ticketId', components: { default: UserTicketsView, AppSidebar: TicketSidebarView }, props: { default: false, AppSidebar: true } },
		{ path: '/mis-incidencias/:ticketId/completo', components: { default: UserTicketFullView }, props: { default: true } },
		{ path: '/nuevo-ticket', redirect: '/mis-incidencias/nuevo' },
		{ path: '/configuracion-personal', components: { default: PersonalConfigView } },
		{ path: '/soporte', components: { default: SupportConsoleView } },
		{ path: '/soporte/:ticketId', components: { default: SupportConsoleView, AppSidebar: TicketSidebarView }, props: { default: false, AppSidebar: true } },
		{ path: '/soporte/:ticketId/completo', components: { default: SupportTicketFullView }, props: { default: true } },
		{ path: '/administracion', components: { default: AdminConsoleView } },
	],
})