# Seguridad — EIRA Resultados (prototipo)

Este documento describe riesgos y controles del prototipo. **No es una declaración de cumplimiento normativo.**

## Qué se protege

Documentos de resultados (datos personales sensibles de salud), los datos de contacto de las pacientes y los códigos temporales de acceso.

## Riesgos y controles

| Riesgo | Control en el prototipo | Riesgo residual / pendiente para piloto |
| --- | --- | --- |
| **Mala asociación a un estudio** | Asociación solo manual, por coincidencia exacta de folio interno, folio del formulario o número citológico, con confirmación explícita. Nunca por nombre ni ID EIRA. Coincidencia múltiple se marca como ambigua. Revisión posterior confirma correspondencia; publicación la hace otra cuenta. | Error humano en dos pasos. Definir doble verificación o lectura de QR del formato. |
| **Código (OTP) interceptado** | El aviso no incluye el folio del estudio: se necesitan ambos. Código de un solo uso, vigencia corta, máximo de intentos, canje atómico; al canjear uno se revocan los demás del estudio. | Quien controle el teléfono/correo **y** tenga el comprobante puede entrar. El folio del formato es previsible y puede repetirse, así que el código es el único secreto. Control del canal ≠ prueba de identidad. |
| **Revisión y publicación en un solo paso** (`EIRA_PUBLISH_ON_REVIEW=true`, decisión temporal) | La misma operadora confirma los tres puntos de revisión y, con ese clic, publica y notifica; todo queda en bitácora. La autorización clínica se toma como la de origen (laboratorio). | Se pierde el doble control: un error de asociación o de revisión llega directo a la paciente. Volver a `false` para exigir una cuenta de publicación distinta. |
| **Vigencia larga del código (7 días)** | Decisión de EIRA para que la paciente tenga margen. Se mantiene un solo uso, máximo de intentos, límites por IP y por folio, y revocación de los demás códigos al canjear uno. | Una ventana de 7 días amplía el tiempo en que un SMS leído por otra persona sirve para entrar. Reducir si el piloto muestra que se consulta antes. |
| **Teléfono compartido o no verificado** | Solo se avisa a contactos marcados como verificados; un teléfono escrito en el formulario no se considera verificado. No se puede indicar un contacto nuevo desde el portal. | El procedimiento de verificación de contactos no está definido ni implementado. |
| **Error de liberación** | Publicar exige estado «revisado», checklist completo, integridad SHA-256 del archivo y rol de publicación verificado en servidor (403 en ruta directa). Revocación inmediata: la siguiente solicitud ya no descarga. | Si la paciente ya descargó el PDF, la revocación no lo recupera. |
| **Acceso cruzado entre estudios** | Sesión de paciente ligada a un único estudio; cada solicitud valida estudio, estado publicado y revocación. Documentos ajenos responden 404. | — |
| **Documentos públicos** | Disco privado (`storage/app/private`), sin `storage:link`, ruta `/storage` de Laravel deshabilitada, sin URLs firmadas. Nombres de archivo opacos (UUID). Respuestas `Cache-Control: private, no-store`. | Con S3: configurar Block Public Access, cifrado, IAM mínimo y revisar backups. No probado en este prototipo. |
| **Duplicados** | Archivo idéntico activo (mismo SHA-256) se rechaza. Segundo resultado para el mismo estudio exige confirmar que es corrección y crea versión nueva; la anterior se revoca al publicar la nueva y su archivo se conserva. Avisos idempotentes por documento + versión + contacto. | Dos fotos distintas del mismo papel no comparten hash; depende de la advertencia y la revisión humana. |
| **Pérdida del formato / comprobante** | El portal no permite recuperar con ID EIRA + fecha de nacimiento (el ID EIRA es derivable). Se indica atención asistida. | **Recuperación asistida no implementada**: falta definir cómo se verifica identidad y quién custodia el original. |
| **Enumeración y fuerza bruta** | Mensaje único e idéntico para folio inexistente, código incorrecto o documento no publicado. Límite por IP y por folio. Reenvío de código con respuesta neutra y enfriamiento. | El folio es secuencial y previsible; los límites por IP son evitables con múltiples orígenes; añadir WAF/captcha en piloto. |
| **Archivos maliciosos** | Tipo real por contenido (no por extensión), extensión coherente, tamaño máximo, decodificación de imagen con límites de dimensiones, estructura mínima de PDF. | No hay antivirus ni saneamiento de PDF (un PDF válido puede contener contenido activo). |
| **Fuga por bitácora o logs** | La bitácora guarda actor, recurso, acción, resultado y metadatos técnicos; nunca códigos, contactos, cuerpos de mensaje ni diagnósticos. Contactos cifrados en base de datos. | Documentos en disco local **sin cifrado en reposo** en el demo. |
| **Cuentas internas** | Cuentas individuales, roles verificados en servidor, CSRF, regeneración de sesión, límite de intentos de inicio de sesión. Administración no hereda publicación. | Sin 2FA ni política de contraseñas. Contraseña DEMO compartida y documentada. |

## Concesiones propias de la demo (desactivar antes de un piloto)

- **Bandeja simulada** (`EIRA_DEMO_OUTBOX=true`): el personal puede ver el aviso con el código para demostrar el flujo sin un teléfono real. Rompe deliberadamente la separación entre personal y paciente; solo funciona con transportes simulados y fuera de producción.
- **Cambio rápido de cuenta** (`EIRA_DEMO_QUICK_LOGIN=true`): botones para entrar como Operadora, Publicadora o Admin DEMO sin contraseña. Solo funciona con las cuentas DEMO sembradas, nunca en producción, y cada acción queda a nombre de la cuenta real (no es un superadmin). Quien tenga acceso a la URL puede entrar como cualquier cuenta DEMO: desactivar fuera de la demo.
- **Tipografías e íconos desde Google Fonts** (solicitud a un tercero desde el navegador).
- `APP_DEBUG=true` y cookies sin `Secure` en local.

## Reporte

Prototipo de hackathon sin datos reales. No cargar información de pacientes reales en este sistema.
