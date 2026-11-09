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
  Support/                 Api, View, Urls, Nav, Config, Assets, Icons
  Admin/Settings.php       Ajustes › Motorlan Portal (casillas por sección)
templates/template-portal.php   plantilla que carga template_include
views/{layout,components,pages,partials}/
assets/{js,css}/           fuentes del build
scripts/                   build de iconos y public.css, vista previa sin WordPress
tests/visual/              Playwright: referencias de la SPA y del catálogo
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

## Probar sin tocar la base de datos

El WordPress local usa la base de datos **de producción**: activar el plugin escribe
`active_plugins` y, con alguna sección encendida, `rewrite_rules` y
`motorlan_portal_rewrite_sig`. Para revisar vistas sin WordPress:

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
desde la vista previa (`ML_PREVIEW_BASE`). Usa el Chrome instalado (`ML_E2E_CHANNEL`).

## Despliegue

`.github/workflows/deploy-motorlan-plugin.yml`, a mano, con **paquete = motorlan-portal**:
`composer install --no-dev`, `npm ci && npm run build`, lint PHP y solo los archivos de
producción (`motorlan-portal.php`, `composer.json`, `src`, `templates`, `views`, `vendor`,
`dist`). Simulación por defecto, `SUBIR` para el real, solo desde `main`. Destino
`FTP_SERVER_DIR_PORTAL` (por defecto `/public/wp-content/plugins/motorlan-portal/`).
Tras el primer despliegue el plugin se activa a mano en wp-admin.
