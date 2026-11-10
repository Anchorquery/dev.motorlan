# Catálogo de la API REST de Motorlan (`motorlan-api-vue`)

Estado: rama `anchorquery/api-bugs-codigo-muerto-6899bb`, noviembre de 2026. Las rutas y líneas
salen de cargar el plugin con stubs y volcar cada `register_rest_route` con su callback real
(reflexión). Rutas de archivo relativas a `wp-content/plugins/motorlan-api-vue/`.

Total: **74 endpoints** (ruta + método), todos en el namespace `motorlan/v1`.

## Autenticación

- `motorlan_is_user_authenticated()` = `is_user_logged_in()`.
  `motorlan_is_admin_user()` (`includes/api/admin-approval-routes.php:45`) = `current_user_can('administrator')`.
- **Sesión de WordPress (cookie + `X-WP-Nonce`)**: así trabaja la SPA y así trabajará el plugin
  nuevo. En POST/DELETE sin nonce, WordPress trata la petición como anónima (401 en las rutas
  protegidas). Con un nonce caducado responde 403 `rest_cookie_invalid_nonce`, y la SPA lo
  renueva y reintenta (`fetchWithNonceRetry` en `app/src/composables/useApi.ts`). El nonce nuevo
  sale de `admin-ajax.php?action=motorlan_rest_nonce` (`includes/vue-app-setup.php:402-403`).
- **JWT** (`jwt-authentication-for-wp-rest-api`): sigue activo. Fija el usuario en
  `determine_current_user`, así que `Authorization: Bearer …` vale en todas las rutas.
  **Ningún endpoint depende solo de JWT**: todos funcionan con cookie + nonce. `/login` emite la
  cookie de WordPress, no un token JWT.
- **Flujos anónimos POST** (`includes/api/rest-auth.php`): `register`, `login`, `forgot-password`,
  `reset-password`, `verify-email`, `resend-verification` y `profile/confirm-email-change`. Si la
  comprobación de cookie o nonce falla en una de ellas, la petición sigue adelante **como
  anónima** (`wp_set_current_user(0)`). Así no queda bloqueado quien tiene una pestaña abierta
  desde ayer. Las GET no se tocan: una GET con nonce caducado da 403 y la SPA renueva el nonce.
  Antes este filtro no hacía nada (dependía de `rest_is_request()`, que no existe en WP 6.9.4).
- **JSONP desactivado** (`rest_jsonp_enabled` → false). Con `?_jsonp=`, otra web podía leer
  `/session` u otras GET con la cookie de la víctima.
- **JSON obligatorio**: las rutas marcadas con **JSON** exigen `Content-Type: application/json`
  (`motorlan_validate_json_content_type`, `includes/api/motor-helpers.php`) y responden 415 si no.
  El plugin nuevo tiene que mandarlo también en los POST sin cuerpo.

Leyenda: 🔓 = `__return_true` (público). ⚠️ = conviene revisar (ver la nota de la fila).
«SPA: no» = `app/src` no la llama.

## Sesión y cuenta — `includes/api/session-routes.php`

| Ruta | Método | Permiso (qué comprueba) | Callback | Params | Devuelve |
|---|---|---|---|---|---|
| `/session` | GET | 🔓 público, correcto | `motorlan_get_session_data_callback` :370 | — | 200 `{is_logged_in, user:{id,email,display_name,nicename,is_admin}\|null}` |
| `/login` | POST **JSON** | 🔓 flujo anónimo | `motorlan_login_callback` :212 | `username` (o email), `password` (string), `remember` | 200 `{success,message,user,profile}` y emite la cookie; 400 `missing_credentials`; 401 `invalid_credentials`; 403 `email_not_verified`; 429 (5 intentos/15 min por IP) |
| `/logout` | POST | login (**cambiado**: antes 🔓) | `motorlan_logout_callback` :339 | — | 200 `{success,message}`; borra la cookie de auth y las antiguas (`accessToken`, `userData`, `userAbilityRules`) |
| `/register` | POST **JSON** | 🔓 flujo anónimo | `motorlan_register_user_callback` :448 | `username`, `email`, `password`, `first_name`, `last_name`, `name`, `lang` | 200 `{success,code:'registered'}`; 400 (`missing_fields`, `invalid_email`, `email_exists`, `weak_password`…); 429 (3 altas/h y 20 intentos/h por IP) |
| `/forgot-password` | POST **JSON** | 🔓 flujo anónimo | `motorlan_forgot_password_callback` :772 | `email` | 200 `reset_sent` siempre (sin enumeración); 400; 429; 500 |
| `/reset-password` | POST **JSON** | 🔓 flujo anónimo | `motorlan_reset_password_callback` :870 | `key`, `login`, `password` | 200 `password_reset`; 400 (`expired_key`, `invalid_key`, `weak_password`); 429 |
| `/verify-email` | POST **JSON** | 🔓 flujo anónimo | `motorlan_verify_email_callback` :630 | `token` | 200 `verified`/`already_verified`; 400; 429 |
| `/resend-verification` | POST **JSON** | 🔓 flujo anónimo | `motorlan_resend_verification_callback` :691 | `email` o `username`, `token` | 200 `resent`/`resend_neutral`/`already_verified`; 400; 429 |

## Perfil — `includes/api/profile-routes.php`

Todo opera sobre `get_current_user_id()`: no hay IDOR posible.

| Ruta | Método | Permiso | Callback | Params | Devuelve |
|---|---|---|---|---|---|
| `/profile` | GET | login | `get_user_profile_data` :69 | — | 200 `{id, personal_data{nombre,apellidos,email,telefono,avatar}, company_data{…7 campos}, pending_email}` |
| `/profile` | POST | login | `update_user_profile_data` :107 | multipart o JSON: `personal_data` (array o JSON), `company_data` (array o JSON, 7 claves permitidas), `current_password` (string), fichero `avatar` | 200 `{message, avatar, pending_email[, code:'email_change_pending']}`; 400; 429. Cambiar el email exige la contraseña y manda un enlace de confirmación |
| `/profile/change-password-request` | POST **JSON** | login | `motorlan_handle_change_password_request` :472 | `password` (opcional, comprueba la fuerza) | 200 `code_sent` (código de 6 dígitos por email); 400; 429 (3/h) |
| `/profile/change-password-confirm` | POST **JSON** | login | `motorlan_handle_change_password_confirm` :552 | `code`, `password` | 200 `password_changed` (vuelve a emitir la cookie); 400; 429 |
| `/profile/confirm-email-change` | POST **JSON** | 🔓 flujo anónimo (la prueba es el token, guardado como hash) | `motorlan_handle_confirm_email_change` :390 | `token` | 200 `{code:'email_changed', email}`; 400; 429 |
| `/profile/cancel-email-change` | POST | login | `motorlan_handle_cancel_email_change` :464 | — | 200 `email_change_cancelled` |

## Publicaciones — `includes/api/publicaciones/routes.php` (callbacks en `includes/api/publicaciones/callbacks/`)

| Ruta | Método | Permiso (qué comprueba) | Callback | Params | Devuelve |
|---|---|---|---|---|---|
| `/publicaciones` | GET | 🔓; si no eres admin, el builder fuerza `status=publish` (**cambiado**: antes, sin `status`, salían borradores y pendientes) y quita `post_status`, `stock_min`, `precio_de_venta` y `orderby=price` | `motorlan_get_publicaciones_callback` get-publicaciones.php:18 | `page`, `per_page` (1-100), filtros ACF (`marca`, `tipo_o_referencia`, `potencia`/`velocidad`/`par_nominal` en rango `a-b`, `voltaje`, `intensidad`, `pais`, `provincia`, `estado_del_articulo`…), `tipo` (slugs CSV), `search`, `orderby`, `order` | 200 `{data:[publicación], pagination:{total,totalPages,currentPage,perPage}}` + `X-WP-Total`/`X-WP-TotalPages`. SPA: no |
| `/publicaciones` | POST | login; si no eres admin, `publish` pasa a `pending` | `motorlan_create_publicacion_callback` create-publicacion.php:52 | multipart: `title`, `acf` (JSON, campos permitidos), `tipo`, `marca_custom`, `status`, `client_request_id` (idempotencia), `motor_image` (obligatorio), `motor_gallery[]` (≤5), `documentacion_adicional_archivos[]` (≤5) + `documentacion_adicional_nombres` | 201 `{message,id,uuid,status,upload_errors}`; 200 `{duplicate:true}` en un reintento; 400; 409 (alta en curso); 413 |
| `/publicaciones/uuid/{uuid}` | GET | 🔓; en el callback solo el autor o `manage_options` (403 al resto) | `motorlan_get_publicacion_by_uuid` get-publicacion-by-uuid.php:18 | `uuid` | 200 publicación con datos sensibles (email del autor, precio oculto); 403/404 |
| `/publicaciones/uuid/{uuid}` | POST | login; en el callback autor o admin; un no admin recibe 404 si está en la papelera y 403 si está en revisión | `motorlan_update_publicacion_by_uuid` update-publicacion-by-uuid.php:53 | multipart: `title`, `status` (publish/draft/pending/paused/sold), `acf` (JSON), `tipo`, `marca_custom`, `motor_image` o `motor_image_id`, `motor_gallery[]` + `motor_gallery_ids` (CSV **o array**), `documentacion_adicional_*` (JSON **o array**) | 200 `{message,slug,status,upload_errors}`; 400 `not_approved` (un no admin pasando a `paused`/`sold` una publicación nunca aprobada; **nuevo**); 403/404/413 |
| `/publicaciones/{slug}` | GET | 🔓; el callback solo enseña `publish`/`paused`/`sold` con `publicar_acf` distinto de draft/pending; el resto, solo al autor o al admin | `motorlan_get_publicacion_by_slug` get-publicacion-by-slug.php:18 | `slug` (también busca en `_wp_old_slug`) | 200 `{data: publicación + reputación del autor}`; 404 |
| `/publicaciones/{id}` | DELETE | login; en el callback autor o admin; un no admin no puede si está en revisión, en la papelera o tiene compras | `motorlan_delete_publicacion` delete-publicacion.php:18 | `id` | 200 (a la papelera); 403/404/409/500 |
| `/publicaciones/duplicate/{id}` | POST **JSON** | login; autor o admin | `motorlan_duplicate_publicacion` duplicate-publicacion.php:18 | `id`, sin cuerpo | 200 `{message,new_post_id,uuid}` (borrador del usuario actual) |
| `/publicaciones/{id}/status` | POST **JSON** | login; autor o admin | `motorlan_update_publicacion_status` update-publicacion-status.php:18 | `status` ∈ publish/pending/draft/paused/sold | 200 `{message,new_status}`; 400 `not_approved` (mismo control que arriba, **nuevo**); 403/404/415 |
| `/publicaciones/bulk-delete` | POST **JSON** | login; cada id debe ser una `publicacion` tuya (o eres admin); las demás se saltan | `motorlan_bulk_delete_publicaciones` bulk-delete-publicaciones.php:18 | `ids` (array; **ahora** se pasan por absint, sin duplicados, máximo 100) | 200 `{message,deleted,skipped_purchases}`; 409; 500 |
| `/store/publicaciones` | GET | 🔓 tienda pública: fuerza `publish` y quita `author`, `post_status`, `stock_min` y (**nuevo**) `orderby=price` | `motorlan_get_public_publicaciones_callback` get-public-publicaciones.php:19 | los mismos filtros que `/publicaciones` | 200 igual en modo listado + `X-Motorlan-Cache: HIT/MISS` (caché en transient) |
| `/tipos` | GET | 🔓 catálogo | `motorlan_get_tipos_callback` get-tipos.php:17 | — | 200 array de `WP_Term` en crudo (`hide_empty=false`) |
| `/marcas` | GET | 🔓 catálogo | `motorlan_get_marcas_callback` get-marcas.php:17 | — | 200 array de `WP_Term` en crudo (también las marcas vacías) |
| `/countries` | GET | 🔓 catálogo | `motorlan_get_countries_callback` get-countries.php:224 | `lang` (string; un array ya no da TypeError) | 200 `[{value, title}]` |
| `/favorites` | GET | login | `motorlan_get_user_favorites` get-user-favorites.php:18 | `fields=ids` (opcional) | 200 `{data:[{id}]}` o `{data:[publicación]}` |
| `/favorites` | POST **JSON** | login; solo publicaciones `publish` o propias | `motorlan_add_user_favorite` add-user-favorite.php:18 | `publicacion_id` | 201 `{message}`; 400/415 |
| `/favorites/{id}` | DELETE | login; solo toca la meta del usuario actual | `motorlan_remove_user_favorite` remove-user-favorite.php:18 | `id` | 200 |

**Solapes de regex**: `/publicaciones/{slug}` (solo GET) también casa con `123` o con `bulk-delete`.
WordPress descarta los handlers que no sirven el método y sigue buscando, así que DELETE y POST
llegan a su ruta. Un `GET /publicaciones/123` busca un slug `123` y da 404.

## Mis publicaciones — `includes/api/my-publications-routes.php`

| Ruta | Método | Permiso | Callback | Params | Devuelve |
|---|---|---|---|---|---|
| `/my-publications` | GET | login; siempre `author = usuario actual` | `motorlan_get_my_publications_callback` :22 | `page`, `per_page` (1-100), los filtros ACF de `/publicaciones`, `status` (→ `publicar_acf`), `tipo` (CSV o array), `search`, `orderby` (lista blanca), `order` (string) | 200 `{data, pagination}` + `X-WP-Total`/`X-WP-TotalPages` |

## Compras — `includes/api/purchases-routes.php`

Acceso a una compra (`motorlan_user_can_access_purchase`): admin (`manage_options`), comprador o
vendedor.

| Ruta | Método | Permiso | Callback | Params | Devuelve |
|---|---|---|---|---|---|
| `/purchases/purchases` | GET | login; solo las compras del usuario como comprador | `motorlan_get_my_purchases_callback` :174 | `page`, `per_page` (1-100), `search`, `order`, `orderby` | 200 `{data:[{uuid,title,fecha_compra,publicacion,vendedor,comprador,estado,tipo_venta,offer_id,review_done}], pagination}` |
| `/purchases/opinions` | GET | login; valoraciones hechas por el usuario | `motorlan_get_my_opinions_callback` :298 | `page`, `per_page` (1-100), `search`, `order`, `orderby` | 200 `{data:[{id,purchase_uuid,title,valoracion,comentario,target_role,fecha,publicacion}], pagination}` |
| `/purchases/favorites` | GET | login | `motorlan_get_my_favorites_callback` :406 | `page`, `per_page` (1-100), `search`, `status`, `orderby`, `order` | 200 `{data:[publicación], pagination}` |
| `/purchases/{uuid}` | GET | login; 403 si no eres comprador, vendedor ni admin | `motorlan_get_purchase_callback` :501 | `uuid` | 200 `{data:{…, viewer_role, precio_compra (null para el comprador si es precio de referencia), offer, review_done}}`; 403/404 |

### Chat de compra — `includes/api/purchases/controllers/class-purchase-chat-controller.php`

| Ruta | Método | Permiso | Callback | Params | Devuelve |
|---|---|---|---|---|---|
| `/purchases/{uuid}/messages` | GET | login; en el callback participante o admin | `Motorlan_Purchase_Chat_Controller::get_messages` :369 | `since_timestamp` (string con fecha; **ahora** un array da 400, no TypeError) | 200 `{data:[mensaje], meta:{purchase_uuid, participants, server_timestamp, viewer_role}}`; 403/404; 429 (200/min) |
| `/purchases/{uuid}/messages` | POST **JSON** | login; participante | `::create_message` :450 | `message` (1-1000 caracteres; **ya no** se le quitan las barras invertidas) | 201 `{data:mensaje, meta}`; 400/403/404/415; 429 (20/min) |

## Ofertas — `includes/api/offers-routes.php`

Tabla `{prefix}motorlan_offers`. Estados: `pending`, `rejected`, `accepted` (antiguo, se
convierte en `confirmed`), `accepted_pending_confirmation`, `confirmed`, `expired`. Ojo: los GET
también **escriben** (`motorlan_offers_refresh_offer` normaliza estados y caduca ofertas).

| Ruta | Método | Permiso (qué comprueba) | Callback | Params | Devuelve |
|---|---|---|---|---|---|
| `/publicaciones/{id}/offers` | POST **JSON** | `is_user_logged_in()`; en el callback: no es tuya, está en `publish` (**nuevo**: antes valían borradores y privadas) y tiene stock | `motorlan_handle_create_offer` :282 | `amount` (>0), `justification` | 200 `{success,message,remaining_offers,data}`; 400 (`own_publication`, `publication_unavailable`, `no_stock`); 404; 429 (10 ofertas/día; el contador **ahora** usa la fecha local y aguanta metas corruptas) |
| `/offers/received` | GET | login; ofertas a tus publicaciones | `motorlan_handle_get_received_offers` :371 | `page` (≥1), `per_page` (1-100; ≤0 vale 100, **antes** `per_page=0` daba DivisionByZero), `search`, `status`, `date_from`, `date_to`, `orderby`, `order` | 200 `{data:[oferta + publication_title + user_name], pagination}` |
| `/offers/sent` | GET | login; tus ofertas | `motorlan_handle_get_sent_offers` :723 | igual que `received` | 200 igual |
| `/offers/{id}/status` | POST **JSON** | login; en el callback, el autor de la publicación | `motorlan_handle_update_offer_status` :479 | `status` ∈ accepted/rejected | 200; 400 (`no_stock`, `reserved_stock`); 403/404. Aceptar deja 24 h para confirmar |
| `/offers/{id}/confirm` | POST **JSON** | login; en el callback, quien hizo la oferta | `motorlan_handle_confirm_offer` :576 | — | 200 `{success,data,purchase_uuid,purchase_id,stock_remaining}`; 400 `already_confirmed`/`expired_offer`/…; 409 (estado cambiado a mitad); 403/404/500. **Ahora** relee el estado con `FOR UPDATE` dentro de la transacción (antes, dos confirmaciones a la vez creaban dos compras) y dispara `motorlan_new_purchase` (antes el vendedor no recibía aviso) |
| `/offers/{id}` | DELETE | login; `user_id` = tú, en el SQL | `motorlan_handle_withdraw_offer` :827 | — | 200; 400 (solo pending/expired); 404 |

## Ventas — `includes/api/sales-routes.php`

| Ruta | Método | Permiso | Callback | Params | Devuelve |
|---|---|---|---|---|---|
| `/user/sales` | GET | login; solo las compras donde eres el vendedor | `motorlan_get_user_sales_callback` :245 | `per_page` (1-100), `page`, `search`, `status` (vacío = `completed`; `all`), `type`, `order`, `orderby`, `date_from`, `date_to` | 200 `{data:[venta + publicación], pagination}` (usuarios y posts precargados, **nuevo**) |
| `/user/sale-details/{uuid}` | GET | login; 403 si no eres el vendedor | `motorlan_get_user_sale_by_uuid_callback` :425 | `uuid` | 200 `{data: venta + notes, payment_type, payment_meta, offer}` |
| `/user/sales/manual` | POST **JSON** | login; autor de la publicación, y el comprador tiene que haber escrito en la sala | `motorlan_handle_create_manual_sale` :521 | `product_id`, `room_key`, `amount` | 200 `{success,data:{uuid,id},stock}`; 400/403/404 |

## Valoraciones — `includes/api/reviews/controllers/class-motorlan-reviews-controller.php`

| Ruta | Método | Permiso | Callback | Params | Devuelve |
|---|---|---|---|---|---|
| `/reviews` | POST | `is_user_logged_in`; para `seller` tienes que ser el comprador y para `buyer` el vendedor | `Motorlan_Reviews_Controller::create_review` :42 | `purchase_uuid`, `rating` (1-5), `comment` (≤1000), `target_role` | 201; 400/403/404; 409 si ya valoraste |

## Notificaciones — `includes/api/notifications-routes.php`

Todas filtran por `user_id = usuario actual` en `Motorlan_Notification_Manager`: no hay IDOR.

| Ruta | Método | Permiso | Callback | Params | Devuelve |
|---|---|---|---|---|---|
| `/notifications` | GET | login | `motorlan_get_notifications` :91 | `page`, `per_page` (1-50), `status` (all/read/unread) | 200 `[{id,title,subtitle,time,created_at,isSeen,icon,color,data}]` (sin datos de paginación) |
| `/notifications/unread-count` | GET | login | `motorlan_get_notifications_unread_count` :115 | — | 200 `{count}` |
| `/notifications/read` | POST/PUT/PATCH **JSON** | login | `motorlan_mark_notifications_as_read` :131 | `notification_ids` (1-100, **antes** sin tope) | 200 `{success:true}` |
| `/notifications/unread` | POST/PUT/PATCH **JSON** | login | `motorlan_mark_notifications_as_unread` :159 | `notification_ids` (1-100) | 200 `{success:true}` |
| `/notifications/{id}` | DELETE | login | `motorlan_delete_notification` :181 | `id` | 200 `{success:true}` (también si no es tuya: borra 0 filas) |

## Chat de producto (consultas) — `includes/api/products/controllers/class-product-chat-controller.php`

Sala `pub-{productId}-viewer-{viewerId}`. Si `viewerId` es numérico, es un usuario de WP (exige
login). Si no, es un invitado: id aleatorio guardado en el localStorage del navegador. El acceso
real se decide en el callback (`user_can_access_room`), de tres maneras:

1. El vendedor accede a todas las salas de su producto.
2. Un usuario accede a la sala de su propio id.
3. Un invitado necesita el `room_token`, un HMAC de la clave de sala que se devuelve en el primer
   POST o en el enlace del email. La excepción es una sala todavía vacía.

| Ruta | Método | Permiso (qué comprueba) | Callback | Params | Devuelve |
|---|---|---|---|---|---|
| `/products/{id}/messages` | GET | 🔓 intencionado (chat de invitados); el acceso se comprueba en el callback | `Motorlan_Product_Chat_Controller::get_messages` :610 | `room_key`, `since_timestamp`, `room_token` | 200 `{data:[mensaje], meta:{current_user_id,viewer_role,server_timestamp}}`; 400/403/404; 429 |
| `/products/{id}/messages` | POST **JSON** | 🔓 intencionado; el acceso se comprueba en el callback. **Nuevo**: el vendedor no puede abrir una sala en la que todavía no ha escrito nadie más (403) | `::create_message` :676 | `message` (≤2000), `room_key`, `viewer_name`, `guest_email` (sin verificar), `room_token`, `website` (honeypot) | 201 `{data, meta:{…, room_token?}}`; 400/403/404; 429 (20/min; salas nuevas de invitado: 30/h por producto y 30/h por IP). ⚠️ quien conozca la clave de una sala vacía puede ocuparla con el primer mensaje (riesgo bajo) |
| `/buyer/claim-guest-chats` | POST | login | `::claim_guest_chats` :295 | — | 200 `{claimed}`. Te asigna las salas de invitado con tu email (solo si el email está verificado o eres un usuario antiguo) |
| `/products/{id}/rooms` | POST **JSON** | login; acceso a la sala | `::mark_room_read` :1105 | `room_key` | 200 `{success:true}`; 400/403. (El GET de esta ruta se ha eliminado) |
| `/seller/inquiries` | GET | login | `::get_seller_inquiries` :928 | — (sin paginación) | 200 `{data:[{product_id,product_title,product_slug,product_link,product_image,room_key,last_at,user_name,unread}]}` |
| `/buyer/inquiries` | GET | login | `::get_buyer_inquiries` :1130 | — (sin paginación) | 200 `{data:[{…, user_name (vendedor), user_avatar, seller_id, unread}]}` |
| `/chat-tokens/{token}` | GET | 🔓 intencionado: enlace del email, firmado con HMAC (`wp_salt('auth')`) y válido 7 días | `::exchange_chat_token` :191 | `token` | 200 `{room_key, guest_email, product_id, room_token}`; 410 |

## Administración — `includes/api/admin-approval-routes.php`, `includes/api/admin-publications-routes.php`

Todas con `motorlan_is_admin_user` (rol administrator).

| Ruta | Método | Callback | Params | Devuelve |
|---|---|---|---|---|
| `/admin/pending-publications` | GET | `motorlan_get_pending_publications_callback` admin-approval-routes.php:52 | — (sin paginación) | 200 `[publicación + author_info + pending_brand]` |
| `/admin/approve-publication/{id}` | POST **JSON** | `motorlan_approve_publication_callback` admin-approval-routes.php:105 | `brand_name` (opcional) | 200 `{message}`; 404; 500. Publica (**ahora** saca de la papelera si hace falta), crea o asigna la marca, regenera el slug y avisa |
| `/admin/reject-publication/{id}` | POST **JSON** | `motorlan_reject_publication_callback` admin-approval-routes.php:247 | `reason` | 200; 404. Pasa a borrador y avisa |
| `/admin/update-pending-brand/{id}` | POST **JSON** | `motorlan_update_pending_brand_callback` admin-approval-routes.php:210 | `brand_name` | 200 `{message, brand_name}`; 400/404 |
| `/admin/publications` | GET | `motorlan_admin_get_publications_callback` admin-publications-routes.php:56 | `page`, `per_page` (1-100), `search`, `status`, `orderby`, `order`, `exclude_author` | 200 `{data, total, pages}`. ⚠️ genera el `uuid` si falta (escribe en un GET) |
| `/admin/publications/{id}` | DELETE | `motorlan_admin_delete_publication_callback` admin-publications-routes.php:233 | `force=true` para borrar del todo | 200; 404 |
| `/admin/publications/{id}` | POST/PUT/PATCH **JSON** | `motorlan_admin_update_publication_callback` admin-publications-routes.php:254 | `status` ∈ publish/pending/draft/trash/paused/sold | 200; 400/404 |
| `/admin/publications/{id}/contact` | POST **JSON** | `motorlan_admin_contact_publisher_callback` admin-publications-routes.php:354 | `message`, `subject` | 200; 400/404/500. Manda un `wp_mail` y una notificación web (el canal de email de la notificación se ha quitado: no había plantilla) |
| `/admin/publications/transfer-author` | POST **JSON** | `motorlan_admin_transfer_publications_callback` admin-publications-routes.php:402 | `source_email`, `target_email`, `dry_run` | 200; 400/404/500. La usa la página de wp-admin «Transferir motores», no la SPA |

## Migraciones y mantenimiento (solo `manage_options`; SPA: no)

| Ruta | Método | Callback | Notas |
|---|---|---|---|
| `/migrate-publicaciones` | POST | migrate-publicaciones.php:24 | Migración puntual desde el CPT antiguo `publicaciones` (ya no se registra). `dry_run` vale true por defecto |
| `/migration/sync-tipos-from-categories` | POST | migrate-tipos-from-categories.php:21 | La usa `assets/js/admin-tipo-migration.js` |
| `/refresh-slugs` | **GET** | refresh-slugs.php:23 | ⚠️ un GET que escribe con `dry_run=0` |
| `/migration/upload` | POST | `includes/admin/admin-migration.php:369` | Importación CSV. La página que la usaba no está en ningún menú |
| `/migration/chunk` | POST | admin-migration.php:384 | idem |
| `/migration/chunk-featured` | POST | admin-migration.php:1928 | idem |

## Fuera de la API REST

- `admin-ajax.php?action=motorlan_rest_nonce` (con y sin sesión): devuelve un nonce `wp_rest` nuevo
  (`includes/vue-app-setup.php:402`).
- `wp_ajax_motorlan_import_csv_upload` y `wp_ajax_motorlan_import_csv_chunk`
  (`includes/admin/admin-migration.php:336/361`), con `manage_options` y nonce.

## Rutas eliminadas en esta rama

Ninguna tenía un consumidor que funcionara: las de preguntas ya daban 404 antes de esta rama.

| Ruta | Motivo |
|---|---|
| `POST /garantias`, `GET /garantias/publicacion/{uuid}` | IDOR: se podía crear una garantía sobre cualquier post. El CPT no está registrado y la SPA no la usaba. Se mantienen el CPT y el grupo ACF |
| `GET /ai/status`, `POST /ai/chat`, `POST /ai/search` | Públicas, sin límite de peticiones y sin consumidor |
| `GET /purchases/questions` | El CPT `pregunta` ya no existe |
| `POST /purchases/pusher/auth` | Pusher eliminado |
| `DELETE /purchases/favorites/{id}` | Duplicada (la SPA usa `DELETE /favorites/{id}`) |
| `GET /products/{id}/rooms` | Sin consumidor (el POST sigue) |
| `/publicaciones/{id}/questions`, `/questions/{id}/answer`, `/user/questions`, `/user/publications-list` | Ya estaban comentadas. Se borra el archivo. ⚠️ Las páginas de preguntas del panel (`app/src/pages/dashboard/publications/questions/`, `…/publication/questions/[uuid].vue`, `dashboard/purchases/questions.vue`) aún las llaman y dan 404, igual que antes |
| `GET /publicacion-categories` | Ya estaba comentada. Se borra el callback |
