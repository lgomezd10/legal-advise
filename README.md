# Consultas Legales

Consultas Legales es una aplicacion nativa para Nextcloud que centraliza la recepcion, seguimiento y gestion interna de consultas o incidencias con un flujo claro para usuarios, equipos de soporte y administradores.

La app esta pensada para organizaciones que necesitan recoger solicitudes desde Nextcloud, clasificarlas, asignarlas correctamente y mantener todo el historial en un unico lugar, sin depender de servicios externos para el flujo principal.

![alt text](screenshots/image.png)

## Que resuelve

- Permite que los usuarios, incluidos en alguno de los perfiles configurados, creen y gestionen consultas desde una interfaz guiada.
- Da visibilidad al estado de cada incidencia y a su historial de comentarios y adjuntos.
- Facilita el trabajo de soporte con filtros, asignacion, seguimiento y exportacion.
- Centraliza la configuracion funcional en una consola de administracion integrada en Nextcloud.
- Permite crear reglas para la asignación automática de tickets.

## Instalacion

La app puede instalarse desde el apartado `Apps` de Nextcloud. En el Store aparece clasificada dentro de las categorias `Organization`, `Social & communication` y `Tools`.

## Funciones principales

### Alta de incidencias

- Creacion de tickets en dos pasos para guiar mejor al usuario.
- Seleccion jerarquica de tipo de incidencia.
- Seleccion de provincia para apoyar el enrutado y la asignacion.
- Formulario con datos personales configurables por la organizacion.
- Envio inicial con descripcion, archivos y enlaces.

### Area de usuario

- Vista de `Mis incidencias` con acceso rapido a la creacion de un nuevo ticket.
- Consulta del estado actual y del historial visible de cada incidencia.
- Pantalla de detalle en modo solo lectura para los datos del ticket.
- Publicacion de comentarios y adjuntos como parte de la conversacion.
- Accion de `Repetir incidencia` para reutilizar informacion de un caso anterior.
- Configuracion personal para precargar datos frecuentes en nuevas solicitudes.

### Consola de soporte

- Listado configurable con una vista compacta y orientada a gestion diaria.
- Filtros por estado, asignacion, tipo, provincia, fechas y texto libre.
- Filtros predefinidos y filtros guardados por el equipo.
- Gestion de estado, criticidad, asignacion y descripcion interna de soporte.
- Comentarios internos o visibles para el usuario segun el contexto.
- Exportacion CSV del conjunto visible de resultados.

### Administracion

- Gestion de perfiles funcionales y acceso por usuarios o grupos de Nextcloud.
- Configuracion de tipos y subtipos de incidencia.
- Catalogos iniciales para empezar a usar la app desde el primer arranque.
- Reglas de asignacion automatica por tipo y, opcionalmente, por provincia.
- Configuracion de extensiones y limites para adjuntos.
- Preferencias de notificaciones.

## Roles de la aplicacion

La aplicacion organiza la experiencia en tres perfiles funcionales:

- `Usuario`: crea incidencias, consulta su seguimiento y responde cuando se solicita mas informacion.
- `Soporte`: trabaja las incidencias visibles, aplica filtros, comenta, asigna y actualiza el estado.
- `Administrador`: configura catalogos, perfiles, reglas, notificaciones e integraciones.

El acceso no se concede por el simple hecho de tener una cuenta en Nextcloud. La app solo se muestra a usuarios que tengan al menos un perfil efectivo.

Ese perfil efectivo se calcula a partir de la configuracion de `Perfiles`, donde cada perfil puede asignarse directamente a usuarios concretos o a grupos reales de Nextcloud. Un mismo usuario puede tener mas de un perfil si coincide con varias asignaciones.

Si un usuario no tiene ningun perfil efectivo, la aplicacion no carga su navegacion funcional y redirige fuera de la SPA principal.

En una instalacion inicial, la app puede sembrar asignaciones base para grupos de referencia como `userLegal`, `supportLegal` y `admin`. A partir de ahi, la configuracion guardada en `Perfiles` es la que determina el acceso real.

## Integracion con Nextcloud

- Usa usuarios y grupos reales de Nextcloud para permisos y asignacion.
- Guarda archivos adjuntos en la infraestructura de archivos de Nextcloud.
- Se integra con notificaciones nativas de Nextcloud y con correo segun configuracion.

## Pensada para un uso real desde el primer dia

Consultas Legales incluye una base funcional orientada a trabajar desde el primer arranque:

- tipos de consulta iniciales;
- criticidades base;
- flujo de estados inicial;
- soporte para catalogos y configuracion progresiva;
- separacion clara entre experiencia de usuario, soporte y administracion.

## Compatibilidad

- Nextcloud 30 a 33
- PHP 8.1 o superior

## Nota para desarrollo

Este repositorio incluye el codigo fuente completo de la app. Para desarrollo local y pruebas manuales existe un entorno limpio en `dev/clean-nextcloud/`. Si necesitas compilar el frontend o ejecutar tests, consulta los scripts definidos en `package.json` y `composer.json`.

## Licencia

`AGPL-3.0-or-later`