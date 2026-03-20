# Consultas Legales para Nextcloud

Aplicacion nativa para Nextcloud 30.0.4 para el soporte de consultas legales.

## Capacidades incluidas

- Creacion de tickets en dos pasos con tipo jerarquico y datos personales configurables.
- Consola de usuario con listado propio y detalle en sidebar.
- Consola de soporte con visibilidad global en lectura, filtros guardados, filtro inicial configurable, exportacion CSV y detalle lateral o de solo lectura segun permisos.
- Configuracion unificada desde un unico acceso lateral con secciones para configuracion personal, preferencias de soporte y administracion segun el rol del usuario.
- Consola de administracion con catalogos, reglas, perfiles, filtros globales y configuracion de Tasks.
- Notificaciones Nextcloud y correo usando las capacidades nativas de la instancia.
- Integracion best-effort con calendarios VTODO para reflejar asignaciones en Tasks sin bloquear el flujo principal.

## Estructura

- `appinfo/`: manifiesto, rutas y bootstrap de la app.
- `lib/`: controladores, entidades, mapeadores, servicios y migraciones.
- `src/`: SPA Vue 3 con TypeScript, router con vistas nombradas y stores por dominio.
- `templates/`: plantilla de entrada.
- `css/`: estilos globales del shell y utilidades visuales.
- `tests/`: pruebas de servicios y una base para frontend.

## Requisitos

- Nextcloud 30.0.4
- PHP 8.1 o superior
- Node.js 20 o superior
- npm 10 o superior
- Composer 2