# EIRA Resultados

Prototipo para el **Reto 4 del Vibeathon México 2026**: entrega digital segura de resultados de Papanicolaou de EIRA.
Sustituye el envío de PDF por WhatsApp con un flujo de recepción → asociación → revisión → publicación autorizada → aviso neutro → consulta con folio + código recibido por SMS.

> **Todos los datos son ficticios (DEMO).** Es un prototipo: **no acredita cumplimiento normativo**, no incluye firma electrónica ni validación de identidad formal.

## Instalación local

Requisitos: PHP 8.3+ (extensiones `pdo_sqlite`, `gd`, `fileinfo`, `mbstring`, `openssl`; `exif` opcional), Composer, Node 22+.

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed      # SQLite en database/database.sqlite
php artisan eira:demo-files     # PDFs/JPG ficticios en storage/app/demo-samples
npm install
npm run build
php artisan serve               # http://localhost:8000
```

Pruebas: `php artisan test` (SQLite en memoria, disco y transportes simulados).

No ejecutar `php artisan storage:link`: los documentos nunca se sirven desde `public/`.

### Cuentas DEMO

Contraseña de todas: `demo-eira-2026` (solo demo; cambiar antes de cualquier piloto).

| Cuenta | Rol | Puede |
| --- | --- | --- |
| `operador@eira.example` | Operación administrativa | Cargar, asociar, revisar, rechazar antes de publicación |
| `publicador@eira.example` | Responsable de publicación | Publicar, rechazar documentos revisados, revocar |
| `admin@eira.example` | Administración | Solo panel; **no hereda** facultad de publicar ni ve documentos |

### Sin datos precargados

El seeder solo crea las cuentas. **El estudio se registra al recibir el resultado**, con los datos que la operadora transcribe del formato:

- **Obligatorios:** No. de folio del formato e ID EIRA (formato `AAAA` + `AAMMDD`, p. ej. `JACA990214`). El sistema valida el formato pero no lo calcula: el ID EIRA es una referencia, no autentica ni fusiona pacientes.
- **Opcionales:** número citológico, fecha de toma, teléfono, correo. Los contactos quedan **sin verificar** hasta que el personal los confirme.
- El sistema genera un **folio interno** (`EIRA-AAAA-00001`). Si el folio del formato ya existe, el documento **no** se asocia solo: queda «por asociar» para decisión humana (duplicado o corrección).
- La paciente consulta con el **No. de folio de su formato** + el código recibido por SMS.

Archivos ficticios para subir (`php artisan eira:demo-files`): `resultado-folio-900101.pdf` (ID `PELU900101`), `900102` (`GOMA850505`), `900103` (`RISA921212`) y una foto JPG `900104` (`LOTE880303`). Un teléfono que termina en `0000` simula un SMS fallido.

## Guion de demo (≈2 min)

1. Entrar a `/acceso` como **operador** → *Recepción* → subir `storage/app/demo-samples/resultado-folio-900101.pdf` (y, opcional, `receta-folio-900101.pdf` en el campo Receta) con folio `900101`, ID EIRA `PELU900101` y teléfono `5512345678`. Resultado y receta se reciben juntos pero se revisan y publican por separado.
2. En el documento, **confirmar contacto** (sin esto no se envía código).
3. En otra ventana (incógnito) abrir `/consulta` e intentar con el folio `900101` y cualquier código: respuesta neutra, no abre.
4. Confirmar la revisión (legibilidad, correspondencia, autorización de origen). Intentar publicar como operador no es posible (el servidor responde 403 incluso por ruta directa).
5. Cerrar sesión, entrar como **publicador** → *Publicación* → autorizar.
6. Abrir *Bandeja simulada*: ahí aparece el aviso que habría llegado al correo/SMS con el código.
7. En incógnito: folio + código → ver/descargar el PDF.
8. Repetir el código (ya no sirve) o revocar la publicación y volver a descargar (404 en la siguiente solicitud).

## Arquitectura

Laravel 13 · Blade · Tailwind 4 (Vite) · SQLite. Sin SPA, sin colas, sin microservicios.

- `app/Services/Documents` — `DocumentIntake` (validación y guardado privado), `DocumentWorkflow` (asociar, revisar, rechazar, publicar, revocar), `ImageToPdf` (JPG/PNG → PDF sin alterar contenido).
- `app/Services/Access/AccessCodes` — emisión y canje atómico de códigos temporales.
- `app/Services/Notifications` — `NotificationDispatcher` sobre las interfaces `EmailSender` (Resend / fake) y `SmsSender` (fake / disabled).
- `app/Services/Audit` — bitácora sin contenido clínico, códigos ni cuerpos de mensaje.
- `app/Http/Controllers/Staff` y `Portal` — rutas de personal (`/staff`, autenticadas) y portal de pacientes (`/consulta`).

### Estados (propuestos; no oficiales de EIRA)

- **Documento:** `pending_association` → `pending_review` → `ready_for_publication` → `published`; `rejected` / `revoked`.
- **Aviso:** `pending` → `accepted` | `failed` | `disabled`. *Aceptado por el transporte no significa entregado*; `delivered` solo existirá cuando un proveedor lo confirme.
- **Consulta:** eventos `viewed` / `downloaded` en bitácora; no se infiere lectura ni comprensión.

Los tres son independientes: un aviso fallido nunca bloquea ni revoca un documento publicado.

## Configuración

| Tema | Variable | Notas |
| --- | --- | --- |
| Almacenamiento | `EIRA_DOCUMENTS_DISK=local` | `storage/app/private`. Con `s3` usar bucket privado (Block Public Access, sin ACL públicas, cifrado, IAM de mínimo privilegio) y `composer require league/flysystem-aws-s3-v3`. La descarga siempre pasa por Laravel; no hay URLs firmadas para pacientes. |
| Correo | `EIRA_EMAIL_DRIVER=fake\|resend` | Con `resend`: `RESEND_API_KEY` y `RESEND_FROM` (dominio verificado) solo en `.env`. |
| SMS | `SMS_DRIVER=fake\|disabled\|clicksend` | Proveedor elegido: **ClickSend** (REST v3). Con `clicksend`: `CLICKSEND_USERNAME`, `CLICKSEND_API_KEY` y opcional `CLICKSEND_FROM`, solo en `.env`. `SUCCESS` de ClickSend = en cola → se registra como *aceptado*, no entregado. La bandeja simulada solo muestra mensajes de transportes *fake*. |
| Código de acceso | `EIRA_OTP_TTL_MINUTES` (10080 = 7 días, decisión de EIRA), `EIRA_OTP_MAX_ATTEMPTS`, `EIRA_OTP_RESEND_COOLDOWN` | 6 caracteres alfanuméricos, hash bcrypt, un solo uso. |
| Correo de aviso | `resources/views/mail/notification.blade.php` | HTML + texto plano, sin diagnóstico ni folio. Vista previa en `/staff/correo-ejemplo`. |
| Sesión de paciente | `EIRA_PATIENT_SESSION_MINUTES` | Limitada a un estudio. |
| Bandeja simulada | `EIRA_DEMO_OUTBOX` | Solo demo; ver abajo. |

## Implementación en servidor

Requisitos: lo mismo que en local, más HTTPS (Nginx/Apache con raíz en `public/`) y escritura en `storage/` y `bootstrap/cache/`.

```sh
composer install --no-dev --optimize-autoloader
cp .env.example .env            # completar valores (ver abajo)
php artisan key:generate
php artisan migrate --force     # sin --seed: no crear cuentas DEMO
npm ci && npm run build
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

`.env` mínimo para el servidor:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<dominio>
SESSION_SECURE_COOKIE=true
EIRA_DEMO_OUTBOX=false
EIRA_DEMO_QUICK_LOGIN=false
EIRA_EMAIL_DRIVER=resend        # + RESEND_API_KEY, RESEND_FROM (dominio verificado)
SMS_DRIVER=clicksend            # + CLICKSEND_USERNAME, CLICKSEND_API_KEY
EIRA_DOCUMENTS_DISK=local       # o s3 (+ AWS_*, y composer require league/flysystem-aws-s3-v3)
```

Cuentas de personal (individuales; roles `operator`, `publisher`, `admin`):

```sh
php artisan tinker --execute="App\Models\User::create(['name'=>'Nombre','email'=>'correo@dominio','password'=>'<contraseña-robusta>','role'=>'operator']);"
```

No se necesitan colas, cron ni workers (`QUEUE_CONNECTION=sync`). No ejecutar `storage:link`. Respaldar `database/database.sqlite` (o la base usada) y el disco de documentos.

## Implementado, simulado y pendiente

**Implementado y cubierto por pruebas:** carga con validación de tipo real/tamaño/corrupción, almacenamiento privado con nombres opacos y SHA-256, asociación manual con confirmación humana, advertencia de duplicado y versiones controladas, revisión con checklist, publicación con autorización de servidor y verificación de integridad, segregación operador/publicador, revocación, códigos por canal con canje atómico, intentos máximos, vencimiento y reenvío con enfriamiento, sesión limitada al estudio, descarga con `Cache-Control: private, no-store`, avisos idempotentes, bitácora, panel.

**Simulado:**
- Correo y SMS usan transportes *fake* por defecto. El adaptador de ClickSend está implementado y probado con respuestas simuladas de su API, pero **no contra la cuenta real**; los reportes de entrega (webhooks) no están implementados. Convención de demo: correos que empiezan con `fallo` y teléfonos que terminan en `0000` se rechazan para mostrar un aviso fallido.
- **Bandeja simulada** (`/staff/bandeja-demo`): muestra al personal los avisos de los transportes fake, incluido el código, para poder hacer la demo sin teléfono. Vive en caché solo durante la vigencia del código, no se escribe en logs y no existe en producción ni con transportes reales. Es una concesión de demo que debe desactivarse en piloto.
- El adaptador de Resend está escrito pero **no se ha probado contra el servicio real** (no hay API key en este entorno).
- S3 está previsto por configuración pero **no se ha probado**; el demo usa disco local privado.

**Pendiente para piloto (no implementado):**
- Procedimiento de recuperación asistida con verificación humana de identidad (hoy solo se informa a la paciente).
- Alta y verificación de contactos desde la interfaz (hoy se siembran).
- Confirmación de entrega real (`delivered`) vía webhooks del proveedor.
- Logos oficiales: colocar el PNG en `public/img/eira-logo.png` (si no existe se muestra el texto «EIRA»). Tipografía y símbolos se cargan desde Google Fonts; autoalojar para piloto.
- 2FA para personal, política de contraseñas, retención/backups, HTTPS con `SESSION_SECURE_COOKIE=true`.

## Supuestos pendientes de validar con EIRA

- **Quién publica.** El demo usa una cuenta «responsable de publicación» distinta de quien revisa. La facultad real está por confirmar.
- **Autorización clínica.** Se registra que el operador vio *evidencia de autorización de origen* (laboratorio) y, por separado, la *decisión de publicar* de EIRA. No se crea una segunda firma médica. Falta confirmar si esto satisface el requisito del reto.
- **Folios.** Folio interno, folio del formulario, número citológico e ID EIRA se guardan por separado y no se equiparan; su semántica y unicidad están por confirmar.
- **Contactos.** Solo reciben avisos los contactos marcados como verificados; cómo se verifican (y qué pasa con teléfonos compartidos) está por definir.
- **Identificador de consulta.** Por instrucción del equipo la paciente usa el No. de folio de su formato (siempre viene, escrito a mano). Es previsible y podría repetirse; el código recibido por SMS es el único secreto y, si dos estudios comparten folio, el código determina a cuál se accede. Su unicidad está por confirmar con EIRA. El aviso no incluye el folio.
- **ID EIRA.** Regla informada: 2 primeras letras del apellido paterno, inicial del materno, inicial del nombre (si es María con dos nombres, el segundo) y AAMMDD. Se transcribe y se valida solo el formato; es derivable y no se usa para autenticar ni fusionar.
- **Verificación de contactos.** «Confirmar contacto» es un clic del personal registrado en bitácora; el procedimiento real está por definir.

## Costes (a estimar por separado)

Sin cifras inventadas; partidas a cotizar: infraestructura (hosting de la aplicación y base de datos), almacenamiento y backups (S3 para ~300+ documentos/mes), operación (soporte, monitoreo), y mensajería (correo transaccional y SMS por mensaje vía ClickSend; el costo depende del país y del número de segmentos: los acentos fuerzan codificación Unicode y reducen el tamaño de cada segmento).

## Seguridad y cumplimiento

Ver [SECURITY.md](SECURITY.md) para el modelo de amenazas y riesgos conocidos.

Para un piloto con datos reales se requiere análisis legal y técnico en México: protección de datos personales sensibles, expediente clínico según aplicabilidad, funciones del laboratorio y prestadores, consentimiento, aviso de privacidad, retención, contratos con proveedores cloud, controles organizacionales y respuesta a incidentes.
