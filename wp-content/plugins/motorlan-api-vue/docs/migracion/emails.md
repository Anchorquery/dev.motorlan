# Emails del plugin `motorlan-api-vue` — auditoría y revisión

Alcance: todo lo que el plugin envía con `wp_mail()`. Plantillas en
`includes/email-templates/`, orquestación en `Motorlan_Notification_Manager` y
`Motorlan_Notification_Listener`, y tres envíos que no pasan por ellos.

Este documento tiene dos partes: **1. Auditoría** (estado de partida, escrita antes de tocar
código) y **2. Revisión** (antes/después, lo no verificable y lo que queda pendiente, al final).

## 1. Auditoría (estado de partida)

### 1.1 Inventario

Canal: **cola** = `create_notification()` → `queue_email()` (tras la respuesta / WP-Cron / directo);
**directo** = `send_email_notification_direct()` o `wp_mail()` en la misma petición.
«web» = notificación en la campana del panel.

| # | Evento (hook / ruta) | Destinatario | Plantilla | Canal | Problemas encontrados |
|---|---|---|---|---|---|
| 1 | Verificación de email completada (`motorlan_user_welcome`, `session-routes.php:165`) | El usuario | `welcome_email` | cola, solo email | Saludo con `display_name` (suele ser el email). Sin comprobar `$args['user']` nulo. |
| 2 | Registro / reenvío (`motorlan_user_verify_email`) | El usuario | `verify_email` | directo + web | Saludo con `display_name`. La notificación **web** guardaba además datos que solo deben viajar por correo (ver S1). El mensaje de la notificación incluye `display_name` sin sanear. |
| 3 | Olvidé mi contraseña (`session-routes.php:~830`) | El usuario | `password_reset` | directo + web | Saludo con `display_name`. Rama «fallback» (~853) envía `wp_mail()` en texto plano sin plantilla ni cabeceras. |
| 4 | Código para cambiar contraseña (`motorlan_password_reset_code`) | El usuario | `password_reset_code` | directo, solo email | **Todo el texto en español fijo**, sin `__()`. Asunto («Código de restablecimiento…») y cuerpo del mensaje sin traducir. «15 minutos» fijo en vez de usar `$expiry`. Usa estilos propios (`#005b96`, `#0073aa`) que no son la identidad. |
| 5 | Cambio de email (`profile-routes.php:~379`) | El **email nuevo** | (inline, base) | directo | Monta el HTML a mano con `str_replace` de `{{content}}` (el asunto podía inyectar `{{content}}`), clase `.button` sin estilos inline, saludo con `display_name`. |
| 6 | Publicación aprobada (`motorlan_publication_approved`) | Autor | `publication_approved` | cola + web | `get_site_url() . $data['url']` (puede duplicar el prefijo de idioma de WPML; aviso PHP si falta `url`). `wp_kses_post($args['message'])` con mensaje armado con datos de terceros → HTML inyectable. Saludo con `display_name`. Si un admin aprueba su propia publicación, se le avisa a sí mismo. |
| 7 | Publicación rechazada (`motorlan_publication_rejected`) | Autor | `publication_rejected` | cola + web | Igual que 6. El motivo (texto del admin) va dentro del `message` y además en `data['reason']`: se muestra dos veces. |
| 8 | Publicación pendiente (`motorlan_publication_pending_approval`; además `create-publicacion.php:~458` crea la notificación a mano) | **Todos** los administradores | `pending_approval` | cola + web | Si el autor es administrador se avisa a sí mismo. «Hola Administrador» fijo. Dos rutas distintas arman el mismo aviso con textos distintos (una sin `__()`). `wp_kses_post` del mensaje con el nombre del autor sin escapar. Bloque «Detalles técnicos» con fondo `#1A1A1A` fijo (ilegible en claro/oscuro según cliente). Textos del asunto y mensaje sin traducir. |
| 9 | Contacto del admin con el anunciante (`admin-publications-routes.php:~386`) | Autor de la publicación | — (sin plantilla) | directo | `wp_mail()` en **texto plano**, sin marca, sin cabeceras HTML, sin `[Motorlan]`. El asunto lo escribe el admin. Además se crea una notificación web con ese mensaje. |
| 10 | Nueva oferta (`motorlan_offer_created`) | Vendedor | `new_offer` | cola + web | El importe solo se muestra si hay imagen de producto (la tarjeta no sale sin imagen). `get_site_url() . $data['url']`. `wp_kses_post` del mensaje con nombre del comprador sin escapar. Importe sin formato. Comentarios de trabajo del desarrollador pegados en la plantilla. |
| 11 | Oferta aceptada (`motorlan_offer_status_updated`=accepted) | Comprador | `offer_accepted` | cola + web | `get_site_url() . $data['url']`. «24 horas» fijo. Saludo `display_name`. |
| 12 | Venta manual registrada (`motorlan_manual_sale_created`) | Comprador | **`offer_accepted`** («como fallback») | cola + web | **Contenido equivocado**: dice «oferta aceptada… tienes 24 horas para completar la compra… la oferta expirará», cuando es una compra ya registrada. Necesita plantilla propia. |
| 13 | Nueva compra (`motorlan_new_purchase`, también tras confirmar oferta) | Vendedor | `new_purchase` | cola + web | Es el email de la captura: botón roto (clase `.button` solo en `<style>`), saludo con email, «Estado: Vendido» en `#DA291C` sobre oscuro, tarjeta de producto con `div` + `calc()` (no válido en Outlook). `buyer_name` = `display_name` (puede ser el email del comprador → se filtra a un tercero). |
| 14 | Nuevo mensaje de chat de producto (`motorlan_new_chat_message`) | Autor de la publicación | `new_message` | cola + web (email máx. 1 cada 15 min) | `get_site_url() . $data['url']`. Remitente por `display_name` (puede ser su email, visible para el vendedor). Texto `message_full` sin límite en el email. |
| 15 | Respuesta del vendedor a usuario registrado (`motorlan_seller_reply`) | Comprador | `new_message` | cola + web (15 min) | Idem 14. |
| 16 | Respuesta del vendedor a invitado (`motorlan_seller_reply`, `queue_email(0,…)`) | Invitado (`direct_email`) | `new_message` | cola (solo email) | Saludo «Hola Invitado». Enlace con token de chat (7 días) guardado en el payload del cron. |
| 17 | Mensaje de compra (`motorlan_new_purchase_message`) | La otra parte de la compra | `new_message` | cola + web (15 min) | Idem 14. El enlace `dashboard/purchases/{uuid}?openChat=true` resuelve para comprador y vendedor (la página decide con `isBuyer`). |
| 18 | Copia al invitado tras escribir (`motorlan_send_guest_message_copy`) | Invitado | `message_sent` | cola (solo email) | URL relativa + `home_url()`. Sin texto del mensaje a propósito (anti-relay). Botón azul `#0073aa` ajeno a la marca. `$args['user']` nulo gestionado. |
| 19 | Nueva pregunta / pregunta enviada / pregunta respondida | — | `new_question`, `question_sent`, `question_answered` | **ningún evento las dispara** | Plantillas huérfanas: no hay listener ni `do_action`. Textos fijos en español, azul `#0073aa`, mezclan estilos. Se conservan y se arreglan (están en el alcance), se anota en la revisión. |
| 20 | Interés en producto, valoración, venta manual marcada, contacto admin | Autor / valorado | — | solo web | No envían email (correcto). Los textos de la notificación web van sin `__()` en parte. Fuera de alcance de email. |

Hay una plantilla sin evento propio más: `admin_contact`/`new_review` **no existen** como plantilla y los
listeners ya las marcan «solo web» (correcto).

### 1.2 Problemas transversales

**Nombre / email mal usado**
- 9 de 10 plantillas con saludo usan `$args['user']->display_name`. En WordPress, `display_name`
  cae a `user_login` o al email en cuentas creadas por registro rápido: sale «Hola
  vendedor.prueba@motorlan.test,». No hay jerarquía de nombres ni saludo neutro.
- Los listeners reutilizan `display_name` para el nombre de comprador, remitente, valorador, autor… que
  acaba **en el email de un tercero** (el vendedor ve el email del comprador como «nombre»).
- `new_message` y otras fallan con `Attempt to read property "display_name" on null` si `user` es nulo
  (la página de depuración envía con `user_id = 0`).

**URLs**
- Seis plantillas hacen `get_site_url() . $data['url']`, y `$data['url']` ya viene de
  `motorlan_account_url()`/`motorlan_localized_page_url()`, que devuelven la **ruta con prefijo de idioma
  de WPML** (`/en/mi-cuenta/…`). Con WPML activo, `site_url`/`home_url` también se filtran y el prefijo
  puede salir duplicado (`/en/en/…`), y en un WordPress en subcarpeta la ruta se duplica. Sin
  `$data['url']` hay `Undefined array key "url"`.
- Otras plantillas usan `home_url($url)` con el mismo riesgo.
- Destinos revisados contra la SPA actual (`app/src/pages`): `dashboard/publications/offers-received`,
  `dashboard/purchases/{offers-sent,purchases,inquiries,[uuid]}`, `dashboard/inquiries`,
  `dashboard/publications/publication/list`, `dashboard/admin/approvals`, `reset-password`,
  `verify-email`, `login`: **existen todos**. `dashboard/user/profile` (valoraciones, solo web) sí
  existe como carpeta.
- Portal (`motorlan-portal`): con `acceso` encendido toma `/mi-cuenta/{login|register|forgot-password|reset-password|verify-email}`
  **en las mismas URLs**, así que los enlaces de verificación y de restablecer contraseña no cambian.
  Los enlaces a `/marketplace-motorlan/{slug}/` también se conservan (`tienda`). Las pantallas del panel
  (`/mi-cuenta/dashboard/…`) siguen siendo de la SPA hasta que lleguen las fases 3-5 del portal; si sus
  URLs cambian, bastará con el filtro `motorlan_email_url` que se añade aquí (ver Revisión).

**Escapado / HTML inyectable**
- `wp_kses_post( $args['message'] )` en `new_offer`, `pending_approval`, `publication_approved`,
  `publication_rejected`: el `message` se construye en el listener con `sprintf` y datos de terceros
  (nombre del comprador, título, motivo, nombre del autor). `wp_kses_post` **permite** `<a>`, `<img>`,
  etc.: un nombre con HTML llega como enlace/imagen dentro de un email con la marca de Motorlan.
- `printf( __( '…<strong>%s</strong>…' ) )`: el HTML lo pone la traducción (aceptable) pero sin
  `wp_kses` del conjunto; los argumentos sí se escapan.
- `message_full`, `question_text`, `answer`: `nl2br(esc_html())` — correcto.
- El **asunto** se concatena tal cual (`'[Motorlan] ' . $title`) sin quitar saltos de línea ni etiquetas
  (el asunto del admin en el envío 9 también).
- `direct_email` se usa como destinatario sin `is_email()`.

**Traducción y variables sin definir**
- Sin `__()`: `password_reset_code` (entero), `question_answered`, `question_sent` (parcial), asunto y
  cuerpo del código de contraseña en el listener, `Solicitud de publicación`, `Publicación marcada como
  vendida`, `Nueva valoración…`, `Hola Administrador`, fallbacks `'Usuario'`, `'Un usuario'`,
  `'Producto'`, `'Alguien'`, y el pie de `base.php` («Preferencias», «Privacidad», «Este es un mensaje
  automático…», `©`).
- `<html lang="es">` fijo en cualquier idioma.
- Variables potencialmente sin definir: `$data['url']` (6 plantillas), `$args['user']` (nulo con
  `user_id = 0`), `$args['message']` en `question_*`.

**Salida distinta si falta algún campo**
- `new_offer`: sin imagen de producto se pierde la tarjeta **y el importe ofertado**.
- `pending_approval`: tabla de «Detalles técnicos» vacía con cabecera cuando faltan todos los campos.
- `new_message`: sin título e imagen desaparece el contexto; guest vs. usuario cambia el saludo.
- `publication_*`: sin imagen, el título queda pegado al borde.

**Duplicados**
- `pending_approval`: la ruta de creación (`create-publicacion.php`) crea la notificación a mano para cada
  admin y el hook `motorlan_publication_pending_approval` lo hace desde otras dos rutas (edición, cambio de
  estado). No hay un duplicado en el mismo evento, pero con dos textos y dos asuntos distintos.
- `verify_email`: se envía directo **y** se crea una notificación web con el token (ver S1).
- Chat: una notificación web por mensaje y un email como mucho cada 15 min (correcto, ya limitado).

**Asuntos**
- Mezcla de `[Motorlan] ` + título traducido / sin traducir; el cambio de email añade `[Motorlan] ` por su
  cuenta, el de restablecer contraseña usa «Restablecer contraseña - Motorlan» y el del admin no lleva
  prefijo. El prefijo se aplica en `send_email_notification_direct`, pero **no** en los envíos directos 5 y 9.

**Destinatarios**
- `pending_approval` incluye al propio autor si es administrador (se avisa a sí mismo).
- `publication_approved/rejected` avisan al autor aunque sea quien aprueba.
- El nombre del comprador en `new_purchase` puede revelar el email del comprador al vendedor.

**Preferencias**
- `base.php` enlaza a `…/dashboard/notifications`: **esa pantalla no existe** (ni en la SPA ni en el
  portal). La cuenta tiene `dashboard/user/account`. No hay ajustes de notificación en el producto.

### 1.3 Seguridad (descrito en genérico: el repo es público)

- **S1** Datos de verificación de cuenta duplicados en la notificación web: se deja de guardarlos (solo viajan por el correo del usuario).
- **S2** `wp_kses_post` sobre mensajes con datos de terceros y asuntos sin sanear → escapado de datos en emails.
- **S3** Nombre del comprador/remitente = `display_name`, que puede ser el email → exposición de datos
  personales a la otra parte.
- **S4** Destinatario no validado y cabeceras de asunto sin limpiar.

### 1.4 Visual / cliente de correo (síntoma de la captura)

1. `base.php` define el botón con `.button` **solo** en el `<style>` de cabecera. Los clientes que
   reescriben o descartan `<style>` (Outlook Windows, Gmail en algunos casos, correos reenviados) muestran un
   enlace sin relleno; en modo oscuro el texto negro del tema sobre el fondo rojo/salmón de un `<a>` en
   línea es exactamente lo de la captura.
2. Las reglas `p, span, div { color: … !important }` del modo oscuro pisan colores intencionados (estado
   rojo/verde, textos de tarjeta).
3. Estados (`#DA291C`, `#28C76F`, `#E68F3C`) sin contraste suficiente sobre oscuro (rojo sobre `#2D2D2D`
   = 2,9:1; WCAG AA exige 4,5:1).
4. El logo es un PNG **oscuro y transparente**; sobre cabecera oscura se pierde.
5. Pie: `opacity: 0.7`, enlaces `#808390` sobre `#F4F5F7` (3,6:1), la captura lo muestra cortado; layout con
   `div`, `calc()`, `border-radius` + `overflow: hidden` (no soportado en Outlook de escritorio).
6. Rojo de marca `#e42313` en `base.php` frente a `#DA291C` de la web.
7. Sin `color-scheme`/`supported-color-schemes`, sin preheader, sin VML para Outlook, tarjeta
   `display:inline-block` + `calc()`.

### 1.5 Fuera de alcance / no tocar

- `app/src` (SPA) y `motorlan-portal`: otras sesiones trabajan ahí.
- Remitente (`From`), SMTP, SPF/DKIM: configuración del servidor/plugin SMTP, no del plugin.

---

## 2. Revisión (después de los cambios)

Rama `anchorquery/emails-arreglo-revision-e6e50c`. Todo lo descrito está verificado con los tests
(`tests/email-templates-test.php`, 967 comprobaciones), con las previews HTML
(`docs/migracion/emails-previews/`) y con capturas de Chrome vía Playwright
(`docs/migracion/emails-previews/capturas/`). No se ha enviado ningún email real ni se ha tocado
la base de datos.

### 2.1 Antes / después

| Síntoma / problema | Antes | Después |
|---|---|---|
| Botón «Ver Detalles de la Venta» sin forma | `<a class="button">` con el estilo solo en el `<style>` de cabecera: si el cliente lo descarta, queda un enlace pelado | Tabla + celda con `bgcolor` y `background-color` inline, `<a>` con padding/color/fondo inline y VML para Outlook. Con el `<style>` eliminado a propósito sigue siendo un botón (capturas `new_purchase__ANTES__sin-style.png` / `new_purchase__DESPUES__sin-style.png`) |
| «Hola vendedor.prueba@motorlan.test» | `$args['user']->display_name` | `Motorlan_Email::user_name()`: nombre y apellidos → nombre → empresa → `display_name` si no es un email ni el login. Si no hay: «Hola,». Probado con email, login, HTML y usuario nulo |
| «Estado: Vendido» rojo ilegible, tarjeta con franjas | `color:#DA291C` sobre fondo oscuro; `div` + `calc()` | Chip con fondo y color propios por tono (rojo/verde/ámbar/info, claro y oscuro) y tarjeta de producto en tablas. Contraste ≥ 4,5:1 en todos los pares (comprobado en el test) |
| Logo que se pierde en oscuro | PNG transparente oscuro sobre cabecera oscura | Logo sobre un chip blanco fijo en la cabecera; filtro `motorlan_email_logo_url` para cambiarlo |
| Pie cortado, enlaces con poco contraste | `opacity:0.7`, `#808390` sobre `#F4F5F7` (3,6:1), `div` | Pie en tabla fuera de la tarjeta, texto `#6B6880` (5,0:1 sobre el fondo) y `#B7B4C4` en oscuro; sin `opacity` |
| `p, span, div { color !important }` en oscuro | Pisaba los colores de estado | Eliminada: el oscuro se define con clases `ml-*` solo donde hace falta; los estados llevan su propio par fondo/texto |
| Rojo | `#e42313` en `base.php`, `#DA291C` en algunas plantillas | `#DA291C` único (botón, franja, acentos), fondo `#F8F7FA`, texto `#2F2B3D` |
| «Preferencias» | `…/dashboard/notifications` (no existe) | `…/dashboard/user/account` (existe); oculto para invitados |
| Cabecera del documento | sin `color-scheme`, sin preheader, `lang="es"` fijo | `color-scheme`/`supported-color-schemes`, preheader oculto, `lang` y `dir` según idioma (árabe RTL), `format-detection`, VML/`o:` |
| URLs | `get_site_url() . $data['url']` (6 plantillas) | `Motorlan_Email::abs_url()`: solo se antepone esquema+host a la ruta que ya lleva idioma/carpeta. Sin `url` se enlaza a una pantalla de la cuenta, nunca a un enlace roto. Filtro `motorlan_email_url` |
| Escapado | `wp_kses_post($args['message'])` con datos de terceros | `esc_html()` en todo; `wp_kses()` solo `<strong>` en frases traducidas; `esc_url()` (descarta `javascript:`); asunto saneado |
| Venta manual | Usaba `offer_accepted` («24 horas… la oferta expirará») | Plantilla propia `manual_sale` (`data['email_template']`) |
| `new_offer` sin imagen | Se perdía la tarjeta **y el importe** | La tarjeta sale siempre |
| Envío directo del admin | `wp_mail()` en texto plano | Plantilla base, cabeceras HTML, idioma del anunciante, publicación como contexto |
| Cambio de email | HTML a mano, `str_replace` de `{{content}}` | Mismos componentes y `Motorlan_Notification_Manager::send_custom_email()` |
| Restablecer contraseña (fallback) | `wp_mail()` texto plano en rama inalcanzable | Rama eliminada: siempre la plantilla `password_reset` |
| Cabeceras | solo `Content-Type` | `Content-Type`, `Auto-Submitted`, `X-Auto-Response-Suppress` y versión en texto plano (`AltBody`) |
| Destinatario | `direct_email` sin validar | `sanitize_email()` + `is_email()` |
| Autor admin | Se avisaba a sí mismo en `pending_approval` | Se excluye al autor; cada admin en su idioma |
| Remitente del chat | `display_name`, a veces el email | Nombre sin emails o «Un usuario» |
| Textos sin traducir | `password_reset_code`, preguntas, listener | Todos con `__()` y dominio `motorlan-api-vue` |

### 2.2 Qué se ha cambiado, por archivo

- `includes/classes/class-motorlan-email.php` (nuevo): paleta, nombres, URLs, importes, asunto, cabeceras,
  texto plano y componentes (`h1`, `p`, `quote`, `notice`, `chip`, `product_card`, `key_values`, `button`,
  `code_box`, `panel`…). Cada componente lleva estilos inline y clases `ml-*`.
- `includes/email-templates/base.php`: layout nuevo (tablas, 600 px, preheader, modo oscuro por
  `@media (prefers-color-scheme)` y `[data-ogsc]/[data-ogsb]`, VML).
- 15 plantillas reescritas con los componentes + `manual_sale.php` nueva.
- `Motorlan_Notification_Manager`: `get_email_template()` (siempre `title/message/data/user`),
  `send_email_notification_direct()` (plantilla alternativa, destinatario validado, asunto saneado),
  `send_custom_email()` y `deliver()` (único punto que llama a `wp_mail()`).
- `Motorlan_Notification_Listener`: nombres, `email_template` de la venta manual, textos traducibles,
  código de contraseña (idioma del usuario y caducidad real), avisos a admins sin el autor.
- `admin-publications-routes.php`, `profile-routes.php`, `session-routes.php`: los tres envíos directos.
- `create-publicacion.php`: aviso a administradores con nombre real, traducible y sin autoaviso.
- `admin-email-debug.php`: datos de ejemplo con las claves reales del listener, variantes
  (con nombre / email como nombre / invitado / hostil), ancho escritorio o móvil, plantilla validada.
- `tests/email-templates-test.php`, `tests/email-stubs.php`, `tests/email-previews.php`.

### 2.3 Qué NO se puede verificar sin clientes reales

- **Inversión automática de color** de Gmail (web y apps), Outlook de escritorio y Outlook.com (modo
  «invertir todo»): no se puede reproducir fuera de ellos. Las capturas usan `prefers-color-scheme`
  (Apple Mail, Outlook móvil/Mac, Thunderbird, Chrome), que es el único caso que Playwright emula. El
  diseño reduce el riesgo (cada bloque trae su propio par fondo/texto, el botón y el logo no dependen del
  cuerpo), pero hay que mirar un correo real en Gmail y en Outlook para darlo por bueno.
- **VML del botón en Outlook de escritorio** (Word como motor): el código sigue el patrón estándar, pero
  no está comprobado en Outlook; en el peor caso se ve como texto con fondo.
- **Carga del logo**: sigue siendo el PNG remoto de `motorlan.es`. Si el cliente bloquea imágenes se ve el
  texto alternativo «Motorlan». Con la inversión automática de Gmail el chip blanco puede oscurecerse;
  la solución limpia es un PNG con fondo blanco opaco (o uno claro) y apuntar `motorlan_email_logo_url` a él.
- **WPML con prefijos de idioma reales**, `get_privacy_policy_url()` y los `get_user_locale()` de
  usuarios reales: los tests usan stubs; no se renderizó pasando por WordPress+WPML.
- **Entrega** (SPF/DKIM/DMARC, spam, remitente): depende del servidor/SMTP, no del plugin.
- Traducciones a eu/fr/ar/en de los textos nuevos: ver 2.5.

### 2.4 Problemas del backend que NO se han arreglado (y por qué)

1. **Nombre visible del chat**: los controladores del chat guardan `display_name` con cada mensaje y lo
   usan en la propia interfaz. Aquí solo se evita mostrarlo cuando es un email en los avisos; cambiar qué se
   guarda en el chat es otra decisión de producto y toca otro módulo.
2. **Plantillas de preguntas (`new_question`, `question_sent`, `question_answered`) huérfanas**: ningún
   evento las dispara (el módulo se sustituyó por el chat). Se arreglaron por si se reutilizan; borrarlas
   sería una decisión de producto.
3. **Aviso `pending_approval` por dos caminos**: `create-publicacion.php` lo crea a mano y los demás por el
   hook `motorlan_publication_pending_approval`. Se unificaron textos y se evita el autoaviso, pero no el
   código: pasarlo todo por el hook cambia el orden en que se calculan marca/potencia y es más arriesgado.
4. **Payload de la cola**: el enlace del invitado (`new_message` a invitados) se guarda en el evento de
   WP-Cron hasta enviarse (temporal). Los emails de verificación/restablecimiento no pasan por la cola.
   Mejor sería guardar solo ids y generar el enlace al enviar.
5. **`verify_email` se envía de forma síncrona** (y no en cola) para que no se pierda en el registro: añade
   latencia SMTP a la petición de registro. Se mantiene por fiabilidad.
6. **Enlaces del panel**: apuntan a `/mi-cuenta/dashboard/…` (SPA). Cuando las fases 3-5 del portal cambien
   esas URLs hay que remapearlas con el filtro `motorlan_email_url` (o cambiar `motorlan_account_url()`);
   no se tocó `motorlan-portal`. Los enlaces de acceso (`login`, `verify-email`, `reset-password`) no cambian
   cuando se enciende `acceso`.
7. **Remitente**: se usa el `From` por defecto de WordPress. Convendría un `From` fijo y `Reply-To`
   (configuración del servidor/SMTP).
8. **No existen preferencias de notificación**: «Preferencias» abre la cuenta (`dashboard/user/account`);
   si hay que poder darse de baja de avisos, es funcionalidad nueva.
9. **`create_notification()` guarda `data` completo en la tabla** (incluye `url`, `vue_path`, etc.). Se dejó
   de guardar el token de verificación; el resto no es sensible.

### 2.5 Traducciones

No hay `.po/.pot` en el plugin (las cadenas van por WPML String Translation). Se conservaron los msgid
existentes; los **nuevos** (en español como idioma base) que habrá que traducir:

`Hola,` · `Un usuario` · `Usuario` · `Alguien` · `Compra registrada` · `Ver mi compra` ·
`Coordina el pago y la entrega directamente con el vendedor.` · `Oferta aceptada` ·
`Código de verificación` · `Este código expirará en %d minutos.` ·
`Si no solicitaste este cambio, ignora este mensaje.` ·
`Tu código para confirmar el cambio de contraseña` · `Código de restablecimiento de contraseña` ·
`Tienes una respuesta` · `Pregunta enviada` · `Ver respuesta` · `Ver mis preguntas` ·
`Ver mis publicaciones` · `Mensaje del Administrador` · `Solicitud de publicación` ·
`El usuario %1$s ha solicitado publicar "%2$s".` · `Nueva publicación pendiente` ·
`El usuario %s ha creado una nueva publicación que requiere tu aprobación.` ·
`Publicación marcada como vendida` · `Has marcado "%s" como vendida.` · `Nueva valoración de %s` ·
`%s te ha dejado una valoración por una compra reciente.` ·
`Gracias por registrarte en Motorlan. Tu cuenta ha sido verificada con éxito.` ·
`Por favor, verifica tu cuenta haciendo clic en el siguiente enlace.` · `Notificación de Motorlan` ·
y las frases de preguntas que antes no usaban `__()`.
Hasta que se traduzcan, esos textos salen en español en los demás idiomas (como ya salían los que no
usaban `__()`).

### 2.6 Cómo probar

```bash
php tests/email-templates-test.php          # 967 comprobaciones
php tests/email-previews.php                # regenera docs/migracion/emails-previews/*.html
```

En el admin: Herramientas › Motorlan Emails (variantes de destinatario, ancho móvil, envío de prueba a
una dirección propia).
