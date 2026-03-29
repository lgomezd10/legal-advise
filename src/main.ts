import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import { router } from './router'
import { useBootstrapStore } from '@/store/bootstrap'

import '../css/style.css'

const app = createApp(App)
const pinia = createPinia()

function hasNavigationRoute(routePrefix: string, bootstrapStore: ReturnType<typeof useBootstrapStore>) {
	return bootstrapStore.data.navigation.some((item) => item.route === routePrefix)
}

function resolveLandingRoute(bootstrapStore: ReturnType<typeof useBootstrapStore>) {
	return bootstrapStore.data.navigation[0]?.route ?? '/sin-acceso'
}

function canAccessRoute(path: string, bootstrapStore: ReturnType<typeof useBootstrapStore>) {
	if (path === '/sin-acceso') {
		return true
	}

	if (path.startsWith('/soporte')) {
		return hasNavigationRoute('/soporte', bootstrapStore)
	}

	if (path.startsWith('/mis-incidencias') || path === '/nuevo-ticket') {
		return hasNavigationRoute('/mis-incidencias', bootstrapStore)
	}

	if (path.startsWith('/configuracion') || path === '/administracion') {
		return hasNavigationRoute('/configuracion', bootstrapStore)
	}

	return true
}

app.use(pinia)
const bootstrapStore = useBootstrapStore(pinia)

router.beforeEach((to) => {
	if (to.path === '/') {
		return resolveLandingRoute(bootstrapStore)
	}

	if (to.path === '/sin-acceso') {
		return bootstrapStore.data.navigation.length > 0 ? resolveLandingRoute(bootstrapStore) : true
	}

	if (canAccessRoute(to.path, bootstrapStore)) {
		return true
	}

	return resolveLandingRoute(bootstrapStore)
})

app.use(router)
app.mount('#gestion-incidencias')