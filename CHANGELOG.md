## 0.3.2

### Changed
- Refinado el flujo de comentarios en tickets para unificar la experiencia entre usuario y soporte.
- Ajustada la composición en móvil y la presentación del historial de comentarios.
- Añadido reordenado drag and drop de estados en administración, usado también por el selector de estado en soporte.
- Agrupados filtros y opciones de comentarios de soporte bajo el botón `Filtros y opciones`.
- Añadidas acciones de edición y borrado de comentarios según permisos del perfil y del contexto de la incidencia.

### Added
- La descripción inicial del ticket pasa a publicarse como primer comentario, incluyendo sus adjuntos iniciales.
- La exportación de comentarios añade la columna `Adjuntos` y la apertura de URLs externas pide confirmación.
- Añadida columna opcional de adjuntos en la consola de soporte.
- Añadida la opción de borrado de tickets con validación y confirmación desde los perfiles con permiso.

### Fixed
- Corregidos varios fallos en comentarios y asignación.
- Corregida la coherencia del comportamiento de comentarios y adjuntos entre las distintas vistas de ticket.
- Corregido el orden de criticidad por defecto para que `Alta=1`, `Media=2` y `Baja=3`.
- Corregida la visibilidad de acciones de comentarios para que `Mis incidencias` mantenga comportamiento de usuario aunque la cuenta tenga también roles de soporte o administración.
- Corregida la persistencia de filtros guardados vacíos en soporte y la visualización de etiquetas visibles de estado en la tabla.

## 0.2.0
### Fixed
- Corregidas las llamadas frontend a las APIs OCS de la app para usar la ruta real de Nextcloud bajo `ocsapp` y solicitar JSON explícitamente.
- Reforzada la resiliencia de la app para que fallos de configuración o integraciones no críticas como Tasks, notificaciones, adjuntos, bootstrap y configuración administrativa no bloqueen la carga parcial de las vistas principales.

## 0.1.8
### Added
- Primera publicación preparada para App Store con metadatos y proceso de firma.