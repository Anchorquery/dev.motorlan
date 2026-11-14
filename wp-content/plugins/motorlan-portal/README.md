# Motorlan Portal

Plugin de WordPress para migrar, pantalla a pantalla, la SPA de Motorlan
(`motorlan-api-vue`, Vue 3 + Vuetify/Vuexy) a páginas renderizadas en servidor con
**Timber/Twig + HTMX + Alpine + Tailwind**. Sigue el patrón de `fagor-portal`.

**Viene apagado.** Activar el plugin no cambia nada: cada sección se enciende por
separado y, mientras está apagada, el plugin no registra reglas ni hooks para ella y la
SPA sigue sirviéndola. Solo el catálogo de componentes (`ui-kit`, para administradores)
está migrado en esta fase.

Guía visual y de componentes: [DESIGN.md](DESIGN.md).

## Stack

| Capa | Qué | Dónde |
|---|---|---|
| PHP | PHP ≥ 8.1, Timber 2 (Twig 3), PSR-4 `MotorlanPortal\` → `src/` | `composer.json` |
| Vistas | Twig: layouts, componentes (macros/embeds), páginas, parciales | `views/` |
| Interacción | HTMX 2 (parciales del servidor), Alpine 3 + `@alpinejs/focus` | `assets/js/app.js` |
| Estilos | Tailwind 4 (`@tailwindcss/vite`), tokens en variables CSS | `assets/css/app.css` |
| Iconos | Tabler (outline), SVG en línea solo de los iconos usados | `scripts/build-icons.mjs` |
| Datos | API REST de `motorlan-api-vue` (`motorlan/v1`) por `rest_do_request`, sin HTTP | `src/Support/Api.php` |

### Build

Una sola entrada de Vite y nombres fijos (sin hash, sin trozos): el despliegue sube muy
pocos archivos y la versión de caché la pone `Support\Assets::url()` con `filemtime`.

```
dist/js/app.js       HTMX + Alpine + código del portal
dist/css/app.css     Tailwind + componentes (layouts propios: app-shell y auth-shell)
dist/css/public.css  lo mismo, acotado a #ml-portal (layout public, dentro del tema)
dist/icons.json      SVG de Tabler usados en views/ y src/
```

```bash
npm ci
composer install
npm run build        # vite build + iconos + public.css
npm run dev          # vite build --watch
```

`dist/`, `vendor/` y `node_modules/` no están en git (el workflow los genera).

## Estructura

```
motorlan-portal.php        cabecera, constantes, autoload, activación
src/
  Plugin.php               arranque; solo engancha routing si hay alguna sección encendida
  Sections.php             registro de secciones + interruptor (opción / constante)
  Routing/Rewrites.php     /ml-portal/{sección}/{param} → query vars (solo secciones encendidas)
  Routing/Takeover.php     toma rutas de la SPA (spa_paths) de las secciones encendidas
  Routing/Router.php       sección → controlador, con control de acceso en servidor
  Controllers/             un controlador por sección (handle(string $param))
  Rest/                    endpoints propios motorlan-portal/v1 (parciales HTMX)
  Security/StaleNonce.php  nonce caducado en GET → respuesta de invitado + cabecera de aviso
  Support/                 Api, View, Urls, Nav, Config, Assets, Icons, Seo, SpaPages, Store, AuthMessages,
                           Chat (kit de chat), Publicacion (filas de publicación del panel)
  Admin/Settings.php       Ajustes › Motorlan Portal (casillas por sección)
templates/template-portal.php   plantilla que carga template_include
views/{layout,components,pages,partials}/
assets/{js,css}/           fuentes del build
scripts/                   build de iconos y public.css, vista previa sin WordPress
tests/visual/              Playwright: referencias de la SPA, catálogo y pantallas migradas
```

## Coexistencia con la SPA

- **Interruptor por sección**: opción `motorlan_portal_sections` (array de claves), en
  *Ajustes › Motorlan Portal*. Todas apagadas por defecto. La constante
  `MOTORLAN_PORTAL_SECTIONS` (`'ui-kit,tienda'`) en `wp-config.php` manda sobre la opción:
  sirve para probar en local **sin escribir en la base de datos**.
- Sin secciones encendidas, `Plugin::boot()` sale antes de registrar reglas, `parse_request`,
  `template_include`, endpoints REST o Timber.
- URLs propias: `/ml-portal/{sección}/…` (regla de reescritura solo para las encendidas).
  Las reglas se regeneran solas cuando cambia el conjunto de secciones encendidas
  (firma en `motorlan_portal_rewrite_sig`), no en cada carga.
- URLs de la SPA: una sección puede declarar `spa_paths`; encendida, `Takeover`
  (`parse_request` prioridad 20, después del modo history de la SPA en prioridad 1) le
  quita esas rutas y `template_include` (prioridad 1000, después del 99 de la SPA) carga la
  plantilla del portal. Apagada, no casa con nada.
- JWT de la SPA: intacto. El portal usa la sesión de WordPress (cookie) + `X-WP-Nonce`.

### Sesión y nonce

- El servidor decide el acceso (`auth` de la sección): `public`, `user` (redirige a
  `wp_login_url` con vuelta) o `admin` (`manage_options`, si no 403).
- HTMX añade `X-WP-Nonce` a cada petición (`window.motorlanPortal.nonce`).
- Nonce caducado: en GET a `motorlan-portal/v1/` el servidor responde como invitado con
  `X-Motorlan-Nonce-Stale: 1`; en el resto, 403 `rest_cookie_invalid_nonce`. En ambos casos
  `app.js` no pinta la respuesta, pide un nonce nuevo (`motorlan_rest_nonce` de admin-ajax,
  el mismo que usa `nonce.js` de la SPA) y repite la petición **una vez**. `mlFetch()` hace
  lo mismo para `fetch`.

### API

```php
use MotorlanPortal\Support\Api;

$pubs = Api::get('/motorlan/v1/my-account/publications', ['per_page' => 10]); // datos o null
$res  = Api::call('POST', '/motorlan/v1/offers', [], ['amount' => 100]);       // {ok,status,data,headers,error}
$err  = Api::last_error();
```

Llama a los endpoints de `motorlan-api-vue` dentro del mismo proceso
(`rest_do_request`): mismos permisos, sin HTTP, sin CORS. Devuelve arrays (no `stdClass`).

## Secciones migradas

| Sección | URLs (con la sección encendida) | Controlador |
|---|---|---|
| `tienda` | `/marketplace-motorlan/` (listado) y `/marketplace-motorlan/{slug}/` (ficha), en cada idioma de WPML | `TiendaController` |
| `acceso` | `/mi-cuenta/{login,register,register-success,forgot-password,reset-password,verify-email}` (también bajo la página de la tienda, como la SPA) | `AccesoController` |
| `panel` | `/mi-cuenta/` y `/mi-cuenta/dashboard` → Mis compras; URLs antiguas del panel (`purchases/favorites`, `user/company`, `chat`, `questions/list`…) → su pantalla actual | `PanelController` |
| `cuenta` | `/mi-cuenta/dashboard/user/account` (y `user/profile`, que redirige aquí) | `CuentaController` |
| `favoritos` | `/mi-cuenta/dashboard/favorites/list` | `FavoritosController` |
| `admin` | `/mi-cuenta/dashboard/admin/publications`, `…/admin/approvals` y `…/admin/approvals/preview/{uuid}` (solo `manage_options`) | `AdminController` |

Las URLs son las de la SPA (`Support\SpaPages`: slugs por idioma y rutas que siguen siendo de
la SPA, p. ej. `/marketplace-motorlan/dashboard/…`). La consulta principal sigue siendo la
página de WordPress, así que el tema pinta su cabecera y Rank Math/WPML funcionan igual.

**Tienda.** Un formulario GET con búsqueda, filtros, orden y paginación. Con HTMX pide la
misma URL y el servidor responde solo `partials/tienda-results.twig` (`Vary: HX-Request`,
`HX-Push-Url` con la URL normalizada); sin JS recarga la página. La URL usa los mismos
parámetros que la SPA (`tipo`, `tech`, `marca`…), así que los enlaces viejos siguen valiendo.
SEO en servidor: canonical, `noindex,follow` con filtros u orden, `prev/next` y JSON-LD
`ItemList` (`Support\Seo::store_listing`). En la ficha se reutiliza el SEO de la SPA
(`motorlan_store_seo_setup`: título, canonical, OG, hreflang, JSON-LD `Product`, 301 de
slugs antiguos); `Seo::product()` solo actúa si esa función no existe. Favoritos por
`POST motorlan-portal/v1/tienda/favorito/{id}`; chat de la ficha en `assets/js/product-chat.js`
(mismas claves de `localStorage` que la SPA para el invitado).

**Acceso.** Cada pantalla es un formulario que hace POST a su propia URL (nonce
`motorlan_portal_auth`); el controlador llama a `motorlan/v1/{login,register,…}` con
`Support\Api` y responde el parcial `partials/acceso/{pantalla}.twig` (HTMX) o
`HX-Redirect`/303 si hay que cambiar de página. El login emite la cookie de WordPress
(`wp_signon` dentro del endpoint). `?to=` se respeta solo si es del mismo sitio. Los enlaces
de los emails llevan el token en el fragmento (`#token=…`): `mlAuthToken` (`assets/js/auth.js`)
lo copia al formulario. Con la sección encendida, `Urls::login()` apunta a esta pantalla.
El registro necesita JS (asistente de 3 pasos, como la SPA).

**Panel** (`layout/app-shell.twig`, layout `default` de la SPA). Menú lateral (Ventas /
Compras / Administración, esta solo con `manage_options`), barra con campana, idioma (solo
si WPML tiene más de uno) y menú del usuario; barra inferior en móvil como
`DashboardMobileNav.vue`. Solo tema claro. Cerrar sesión hace `POST motorlan/v1/logout` con
nonce y va al login; al volver atrás desde la caché del navegador (bfcache) se comprueba
`GET /session` y, si ya no hay sesión, al login con `?to=`. El menú enlaza **siempre** a las
URLs de la SPA: cada sección del panel toma su URL al encenderla (`spa_paths`), así que una
pantalla aún sin migrar sigue en la SPA sin tocar el menú.

- *Campana* (`components/notifications.twig` + `assets/js/notifications.js`): lista, contador
  real (`/notifications/unread-count`), leída/no leída (una o todas), eliminar, refresco cada
  60 s con la pestaña visible. Al pulsar: `data.vue_path` (relativo a `/mi-cuenta/`, en el
  idioma actual) o `data.url`, con `offer_id` en la query. «Ver todas» de la SPA no hacía
  nada: aquí es «Cargar más» (hasta 50).
- *Mi perfil*: una pantalla (en la SPA `account` y `profile` eran dos rutas con el mismo
  componente). Datos personales y de empresa con POST a la propia URL; si el email cambia,
  la contraseña actual se pide en un diálogo **antes** de enviar y el cambio queda pendiente
  hasta confirmarlo (con aviso y «Cancelar cambio»). Avatar: se reduce en el navegador
  (800 px, JPEG 0,8) y se sube multipart a `POST /profile`. Contraseña: dos pasos con código
  por email; después se renueva el nonce (la API emite cookie nueva).
- *Favoritos*: tabla con búsqueda, estado, elementos por página y paginación por HTMX;
  quitar pide confirmación y hace POST a la propia URL (`action=remove`) con los filtros.

**Administración** (sección `admin`, `auth: admin`: sin sesión al login, con sesión y sin
`manage_options` 403; la API exige además el rol `administrator`). El menú lateral
(«Administración») y los accesos admin de la barra móvil ya apuntaban a estas URLs de la SPA:
al encender la sección pasan al portal sin tocar `Support\Nav`.

- *Publicaciones* (`GET /admin/publications`, sin las del propio admin, como la SPA):
  búsqueda, estado (incluida la papelera), orden (las cabeceras ordenables de la SPA, aquí un
  desplegable) y paginación por HTMX. Ver (solo publicadas), contactar, editar (pantalla de
  edición de la SPA/fase 4), cambiar estado (`PUT`; «Papelera» se puede deshacer) y eliminar
  (`DELETE`, con confirmación: **definitivo**, ver bugs). Cada acción es un diálogo con
  POST a la propia URL (`action` + `id` + filtros por `hx-include`, nonce `_mlnonce`): el
  servidor devuelve la tabla y un aviso; el diálogo se cierra solo con `X-Ml-Done: 1`, así
  que si falla (p. ej. `wp_mail`) se queda abierto con lo escrito.
- *Aprobaciones* (`GET /admin/pending-publications`, sin paginar en la API: 10 por página en
  el controlador): marca propuesta como chip, «Consultar precio» con el precio de referencia,
  aprobar (POST directo) y rechazar (diálogo, motivo obligatorio).
- *Vista previa* (`GET /publicaciones/uuid/{uuid}`): la ficha con los **mismos parciales** que
  la tienda (`partials/ficha/{galeria,detalle,info,docs}.twig`), el precio visible como
  referencia aunque sea «Consultar precio», sin barra inferior en móvil. Aprobar / rechazar
  vuelven al listado con `?ok=approved|rejected` (aviso con `partials/admin-flash.twig`);
  corregir la marca propuesta (`POST /admin/update-pending-brand/{id}`) recarga la página.

## Panel: añadir una sección

Todo desde el registro, sin tocar el layout ni el menú:

1. **Registro** (`Sections::registry()`), una entrada nueva:
   ```php
   'compras' => ['label' => 'Panel: mis compras', 'auth' => 'user',
       'controller' => Controllers\ComprasController::class,
       // Rutas de la SPA bajo /mi-cuenta/dashboard/ (en todos los idiomas y también bajo la
       // página de la tienda): el grupo `param` llega a handle() (p. ej. "purchases/purchases").
       'spa_paths' => function () { return Support\SpaPages::panel_patterns(['purchases/purchases', 'purchases/[0-9a-f-]{36}']); },
       // Opcional: se ejecuta en Plugin::boot() solo si la sección está encendida.
       'boot' => [Controllers\ComprasController::class, 'boot']],
   ```
2. **Menú**: los elementos de Ventas / Compras / Administración ya existen en `Support\Nav`
   y apuntan a la URL de la SPA, que la sección toma al encenderse: normalmente no hay que
   tocar nada. Si la pantalla es nueva, añade el elemento desde `boot` con los filtros
   `motorlan_portal_nav_vertical`, `motorlan_portal_nav_mobile` o `motorlan_portal_nav_user`:
   ```php
   add_filter('motorlan_portal_nav_vertical', function ($items) {
       $items[] = Support\Nav::item(__('Mis facturas', 'motorlan-portal'), 'file-invoice', 'dashboard/invoices', ['dashboard/invoices']);
       return $items;
   });
   ```
   `Nav::item(título, icono, ruta, prefijos_activos)` construye la URL con `Urls::spa()`
   (idioma incluido) y `mark_active()` lo marca por prefijo de ruta.
3. **Vista** sobre `layout/app-shell.twig` (bloque `main`; `hide_mobile_nav: true` en el
   contexto para pantallas de detalle con botones propios, como la SPA).
4. **Formularios**: POST a la propia URL. `Controller` trae `is_post()`, `valid_nonce()`
   (cabecera `X-WP-Nonce` de HTMX o campo `_mlnonce` con `Controller::PANEL_NONCE`),
   `field()`, `query()`, `redirect()` (`HX-Redirect` con HTMX, 302/303 sin JS) y
   `toast_header()` (aviso en `HX-Trigger`). Con HTMX responde el parcial; sin JS, la página.
   Ejemplos: `CuentaController`, `FavoritosController`.
5. Filas de publicación (nombre como la SPA, estado con color, miniatura):
   `Support\Publicacion::row()`.

## Kit: chat

Una conversación es siempre el mismo componente; lo monta la ficha (fase 1) y lo montarán
compras, ventas, consultas y administración:

- `assets/js/chat.js` → `chatCore()` (leer, sondear, enviar, agrupar por día; sondeo 3 s ×1,5
  hasta 10 s, respeta `Retry-After` en 429, se para con 403/404, con la pestaña oculta o con
  el diálogo cerrado) y `mlChat(cfg)` (diálogo del panel).
- `views/components/chat-thread.twig`: mensajes + caja de escribir (lo usan los dos).
- `views/components/chat-dialog.twig` + `Support\Chat`: el diálogo del panel.

```php
// Controlador
'chat' => Chat::purchase($uuid, ['title' => $nombre, 'image' => $img, 'otherName' => $vendedor,
                                 'autoOpen' => isset($_GET['openChat'])]),
'chat' => Chat::product($product_id, $room_key, ['title' => $nombre, 'otherName' => $comprador]),
```
```twig
{% include 'components/chat-dialog.twig' with { chat: chat } %}
<button @click="$dispatch('ml-chat-open', { id: 'ml-chat' })">Abrir chat</button>
{# Bandeja: una sola instancia; se cambia de conversación con el evento #}
<button @click="$dispatch('ml-chat-open', { id: 'ml-chat', kind: 'product', productId: 12, roomKey: 'pub-12-viewer-5', title: '…', otherName: '…' })">…</button>
```

Compras → `/purchases/{uuid}/messages`; consultas → `/products/{id}/messages` con `room_key`
y, al abrir y al llegar mensajes, `POST /products/{id}/rooms` (marcar leído). El componente
emite `ml-chat-read` y `ml-chat-message` en `window` para que la lista actualice contadores.
Varios chats en una página: un `dialogId` distinto por cada uno. Tiempo real = sondeo (la
SPA quitó pusher-js).

## Migrar una pantalla

1. **Controlador** en `src/Controllers/` que extienda `Controller` y renderice una página:
   ```php
   class TiendaController extends Controller {
       public function handle(string $param): void {
           $data = Api::get('/motorlan/v1/publicaciones', ['page' => max(1, (int) ($_GET['page'] ?? 1))]);
           if ($data === null) { $this->not_found(); return; }
           $this->render('pages/tienda.twig', ['page_title' => __('Tienda', 'motorlan-portal'), 'pubs' => $data]);
       }
   }
   ```
2. **Registro**: en `Sections::registry()`, pon `controller` y, si va a sustituir a la SPA,
   `spa_paths` (regex sobre la ruta sin idioma, con el grupo `param` opcional):
   ```php
   'tienda' => ['label' => 'Tienda y ficha de producto', 'auth' => 'public',
                'controller' => Controllers\TiendaController::class,
                'spa_paths' => ['#^/marketplace-motorlan(?:/(?<param>[^/]+))?/?$#']],
   ```
3. **Vista** en `views/pages/` sobre un layout (`layout/app-shell.twig` para el panel,
   `layout/auth-shell.twig` para acceso, `layout/public.twig` para páginas con cabecera y pie
   del tema) usando los componentes de `views/components/`.
4. **Interacción parcial** (filtros, paginación, formularios): endpoint en `src/Rest/`
   (`motorlan-portal/v1/…`) que compile un parcial de `views/partials/` con `View::compile()`
   y lo devuelva con `View::html_response()`. La página y el endpoint renderizan el **mismo**
   parcial (ver `UiKitApi` + `partials/ui-kit-table.twig`). Sin JS, los enlaces y formularios
   recargan la página con los mismos parámetros en `$_GET`.
5. **SPA**: si la SPA engancha algo en esas URLs (SEO de la ficha en `template_redirect`,
   encolado del bundle…), neutralízalo en `add_action('motorlan_portal_takeover', …)` desde
   el controlador o el arranque de la sección, **sin editar `motorlan-api-vue`**.
6. **Menú**: los enlaces entre pantallas usan `ml_screen(sección, ruta_portal, ruta_spa)`, que
   apunta al portal si la sección está encendida y a la SPA si no.
7. **Iconos**: escribe los nombres como literales (`'shopping-bag'`): el build solo incluye
   los iconos que encuentra entre comillas en `views/` y `src/`.
8. Compara con la SPA (`tests/visual`), enciende la sección en local con la constante y,
   después del despliegue, en *Ajustes › Motorlan Portal*.

## Probar en local

El WordPress local usa la base de datos local `motorlandev` (variables `SetEnv` en el
vhost de Laragon). **Ojo:** el PHP de la línea de comandos no ve esas variables y caería en
la configuración por defecto: antes de escribir nada por CLI comprueba que
`DB_NAME === 'motorlandev'`. Para encender secciones sin tocar la BD, usa la constante
`MOTORLAN_PORTAL_SECTIONS`.

Para el panel hace falta un usuario de prueba **en la BD local** (nunca uno real) con algún
favorito (user meta `motorlan_favorites`) y notificaciones
(`Motorlan_Notification_Manager::create_notification()`); sus credenciales van en
`ML_PORTAL_USER` / `ML_PORTAL_PASS` al lanzar las pruebas, no en el repositorio.

Para revisar vistas sin WordPress:

```bash
php -S 127.0.0.1:8765 scripts/preview-ui-kit.php
```

Abre `http://127.0.0.1:8765/` (catálogo), `/auth/` y `/public/`. Twig puro con las funciones
de WordPress sustituidas; la tabla funciona con HTMX contra un parcial local.

## Pruebas visuales (Playwright)

```bash
npm run test:visual          # compara
npm run test:visual:update   # regenera referencias
```

`tests/visual/spa-reference.spec.js` captura la SPA actual (tienda, ficha, login y, con
`ML_E2E_USER`/`ML_E2E_PASS`, el panel) en escritorio y móvil, **solo lectura**
(`ML_E2E_BASE`, por defecto producción). `tests/visual/ui-kit.spec.js` captura el catálogo
desde la vista previa (`ML_PREVIEW_BASE`). `tests/visual/portal-publico.spec.js` recorre las
pantallas migradas en un sitio con las secciones encendidas (`ML_PORTAL_BASE`, por defecto
`https://dev.motorlan.test`): tienda (SEO + filtro HTMX), ficha (JSON-LD + chat), login con
credenciales falsas, pasos del registro y enlaces sin token; guarda `portal-*.png` junto a
las capturas de la SPA (`tienda.png` ↔ `portal-tienda.png`…). Usa el Chrome instalado
(`ML_E2E_CHANNEL`).

`tests/visual/portal-panel.spec.js` (con `ML_PORTAL_USER`/`ML_PORTAL_PASS` de un usuario de
la BD local): layout y menú, campana (marcar todas, navegar con `vue_path` + `offer_id`),
favoritos (HTMX, quitar con confirmación y volver a añadirlo), Mi perfil (redirección de
`user/profile`, guardar, validación, diálogo de contraseña para el email, primer paso del
cambio de contraseña) y el chat de la ficha con sesión. Con `ML_PANEL_SPA=1` y las
secciones apagadas captura las referencias de la SPA (`cuenta.png`, `favoritos.png`).

`tests/visual/portal-admin.spec.js` (con `ML_ADMIN_USER`/`ML_ADMIN_PASS` de un administrador
de la BD local y `ML_NOADMIN_USER`/`ML_NOADMIN_PASS` de un usuario normal): menú, filtros por
HTMX, cambiar estado (también a la papelera y de vuelta), eliminar, contactar, rechazar con motivo, vista previa con
corrección de marca, uuid inexistente (404) y 403 sin permiso (también en un POST directo).
Necesita publicaciones pendientes de prueba de otro usuario con «prueba fase 5» en el título,
una con `_pending_brand_name`, y otra con «prueba fase 5 borrar» que el test de eliminar borra
de verdad (hay que volver a crearla); el resto de cambios se deshacen (no aprueban: crearía
la marca). Con `ML_ADMIN_SPA=1` y la sección apagada captura las
referencias de la SPA (`admin-*.png`).

## Despliegue

`.github/workflows/deploy-motorlan-plugin.yml`, a mano, con **paquete = motorlan-portal**:
`composer install --no-dev`, `npm ci && npm run build`, lint PHP y solo los archivos de
producción (`motorlan-portal.php`, `composer.json`, `src`, `templates`, `views`, `vendor`,
`dist`). Simulación por defecto, `SUBIR` para el real, solo desde `main`. Destino
`FTP_SERVER_DIR_PORTAL` (por defecto `/public/wp-content/plugins/motorlan-portal/`).
Tras el primer despliegue el plugin se activa a mano en wp-admin.
