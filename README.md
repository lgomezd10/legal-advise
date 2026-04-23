# Consultas Legales para Nextcloud

Aplicacion nativa para Nextcloud 30.0.4 para registrar, enrutar, gestionar y resolver consultas legales desde una SPA Vue 3 con backend PHP y APIs OCS.

- App ID: `legal_advice`
- Nombre visible: `Consultas Legales`
- Version actual: `0.1.4`
- Licencia: `AGPL-3.0-or-later`

## Capacidades incluidas

- Creacion de tickets en dos pasos con tipo jerarquico, provincia y datos personales configurables.
- Consola de usuario con listado propio, detalle lateral, pantalla completa en solo lectura y repeticion de incidencias.
- Consola de soporte con tabla configurable, filtros guardados, exportacion CSV y detalle lateral o de solo lectura segun permisos.
- Configuracion unificada con secciones para configuracion personal, preferencias de soporte y administracion segun el rol.
- Consola de administracion con catalogos, reglas de asignacion, perfiles, filtros globales y configuracion de adjuntos y Tasks.
- Notificaciones Nextcloud y correo usando las capacidades nativas de la instancia.
- Integracion best-effort con calendarios VTODO para reflejar asignaciones en Tasks sin bloquear el flujo principal.

## Estructura del repositorio

- `appinfo/`: manifiesto, rutas OCS y bootstrap de la app.
- `lib/`: controladores, entidades, mapeadores, servicios y migraciones.
- `src/`: SPA Vue 3 con TypeScript, router, stores y vistas.
- `templates/`: plantilla de entrada de Nextcloud.
- `css/`: estilos globales del shell y utilidades visuales.
- `img/`: iconos y recursos estaticos.
- `js/`: artefactos compilados del frontend usados en runtime.
- `tests/`: pruebas unitarias, de integracion y frontend.
- `dev/clean-nextcloud/`: stack limpio para pruebas manuales locales.

## Requisitos

- Nextcloud 30.0.4
- PHP 8.1 o superior
- Node.js 20 o superior
- npm 10 o superior
- Composer 2

## Instalacion en desarrollo

1. Coloca el repositorio en `apps/legal_advice` dentro de tu instancia Nextcloud.
2. Instala dependencias PHP:

```bash
composer install
```

3. Instala dependencias frontend:

```bash
npm install
```

4. Compila la SPA:

```bash
npm run build
```

5. Activa la app desde la CLI de Nextcloud:

```bash
php occ app:enable legal_advice
```

Si la app ya estaba activa y has cambiado backend, migraciones o manifiesto, desactiva y activa de nuevo la app o ejecuta el flujo de recarga de tu entorno.

## Flujo de desarrollo

- Compilacion frontend:

```bash
npm run build
```

- Compilacion incremental:

```bash
npm run watch
```

- Lint frontend:

```bash
npm run lint
```

- Tests frontend:

```bash
npm run test:frontend
```

- Tests PHP:

```bash
composer test:unit
```

## Stack limpio local

El repositorio incluye un stack de Nextcloud limpio en `dev/clean-nextcloud/` para pruebas manuales sobre `http://localhost:8090`.

- Recarga rapida de la app:

```powershell
./dev/clean-nextcloud/reload-app.ps1
```

La documentacion adicional del stack esta en `dev/clean-nextcloud/README.md`.

## Despliegue y publicacion

Este repositorio es el arbol de desarrollo. Para distribuir la app o preparar una release para la tienda de Nextcloud, genera primero los artefactos de frontend y empaqueta solo el contenido necesario en runtime.

El paquete de distribucion debe incluir, como minimo:

- `appinfo/`
- `css/`
- `img/`
- `js/`
- `lib/`
- `templates/`
- `LICENSE`
- `README.md`

No conviene publicar la app en la tienda tomando sin mas el repositorio fuente si faltan artefactos compilados o si el paquete incluye contenido solo de desarrollo.

## Notas operativas

- Los permisos y validaciones sensibles se resuelven en backend.
- Los adjuntos se guardan en AppData de Nextcloud y se sirven respetando permisos backend.
- La integracion con Tasks es best-effort: si falla, el flujo principal del ticket continua.