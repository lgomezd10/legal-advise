## 0.3.1

### Changed
- Refinado el flujo de comentarios en tickets para unificar la experiencia entre usuario y soporte.
- Ajustada la composicion en movil y la presentacion del historial de comentarios.

### Added
- La descripcion inicial del ticket pasa a publicarse como primer comentario, incluyendo sus adjuntos iniciales.
- La exportacion de comentarios añade la columna `Adjuntos` y la apertura de URLs externas pide confirmacion.

### Fixed
- Corregidos varios fallos en comentarios y asignación.
- Corregida la coherencia del comportamiento de comentarios y adjuntos entre las distintas vistas de ticket.

## 0.2.0
### Fixed
- Corregidas las llamadas frontend a las APIs OCS de la app para usar la ruta real de Nextcloud bajo `ocsapp` y solicitar JSON explicitamente.
- Reforzada la resiliencia de la app para que fallos de configuracion o integraciones no criticas como Tasks, notificaciones, adjuntos, bootstrap y configuracion administrativa no bloqueen la carga parcial de las vistas principales.

## 0.1.8
### Added
- Primera publicacion preparada para App Store con metadatos y proceso de firma.