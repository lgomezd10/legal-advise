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
			<nav class="gi-nav-list" aria-label="Navegacion principal">
				<button
					v-for="item in navigation"
					:key="item.id"
					class="gi-nav-item"
					:class="{ 'gi-nav-item--active': route.path.startsWith(item.route) }"
					@click="navigateTo(item.route)">
					{{ item.label }}
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
			<div class="gi-sidebar-title">{{ currentSectionTitle }}</div>
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
	padding: 1rem 1.1rem;
	font-weight: 700;
	font-size: 1.1rem;
	color: #182433;
	border-bottom: 1px solid rgba(15, 36, 51, .08);
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