# AGENTS.md — EIRA Resultados

> Instrucciones para agentes de desarrollo que trabajen en este repositorio.
> **Proyecto:** Vibeathon México 2026 · EIRA · Reto 4: entrega digital segura de resultados de Papanicolaou.
> **Prioridad:** prototipo funcional, demostrable y seguro por diseño; no una plataforma clínica completa.
> **Fecha de referencia del hackathon:** 2026-11-21 (CDMX). Meta de envío Devpost: 15:30; límite: 16:00; congelar extras a las 14:45.

## 0. Antes de tocar código

1. **Inspecciona el repositorio.** Determina estructura, versiones, comandos, tests, dependencias, convenciones y qué funciona. No presupongas que Laravel está instalado ni que se inicia desde cero.
2. Inspecciona `template/` si existe: contendrá pantallas o recursos exportados de Stitch. Reutiliza diseño, estilos y assets; **no copies lógica de autenticación simulada ni confíes en permisos del frontend**. Si aún no existe, continúa sin bloquearte.
3. Conserva los avances existentes. Propón cambios pequeños y ejecutables; no reescribas el proyecto ni cambies stack sin una razón técnica y autorización.
4. Comprueba el flujo vertical y sus tests antes de añadir funcionalidades vistosas. Registra lo implementado, simulado, pendiente y los comandos para reproducirlo.
5. **Datos 100 % ficticios** en código, seeders, capturas y demo. No uses pacientes reales ni subas formularios llenados por EIRA, expedientes, teléfonos reales de pacientes, credenciales o secretos a repositorios ni asistentes.
6. No afirmes cumplimiento legal, certificaciones, firma electrónica ni validación de identidad que no estén implementados y comprobados.

## 1. Contexto: confirmado vs. propuesto

### Confirmado por reto y conversaciones con EIRA

- El laboratorio entrega los resultados impresos a EIRA; EIRA los fotografía/escanea, transforma a PDF y los envía por WhatsApp. El bloqueo de ese canal interrumpe la entrega. Volumen aproximado: 300+ resultados/mes.
- El formato de una página «Solicitud y reporte de citología cervical» incluye **No. de folio**, **ID EIRA**, **Número citológico**, teléfonos y correo. No contiene CURP.
- EIRA informó verbalmente que los resultados llegan autorizados desde el laboratorio. El formulario permite indicar si hubo revisión por patólogo; **no suponer que todos los documentos tienen firma de patólogo**.
- Ocasionalmente un médico adjunta una **receta** a la entrega; no es obligatoria. Detalle verbal, no requisito escrito del reto.
- La paciente a veces pierde su formato/comprobante. En brigadas puede faltar señal e impresora.
- Según un mensaje compartido por EIRA, el **ID EIRA se construye a partir de iniciales de nombre/apellidos y fecha de nacimiento**. Es derivable, puede colisionar y **no es credencial de autenticación**.
- El reto exige revisión administrativa, autorización clínica antes de publicar, acceso diferenciado y autenticado, seguimiento y notificación por SMS o correo sin diagnósticos, y alternativa asistida.

### Supuestos pendientes de validar; no inventar respuestas

- Qué representa exactamente cada folio, su unicidad y momento de asignación, y si ID EIRA es referencia persistente o por toma.
- Quién sube materialmente cada documento, quién realiza la revisión y **quién tiene facultad de publicarlo**.
- Si la autorización clínica de origen del laboratorio satisface el requisito de autorización del reto o si esperan control adicional. **No inventar segunda firma o nueva interpretación médica**.
- Cómo verificar y autorizar teléfonos/correos, especialmente familiares o dispositivos compartidos.
- Cómo se verificará la identidad para recuperación asistida y quién custodia el expediente original.

**Regla:** cuando una decisión dependa de estos supuestos, adoptar la variante más pequeña y segura para el demo, etiquetarla como supuesto y dejarla documentada para piloto.

## 2. Objetivo y límites del MVP

Construir un flujo vertical real y probado:

1. Sembrar pacientes y estudios **DEMO**, cada estudio con UUID interno y referencia de consulta única.
2. Cargar **PDF** ficticio; permitir **JPG/PNG** como incremento cercano, guardando el original y convirtiendo la imagen a PDF para entrega solo tras revisión.
3. Asociar manualmente el documento a un estudio mediante referencias administrativas y confirmación humana. Si hay ambigüedad, dejar pendiente; nunca asociar automáticamente por nombre, EIRA ID o coincidencia difusa.
4. Revisar legibilidad, correspondencia y evidencia de autorización clínica de origen. Registrar quién y cuándo.
5. Publicar exclusivamente mediante autorización de servidor y precondiciones verificadas. Separar revisión administrativa de publicación; el sujeto responsable de publicación es configurable y **pendiente de confirmación con EIRA**. Para demostrar segregación de funciones, usar cuentas de demo distintas.
6. Crear un **código temporal de acceso** y notificar al contacto previamente vinculado/autorizado usando **Resend para email** y una interfaz SMS con proveedor pendiente. Se pueden enviar avisos por ambos canales si hay datos autorizados; manejar envíos y fallos independientemente.
7. Paciente introduce **referencia única del estudio + código temporal** y recibe una sesión corta limitada a ese estudio, sin cuenta tradicional; visualiza/descarga únicamente documentos publicados.
8. Registrar acciones y estados sin almacenar contenidos clínicos en logs. Mostrar panel operativo pequeño.

**Fuera de alcance** salvo que todo lo anterior pase tests: expediente clínico único, recaptura clínica, interpretación/diagnóstico IA, OCR, editor o emisión de recetas, PWA offline, microservicios, SPA, búsqueda biométrica, identificación automática de pacientes, portal complejo de laboratorio, firma digital formal y certificaciones regulatorias.

## 3. Stack preferido

- **Laravel** existente; Blade + Tailwind CSS + Alpine.js solo si hace falta interactividad pequeña. Vite para assets.
- Base de datos relacional que ya esté operativa en el repo; **SQLite para demo local** si no hay DB configurada. MySQL/PostgreSQL son válidos si ya existen. No cambiar el motor por preferencia estética.
- **AWS S3 como destino previsto de almacenamiento privado de documentos**. Implementar detrás de Laravel `Storage` / servicio abstracto, con disco local **privado** como fallback de desarrollo y pruebas. No se debe exigir AWS para correr el MVP local.
- **Resend para correo transaccional** mediante un adaptador de notificación; usar mail fake/log seguro en tests/desarrollo. Resend es correo; **S3 no se utiliza para enviar correo**.
- **SMS preparado pero proveedor NO elegido**: interfaz `SmsSender` / `NotificationChannel` con implementaciones `FakeSmsSender` y `DisabledSmsSender`, más adaptador futuro. No inventar claves, endpoints, precios ni configurar un servicio pagado sin indicación.
- Tests: PHPUnit/Pest según lo que ya exista en el repo, priorizando Feature Tests.
- Identidad visual oficial de EIRA: logos PNG proporcionados por el usuario; Google Font **M PLUS Rounded 1c**, preferiblemente autoalojada para piloto; usar `page_template/` como referencia de Stitch. Responsive/mobile-first, accesible, interfaz en español de México.

## 4. Modelo mínimo recomendado

Ajustar a lo que ya exista en el repositorio; no crear campos innecesarios.

- `patients`: `id` UUID, `external_eira_id` opcional (referencia externa, **no única por defecto**).
- `studies`: `id` UUID, `patient_id` nullable al principio, `folio` interno único, `public_reference` única y opaca, `external_form_folio` opcional, `external_cytology_number` opcional, `taken_at` opcional.
- `documents`: `id` UUID, `study_id` nullable mientras no se confirme, `type` (`result`, `prescription`), rutas opacas de original y versión de entrega, `sha256`, MIME, tamaño, versión/estado, usuario y timestamps de carga, asociación, revisión y publicación.
- `users`: cuentas internas individuales con `role`/permissions y verificación de alcance; para demo `operator` y `publisher`/`clinician` según nomenclatura existente. El administrador **no hereda poder clínico de publicación**.
- `study_contacts` o equivalente: contacto autorizado por estudio/canal, estado de verificación y consentimiento/procedencia, protegidos. **No asumir que un teléfono escrito en el formulario está verificado.**
- `access_challenges`: `study_id`, `channel`, hash de OTP, `expires_at`, intentos, `consumed_at`, revocación. Nunca guardar OTP en claro.
- `notifications` / `notification_attempts`: canal, intento, idempotency key, estado propio del proveedor, tiempos, errores sanitizados.
- `audit_events`: referencia de actor, estudio, documento, acción, resultado y timestamp; sin información clínica, OTP, tokens o cuerpos de mensaje sensibles.

**No equiparar** ID EIRA, folio del formulario, número citológico y folio interno; podrían tener semánticas diferentes. Soportar múltiples documentos por estudio, con resultado principal y receta opcional independientes.

## 5. Flujo de estados propuesto, no oficial de EIRA

- Documento: `received` → `pending_association` → `pending_review` → `ready_for_publication` → `published`; rutas de `rejected`/`revoked` donde corresponda.
- Avisos: `pending` → `accepted` → `delivered` o `failed`/`unknown`; **accepted/sent ≠ delivered**.
- Consulta: registrar `viewed` y `downloaded` como **eventos**, sin inferir que la paciente leyó o comprendió el resultado.

Estado documental, notificación y consulta deben ser independientes. Falla de SMS/Resend **nunca revoca o bloquea** un PDF ya publicado. No crear una firma médica adicional ficticia solo para satisfacer una pantalla de Stitch.

## 6. Seguridad no negociable

### Archivos

- S3 **privado**: Block Public Access, ACL públicas deshabilitadas, cifrado en reposo, TLS, identidad IAM de mínimo privilegio, credenciales fuera del repositorio, rutas de objetos opacas sin nombres ni diagnósticos. Revisar backups/retención para piloto.
- En desarrollo usar `storage/app/private` o disco equivalente, **nunca** `public` ni `storage:link` hacia resultados.
- **Descarga a través de ruta autenticada de Laravel**, validando en *cada request* sesión, estudio, pertenencia, estado publicado y revocación. Laravel transmite/stream el archivo desde el disco privado. No utilizar Signed URLs descargables directamente por pacientes en el MVP.
- Archivo original inmutable; SHA-256 como comprobación de integridad (no cifrado). No sobrescribir una versión publicada; correcciones requieren trazabilidad.
- Validar extensiones, tipo real/MIME, tamaño, imágenes corruptas y dimensiones de procesamiento; usar nombres generados por servidor. Limitar recursos; inspeccionar la imagen antes de generar PDF. Nunca aplicar mejoramiento generativo que altere texto clínico.

### Identidad y OTP

- **Referencia de consulta pública opaca y única + OTP temporal** emitido a contacto vinculado. Ni EIRA ID, ni fecha de nacimiento, ni teléfono por sí solos autentican.
- OTP de alta aleatoriedad (si son 6 caracteres, alfanuméricos seguros), hash con mecanismo adecuado, vigencia corta (valor inicial orientativo 5–15 min, a ajustar por entrega real), máximo de intentos (p. ej. 5), cooldown de reenvío y rate limits por IP/estudio/canal.
- Un OTP se consume **atómicamente** una sola vez y crea sesión server-side de 15–30 minutos limitada al estudio; regenerar ID de sesión al autenticarse. No permitir acceso cruzado entre estudios.
- Antes de publicar, no mostrar el PDF; la respuesta pública es neutra y no debe facilitar enumeración. Si se emiten códigos antes de la publicación, no consumirlos por una consulta prematura.
- Enviar referencias/códigos por SMS o email es una **decisión de simplicidad del demo**, no garantía de autenticación fuerte; control del canal ≠ prueba de identidad. Si un código caduca antes de consulta, el reenvío debe estar protegido contra enumeración/abuso; nunca enviar uno a un contacto arbitrario introducido en el portal.
- Si hay SMS y email, manejar **códigos independientes por canal** y revocar otros desafíos de ese estudio cuando se canjee uno. No reutilizar un OTP indefinidamente.
- Si se pierde el formato y no hay acceso a un contacto previamente autorizado, indicar **atención asistida con verificación humana**, sin habilitar descarga por ID EIRA + nacimiento. Para piloto definir y validar el procedimiento, sin fingir que ya está implementado.

### Autorización y auditoría

- `Policies`/gates del servidor: operador carga/asocia/revisa; quien puede publicar está autorizado explícitamente y condicionado a revisión previa. No confiar en roles de Blade ni en botones ocultos.
- Distinguir **validación clínica de origen** (laboratorio) de **decisión de publicar** (EIRA). No diagnosticar ni asumir que el segundo paso es una nueva autorización médica. La interpretación del requisito de autorización clínica queda documentada como punto de validación.
- Todas las operaciones sensibles dejan evento con usuario, recurso, acción, resultado y fecha; no registrar diagnósticos, secretos, archivos, tokens ni información clínica.
- Aplicar cookies `Secure`/`HttpOnly`/`SameSite` según entorno, protección CSRF, validación server-side, respuestas y headers sin caché para PDFs (`Cache-Control: private, no-store`). No almacenar PDFs sensibles en service workers.

## 7. Duplicados y versiones

- `studies.folio` interno con índice `UNIQUE`.
- SHA-256 permite advertir archivo idéntico; fotos diferentes del mismo papel no compartirán hash.
- Antes de crear otro `result` para el mismo estudio, **advertir y exigir revisión**; permitir una corrección/versionado controlado, nunca sobrescritura silenciosa.
- ID EIRA coincidente **no fusiona pacientes automáticamente**. Número citológico + laboratorio es señal auxiliar de posible duplicado solo si se confirma su semántica.
- Notificaciones idempotentes por evento de publicación + canal + versión; reintentos explícitos y auditables, no envíos duplicados por doble clic.
- Una receta opcional no reemplaza el resultado y requiere su propia verificación/publicación.

## 8. Canales de notificación

Contrato recomendado: `NotificationDispatcher` que trabaja con `EmailSender` (Resend) y `SmsSender` (fake/disabled por ahora). Que el negocio dependa de interfaces y no de APIs de terceros.

- **Email:** Resend mediante API/SDK o transporte compatible; credenciales (`RESEND_API_KEY`, remitente/dominio verificado) solo en `.env`. Jamás registrar API key, OTP o cuerpo clínico. En local/tests simular.
- **SMS:** no elegir provider hasta que el usuario lo decida. Mantener `SMS_DRIVER=fake|disabled` y preparar interfaz/adaptador. No simular un estado `delivered` real cuando solo hubo aceptación.
- Si hay ambos contactos autorizados, enviar por ambos con códigos diferentes; si uno falla, el otro sigue funcionando. La disponibilidad del documento no depende del aviso.
- Texto neutro y sin tipo de estudio, diagnóstico, hallazgos ni enlaces personales. Ejemplo **DEMO** (dominio ilustrativo, no oficial):

  `EIRA: Tenemos una actualización de tu estudio. Consulta resultados.eira.example con tu referencia y el código temporal ABC123.`

- Los avisos contienen credenciales y por tanto son sensibles; conservar contenido mínimo, nunca en logs. No anunciar que se envió/entregó hasta confirmarlo con el transporte.

## 9. Pantallas y UX

Usar recursos existentes y revisar `page_template/` antes de implementar. Priorizar cuatro vistas:

1. **Dashboard EIRA**: pendientes, publicados, envíos, consultas, actividad; no mostrar diagnósticos.
2. **Recepción/revisión**: subir PDF/JPG/PNG, vista previa privada, búsqueda por folio, referencias opcionales, advertencia de duplicado, checklist legibilidad/asociación/autorización de origen, confirmar revisión.
3. **Publicación**: documento revisado, evidencia de origen, acción explícita de publicar/rechazar; roles en servidor.
4. **Portal paciente mobile-first**: referencia de consulta + código temporal, mensajes neutros, visor/descarga autorizados y ayuda de acceso asistido.

Diseño con logos PNG originales, M PLUS Rounded 1c, paleta tomada de recursos EIRA en `page_template/` o assets reales; jamás inventar una nueva marca. Interfaces en español de México; accesibilidad, contraste, targets táctiles grandes, errores y estados vacíos. Stitch es referencia visual, **Laravel es la fuente de verdad**.

## 10. Pruebas de aceptación obligatorias

Escribir/ejecutar tests en el primer corte, con archivos ficticios:

1. Operador sube documento sintético y puede asociarlo al estudio correcto.
2. Archivo no válido/corrupto/sobredimensionado se rechaza con error seguro.
3. Sin asociación o revisión no se puede publicar.
4. Operador sin permiso de publicación recibe `403` incluso por ruta directa.
5. Publicador autorizado puede publicar solo después de revisión y control de origen.
6. Antes de publicar, la paciente no puede descargar; el código no se consume si se decide permitir consulta prematura.
7. Sesión del estudio A no accede al PDF del estudio B, ni con URL directa.
8. Documento revocado deja de poder descargarse en la siguiente solicitud.
9. Código correcto canjea una vez; código reutilizado, vencido o tras demasiados intentos se rechaza.
10. SMS `failed` o `disabled` y correo fallido no impiden consulta válida ni publicación.
11. No se crean documentos activos idénticos ni notificaciones duplicadas accidentalmente; correcciones son versiones controladas.
12. Dos pacientes con el mismo ID EIRA no se fusionan automáticamente.
13. No existen rutas públicas de los PDFs; autenticación de staff y CSRF activos.

## 11. Orden de ejecución y criterios de terminación

**Primero:** arranque local reproducible, migraciones/seeders DEMO, PDF privado, asociación confirmada, revisión, publicación autorizada, lectura protegida y tests de acceso.

**Luego:** integrar `page_template/` y móvil, JPG/PNG→PDF, duplicados, bitácora, OTP y email fake/Resend, SMS fake, panel de estados.

**Extras solamente si todo pasa:** SMS real cuando se elija proveedor y se disponga de un número propio de prueba; receta opcional; mejora no generativa de foto; generador de formatos/QR.

Un corte se considera terminado solo si: corre localmente con instrucciones claras, migra y siembra DEMO, pasan las pruebas críticas, es demostrable en ventana incógnita y no hay secretos ni datos reales.

### Demo objetivo (aprox. 2 minutos)

Cargar PDF ficticio → asociar a folio de estudio → demostrar que no abre antes de publicación → verificar/revisar → publicar con cuenta autorizada → simular aviso fallido o enviar email demo → acceder con referencia + OTP → visualizar/descargar PDF → mostrar que otra sesión no tiene acceso. Explicar lo implementado, lo simulado y lo pendiente para piloto. No prometer cumplimiento normativo.

## 12. Documentación y entrega

Mantener README breve con: instalación, `.env.example` sin secretos, configuración S3/privado local, Resend fake/real, SMS pending/fake, migraciones, seeders, pruebas, cuentas DEMO, arquitectura, estados, decisiones y costes separados (infraestructura, almacenamiento/backups, operación, SMS/email), modelo de amenazas, riesgos y supuestos.

Añadir `SECURITY.md` simple con riesgos: mala asociación a estudio, OTP interceptado, teléfono compartido/no verificado, errores de liberación, acceso cruzado, documentos públicos, duplicados, pérdida de formato y recuperación asistida.

El sistema **no acredita cumplimiento normativo**. Para piloto con datos reales se requiere análisis legal/técnico en México (protección de datos personales sensibles, expediente clínico según aplicabilidad, funciones del laboratorio y prestadores, consentimiento, privacidad, retención, contratos cloud, controles organizacionales y respuesta a incidentes).

Referencias del proyecto (contexto, no datos de prueba):
- https://vibeathon-mexico-2026.devpost.com/
- https://vibeathon-mexico-2026.devpost.com/rules

**Regla final para agentes:** ejecuta la mínima modificación que complete el siguiente flujo verificable. No agregues frameworks, proveedores ni pantallas por estética. Si algo no está confirmado, haz explícita la hipótesis y mantén una ruta segura para la demo.

## 13. Estado del repositorio y comandos (actualizado 2026-11-21)

- Laravel 13 + Blade + Tailwind 4 (Vite) + SQLite (`database/database.sqlite`). Pruebas con PHPUnit. Detalle de lo implementado, simulado y pendiente en `README.md`; riesgos en `SECURITY.md`.
- Los recursos de Stitch están en **`template/`** (no `page_template/`). Los tokens de diseño ya están portados a `resources/css/app.css`; no se usa el CDN de Tailwind ni Alpine.
- En esta máquina PHP, Composer y Node vienen de Laragon y **no están en el PATH**. En PowerShell, anteponer:
  `$env:PATH = "D:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;D:\laragon\bin\nodejs\node-v22;" + $env:PATH`
  Composer: `php -d extension=zip D:\laragon\bin\composer\composer.phar <comando>` (la extensión `zip` no está habilitada en `php.ini`).
- Verificación antes de dar un cambio por terminado: `php artisan test` · `php vendor\bin\pint --test` · `npm run build` (si se tocaron vistas o CSS).
- Arranque local: `php artisan migrate --seed` · `php artisan eira:demo-files` · `php artisan serve`. Cuentas y referencias DEMO en `README.md`.
- No instalar Laravel Boost ni ejecutar `boost:install`: regenera `AGENTS.md` y sobrescribiría estas instrucciones.
- No ejecutar `storage:link` ni reactivar `'serve' => true` en el disco `local`: ambos abrirían rutas públicas hacia documentos.
- Por decisión del usuario, `EIRA_PUBLISH_ON_REVIEW=true` en `.env`: confirmar la revisión publica y notifica (sin publicador). Las pruebas fijan `false` para seguir cubriendo la separación de funciones; `PublishOnReviewTest` cubre el modo de un paso.
- Proveedor SMS elegido por el usuario: **ClickSend** (`SMS_DRIVER=clicksend`, `App\Services\Notifications\ClickSendSmsSender`). `SUCCESS` = en cola → `accepted`; no hay webhooks de entrega todavía. Credenciales solo en `.env`.
- Cambios decididos por el usuario (2026-11-21): no hay datos precargados; el estudio se registra en Recepción con los datos transcritos del formato (folio del formato e ID EIRA siempre vienen; el folio interno lo genera el sistema). La paciente consulta con el **No. de folio del formato** + «Código recibido por SMS» (`public_reference` se eliminó). Ese folio es previsible y puede repetirse: el código es el único secreto. ID EIRA: 2 letras apellido paterno + inicial materno + inicial nombre (segundo si es María) + AAMMDD; solo se valida formato.
