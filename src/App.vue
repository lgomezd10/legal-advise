<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBootstrapStore } from '@/store/bootstrap'
import { useSupportFiltersStore } from '@/store/supportFilters'
import type { SavedFilter } from '@/types'

const DEFAULT_SIDEBAR_WIDTH = 570
const DESKTOP_NAV_BREAKPOINT = 900
const DESKTOP_SIDEBAR_BREAKPOINT = 1100

const router = useRouter()
const route = useRoute()
const bootstrapStore = useBootstrapStore()
const supportFiltersStore = useSupportFiltersStore()
const initialHasSupportAccess = bootstrapStore.data.roles.includes('soporte')
	|| bootstrapStore.data.roles.includes('administrador')
	|| bootstrapStore.data.navigation.some((item) => item.route === '/soporte' && item.visible)
const viewportWidth = ref(typeof window === 'undefined' ? 1440 : window.innerWidth)
const sidebarWidth = ref(DEFAULT_SIDEBAR_WIDTH)
const shellRef = ref<HTMLElement | null>(null)
const navigationVisible = ref(typeof window === 'undefined' ? initialHasSupportAccess : window.innerWidth >= DESKTOP_NAV_BREAKPOINT && initialHasSupportAccess)

const navigation = computed(() => bootstrapStore.data.navigation)
const hasNavigation = computed(() => navigation.value.length > 0)
const mainNavigation = computed(() => navigation.value.filter((item) => item.id !== 'configuracion'))
const configurationNavigationItem = computed(() => navigation.value.find((item) => item.id === 'configuracion') ?? null)
const configurationRoute = computed(() => configurationNavigationItem.value?.route ?? '/configuracion')
const supportNavigationItem = computed(() => navigation.value.find((item) => item.route === '/soporte') ?? null)
const supportFilterItems = computed<SavedFilter[]>(() => {
	if (supportFiltersStore.items.length > 0) {
		return supportFiltersStore.items
	}

	return bootstrapStore.data.supportFilters ?? []
})
const supportSubmenuExpanded = ref(false)
const currentSupportFilterId = computed(() => {
	const raw = Array.isArray(route.query.filterId) ? route.query.filterId[0] : route.query.filterId
	const parsed = Number(raw)
	return Number.isInteger(parsed) && parsed > 0 ? parsed : null
})
const hasSupportAccess = computed(() => bootstrapStore.data.roles.includes('soporte')
	|| bootstrapStore.data.roles.includes('administrador')
	|| bootstrapStore.data.navigation.some((item) => item.route === '/soporte' && item.visible))
const currentNavigationItem = computed(() => {
	const matched = [...navigation.value]
		.sort((left, right) => right.route.length - left.route.length)
		.find((item) => route.path.startsWith(item.route))

	return matched ?? navigation.value[0] ?? null
})
const currentSectionTitle = computed(() => currentNavigationItem.value?.label ?? 'Consultas Legales')
const hasSidebar = computed(() => {
	const hasTicketSidebarRoute = typeof route.params.ticketId !== 'undefined'
	const isFullscreenRoute = route.path.endsWith('/completo')
	return hasTicketSidebarRoute && !isFullscreenRoute
})
const sidebarBaseRoute = computed(() => route.path.startsWith('/soporte') ? '/soporte' : '/mis-incidencias')
const isDesktopSidebar = computed(() => hasSidebar.value && viewportWidth.value >= DESKTOP_SIDEBAR_BREAKPOINT)
const isDesktopNavigation = computed(() => viewportWidth.value >= DESKTOP_NAV_BREAKPOINT)
const shellStyle = computed(() => isDesktopSidebar.value ? { '--gi-sidebar-width': `${sidebarWidth.value}px` } : {})

watch(hasSidebar, (nextHasSidebar, previousHasSidebar) => {
	if (!nextHasSidebar || previousHasSidebar === nextHasSidebar) {
		return
	}

	sidebarWidth.value = DEFAULT_SIDEBAR_WIDTH
	navigationVisible.value = false
})

watch(hasSupportAccess, (nextHasSupportAccess) => {
	if (!nextHasSupportAccess) {
		navigationVisible.value = false
	}
})

function updateViewportWidth() {
	const previousDesktop = viewportWidth.value >= DESKTOP_NAV_BREAKPOINT
	viewportWidth.value = window.innerWidth
	if (window.innerWidth < DESKTOP_NAV_BREAKPOINT) {
		navigationVisible.value = false
	} else if (!previousDesktop) {
		navigationVisible.value = hasSupportAccess.value
	}
}

function syncShellLayoutAfterResume() {
	window.requestAnimationFrame(() => {
		updateViewportWidth()
	})
}

function onVisibilityChange() {
	if (document.visibilityState === 'visible') {
		syncShellLayoutAfterResume()
	}
}

function onPointerMove(event: MouseEvent) {
	if (!shellRef.value) {
		return
	}

	const bounds = shellRef.value.getBoundingClientRect()
	const nextWidth = bounds.right - event.clientX
	const maxWidth = Math.max(320, Math.floor(bounds.width * 0.6))
	sidebarWidth.value = Math.max(320, Math.min(maxWidth, nextWidth))
}

function stopResize() {
	window.removeEventListener('mousemove', onPointerMove)
	window.removeEventListener('mouseup', stopResize)
	document.body.classList.remove('gi-resizing-sidebar')
}

function startResize() {
	if (!isDesktopSidebar.value) {
		return
	}

	document.body.classList.add('gi-resizing-sidebar')
	window.addEventListener('mousemove', onPointerMove)
	window.addEventListener('mouseup', stopResize)
}

function openNavigation() {
	if (!hasNavigation.value) {
		return
	}

	navigationVisible.value = true
}

function closeNavigation() {
	navigationVisible.value = false
}

function toggleNavigation() {
	if (!hasNavigation.value) {
		return
	}

	navigationVisible.value = !navigationVisible.value
}

function navigateTo(target: string | { path: string, query?: Record<string, string> }) {
	void router.push(target)
	if (!isDesktopNavigation.value) {
		navigationVisible.value = false
	}
}

function navigateToSupportFilter(filterId: number) {
	const baseRoute = supportNavigationItem.value?.route ?? '/soporte'
	navigateTo({ path: baseRoute, query: { filterId: String(filterId) } })
}

function handleSupportNavigation(routePath: string) {
	if (route.path.startsWith(routePath) && supportFilterItems.value.length > 0) {
		supportSubmenuExpanded.value = !supportSubmenuExpanded.value
		return
	}

	navigateTo(routePath)
}

function closeSidebar() {
	if (!hasSidebar.value) {
		return
	}

	void router.push(sidebarBaseRoute.value)
	if (!isDesktopNavigation.value) {
		navigationVisible.value = false
	}
}

function resolveNavigationIcon(itemId: string, routePath: string) {
	if (routePath.startsWith('/mis-incidencias')) {
		return 'M19 3H14.82C14.4 1.84 13.3 1 12 1S9.6 1.84 9.18 3H5C3.9 3 3 3.9 3 5V21C3 22.1 3.9 23 5 23H19C20.1 23 21 22.1 21 21V5C21 3.9 20.1 3 19 3M12 3C12.55 3 13 3.45 13 4S12.55 5 12 5 11 4.55 11 4 11.45 3 12 3M14 19H7V17H14V19M17 15H7V13H17V15M17 11H7V9H17V11Z'
	}

	if (routePath.startsWith('/soporte')) {
		return 'M20 12V8H4V12H2V8C2 6.89 2.89 6 4 6H9V4H15V6H20C21.11 6 22 6.89 22 8V12H20M13 14H11V12H13V14M20 10V19C20 20.11 19.11 21 18 21H6C4.89 21 4 20.11 4 19V10H9V16H15V10H20Z'
	}

	if (routePath.startsWith('/administracion') || itemId === 'administracion') {
		return 'M12 2L4 5V11C4 16.55 7.84 21.74 12 23C16.16 21.74 20 16.55 20 11V5L12 2M14.29 16.29L12 14L9.71 16.29L8.29 14.88L10.59 12.59L8.29 10.29L9.71 8.88L12 11.17L14.29 8.88L15.71 10.29L13.41 12.59L15.71 14.88L14.29 16.29Z'
	}

	if (itemId === 'configuracion' || routePath.startsWith('/configuracion')) {
		return 'M19.14 12.94C19.18 12.64 19.2 12.33 19.2 12C19.2 11.67 19.18 11.36 19.14 11.06L21.19 9.47C21.37 9.33 21.42 9.08 21.31 8.87L19.37 5.13C19.26 4.92 19 4.84 18.78 4.91L16.36 5.69C15.86 5.31 15.32 4.99 14.73 4.73L14.36 2.16C14.33 1.94 14.14 1.78 13.91 1.78H10.09C9.86 1.78 9.67 1.94 9.64 2.16L9.27 4.73C8.68 4.99 8.14 5.31 7.64 5.69L5.22 4.91C5 4.84 4.74 4.92 4.63 5.13L2.69 8.87C2.58 9.08 2.63 9.33 2.81 9.47L4.86 11.06C4.82 11.36 4.8 11.68 4.8 12C4.8 12.32 4.82 12.64 4.86 12.94L2.81 14.53C2.63 14.67 2.58 14.92 2.69 15.13L4.63 18.87C4.74 19.08 5 19.16 5.22 19.09L7.64 18.31C8.14 18.69 8.68 19.01 9.27 19.27L9.64 21.84C9.67 22.06 9.86 22.22 10.09 22.22H13.91C14.14 22.22 14.33 22.06 14.36 21.84L14.73 19.27C15.32 19.01 15.86 18.69 16.36 18.31L18.78 19.09C19 19.16 19.26 19.08 19.37 18.87L21.31 15.13C21.42 14.92 21.37 14.67 21.19 14.53L19.14 12.94M12 15.5A3.5 3.5 0 0 1 8.5 12A3.5 3.5 0 0 1 12 8.5A3.5 3.5 0 0 1 15.5 12A3.5 3.5 0 0 1 12 15.5Z'
	}

	return 'M14 2H6C4.9 2 4 2.9 4 4V20C4 21.1 4.9 22 6 22H18C19.1 22 20 21.1 20 20V8L14 2M13 9V3.5L18.5 9H13Z'
}

function resolveSupportFilterIcon() {
	return 'M3 5H21V7H3V5M6 11H18V13H6V11M10 17H14V19H10V17Z'
}

onMounted(() => {
	window.addEventListener('resize', updateViewportWidth)
	window.addEventListener('focus', syncShellLayoutAfterResume)
	window.addEventListener('pageshow', syncShellLayoutAfterResume)
	document.addEventListener('visibilitychange', onVisibilityChange)
})

onBeforeUnmount(() => {
	window.removeEventListener('resize', updateViewportWidth)
	window.removeEventListener('focus', syncShellLayoutAfterResume)
	window.removeEventListener('pageshow', syncShellLayoutAfterResume)
	document.removeEventListener('visibilitychange', onVisibilityChange)
	stopResize()
})
</script>

<template>
	<div ref="shellRef" class="gi-shell" :class="{ 'gi-shell--nav-open': navigationVisible, 'gi-shell--with-sidebar': hasSidebar, 'gi-shell--desktop-sidebar': isDesktopSidebar }" :style="shellStyle">
		<div v-if="navigationVisible && !isDesktopNavigation" class="gi-navigation-backdrop" @click="closeNavigation" />
		<aside v-if="navigationVisible" class="gi-navigation" :class="{ 'gi-navigation--overlay': !isDesktopNavigation }">
			<nav class="gi-navigation__body" aria-label="Navegacion principal">
				<div class="gi-nav-list">
					<div v-for="item in mainNavigation" :key="item.id" class="gi-nav-entry">
						<button
							class="gi-nav-item"
							:class="{ 'gi-nav-item--active': route.path.startsWith(item.route), 'gi-nav-item--with-toggle': item.route === '/soporte' && supportFilterItems.length > 0 }"
							:type="'button'"
							:aria-expanded="item.route === '/soporte' && supportFilterItems.length > 0 ? (supportSubmenuExpanded ? 'true' : 'false') : undefined"
							@click="item.route === '/soporte' ? handleSupportNavigation(item.route) : navigateTo(item.route)">
							<svg class="gi-nav-item__icon" viewBox="0 0 24 24" aria-hidden="true">
								<path :d="resolveNavigationIcon(item.id, item.route)" />
							</svg>
							<span>{{ item.label }}</span>
							<svg v-if="item.route === '/soporte' && supportFilterItems.length > 0" class="gi-nav-item__toggle-icon" viewBox="0 0 24 24" aria-hidden="true">
								<path d="M7.41 8.59 12 13.17l4.59-4.58L18 10l-6 6-6-6z" />
							</svg>
						</button>
						<div v-if="item.route === '/soporte' && supportFilterItems.length > 0 && supportSubmenuExpanded" class="gi-nav-submenu">
							<button
								v-for="filter in supportFilterItems"
								:key="filter.id"
								class="gi-nav-subitem"
								:class="{ 'gi-nav-subitem--active': route.path.startsWith('/soporte') && currentSupportFilterId === filter.id }"
								@click="navigateToSupportFilter(filter.id)">
								<svg class="gi-nav-subitem__icon" viewBox="0 0 24 24" aria-hidden="true">
									<path :d="resolveSupportFilterIcon()" />
								</svg>
								{{ filter.name }}
							</button>
						</div>
					</div>
				</div>
				<button
					v-if="configurationNavigationItem"
					class="gi-nav-item gi-nav-item--settings"
					:class="{ 'gi-nav-item--active': route.path.startsWith(configurationRoute) }"
					@click="navigateTo(configurationRoute)">
					<svg class="gi-nav-item__icon" viewBox="0 0 24 24" aria-hidden="true">
						<path d="M19.14,12.94C19.18,12.64 19.2,12.33 19.2,12C19.2,11.67 19.18,11.36 19.14,11.06L21.19,9.47C21.37,9.33 21.42,9.08 21.31,8.87L19.37,5.13C19.26,4.92 19,4.84 18.78,4.91L16.36,5.69C15.86,5.31 15.32,4.99 14.73,4.73L14.36,2.16C14.33,1.94 14.14,1.78 13.91,1.78H10.09C9.86,1.78 9.67,1.94 9.64,2.16L9.27,4.73C8.68,4.99 8.14,5.31 7.64,5.69L5.22,4.91C5,4.84 4.74,4.92 4.63,5.13L2.69,8.87C2.58,9.08 2.63,9.33 2.81,9.47L4.86,11.06C4.82,11.36 4.8,11.68 4.8,12C4.8,12.32 4.82,12.64 4.86,12.94L2.81,14.53C2.63,14.67 2.58,14.92 2.69,15.13L4.63,18.87C4.74,19.08 5,19.16 5.22,19.09L7.64,18.31C8.14,18.69 8.68,19.01 9.27,19.27L9.64,21.84C9.67,22.06 9.86,22.22 10.09,22.22H13.91C14.14,22.22 14.33,22.06 14.36,21.84L14.73,19.27C15.32,19.01 15.86,18.69 16.36,18.31L18.78,19.09C19,19.16 19.26,19.08 19.37,18.87L21.31,15.13C21.42,14.92 21.37,14.67 21.19,14.53L19.14,12.94M12,15.5A3.5,3.5 0 0,1 8.5,12A3.5,3.5 0 0,1 12,8.5A3.5,3.5 0 0,1 15.5,12A3.5,3.5 0 0,1 12,15.5Z" />
					</svg>
					<span>Configuración</span>
				</button>
			</nav>
		</aside>
		<main class="gi-content">
			<header class="gi-content-header">
				<button v-if="hasNavigation" class="gi-nav-toggle gi-nav-toggle--surface" type="button" :aria-label="navigationVisible ? 'Ocultar menu' : 'Mostrar menu'" @click="toggleNavigation">
					<svg v-if="navigationVisible" class="gi-nav-toggle__icon gi-nav-toggle__icon--open" viewBox="0 0 24 24" aria-hidden="true">
						<path d="M21,15.61L19.59,17L14.58,12L19.59,7L21,8.39L17.44,12L21,15.61M3,6H16V8H3V6M3,13V11H13V13H3M3,18V16H16V18H3Z" />
					</svg>
					<template v-else>
						<span class="gi-nav-toggle__line" />
						<span class="gi-nav-toggle__line" />
						<span class="gi-nav-toggle__line" />
					</template>
				</button>
				<h1 class="gi-content-header__title">{{ currentSectionTitle }}</h1>
			</header>
			<div class="gi-content-body">
				<router-view />
			</div>
		</main>
		<div v-if="isDesktopSidebar" class="gi-sidebar-resizer" role="separator" aria-orientation="vertical" @mousedown="startResize" />
		<aside v-if="hasSidebar" class="gi-sidebar-shell">
			<div class="gi-sidebar-title">
				<span>{{ currentSectionTitle }}</span>
				<button class="gi-sidebar-close" type="button" aria-label="Cerrar panel" @click="closeSidebar">
					<svg viewBox="0 0 24 24" aria-hidden="true">
						<path d="M18.3 5.71 12 12l6.3 6.29-1.41 1.41L10.59 13.41 4.29 19.7 2.88 18.29 9.17 12 2.88 5.71 4.29 4.3l6.3 6.29 6.29-6.29z" />
					</svg>
				</button>
			</div>
			<router-view name="AppSidebar" />
		</aside>
	</div>
</template>

<style scoped>
.gi-shell {
	--gi-nav-bg: rgba(255, 255, 255, .8);
	--gi-nav-accent: var(--gi-color-primary);
	--gi-nav-text: var(--gi-color-text);
	--gi-content-bg: transparent;
	display: grid;
	grid-template-columns: minmax(0, 1fr);
	width: 100%;
	max-width: none;
	min-width: 0;
	background: transparent;
	height: calc(100vh - 50px);
	min-height: calc(100vh - 50px);
	overflow: hidden;
	position: relative;
}

.gi-shell--nav-open {
	grid-template-columns: minmax(16rem, 22rem) minmax(0, 1fr);
}

.gi-shell--nav-open.gi-shell--desktop-sidebar {
	grid-template-columns: minmax(16rem, 22rem) minmax(0, 1fr) .55rem minmax(20rem, var(--gi-sidebar-width));
}

.gi-navigation {
	border-right: 1px solid var(--gi-color-border);
	background: var(--gi-nav-bg);
	backdrop-filter: blur(25px);
	-webkit-backdrop-filter: blur(25px);
	overflow: auto;
	padding: 1rem .75rem .75rem;
}

.gi-navigation__body {
	display: flex;
	flex-direction: column;
	justify-content: space-between;
	min-height: 100%;
	gap: 1rem;
}

.gi-navigation--overlay {
	position: absolute;
	top: 0;
	left: 0;
	bottom: 0;
	width: min(21rem, calc(100vw - 2rem));
	z-index: 20;
	box-shadow: 0 18px 40px var(--gi-color-shadow-strong);
}

.gi-navigation-backdrop {
	position: absolute;
	inset: 0;
	background: var(--gi-color-overlay);
	z-index: 15;
}

.gi-nav-list {
	display: grid;
	gap: .5rem;
	padding: 0;
}

.gi-nav-entry {
	display: grid;
	gap: .3rem;
}

.gi-nav-item {
	display: flex;
	align-items: center;
	gap: .72rem;
	padding: .78rem .88rem;
	border: 1px solid transparent;
	border-radius: 10px;
	background: transparent;
	color: var(--gi-nav-text);
	font: inherit;
	font-size: .94rem;
	font-weight: 600;
	line-height: 1.25;
	text-align: left;
	cursor: pointer;
}

.gi-nav-item--with-toggle {
	justify-content: space-between;
}

.gi-nav-item--settings {
	margin-top: auto;
	background: var(--color-main-background, rgba(255, 255, 255, .96));
	border-color: var(--gi-color-border);
}

.gi-nav-item__icon {
	width: 1.18rem;
	height: 1.18rem;
	fill: currentColor;
	flex: 0 0 auto;
}

.gi-nav-item--active {
	background: var(--gi-nav-accent);
	border-color: var(--gi-nav-accent);
	color: var(--gi-color-primary-text);
}

.gi-nav-item__toggle-icon {
	width: 1.2rem;
	height: 1.2rem;
	fill: currentColor;
	flex: 0 0 auto;
	margin-left: auto;
	transition: transform .18s ease;
}

.gi-nav-item[aria-expanded='true'] .gi-nav-item__toggle-icon {
	transform: rotate(180deg);
}

.gi-nav-submenu {
	display: grid;
	gap: .2rem;
	padding-left: 1.85rem;
	padding-top: .1rem;
}

.gi-nav-subitem {
	display: flex;
	align-items: center;
	gap: .55rem;
	padding: .46rem .65rem;
	border: 0;
	border-radius: 8px;
	background: transparent;
	color: var(--gi-nav-text);
	font: inherit;
	font-size: .86rem;
	font-weight: 500;
	text-align: left;
	cursor: pointer;
	transition: background-color .18s ease, color .18s ease, transform .18s ease;
}

.gi-nav-subitem__icon {
	width: 1rem;
	height: 1rem;
	fill: currentColor;
	flex: 0 0 auto;
}

.gi-nav-subitem:hover {
	background: var(--gi-color-primary-soft-hover);
	color: var(--gi-color-primary-soft-text);
	transform: translateX(1px);
}

.gi-nav-subitem--active {
	background: var(--gi-color-primary-soft-hover);
	color: var(--gi-color-primary);
	font-weight: 700;
}

.gi-content {
	min-width: 0;
	width: 100%;
	overflow: auto;
	background: var(--gi-color-surface-plain);
	display: grid;
	grid-template-rows: auto minmax(0, 1fr);
}

.gi-content-header {
	display: flex;
	align-items: center;
	gap: 1rem;
	padding: .8rem 1rem;
	background: var(--gi-color-surface);
	border-bottom: 1px solid var(--gi-color-border);
	position: sticky;
	top: 0;
	z-index: 5;
}

.gi-content-header__title {
	margin: 0;
	font-size: 1.2rem;
	font-weight: 700;
	color: var(--gi-color-text);
}

.gi-content-body {
	min-height: 0;
	overflow: auto;
}

.gi-sidebar-shell {
	border-left: 1px solid var(--gi-color-border);
	background: var(--gi-color-surface-plain);
	overflow: auto;
}

.gi-sidebar-resizer {
	position: relative;
	background: linear-gradient(180deg, var(--gi-color-surface-subtle), var(--gi-color-surface-muted));
	cursor: col-resize;
}

.gi-sidebar-resizer::after {
	content: '';
	position: absolute;
	top: 50%;
	left: 50%;
	width: .2rem;
	height: 4rem;
	border-radius: 999px;
	background: var(--gi-color-border-strong);
	transform: translate(-50%, -50%);
}

.gi-sidebar-title {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: .75rem;
	padding: 1rem 1.1rem;
	font-weight: 700;
	font-size: 1.1rem;
	color: var(--gi-color-text);
	border-bottom: 1px solid var(--gi-color-border);
}

.gi-sidebar-close {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 2rem;
	height: 2rem;
	border: 1px solid var(--gi-color-border-strong);
	background: var(--gi-color-surface-subtle);
	color: var(--gi-color-text);
	border-radius: 999px;
	padding: 0;
	cursor: pointer;
}

.gi-sidebar-close svg {
	width: 1rem;
	height: 1rem;
	fill: currentColor;
}

.gi-nav-toggle {
	width: 2.6rem;
	height: 2.6rem;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex-direction: column;
	gap: .22rem;
	border: none;
	border-radius: 10px;
	background: transparent;
	cursor: pointer;
}

.gi-nav-toggle--surface {
	background: var(--gi-color-surface-subtle);
	box-shadow: inset 0 0 0 1px var(--gi-color-border);
	border-radius: 12px;
}

.gi-nav-toggle__line {
	width: 1rem;
	height: .14rem;
	border-radius: 999px;
	background: var(--gi-color-text);
}

.gi-nav-toggle__icon {
	width: 1.2rem;
	height: 1.2rem;
	fill: var(--gi-color-text);
	display: block;
}

.gi-nav-toggle__icon--open {
	transform: translateX(.02rem);
}

@media (min-width: 900px) {
	.gi-shell--nav-open {
		grid-template-columns: minmax(15rem, 19rem) minmax(0, 1fr);
	}

	.gi-shell--nav-open.gi-shell--desktop-sidebar {
		grid-template-columns: minmax(15rem, 19rem) minmax(0, 1fr) .55rem minmax(20rem, var(--gi-sidebar-width));
	}
}

@media (min-width: 1100px) {
	.gi-shell.gi-shell--with-sidebar {
		grid-template-columns: minmax(0, 1fr) minmax(20rem, 24rem);
	}

	.gi-shell.gi-shell--desktop-sidebar.gi-shell--with-sidebar {
		grid-template-columns: minmax(0, 1fr) .55rem minmax(20rem, var(--gi-sidebar-width));
	}

		.gi-shell.gi-shell--nav-open.gi-shell--with-sidebar {
		grid-template-columns: minmax(15rem, 19rem) minmax(0, 1fr) minmax(20rem, 24rem);
	}

	.gi-shell.gi-shell--nav-open.gi-shell--desktop-sidebar {
		grid-template-columns: minmax(15rem, 19rem) minmax(0, 1fr) .55rem minmax(20rem, var(--gi-sidebar-width));
	}
}

@media (max-width: 899px) {
	.gi-navigation {
		border-right: none;
	}

	.gi-sidebar-shell {
		border-left: none;
		border-top: 1px solid var(--gi-color-border);
	}

	.gi-sidebar-resizer {
		display: none;
	}
}
</style>