<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBootstrapStore } from '@/store/bootstrap'

const router = useRouter()
const route = useRoute()
const bootstrapStore = useBootstrapStore()
const viewportWidth = ref(typeof window === 'undefined' ? 1440 : window.innerWidth)
const sidebarWidth = ref(380)
const shellRef = ref<HTMLElement | null>(null)
const navigationVisible = ref(typeof window === 'undefined' ? true : window.innerWidth >= 1100)

const navigation = computed(() => bootstrapStore.data.navigation)
const mainNavigation = computed(() => navigation.value.filter((item) => item.id !== 'configuracion'))
const configurationNavigationItem = computed(() => navigation.value.find((item) => item.id === 'configuracion') ?? null)
const configurationRoute = computed(() => configurationNavigationItem.value?.route ?? '/configuracion')
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
const isDesktopSidebar = computed(() => hasSidebar.value && viewportWidth.value >= 1100)
const isDesktopNavigation = computed(() => viewportWidth.value >= 1100)
const shellStyle = computed(() => isDesktopSidebar.value ? { '--gi-sidebar-width': `${sidebarWidth.value}px` } : {})

function updateViewportWidth() {
	const previousDesktop = viewportWidth.value >= 1100
	viewportWidth.value = window.innerWidth
	if (window.innerWidth < 1100) {
		navigationVisible.value = false
	} else if (!previousDesktop) {
		navigationVisible.value = true
	}
}

function onPointerMove(event: MouseEvent) {
	if (!shellRef.value) {
		return
	}

	const bounds = shellRef.value.getBoundingClientRect()
	const nextWidth = bounds.right - event.clientX
	sidebarWidth.value = Math.max(320, Math.min(640, nextWidth))
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
	navigationVisible.value = true
}

function closeNavigation() {
	navigationVisible.value = false
}

function toggleNavigation() {
	navigationVisible.value = !navigationVisible.value
}

function navigateTo(target: string) {
	void router.push(target)
	if (!isDesktopNavigation.value) {
		navigationVisible.value = false
	}
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

onMounted(() => {
	window.addEventListener('resize', updateViewportWidth)
})

onBeforeUnmount(() => {
	window.removeEventListener('resize', updateViewportWidth)
	stopResize()
})
</script>

<template>
	<div ref="shellRef" class="gi-shell" :class="{ 'gi-shell--nav-open': navigationVisible, 'gi-shell--with-sidebar': hasSidebar, 'gi-shell--desktop-sidebar': isDesktopSidebar }" :style="shellStyle">
		<div v-if="navigationVisible && !isDesktopNavigation" class="gi-navigation-backdrop" @click="closeNavigation" />
		<aside v-if="navigationVisible" class="gi-navigation" :class="{ 'gi-navigation--overlay': !isDesktopNavigation }">
			<nav class="gi-navigation__body" aria-label="Navegacion principal">
				<div class="gi-nav-list">
				<button
					v-for="item in mainNavigation"
					:key="item.id"
					class="gi-nav-item"
					:class="{ 'gi-nav-item--active': route.path.startsWith(item.route) }"
					@click="navigateTo(item.route)">
					{{ item.label }}
				</button>
				</div>
				<button
					v-if="configurationNavigationItem"
					class="gi-nav-item gi-nav-item--settings"
					:class="{ 'gi-nav-item--active': route.path.startsWith(configurationRoute) }"
					@click="navigateTo(configurationRoute)">
					<svg class="gi-nav-item__icon" viewBox="0 0 24 24" aria-hidden="true">
						<path d="M19.14,12.94C19.18,12.64 19.2,12.33 19.2,12C19.2,11.67 19.18,11.36 19.14,11.06L21.19,9.47C21.37,9.33 21.42,9.08 21.31,8.87L19.37,5.13C19.26,4.92 19,4.84 18.78,4.91L16.36,5.69C15.86,5.31 15.32,4.99 14.73,4.73L14.36,2.16C14.33,1.94 14.14,1.78 13.91,1.78H10.09C9.86,1.78 9.67,1.94 9.64,2.16L9.27,4.73C8.68,4.99 8.14,5.31 7.64,5.69L5.22,4.91C5,4.84 4.74,4.92 4.63,5.13L2.69,8.87C2.58,9.08 2.63,9.33 2.81,9.47L4.86,11.06C4.82,11.36 4.8,11.68 4.8,12C4.8,12.32 4.82,12.64 4.86,12.94L2.81,14.53C2.63,14.67 2.58,14.92 2.69,15.13L4.63,18.87C4.74,19.08 5,19.16 5.22,19.09L7.64,18.31C8.14,18.69 8.68,19.01 9.27,19.27L9.64,21.84C9.67,22.06 9.86,22.22 10.09,22.22H13.91C14.14,22.22 14.33,22.06 14.36,21.84L14.73,19.27C15.32,19.01 15.86,18.69 16.36,18.31L18.78,19.09C19,19.16 19.26,19.08 19.37,18.87L21.31,15.13C21.42,14.92 21.37,14.67 21.19,14.53L19.14,12.94M12,15.5A3.5,3.5 0 0,1 8.5,12A3.5,3.5 0 0,1 12,8.5A3.5,3.5 0 0,1 15.5,12A3.5,3.5 0 0,1 12,15.5Z" />
					</svg>
					<span>Configuracion</span>
				</button>
			</nav>
		</aside>
		<main class="gi-content">
			<header class="gi-content-header">
				<button class="gi-nav-toggle gi-nav-toggle--surface" type="button" :aria-label="navigationVisible ? 'Ocultar menu' : 'Mostrar menu'" @click="toggleNavigation">
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
	--gi-nav-bg: #cfe0ef;
	--gi-nav-accent: #0a6ea8;
	--gi-nav-text: #0f2433;
	--gi-content-bg: #f6f7f9;
	display: grid;
	grid-template-columns: minmax(0, 1fr);
	width: 100%;
	max-width: none;
	min-width: 0;
	background: var(--gi-content-bg);
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
	border-right: 1px solid rgba(10, 110, 168, .14);
	background: var(--gi-nav-bg);
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
	box-shadow: 0 18px 40px rgba(16, 36, 51, .18);
}

.gi-navigation-backdrop {
	position: absolute;
	inset: 0;
	background: rgba(15, 36, 51, .16);
	z-index: 15;
}

.gi-nav-list {
	display: grid;
	gap: .5rem;
	padding: 0;
}

.gi-nav-item {
	display: flex;
	align-items: center;
	gap: .7rem;
	padding: .95rem 1rem;
	border: 1px solid transparent;
	border-radius: 12px;
	background: transparent;
	color: var(--gi-nav-text);
	font: inherit;
	font-size: 1rem;
	font-weight: 500;
	text-align: left;
	cursor: pointer;
}

.gi-nav-item--settings {
	margin-top: auto;
	background: rgba(255, 255, 255, .32);
	border-color: rgba(15, 36, 51, .12);
}

.gi-nav-item__icon {
	width: 1.1rem;
	height: 1.1rem;
	fill: currentColor;
	flex: 0 0 auto;
}

.gi-nav-item--active {
	background: var(--gi-nav-accent);
	border-color: rgba(10, 110, 168, .28);
	color: #fff;
}

.gi-content {
	min-width: 0;
	width: 100%;
	overflow: auto;
	background: var(--gi-content-bg);
	display: grid;
	grid-template-rows: auto minmax(0, 1fr);
}

.gi-content-header {
	display: flex;
	align-items: center;
	gap: 1rem;
	padding: .8rem 1rem;
	background: rgba(255, 255, 255, .9);
	border-bottom: 1px solid rgba(15, 36, 51, .08);
	position: sticky;
	top: 0;
	z-index: 5;
}

.gi-content-header__title {
	margin: 0;
	font-size: 1.2rem;
	font-weight: 700;
	color: #182433;
}

.gi-content-body {
	min-height: 0;
	overflow: auto;
}

.gi-sidebar-shell {
	border-left: 1px solid rgba(15, 36, 51, .08);
	background: #ffffff;
	overflow: auto;
}

.gi-sidebar-resizer {
	position: relative;
	background: linear-gradient(180deg, rgba(15, 36, 51, .04), rgba(15, 36, 51, .08));
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
	background: rgba(15, 36, 51, .18);
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
	color: #182433;
	border-bottom: 1px solid rgba(15, 36, 51, .08);
}

.gi-sidebar-close {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 2rem;
	height: 2rem;
	border: 1px solid rgba(15, 36, 51, .12);
	background: #eef1f4;
	color: #213544;
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
	background: #eef1f4;
	box-shadow: inset 0 0 0 1px rgba(15, 36, 51, .08);
	border-radius: 12px;
}

.gi-nav-toggle__line {
	width: 1rem;
	height: .14rem;
	border-radius: 999px;
	background: #213544;
}

.gi-nav-toggle__icon {
	width: 1.2rem;
	height: 1.2rem;
	fill: #213544;
	display: block;
}

.gi-nav-toggle__icon--open {
	transform: translateX(.02rem);
}

@media (min-width: 1100px) {
	.gi-shell.gi-shell--with-sidebar {
		grid-template-columns: minmax(0, 1fr) minmax(20rem, 24rem);
	}

		.gi-shell.gi-shell--nav-open.gi-shell--with-sidebar {
		grid-template-columns: minmax(16rem, 22rem) minmax(0, 1fr) minmax(20rem, 24rem);
	}

	.gi-shell.gi-shell--nav-open.gi-shell--desktop-sidebar {
		grid-template-columns: minmax(16rem, 22rem) minmax(0, 1fr) .55rem minmax(20rem, var(--gi-sidebar-width));
	}
}

@media (max-width: 1099px) {
	.gi-navigation {
		border-right: none;
	}

	.gi-sidebar-shell {
		border-left: none;
		border-top: 1px solid rgba(15, 36, 51, .08);
	}

	.gi-sidebar-resizer {
		display: none;
	}
}
</style>