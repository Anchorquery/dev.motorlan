# Inventario del frontend (SPA Vue) — qué se usa

Estado a 2026-11-09, rama `anchorquery/vue-limpieza-codigo-muerto-d4855b`, tras la limpieza de código muerto.
Sirve de guía para migrar a `motorlan-portal` (Timber/Twig + HTMX + Alpine + Tailwind): **solo hay que migrar lo que aparece en la tabla de rutas vivas**.

Todas las rutas REST son del namespace `motorlan/v1` (prefijo `/wp-json/motorlan/v1`). `{id}`/`{uuid}` = parámetro de URL.
El detalle de cada endpoint (permisos, parámetros, respuesta) está en los catálogos de la sesión de backend (`catalog-A..D.md` en la carpeta de notas compartidas); aquí solo figura qué pantalla lo llama.

## 1. Cómo se monta la SPA

- **Entrada**: `app/src/main.ts` monta en `#motorlan-app`. El HTML, el skeleton de carga y `window.wpData` los genera `includes/vue-app-setup.php` (shortcode / página de WordPress).
- **Dos bases (history mode)**: `wpData.vue_base` es la página de WP donde está montada la app. En la práctica:
  - `/marketplace-motorlan/` → tienda y fichas (`/`, `/:slug`).
  - `/mi-cuenta/` → login/registro y panel (`/login`, `/dashboard/...`). Si se entra en `/` con la base de cuenta, el guard redirige a `/dashboard`.
  - Con WPML las bases llevan prefijo de idioma (`/en/...`, `/eu/...`); `wpData.vue_page_urls` da la URL de cada página por idioma y `utils/urls.ts` (`pageUrl`, `storeUrl`, `accountUrl`) la usa para enlaces entre bases (siempre con `window.location`, no con el router).
  - `wpData.initial_route` (atributo del shortcode) fuerza la ruta inicial.
- **Rutas por archivos** (`unplugin-vue-router`): cada `.vue` de `src/pages` es una ruta, salvo `src/pages/**/components/**` (excluido en `vite.config.ts`). Nombre de ruta = path en kebab-case (`dashboard-purchases-purchases`).
- **Layouts** (`vite-plugin-vue-meta-layouts`): `default` (panel con menú lateral, barra superior y menú inferior móvil) salvo que la página declare `meta.layout: 'blank'` (pantalla completa sin menú).
- **Guard** (`plugins/1.router/guards.ts`): `meta.public` → libre; `meta.unauthenticatedOnly` → si ya hay sesión manda a `dashboard-purchases-purchases`; el resto exige sesión (espera a `useUserStore`; si no hay sesión → `login?to=<ruta>`). **No comprueba roles**: las páginas de admin solo se ocultan del menú; el backend es quien deniega.
- **Sesión**: `@core/stores/user.ts` arranca con `wpData.user_data` y consulta `GET /session`. Las peticiones van por `composables/useApi.ts` (añade `X-WP-Nonce`, reintenta con nonce nuevo desde `wpData.nonce_refresh_url`, y con 401/403 de sesión caducada redirige al login). Las públicas usan `usePublicApi.ts`.
- **Permisos CASL** (`plugins/casl`): solo dos reglas, `manage all` (admin, `user.is_admin`) o `read all` (usuario logueado). Se usan en el menú lateral (`navigation/vertical/apps-and-pages.ts`) y se recalculan en `login.vue`/`UserProfile.vue` (logout). El menú inferior móvil (`DashboardMobileNav.vue`) usa `userStore.isAdmin` directamente.
- **Tiempo real**: no hay Pusher (se quitó `pusher-js`). Chats y avisos funcionan por **polling**: `usePolling.ts` (intervalo adaptativo, 3 s de base) en los chats; la campana de notificaciones refresca cada 60 s; la bandeja de Interesados cada 60 s.
- **i18n**: `vue-i18n` con `plugins/i18n/locales/{es,en,eu,fr,ar}.json` + `wpData.i18n_messages` (traducciones servidas por WP, tienen prioridad). El idioma lo decide WP (`wpData.language`); el selector de idioma (`@core/components/I18n.vue`) navega a la URL WPML del idioma. `fr.json` y `ar.json` están a medias (~360 claves frente a ~1.480).
- **Estilos**: Vuetify 3 + SCSS de Vuexy (`@core/scss`, `@layouts/styles`) + `assets/styles/{styles.scss,design-improvements.css,theme-isolation.css}`. `theme-isolation.css` neutraliza los estilos del tema WordPress (GeneratePress) dentro de la app. Iconos: solo Tabler (`tabler-*`), empaquetados por `plugins/iconify/build-icons.ts` (en producción solo los nombres literales encontrados en `src/`, `themeConfig.ts` e `includes/`).

### Piezas globales (en todas las pantallas del layout `default`)

| Pieza | Archivo | Endpoints | Notas |
|---|---|---|---|
| Menú lateral | `@layouts/components/VerticalNav*.vue`, `navigation/vertical/apps-and-pages.ts` | — | Secciones Ventas / Compras / Administración (esta solo con `manage all`) |
| Menú inferior móvil | `layouts/components/DashboardMobileNav.vue` | — | Items distintos para admin y usuario; se oculta en detalle de venta y en la vista previa de aprobación |
| Campana de notificaciones | `layouts/components/NavBarNotifications.vue`, `@core/components/Notifications.vue`, `@core/stores/notification.ts` | `GET /notifications`, `GET /notifications/unread-count`, `POST /notifications/read`, `POST /notifications/unread`, `DELETE /notifications/{id}` | Cada aviso trae `vue_path` (navega con el router) o `url` (cambio de base → `window.location`) |
| Menú de usuario | `layouts/components/UserProfile.vue` | `POST /logout` | Tras cerrar sesión va al login con recarga completa |
| Selector de tema | `layouts/components/NavbarThemeSwitcher.vue`, `@core/components/ThemeSwitcher.vue` | — | claro / oscuro / sistema, en cookie |
| Selector de idioma | `@core/components/I18n.vue` | — | Usa `wpData.languages` (WPML) |
| Comprobación de sesión al volver atrás (bfcache) | `App.vue` | `GET /session` | |
| Toast global | `composables/useToast.ts` + `VSnackbar` en `App.vue` | — | |

## 2. Rutas vivas

Leyenda de «¿Se usa?»: **menú** (lateral y/o móvil), **enlace** (desde otra pantalla), **aviso** (URL que generan notificaciones o emails del backend), **huérfana** (existe y funciona pero nada la enlaza).

### Públicas / autenticación (layout `blank`)

| Ruta | Archivo | ¿Se usa? | Acceso | Endpoints REST | Componentes propios | Notas |
|---|---|---|---|---|---|---|
| `/` | `pages/index.vue` | Sí: portada de la tienda en `/marketplace-motorlan/` | público | `GET /marcas`, `GET /tipos`, `GET /store/publicaciones` | `views/store/StoreContent.vue`, `useStoreFiltersStore.ts` (Pinia), `pages/store/components/{TiendaFilters,SearchBar,PublicacionItems,PaginationControls,StoreMobileNav}.vue` | Filtros, búsqueda, orden y página en la query (`router.replace`). En la base `/mi-cuenta/` el guard la redirige a `/dashboard` |
| `/:slug` | `pages/[slug].vue` | Sí: ficha de producto (enlaces del listado, relacionados, emails) | público | `GET /publicaciones/{slug}`, `GET /chat-tokens/{token}`, `GET /countries`, `GET /store/publicaciones` (relacionados), `GET /favorites?fields=ids`, `POST /favorites`, `DELETE /favorites/{id}`, `POST /publicaciones/{id}/offers`, `DELETE /offers/{id}`; chat: `GET`/`POST /products/{id}/messages` (polling), `GET /buyer/inquiries`, `POST /buyer/claim-guest-chats`, `GET /marcas` | `pages/store/components/{ProductImage,ProductDetails,PublicacionInfo,ProductDocs,RelatedProducts,ChatModal,EmptyState}.vue`; `composables/{useProductChat,usePolling,useSanitize,useCountries}.ts`; `utils/{guest,roomKey,tipoMapping,formatCurrency}.ts` | Si el slug no es el canónico, `router.replace` al canónico. Descripción con `v-html` saneada por DOMPurify. Galería con Swiper. Chat de invitado: id de sala aleatorio por producto en `localStorage` (`utils/guest.ts`); `?room_key=`/`?ct=` (token del email) abren la conversación. El PHP devuelve 301 para slugs antiguos y 404 noindex para slugs inexistentes |
| `/login` | `pages/login.vue` | Sí | solo sin sesión | `POST /login` | — | `?to=` (solo mismo origen). Tras entrar recarga completa a `dashboard/purchases/purchases` (o a `dashboard/user/account` si el perfil está incompleto). Actualiza CASL |
| `/register` | `pages/register.vue` | Sí (login, `ChatModal`) | solo sin sesión | `POST /register` | — | Enlace a `wpData.privacy_url`. Al terminar → `register-success` con recarga |
| `/register-success` | `pages/register-success.vue` | Sí (tras registrarse) | solo sin sesión | `POST /resend-verification` | — | En dev muestra `dev_verify_url` |
| `/forgot-password` | `pages/forgot-password.vue` | Sí (login) | solo sin sesión | `POST /forgot-password` | — | |
| `/reset-password` | `pages/reset-password.vue` | Sí (email de recuperación) | solo sin sesión | `POST /reset-password` | `views/apps/user/view/PasswordRequirements.vue` | `key` y `login` llegan en el hash/query y se borran de la URL |
| `/verify-email` | `pages/verify-email.vue` | Sí (email de verificación y de cambio de email) | público | `POST /verify-email`, `POST /resend-verification`, `POST /profile/confirm-email-change` | — | Pública a propósito (el cambio de email se confirma con sesión abierta) |
| `/:error(.*)` | `pages/[...error].vue` | Sí: 404 de la SPA | público | — | `components/ErrorHeader.vue` | Alias `/pages/misc/not-found/:error(.*)` |
| `/not-authorized` | `pages/not-authorized.vue` | **Huérfana** (nada navega aquí) | público | — | `components/ErrorHeader.vue` | Alias `/pages/misc/not-authorized`. Está en la lista `motorlan_store_spa_routes` del PHP |
| `/public-store` | `pages/public-store/index.vue` | **Dudosa**: nada la enlaza en la SPA | público | igual que `/` | `StoreContent.vue` | Está en `motorlan_store_spa_routes`. Puede usarse con `initial_route="public-store"` en algún shortcode (no verificable sin mirar la BD) |
| `/store` | `pages/store/index.vue` | **No**: al cargar hace `window.location` a `dashboard/purchases/purchases` | público | — | `StoreContent.vue` | Resto de una versión anterior; en `motorlan_store_spa_routes`. No migrar |

### Panel (layout `default`, exige sesión)

| Ruta | Archivo | ¿Se usa? | Acceso | Endpoints REST | Componentes propios | Notas |
|---|---|---|---|---|---|---|
| `/dashboard` | `pages/dashboard/index.vue` | Sí (redirige) | `meta.public` | — | — | Solo redirige a `dashboard-purchases-purchases` |
| `/dashboard/purchases/purchases` | `pages/dashboard/purchases/purchases.vue` | Menú «Mis Compras», menú móvil, aviso | sesión | `GET /purchases/purchases`, `GET /marcas`, `POST /reviews` | `components/RatingModal.vue`, `TablePagination`, `AppSelect`, `AppTextField` | Tabla en escritorio y tarjetas en móvil. Destino por defecto tras login |
| `/dashboard/purchases/:uuid` | `pages/dashboard/purchases/[uuid].vue` | Enlace (lista de compras, ofertas enviadas), aviso | sesión (comprador o vendedor; lo valida el backend) | `GET /purchases/{uuid}`, `GET /countries`, `POST /reviews`; chat: `GET`/`POST /purchases/{uuid}/messages` (polling) | `components/ChatModal.vue`, `components/RatingModal.vue`, `composables/{usePurchaseChat,usePolling,useCountries}.ts` | `?openChat=true` abre el chat |
| `/dashboard/purchases/inquiries` | `pages/dashboard/purchases/inquiries.vue` | Menú «Consultas Enviadas», menú móvil, aviso | sesión | `GET /buyer/inquiries`, `POST /buyer/claim-guest-chats`; chat: `GET`/`POST /products/{id}/messages`, `POST /products/{id}/rooms` (marcar leído) | `pages/dashboard/purchases/components/BuyerChatModal.vue`, `useProductChat`, `usePolling`, `utils/guest.ts` | `?room_key=` abre la conversación. Al entrar reclama los chats hechos como invitado |
| `/dashboard/purchases/offers-sent` | `pages/dashboard/purchases/offers-sent.vue` | Menú «Ofertas de Precio», menú móvil, aviso | sesión | `GET /offers/sent`, `POST /offers/{id}/confirm`, `DELETE /offers/{id}` | `AppDateTimePicker` (flatpickr), `TablePagination` | Confirmar una oferta aceptada crea la compra y navega a `/dashboard/purchases/{uuid}` |
| `/dashboard/purchases/opinions` | `pages/dashboard/purchases/opinions.vue` | **Huérfana**: solo se llegaba por las pestañas de `dashboard/purchases/index.vue` (eliminada, estaba rota) | sesión | `GET /purchases/opinions` | `TablePagination` | Funciona; decidir si se enlaza en el nuevo portal o se descarta |
| `/dashboard/favorites/list` | `pages/dashboard/favorites/list/index.vue` | Menú «Favoritos» | sesión | `GET /purchases/favorites`, `DELETE /favorites/{id}` | `TablePagination` | |
| `/dashboard/publications/publication/list` | `pages/dashboard/publications/publication/list/index.vue` | Menú «Mis Publicaciones», menú móvil, aviso | sesión | `GET /my-publications`, `GET /tipos`, `POST /publicaciones/{id}/status`, `DELETE /publicaciones/{id}`, `POST /publicaciones/bulk-delete`, `POST /publicaciones/duplicate/{id}` | `TablePagination` | Duplicar abre el editor de la copia. Enlaces a la ficha con `storeUrl()` |
| `/dashboard/publications/publication/add` | `pages/dashboard/publications/publication/add/index.vue` | Menú móvil «Publicar», listado, `StoreMobileNav` | sesión | `GET /marcas`, `GET /tipos`, `GET /countries`, `POST /publicaciones` (**multipart**) | `pages/dashboard/publications/components/{StepTypeSelection,StepBasicInfo,StepTechSpecs,StepMedia,StepLocationCondition,StepDocumentation}.vue`, `@core/components/DropZone.vue`, `composables/usePublicationForm.ts` | **Subida de archivos**: imagen destacada, galería y documentos PDF en el mismo `FormData`; avisa antes de enviar si supera `wpData.max_upload_size`/`post_max_size`. Pasa a revisión (aprobación de admin) |
| `/dashboard/publications/publication/edit/:uuid` | `pages/dashboard/publications/publication/edit/[uuid].vue` | Enlace (listado, ventas, aprobaciones, admin) | sesión (autor o admin) | `GET /publicaciones/uuid/{uuid}`, `POST /publicaciones/uuid/{uuid}` (**multipart**), `GET /marcas`, `GET /tipos`, `GET /countries` | mismos `Step*` (salvo `StepTypeSelection`), `DropZone`, `usePublicationForm` | Pestañas en lugar de asistente. El admin puede editar cualquiera |
| `/dashboard/publications/sales` | `pages/dashboard/publications/sales/index.vue` | Menú «Ventas», menú móvil | sesión | `GET /user/sales`, `GET /marcas` | `AppDateTimePicker`, `TablePagination` | |
| `/dashboard/publications/sales/:uuid` | `pages/dashboard/publications/sales/[uuid].vue` | Enlace (ventas) | sesión (vendedor) | `GET /user/sale-details/{uuid}`, `GET /marcas`, `POST /reviews`; chat: `GET`/`POST /purchases/{uuid}/messages` | `components/ChatModal.vue`, `components/RatingModal.vue`, `usePurchaseChat`, `usePolling` | Oculta el menú móvil |
| `/dashboard/publications/offers-received` | `pages/dashboard/publications/offers-received.vue` | Menú «Ofertas Recibidas», aviso | sesión | `GET /offers/received`, `POST /offers/{id}/status`, `GET /marcas` | `AppDateTimePicker`, `TablePagination` | `?offer_id=` abre el detalle y se quita de la URL |
| `/dashboard/inquiries` | `pages/dashboard/inquiries/index.vue` | Menú «Interesados», menú móvil, aviso | sesión | `GET /seller/inquiries`; chat: `GET`/`POST /products/{id}/messages`, `POST /products/{id}/rooms`; `POST /user/sales/manual` | `pages/dashboard/inquiries/components/SellerChatModal.vue`, `RatingModal`, `useProductChat`, `usePolling` | `?room_key=` abre la conversación. Desde el chat el vendedor registra una venta manual. Sustituye a «Preguntas» |
| `/dashboard/user/account` | `pages/dashboard/user/account.vue` | Menú móvil «Perfil», login (perfil incompleto) | sesión (el guard la deja pasar siempre con sesión) | `GET /profile`, `POST /profile` (**multipart**: avatar), `POST /profile/cancel-email-change`, `POST /profile/change-password-request`, `POST /profile/change-password-confirm` | `views/apps/user/view/{UserProfile,ChangePasswordDialog,PasswordRequirements}.vue`, `views/apps/user/types.ts`, `DropZone` | Datos personales + empresa. El avatar se redimensiona en el navegador antes de subirlo. Cambiar email pide la contraseña y queda pendiente hasta confirmar por email |
| `/dashboard/user/profile` | `pages/dashboard/user/profile/index.vue` | Menú de usuario, `StoreMobileNav`, `verify-email`, aviso | sesión | igual que `account` | mismo `UserProfile.vue` | **Duplicado** de `account` (mismo componente). En la migración basta una pantalla |
| `/dashboard/admin/publications` | `pages/dashboard/admin/publications/index.vue` | Menú «Administración › Publicaciones» (admin), menú móvil admin | admin (lo exige el backend) | `GET /admin/publications`, `PUT /admin/publications/{id}`, `DELETE /admin/publications/{id}`, `POST /admin/publications/{id}/contact` | `pages/dashboard/admin/publications/components/ContactPublisherModal.vue`, `TablePagination` | Cambio de estado, borrado y mensaje al anunciante |
| `/dashboard/admin/approvals` | `pages/dashboard/admin/approvals/index.vue` | Menú «Aprobaciones» (admin), menú móvil admin, aviso | admin | `GET /admin/pending-publications`, `POST /admin/approve-publication/{id}`, `POST /admin/reject-publication/{id}` | — | |
| `/dashboard/admin/approvals/preview/:uuid` | `pages/dashboard/admin/approvals/preview/[uuid].vue` | Enlace (aprobaciones) | admin | `GET /publicaciones/uuid/{uuid}`, `POST /admin/approve-publication/{id}`, `POST /admin/reject-publication/{id}`, `POST /admin/update-pending-brand/{id}` | reutiliza `ProductImage`, `ProductDetails`, `PublicacionInfo`, `ProductDocs` | Vista previa de la ficha; permite corregir la marca propuesta. Oculta el menú móvil |

### Redirecciones (`plugins/1.router/additional-routes.ts`)

URLs antiguas que se conservan porque pueden estar en emails, notificaciones o marcadores:

| URL antigua | Va a |
|---|---|
| `/dashboard/chat` | `/dashboard/inquiries` |
| `/dashboard/purchases`, `/dashboard/purchases/list` | `/dashboard/purchases/purchases` |
| `/dashboard/purchases/favorites` | `/dashboard/favorites/list` |
| `/dashboard/purchases/chat/:uuid` | `/dashboard/purchases/:uuid?openChat=true` |
| `/dashboard/user/company` | `/dashboard/user/account` |
| `/dashboard/publications/questions`, `/dashboard/publications/publication/questions/:uuid`, `/dashboard/questions/list` | `/dashboard/inquiries` |
| `/dashboard/purchases/questions` | `/dashboard/purchases/inquiries` |

## 3. Componentes, composables y utilidades que siguen vivos

- **`@core/components`** (auto-registrados): `DropZone`, `I18n`, `Notifications`, `ScrollToTop`, `TablePagination`, `ThemeSwitcher`, `app-form-elements/{AppAutocomplete,AppDateTimePicker,AppSelect,AppTextField,AppTextarea}`.
- **`components/`**: `ChatModal.vue` (chat de compra), `RatingModal.vue`, `ErrorHeader.vue`.
- **`@layouts`**: `VerticalNavLayout`, `VerticalNav`, `VerticalNavGroup`, `VerticalNavLink`, `VerticalNavSectionTitle`, `TransitionExpand`, `VNodeRenderer` + config/stores/utils. Siguen ahí los tipos/enums de navegación horizontal (los usa el config de Vuexy), pero ya no sus estilos.
- **`composables/`**: `useApi`, `usePublicApi`, `usePolling`, `useProductChat`, `usePurchaseChat`, `usePublicationForm`, `useMotorFormatter`, `useCountries`, `useSanitize`, `useToast`.
- **`utils/`**: `urls`, `guest`, `roomKey`, `tipoMapping`, `formatCurrency`, `debounce`, `paginationMeta`.
- **Stores Pinia**: `@core/stores/{user,notification,config}.ts`, `views/store/useStoreFiltersStore.ts`, `@layouts/stores/config.ts`.
- **Dependencias npm de runtime**: vue, vue-router, vuetify, pinia, vue-i18n, @casl/{ability,vue}, @vueuse/{core,math}, swiper, vue-flatpickr-component, vue3-perfect-scrollbar, dompurify, webfontloader, cookie-es, destr, ufo, @floating-ui/dom.

## 4. Eliminado en esta limpieza

**Páginas**: `access-control.vue` (demo CASL), `dashboard/chat.vue` (chat de Vuexy, endpoints inexistentes), `dashboard/user/list/index.vue` y `dashboard/user/view/[id].vue` (usuarios de Vuexy con fake-api), `dashboard/user/company.vue` (duplicado de account), `dashboard/purchases/index.vue` (pestañas con `<NuxtPage>`, rota), `dashboard/purchases/list/index.vue` y `dashboard/purchases/chat/[uuid].vue` (solo redirigían; ahora son redirecciones del router), `dashboard/purchases/favorites.vue` (duplicado de `favorites/list`), y las cuatro de preguntas: `dashboard/publications/questions/index.vue`, `dashboard/publications/publication/questions/[uuid].vue`, `dashboard/questions/list/index.vue`, `dashboard/purchases/questions.vue` (sus rutas REST están desactivadas en el backend → 404).

**Vistas y componentes**: `views/apps/{chat,email,motors}`, `views/apps/user/list`, `views/apps/user/view/{UserBioPanel,UserInvoiceTable,UserTab*}`, `views/pages/authentication/AuthProvider.vue`; `components/dialogs/*` (9 diálogos de Vuexy), `components/{AppLoadingIndicator,AppSearchHeader}.vue`; `@core/components/{AppBarSearch,AppDrawerHeaderSection,AppStepper,BuyNow,CardStatisticsVerticalSimple,CustomizerSection,DialogCloseBtn,MoreBtn,MotorDescriptionEditor,TiptapEditor}.vue`, `app-form-elements/{AppCombobox,CustomCheckboxes*,CustomRadios*}.vue`, `cards/*`; `layouts/components/{NavbarShortcuts,Footer,SidebarNotifications}.vue`; `pages/store/components/{LoginModal,OfferModal,PublicacionQuestions}.vue` (no los importaba nadie).

**Código**: `@core/libs` (chartjs, apexcharts), `@core/composable/useResponsiveSidebar.ts`, `composables/{useAppLoading,usePusherChannel}.ts`, `utils/constants.ts`, `interfaces/question.ts`, `plugins/fake-api/` (MSW) y los alias `@db`, `@api-utils`, `@axios`.

**Dependencias npm**: @formkit/drag-and-drop, @sindresorhus/is, @tiptap/* (todas), apexcharts, vue3-apexcharts, chart.js, vue-chartjs, jwt-decode, mapbox-gl, @types/mapbox-gl, ofetch, prismjs, vue-prism-component, pusher-js, roboto-fontface, shepherd.js, vue-shepherd, unplugin-vue-define-options, @iconify/vue, @intlify/unplugin-vue-i18n, @types/dompurify, @videojs-player/vue, video.js, msw, shiki, @iconify-json/mdi, @iconify-json/fa.

**Estilos e imágenes**: SCSS de navegación horizontal (`@core/scss/{base,template}/*horizontal-nav*`), `@core/scss/template/libs/{apex-chart,shepherd,swiper}.scss`; 359 imágenes de demo de `src/assets/images` (~12 MB) y `public/images`.

**Archivos de la plantilla**: `dev.Dockerfile`, `prod.Dockerfile`, `docker-compose.dev.yml`, `nginx.conf`, `.dockerignore`, `jsconfig.json.bak`, `.eslintrc.js`.

**Efecto**: `app.js` 1.562 kB → ~1.400 kB, `style.css` 683 kB → ~636 kB. `yarn typecheck` pasa sin errores (antes fallaba por los tipos de mapbox) y `yarn lint` vuelve a ejecutarse.

## 5. Bugs de frontend arreglados

1. Los componentes de `pages/**/components` eran rutas: `/store/components/ProductDocs`, `/dashboard/publications/components/StepMedia`, etc. pintaban un componente suelto (comprobado en producción). Excluidos del enrutado.
2. Iconos en blanco en el paso «tipo de publicación» (`mdi-*` que no estaban en el bundle) → iconos Tabler.
3. Imagen rota (`/placeholder.png`, 404) en tarjetas de la tienda, relacionados y avatar del perfil → icono de respaldo.
4. Las cuatro pantallas de preguntas llamaban a rutas REST desactivadas (404) → eliminadas y redirigidas al chat (ver arriba). `purchases/questions` además pedía las preguntas recibidas como vendedor en vez de las hechas como comprador.
5. Redirecciones `pages-user-profile` / `pages-account-settings` apuntaban a rutas inexistentes; el menú de atajos enlazaba a apps de Vuexy.
6. `yarn lint` abortaba (faltaba `eslint-internal-rules/`); `yarn typecheck` fallaba (TS2688, tipos de mapbox).

## 6. Bugs de backend detectados (no tocados)

- **Email → «Preferencias»**: `includes/email-templates/base.php` enlaza a `mi-cuenta/dashboard/notifications`, que no existe en la SPA (cae en el 404). No hay pantalla de preferencias de notificaciones.
- **CSP bloquea Google Analytics**: la cabecera `Content-Security-Policy` de `motorlan-api-vue.php` tiene `*.analytics.google.com` pero no `https://analytics.google.com` ni `https://www.google.com`; en la consola de producción todas las llamadas `g/collect` de GA4 salen bloqueadas.
- **Preguntas**: `questions-routes.php` tiene comentado el registro de rutas, pero `class-motorlan-notification-listener.php` y `email-templates/question_{sent,answered}.php` siguen generando enlaces a `dashboard/publications/questions` y `dashboard/purchases/questions` (ahora redirigen al chat). `/purchases/questions` sigue registrada y nadie la usa (además el CPT `pregunta` ya no se registra).
- **`motorlan_store_spa_routes`** (`includes/vue-app-setup.php`): incluye `access-control` (página eliminada), `ai-search` y `assistant` (no existen en la SPA), y `store` / `public-store` / `not-authorized` (ver notas de la tabla).
- **Rutas REST registradas que la SPA no llama**: `/purchases/questions`, `/purchases/pusher/auth` (Pusher eliminado), `/purchases/favorites/{publicacion_id}` (la SPA usa `DELETE /favorites/{id}`), `/garantias`, `/garantias/publicacion/{uuid}`, `/ai/{status,chat,search}`, `/admin/publications/transfer-author`, y las de migración (`/migration/*`, `/migrate-publicaciones`, `/refresh-slugs`). Las de migración/AI/transfer pueden ser de uso administrativo fuera de la SPA.
- `wpData.login_endpoint` apunta a `wp/v2/custom/login`; la SPA no lo usa (usa `POST /login`).

## 7. Pendiente / a decidir (frontend, no arreglado)

- **Lint**: ~8.000 avisos previos, casi todos de formato (indentación, punto y coma, comillas). No se ha aplicado `--fix` masivo para no reescribir todos los archivos antes de la migración. Entre los no de formato: 11 `target="_blank"` sin `rel="noopener"` (los navegadores actuales ya lo implican) y usos antes de definir dentro de funciones/computed (sin efecto en ejecución, revisados).
- `dashboard/user/account` y `dashboard/user/profile` son la misma pantalla.
- `dashboard/purchases/opinions` y `not-authorized` no tienen entrada; `store/index.vue` solo redirige; `public-store` sin uso confirmado.
- Las claves i18n de las pantallas eliminadas siguen en `locales/*.json`.
- En `app/dist/` hay 29 archivos antiguos versionados en git (imágenes de demo); el workflow de despliegue compila `dist` de cero, así que no afectan, pero sobran en el repositorio.
- Scripts sueltos en `app/` ajenos a la SPA (`split_*.cjs`, `split_csv.js`, `run_*.bat`, `conectarse a composer.txt`): parecen herramientas de importación de datos; no se han tocado.
