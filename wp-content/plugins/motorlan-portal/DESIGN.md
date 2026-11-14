# Motorlan Portal: diseño

Objetivo: que una pantalla migrada no se distinga de la de la SPA. La identidad sale de
`app/src/plugins/vuetify/theme.ts` (tema `light`), `themeConfig.ts`,
`design-improvements.css` y el layout vertical de Vuexy. El catálogo
(`/ml-portal/ui-kit/`, o la vista previa `php -S 127.0.0.1:8765 scripts/preview-ui-kit.php`)
muestra todos los componentes; las capturas de `tests/visual/__screenshots__/` son la
referencia de la SPA.

## Tokens

Definidos **una vez** como variables CSS en `:root` (`assets/css/app.css`) y mapeados a
Tailwind con `@theme inline`. Solo hay tema claro; para añadir uno oscuro basta con
redefinir las `--ml-*` bajo `[data-theme="dark"]` (el `<html>` ya lleva `data-theme="light"`).

| Vuetify | Variable | Tailwind | Valor |
|---|---|---|---|
| primary | `--ml-primary` / `--ml-primary-darken` | `bg-primary`, `text-primary` | `#DA291C` / `#A92015` |
| secondary | `--ml-secondary` | `*-secondary` | `#808390` |
| success | `--ml-success` | `*-success` | `#28C76F` |
| info | `--ml-info` | `*-info` | `#00BAD1` |
| warning | `--ml-warning` | `*-warning` | `#FF9F43` |
| error | `--ml-error` | `*-error` | `#FF4C51` |
| background | `--ml-background` | `bg-background` | `#F8F7FA` |
| surface | `--ml-surface` | `bg-surface` | `#FFFFFF` |
| on-surface | `--ml-on-surface` (`--ml-on-surface-rgb: 47 43 61`) | `text-on-surface` | `#2F2B3D` |
| high / medium / disabled emphasis | `--ml-high-emphasis` … | `text-high-emphasis` … | on-surface .9 / .7 / .4 |
| border | `--ml-border` / `--ml-border-strong` | `border-border` | on-surface .12 / .22 |

- **Tipografía**: Inter 300–700 (Google Fonts en `layout/base.twig`; en `public` la carga el
  tema). Escala de Vuexy: `ml-h1`…`ml-h6` (46/38/28/24/18/15 px), cuerpo 15 px.
- **Radios**: controles 6 px (`rounded-control`), tarjetas 16 px (`rounded-card`), ficha y
  diálogos 20 px (`rounded-card-lg`).
- **Sombras**: las de `design-improvements.css` (`shadow-sm/md/lg/xl`, `--ml-shadow-hover`) y
  las de Vuexy para menú lateral, barra superior y menús.
- **Iconos**: Tabler outline, mismo nombre que en la SPA sin el prefijo `tabler-`
  (`ml_icon('shopping-bag')`, `ui.icon('heart', 'size-4')`).

### Color de un componente

Todos los componentes con color usan la clase `ml-c-{color}`, que fija `--c` y `--c-dark`;
las variantes (`--filled`, `--tonal`, `--outlined`, `--text`) se construyen con `--c`. Para un
color nuevo basta con añadir la variable y su `ml-c-*`. Las reglas `.ml-c-*` van al **final**
de la capa `components`: así ganan a los `--c` por defecto de `.ml-btn`, `.ml-chip`,
`.ml-alert`… (antes estaban al principio y `ml-c-error` no coloreaba ningún botón).

### Marca en las pantallas públicas

Fondo y tarjetas con el rojo de marca en tonos suaves, no grises neutros: el hero de la
tienda y la página de acceso comparten degradado (brillo rojo sobre blanco cálido, borde
`rgb(218 41 28 / .12)`); la tarjeta de acceso lleva franja superior roja y antetítulo
(`.ml-eyebrow`). Los avisos informativos dentro del acceso usan `ml-c-primary` con fondo
cálido y texto oscuro (el rojo pleno queda para errores); los secundarios, contorno rojo.

## Layouts

| Layout | Para | Notas |
|---|---|---|
| `layout/app-shell.twig` | panel (comprador, vendedor, admin) | menú lateral Vuexy 260/70 px plegable (se recuerda), panel lateral en < 1280 px, barra superior flotante con desenfoque, contenido máx. 1440 px, barra inferior en < 960 px como `DashboardMobileNav.vue` |
| `layout/auth-shell.twig` | login, registro, recuperar | tarjeta centrada con logo; bloques `auth` y `below` |
| `layout/public.twig` | tienda, ficha | dentro del tema (`get_header`/`get_footer`); estilos acotados a `#ml-portal` (`dist/css/public.css`) para no chocar con el tema ni dejar que el tema los pise |

Los menús salen de `Support\Nav` (copia de `navigation/vertical/apps-and-pages.ts` y de la
barra inferior); `mark_active()` marca el elemento de la URL actual. En la barra del panel:
campana, selector de idioma (solo con varios idiomas en WPML) y menú del usuario con su foto
de Mi perfil. Sin selector de tema: solo claro.

## Componentes

`views/components/`. Cada archivo documenta sus opciones en la cabecera. Equivalencias:

| Twig | SPA | Uso |
|---|---|---|
| `btn.twig` → `b.btn({...})` | VBtn | `variant` filled/tonal/outlined/text, `size` sm/md/lg, `icon`, `href`, `loading` |
| `chip.twig` → `c.chip({...})` | VChip | estados, etiquetas |
| `avatar.twig`, `badge.twig` | VAvatar, VBadge | iniciales, icono o imagen; punto o número |
| `field.twig` → `f.text/select/textarea/checkbox/radio/switch/file` | AppTextField… | etiqueta encima (como Vuexy), `hint`, `error`, `icon` |
| `alert.twig` | VAlert | tonal / outlined / filled, `closable` |
| `card.twig` (embed) | VCard + `motor-card-enhanced` | bloques `media`, `header`, `body`, `actions`; `variant: lg` para ficha |
| `tabs.twig` | VTabs | subrayado o `pill`; paneles con `x-show="isActive('…')"` |
| `stepper.twig` | AppStepper | `mlStepper(n)` |
| `dialog.twig` (embed) | VDialog + DialogCloseBtn | se monta en `#ml-overlays`, Esc/fondo cierran, foco atrapado |
| `table.twig` (embed) | VDataTable | filtros y paginación por HTMX; en móvil cada fila es una tarjeta (`data-label`) |
| `pagination.twig` | VPagination | enlaces reales + `hx` opcional |
| `empty.twig`, `skeleton.twig` | estados vacíos, VSkeletonLoader | |
| `user-menu.twig`, `notifications.twig` | UserProfile, NavBarNotifications | cerrar sesión por `POST /logout`; campana con datos (`mlNotifications`) |
| `chat-thread.twig`, `chat-dialog.twig` | ChatModal, BuyerChatModal, SellerChatModal | kit de chat: burbujas (propias en rojo a la derecha), separador por día, avatar o iniciales del otro en el panel; configuración con `Support\Chat` |

### Diferencias conocidas con la SPA

- El login de producción vive dentro del tema (cabecera y pie de la web) con campos de
  etiqueta flotante y botón en mayúsculas; `auth-shell` es el layout de acceso de Vuexy. Al
  migrar el login, decidir si va en `public` (como ahora) o en `auth-shell`.
- Campana: «Ver todas» (no hacía nada) es «Cargar más»; en táctil el punto de leída y la X
  se ven siempre (en la SPA, solo al pasar el ratón).
- Mi perfil: una sola pantalla para `user/account` y `user/profile`; el email nuevo pide la
  contraseña antes de enviar.
- Favoritos: el filtro de estado incluye «En revisión» (`pending`), que la SPA mostraba como
  «Desconocido».
- Administración: «Cambiar estado» y «Eliminar» son botones de la fila (en la SPA, un menú
  «⋮»): `.ml-table-shell` tiene `overflow-x` y recortaría el desplegable. El orden de la tabla
  es un desplegable en vez de cabeceras ordenables. En móvil las filas son las tarjetas de
  `components/table.twig` (la SPA tenía unas propias con el mismo contenido).

## Convenciones de Twig

- **Timber tiene el autoescape desactivado.** Todo lo que venga de datos se escapa a mano:
  - texto: `{{ pub.title|e }}`
  - atributos: `<a href="{{ url|e('html_attr') }}" title="{{ t|e('html_attr') }}">`
  - JSON para JS: `{{ ml_json(data) }}` (con `JSON_HEX_*`, seguro dentro de `<script>`)
  - URLs en `hx-get`/`href`: `|e('html_attr')`; en parámetros, `|url_encode`.
- Las funciones propias (`ml_icon`, `ml_url`, `ml_screen`, `ml_asset`, `ml_nonce_field`…)
  devuelven HTML ya seguro: no se escapan otra vez.
- Los macros escapan `label`, `title`, `placeholder` y `value`. `attrs` y `append` se
  insertan tal cual: solo HTML ya escapado o literales.
- Traducciones: `{{ __('Texto', 'motorlan-portal') }}`; los textos visibles en español.
- **Dentro de un `embed` no se ven los `import` del padre**: impórtalos dentro de cada bloque.
- Un macro llama a otro del mismo archivo con `_self.otro(...)`; no se anidan macros.
- Iconos con nombre literal entre comillas (el build de iconos busca literales). Si el
  nombre se calcula, añade los posibles a la lista de algún literal (p. ej. un array en `src/`).
- Alpine en las plantillas solo para estado de interfaz (abrir, pestaña, paso); los datos
  vienen del servidor. Componentes Alpine reutilizables en `assets/js/app.js`
  (`appShell`, `mlDialog`, `mlTabs`, `mlFile`, `mlStepper`, `$store.toast`); los de
  pantalla en su propio módulo (`store.js`, `product-chat.js`, `auth.js`). Para cerrar un
  diálogo desde fuera: `$dispatch('ml-dialog-close', 'id')`.
- HTMX: el servidor devuelve HTML; los parciales se reutilizan entre la página completa y el
  endpoint. Avisos desde el servidor con la cabecera `HX-Trigger: {"ml:toast": {"text": "…", "color": "success"}}`.
