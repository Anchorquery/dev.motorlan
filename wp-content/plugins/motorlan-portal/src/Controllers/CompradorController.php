<?php

namespace MotorlanPortal\Controllers;

use MotorlanPortal\Support\Api;
use MotorlanPortal\Support\Chat;
use MotorlanPortal\Support\Nav;
use MotorlanPortal\Support\Publicacion;
use MotorlanPortal\Support\Seo;
use MotorlanPortal\Support\SpaPages;
use MotorlanPortal\Support\Store;
use MotorlanPortal\Support\Urls;
use MotorlanPortal\Support\View;

/**
 * Sección `comprador`: pantallas del comprador del panel (app/src/pages/dashboard/purchases/).
 *
 *  - purchases/purchases   Mis compras (purchases.vue): tabla, en móvil tarjetas; valorar.
 *  - purchases/{uuid}      Detalle de una compra ([uuid].vue). Lo abren comprador y vendedor
 *                          (la API decide el acceso: 403/404); chat de la compra
 *                          (?openChat=true lo abre) y valorar a la otra parte.
 *  - purchases/inquiries   Consultas enviadas (inquiries.vue): reclama los chats de invitado,
 *                          bandeja con no leídos y chat de producto (?room_key= lo abre).
 *  - purchases/offers-sent Ofertas enviadas (offers-sent.vue): detalle, confirmar (crea la
 *                          compra y lleva a su detalle) y retirar.
 *  - purchases/opinions    Mis valoraciones (opinions.vue, huérfana en la SPA): enlazada
 *                          desde Mis compras y el menú (Gestión de Compras).
 *  - URLs antiguas (purchases, purchases/list, purchases/chat/{uuid}, purchases/questions):
 *    las mismas redirecciones que la sección `panel` (PanelController::LEGACY), para que
 *    funcionen aunque esa sección esté apagada.
 *
 * Formularios: POST a la propia URL (Controller::valid_nonce). Con HTMX se responde el
 * parcial de la tabla; sin JS, la página (o 303 a la propia URL).
 */
class CompradorController extends Controller
{
    const PER_PAGE = [5, 10, 20, 25, 50];
    /** UUID de una compra (wp_generate_uuid4, motor-helpers.php). */
    const UUID = '[0-9A-Fa-f]{8}(?:-[0-9A-Fa-f]{4}){3}-[0-9A-Fa-f]{12}';
    /** Rutas antiguas del comprador (las redirige PanelController::handle). */
    const LEGACY = ['purchases', 'purchases/list', 'purchases/chat/[A-Za-z0-9-]+', 'purchases/questions'];
    /** Estados en los que no se valora (como canRate de [uuid].vue; la API también lo rechaza). */
    const NOT_RATEABLE = ['cancelled', 'canceled', 'cancelado', 'refunded', 'rejected'];

    /** spa_paths: pantallas del comprador + rutas antiguas, bajo /mi-cuenta/dashboard/. */
    public static function patterns(): array
    {
        return SpaPages::panel_patterns(array_merge(
            ['purchases/purchases', 'purchases/inquiries', 'purchases/offers-sent', 'purchases/opinions', 'purchases/' . self::UUID],
            self::LEGACY
        ));
    }

    /** Solo con la sección encendida: «Mis valoraciones» en el menú, tras Favoritos. */
    public static function boot(): void
    {
        add_filter('motorlan_portal_nav_vertical', [self::class, 'nav_vertical']);
    }

    public static function nav_vertical($items): array
    {
        $items = (array) $items;
        $item = Nav::item(__('Mis valoraciones', 'motorlan-portal'), 'star', 'dashboard/purchases/opinions');
        $pos = count($items);
        foreach ($items as $i => $it) {
            if (isset($it['match']) && in_array('dashboard/favorites/list', (array) $it['match'], true)) {
                $pos = $i + 1;
                break;
            }
        }
        array_splice($items, $pos, 0, [$item]);
        return $items;
    }

    public function handle(string $param): void
    {
        $route = trim($param, '/');
        if (preg_match('#^purchases/(?<uuid>' . self::UUID . ')$#', $route, $m)) {
            $this->detail(strtolower($m['uuid']));
            return;
        }
        switch ($route) {
            case 'purchases/purchases':
                $this->purchases();
                return;
            case 'purchases/inquiries':
                $this->inquiries();
                return;
            case 'purchases/offers-sent':
                $this->offers();
                return;
            case 'purchases/opinions':
                $this->opinions();
                return;
        }
        // URLs antiguas: mismas redirecciones que la sección `panel` (404 si no casa).
        (new PanelController())->handle($route);
    }

    // ── Mis compras ─────────────────────────────────────────────────────────────────────

    protected function purchases(): void
    {
        $url = Urls::spa('dashboard/purchases/purchases');
        if ($this->is_post()) {
            $headers = $this->review_from_post('seller');
            if (!$this->is_htmx()) {
                $this->redirect($url, 303);
            }
            // El diálogo no se reemplaza: el resultado va en HX-Trigger (ml:review) y la
            // tabla se recarga desde el navegador con sus filtros.
            View::html_response('', 200, $headers + ['HX-Reswap' => 'none']);
        }

        $orders = [
            'recientes'   => ['label' => __('Más recientes', 'motorlan-portal'), 'orderby' => 'fecha_compra', 'order' => 'desc'],
            'antiguas'    => ['label' => __('Más antiguas', 'motorlan-portal'), 'orderby' => 'fecha_compra', 'order' => 'asc'],
            'publicacion' => ['label' => __('Publicación (A-Z)', 'motorlan-portal'), 'orderby' => 'publicacion', 'order' => 'asc'],
            'estado'      => ['label' => __('Estado', 'motorlan-portal'), 'orderby' => 'estado', 'order' => 'asc'],
        ];
        $f = $this->list_filters(['orden' => array_keys($orders)]);
        $o = $orders[$f['orden'] ?: 'recientes'];
        $data = Api::get(Api::route('purchases/purchases'), array_filter([
            'search'   => $f['q'],
            'orderby'  => $o['orderby'],
            'order'    => $o['order'],
            'per_page' => $f['per_page'],
            'page'     => $f['page'],
        ]));
        $rows = [];
        foreach ((array) ($data['data'] ?? []) as $c) {
            if (is_array($c) && !empty($c['uuid'])) {
                $rows[] = $this->purchase_row($c);
            }
        }
        $pagination = (array) ($data['pagination'] ?? []);

        $order_options = [];
        foreach ($orders as $k => $v) {
            $order_options[$k === 'recientes' ? '' : $k] = $v['label'];
        }
        $context = [
            'page_title' => __('Mis compras', 'motorlan-portal'),
            'table'      => $this->table_context($url, $f, $rows, $data === null, $pagination) + ['orders' => $order_options],
            'review'     => $this->review_context($url),
            'opinions_url' => Urls::spa('dashboard/purchases/opinions'),
        ];
        $this->respond('pages/comprador/compras.twig', 'partials/comprador/compras-tabla.twig', $context);
    }

    protected function purchase_row(array $c): array
    {
        $pub = is_array($c['publicacion'] ?? null) ? $c['publicacion'] : [];
        $acf = is_array($pub['acf'] ?? null) ? $pub['acf'] : [];
        $uuid = (string) $c['uuid'];
        $name = self::decode((string) ($pub['title'] ?? '')) ?: self::decode((string) ($c['title'] ?? ''));
        $estado = strtolower((string) ($c['estado'] ?? ''));
        return [
            'uuid'        => $uuid,
            'name'        => $name,
            'reference'   => is_scalar($acf['tipo_o_referencia'] ?? null) ? (string) $acf['tipo_o_referencia'] : '',
            'image'       => Store::image_url($pub['imagen_destacada'] ?? null, 'thumbnail'),
            'product_url' => !empty($pub['slug']) ? SpaPages::product_url((string) $pub['slug']) : '',
            'date'        => (string) ($c['fecha_compra'] ?? ''),
            'type'        => self::purchase_type($c),
            'status'      => self::purchase_status($estado),
            'detail_url'  => Urls::spa('dashboard/purchases/' . $uuid),
            // La SPA ofrecía valorar también compras canceladas (la API lo rechazaba).
            'can_rate'    => empty($c['review_done']) && !in_array($estado, self::NOT_RATEABLE, true),
            // La SPA ponía «Vendedor» en el diálogo: aquí, su nombre.
            'seller'      => self::person_name(is_array($pub['author'] ?? null) ? $pub['author'] : (is_array($c['vendedor'] ?? null) ? $c['vendedor'] : [])),
        ];
    }

    /** Tipo de compra (resolvePurchaseType de purchases.vue). */
    public static function purchase_type(array $c): array
    {
        $raw = strtolower((string) ($c['tipo_venta'] ?? ''));
        if (!empty($c['offer_id'])) {
            return ['label' => __('Oferta', 'motorlan-portal'), 'color' => 'info'];
        }
        if (in_array($raw, ['rent', 'alquiler', 'rental'], true)) {
            return ['label' => __('Alquiler', 'motorlan-portal'), 'color' => 'primary'];
        }
        return ['label' => __('Directa', 'motorlan-portal'), 'color' => 'success'];
    }

    /** Estado de la compra en la tabla (resolveStatus de purchases.vue). */
    public static function purchase_status(string $estado): array
    {
        switch ($estado) {
            case 'pending':
            case 'pendiente':
                return ['label' => __('Pendiente', 'motorlan-portal'), 'color' => 'warning'];
            case 'completed':
            case 'completado':
                return ['label' => __('Completado', 'motorlan-portal'), 'color' => 'success'];
            case 'cancelled':
            case 'canceled':
            case 'cancelado':
                return ['label' => __('Cancelado', 'motorlan-portal'), 'color' => 'error'];
            case 'rejected':
                return ['label' => __('Rechazado', 'motorlan-portal'), 'color' => 'error'];
        }
        return ['label' => $estado !== '' ? self::humanize($estado) : __('Desconocido', 'motorlan-portal'), 'color' => 'info'];
    }

    // ── Valorar (RatingModal) ───────────────────────────────────────────────────────────

    protected function review_context(string $url): array
    {
        return ['url' => $url, 'nonce' => wp_create_nonce(self::PANEL_NONCE)];
    }

    /**
     * POST action=review → POST /reviews. $target por defecto ('seller' en Mis compras);
     * en el detalle lo decide el rol de quien mira. Devuelve las cabeceras: aviso +
     * evento ml:review {ok, message} para el diálogo.
     */
    protected function review_from_post(string $target): array
    {
        $fail = function (string $msg) {
            return ['HX-Trigger' => (string) wp_json_encode(['ml:review' => ['ok' => false, 'message' => $msg]])];
        };
        if (!$this->valid_nonce() || $this->field('action') !== 'review') {
            return $fail(__('La página ha caducado. Recárgala e inténtalo de nuevo.', 'motorlan-portal'));
        }
        $uuid = strtolower($this->field('uuid'));
        $rating = (int) $this->field('rating');
        $comment = trim(sanitize_textarea_field(isset($_POST['comment']) && is_string($_POST['comment']) ? wp_unslash($_POST['comment']) : ''));
        if (!preg_match('#^' . self::UUID . '$#', $uuid)) {
            return $fail(__('No encontramos la compra.', 'motorlan-portal'));
        }
        if ($rating < 1 || $rating > 5) {
            return $fail(__('Elige de 1 a 5 estrellas.', 'motorlan-portal'));
        }
        if ($comment === '') {
            return $fail(__('El comentario es obligatorio.', 'motorlan-portal'));
        }
        if (mb_strlen($comment) > 1000) {
            return $fail(__('Máximo 1000 caracteres.', 'motorlan-portal'));
        }
        $r = Api::call('POST', Api::route('reviews'), [], [
            'purchase_uuid' => $uuid,
            'rating'        => $rating,
            'comment'       => $comment,
            'target_role'   => $target === 'buyer' ? 'buyer' : 'seller',
        ]);
        if (!$r['ok']) {
            $msg = (string) ($r['error']['message'] ?? '');
            return $fail($msg !== '' ? $msg : __('Error al enviar la valoración', 'motorlan-portal'));
        }
        return ['HX-Trigger' => (string) wp_json_encode([
            'ml:review' => ['ok' => true, 'uuid' => $uuid],
            'ml:toast'  => ['text' => __('¡Gracias por tu valoración!', 'motorlan-portal'), 'color' => 'success'],
        ])];
    }

    // ── Detalle de compra ───────────────────────────────────────────────────────────────

    protected function detail(string $uuid): void
    {
        $r = Api::call('GET', Api::route('purchases/' . $uuid));
        $c = $r['ok'] && is_array($r['data']['data'] ?? null) ? $r['data']['data'] : null;
        $role = $c && ($c['viewer_role'] ?? '') === 'seller' ? 'seller' : 'buyer';
        $url = Urls::spa('dashboard/purchases/' . $uuid);

        // Como en [uuid].vue, solo el comprador valora desde aquí (al vendedor).
        if ($this->is_post() && $c && $role === 'buyer') {
            $headers = $this->review_from_post('seller');
            if (!$this->is_htmx()) {
                $this->redirect($url, 303);
            }
            View::html_response('', 200, $headers + ['HX-Reswap' => 'none']);
        }

        if (!$c) {
            status_header($r['status'] === 403 ? 403 : 404);
            Seo::private_page(__('Compra no encontrada', 'motorlan-portal'));
            $this->render('pages/comprador/compra.twig', [
                'page_title' => __('Compra no encontrada', 'motorlan-portal'),
                'error'      => $r['status'] === 403
                    ? __('No tienes permisos para ver esta compra.', 'motorlan-portal')
                    : __('No encontramos la compra', 'motorlan-portal'),
            ]);
            return;
        }

        $view = $this->purchase_view($c, $role);
        $view['chat'] = Chat::purchase($uuid, [
            'title'     => $view['product']['name'],
            'image'     => $view['product']['thumb'],
            'otherName' => $view['party']['name'],
            'autoOpen'  => $this->query('openChat') === 'true',
        ]);
        $view['review'] = $this->review_context($url);
        Seo::private_page($view['product']['name']);
        $this->render('pages/comprador/compra.twig', $view + ['page_title' => $view['product']['name']]);
    }

    /** Todo lo que pinta el detalle a partir de GET /purchases/{uuid}. */
    protected function purchase_view(array $c, string $role): array
    {
        $pub = is_array($c['publicacion'] ?? null) ? self::with_brand($c['publicacion']) : [];
        $acf = is_array($pub['acf'] ?? null) ? $pub['acf'] : [];
        $estado = strtolower((string) ($c['estado'] ?? ''));

        // Producto
        $name = $pub ? Publicacion::name($pub) : '';
        if ($name === '') {
            $name = mb_strtoupper(self::decode((string) ($c['title'] ?? ''))) ?: __('PRODUCTO', 'motorlan-portal');
        }
        $country = is_scalar($acf['pais'] ?? null) ? Store::country_name((string) $acf['pais']) : '';
        $province = is_scalar($acf['provincia'] ?? null) ? (string) $acf['provincia'] : '';
        $types = [];
        foreach ((array) ($pub['tipo'] ?? []) as $t) {
            if (is_array($t) && !empty($t['name'])) {
                $types[] = self::decode((string) $t['name']);
            }
        }

        // Precios (misma lógica que [uuid].vue)
        $qty = max(1, (int) ($c['cantidad'] ?? 1));
        $offer = is_array($c['offer'] ?? null) ? $c['offer'] : null;
        $offer_amount = $offer ? self::num($offer['offer_amount'] ?? null) : null;
        $published = self::num($c['precio_publicado'] ?? null);
        if ($published === null) {
            $published = self::num($acf['precio_de_venta'] ?? null);
        }
        $total = self::num($c['precio_compra'] ?? null);
        if ($total === null) {
            $total = $offer_amount !== null ? $offer_amount : ($published !== null ? $published * $qty : null);
        }
        $unit = $total !== null ? $total / $qty : $published;
        $consult = __('Consultar precio', 'motorlan-portal');
        $notes = [];
        if ($qty > 1) {
            $notes[] = sprintf(__('%d ud.', 'motorlan-portal'), $qty) . ($unit !== null ? ' · ' . self::money($unit) . ' ' . __('c/u', 'motorlan-portal') : '');
        }
        if ($published !== null && $unit !== null && abs($unit - $published) > 0.009) {
            $notes[] = __('Precio publicado', 'motorlan-portal') . ': ' . self::money($published);
        }

        // La otra parte
        if ($role === 'buyer') {
            $author = is_array($pub['author'] ?? null) ? $pub['author'] : [];
            $rep = is_array($author['acf'] ?? null) ? $author['acf'] : [];
            $rating = self::num($rep['calificacion'] ?? null);
            $sales = self::num($rep['ventas'] ?? null);
            $party = [
                'title'   => __('Datos del vendedor', 'motorlan-portal'),
                'name'    => self::person_name($author) ?: __('Vendedor', 'motorlan-portal'),
                'avatar'  => is_string($author['avatar'] ?? null) ? $author['avatar'] : '',
                'company' => is_scalar($rep['empresa'] ?? null) ? (string) $rep['empresa'] : '',
                'email'   => is_string($author['email'] ?? null) ? $author['email'] : '',
                'rating'  => $rating !== null && $rating > 0 ? $rating : null,
                'reviews' => (int) ($rep['valoraciones'] ?? 0),
                'sales'   => $sales,
            ];
        } else {
            // La API solo da el id del comprador: la SPA mostraba «Comprador». Nombre (sin
            // email) del usuario; el acceso a la compra ya lo validó la API.
            $buyer = get_userdata((int) ($c['comprador_id'] ?? 0));
            $avatar_id = $buyer ? (int) get_user_meta($buyer->ID, 'avatar_attachment_id', true) : 0;
            $party = [
                'title'   => __('Datos del comprador', 'motorlan-portal'),
                'name'    => $buyer ? self::person_name(['first_name' => $buyer->first_name, 'last_name' => $buyer->last_name, 'name' => $buyer->display_name]) : __('Comprador', 'motorlan-portal'),
                'avatar'  => $avatar_id ? (string) wp_get_attachment_image_url($avatar_id, 'thumbnail') : '',
                'company' => '',
                'email'   => '',
                'rating'  => null,
                'reviews' => 0,
                'sales'   => null,
            ];
        }
        $party['initials'] = self::initials($party['name']) ?: ($role === 'buyer' ? 'V' : 'C');

        $location = trim($province . ($province !== '' && $country !== '' ? ', ' : '') . $country);
        $is_offer = $offer !== null;

        return [
            'uuid'    => (string) $c['uuid'],
            'role'    => $role,
            'date'    => (string) ($c['fecha_compra'] ?? ''),
            'status'  => self::detail_status($estado, (string) ($c['fecha_compra'] ?? '')),
            'raw_status' => $estado !== '' ? self::humanize($estado) : '',
            // La SPA ponía «Consultar precio» como tipo de compra de una venta directa con
            // precio negociable: aquí, oferta o directa.
            'mode'    => $is_offer ? __('Compra por oferta', 'motorlan-portal') : __('Compra directa', 'motorlan-portal'),
            'product' => [
                'name'     => $name,
                'image'    => Store::image_url($pub['imagen_destacada'] ?? null, 'medium_large'),
                'thumb'    => Store::image_url($pub['imagen_destacada'] ?? null, 'thumbnail'),
                'url'      => !empty($pub['slug']) ? SpaPages::product_url((string) $pub['slug']) : '',
                'location' => $location,
                'type'     => implode(', ', $types),
            ],
            'price'   => [
                'qty'       => sprintf(__('%d ud.', 'motorlan-portal'), $qty),
                'unit'      => $unit !== null ? self::money($unit) : null,
                'total'     => $total !== null ? self::money($total) : ($unit !== null ? self::money($unit * $qty) : $consult),
                'published' => $published !== null ? self::money($published) : null,
                'offer'     => $offer_amount !== null ? self::money($offer_amount) : null,
                'notes'     => $notes,
            ],
            'offer'   => $offer ? self::offer_view($offer) : null,
            'party'   => $party,
            'can_rate' => $role === 'buyer' && empty($c['review_done']) && !in_array($estado, self::NOT_RATEABLE, true),
            // Miga: el vendedor vuelve a Ventas (la SPA siempre llevaba a Mis compras).
            'back'    => $role === 'seller'
                ? ['label' => __('Ventas', 'motorlan-portal'), 'url' => Urls::spa('dashboard/publications/sales'), 'icon' => 'currency-euro']
                : ['label' => __('Compras', 'motorlan-portal'), 'url' => Urls::spa('dashboard/purchases/purchases'), 'icon' => 'shopping-cart'],
        ];
    }

    /** Tarjeta de estado del detalle (statusInfo de [uuid].vue). */
    protected static function detail_status(string $estado, string $date): array
    {
        $with_date = function (string $text) use ($date) {
            return $date !== '' ? sprintf(__('El %1$s: %2$s', 'motorlan-portal'), $date, $text) : $text;
        };
        $registered = [
            'label' => __('Venta registrada', 'motorlan-portal'),
            'title' => __('Compra registrada', 'motorlan-portal'),
            'text'  => $with_date(__('Coordina el pago y la entrega directamente con el vendedor.', 'motorlan-portal')),
            'color' => 'success',
        ];
        $pending = [
            'label' => __('Pendiente', 'motorlan-portal'),
            'title' => __('Acuerdo pendiente', 'motorlan-portal'),
            'text'  => $with_date(__('Coordina el pago y la entrega directamente con el vendedor.', 'motorlan-portal')),
            'color' => 'warning',
        ];
        $cancelled = [
            'label' => __('Cancelada', 'motorlan-portal'),
            'title' => __('La compra se canceló', 'motorlan-portal'),
            'text'  => $with_date(__('La compra fue anulada.', 'motorlan-portal')),
            'color' => 'error',
        ];
        $map = [
            'completed'  => $registered,
            'completado' => $registered,
            'entregado'  => ['label' => __('Entregado', 'motorlan-portal'), 'title' => __('Recibiste la compra', 'motorlan-portal'), 'text' => $with_date(__('confirmamos que ya la tienes.', 'motorlan-portal')), 'color' => 'success'],
            'enviado'    => ['label' => __('Enviado', 'motorlan-portal'), 'title' => __('Tu compra va en camino', 'motorlan-portal'), 'text' => $with_date(__('salió a entrega.', 'motorlan-portal')), 'color' => 'info'],
            'en_proceso' => ['label' => __('En proceso', 'motorlan-portal'), 'title' => __('Estamos preparando tu compra', 'motorlan-portal'), 'text' => $with_date(__('estamos gestionando el envío.', 'motorlan-portal')), 'color' => 'info'],
            'pending'    => $pending,
            'pendiente'  => $pending,
            'cancelled'  => $cancelled,
            'canceled'   => $cancelled,
            'cancelado'  => $cancelled,
        ];
        if (isset($map[$estado])) {
            return $map[$estado];
        }
        $label = $estado !== '' ? self::humanize($estado) : __('En curso', 'motorlan-portal');
        return ['label' => $label, 'title' => $label, 'text' => $with_date(__('seguimos gestionando tu pedido.', 'motorlan-portal')), 'color' => 'info'];
    }

    // ── Ofertas ─────────────────────────────────────────────────────────────────────────

    public static function offer_statuses(): array
    {
        return [
            'pending'                       => ['label' => __('Pendiente', 'motorlan-portal'), 'color' => 'warning', 'text' => __('El vendedor aún no ha respondido a la oferta.', 'motorlan-portal'), 'filter' => __('Pendientes', 'motorlan-portal')],
            'accepted_pending_confirmation' => ['label' => __('Esperando confirmación', 'motorlan-portal'), 'color' => 'info', 'text' => __('La oferta fue aceptada. Queda pendiente tu confirmación de compra.', 'motorlan-portal'), 'filter' => __('Esperando confirmación', 'motorlan-portal')],
            'confirmed'                     => ['label' => __('Confirmada', 'motorlan-portal'), 'color' => 'success', 'text' => __('La oferta se confirmó y se registró la compra.', 'motorlan-portal'), 'filter' => __('Confirmadas', 'motorlan-portal')],
            'rejected'                      => ['label' => __('Rechazada', 'motorlan-portal'), 'color' => 'error', 'text' => __('El vendedor rechazó esta oferta.', 'motorlan-portal'), 'filter' => __('Rechazadas', 'motorlan-portal')],
            'expired'                       => ['label' => __('Expirada', 'motorlan-portal'), 'color' => 'warning', 'text' => __('La oferta venció sin confirmación.', 'motorlan-portal'), 'filter' => __('Expiradas', 'motorlan-portal')],
        ];
    }

    /** Oferta lista para pintar (tabla, diálogo y tarjeta del detalle de compra). */
    public static function offer_view(array $o): array
    {
        $status = (string) ($o['status'] ?? '');
        $s = self::offer_statuses()[$status] ?? ['label' => self::humanize($status), 'color' => 'info', 'text' => ''];
        $pid = (int) ($o['publication_id'] ?? 0);
        $slug = $pid ? (string) get_post_field('post_name', $pid) : '';
        $secs = isset($o['time_to_expire']) && $o['time_to_expire'] !== null ? (int) $o['time_to_expire'] : null;
        $amount = self::num($o['offer_amount'] ?? null);
        return [
            'id'          => (int) ($o['id'] ?? 0),
            'title'       => self::decode((string) ($o['publication_title'] ?? '')),
            'url'         => $slug !== '' ? SpaPages::product_url($slug) : '',
            'amount'      => $amount !== null ? self::money($amount) : '—',
            'date'        => self::when($o['offer_date'] ?? null, false),
            'date_full'   => self::when($o['offer_date'] ?? null, true),
            'accepted'    => self::when($o['accepted_at'] ?? null, true),
            'confirmed'   => self::when($o['confirmed_at'] ?? null, true),
            'expires'     => self::when($o['expires_at'] ?? null, true),
            'remaining'   => $status === 'accepted_pending_confirmation' ? self::remaining($secs) : '',
            'status'      => $status,
            'chip'        => ['label' => $s['label'], 'color' => $s['color']],
            'status_text' => $s['text'],
            'comment'     => trim((string) ($o['justification'] ?? '')),
            'can_confirm' => $status === 'accepted_pending_confirmation',
            'can_withdraw' => in_array($status, ['pending', 'expired'], true),
        ];
    }

    protected function offers(): void
    {
        $url = Urls::spa('dashboard/purchases/offers-sent');
        $headers = $this->is_post() ? $this->offer_action() : [];

        $f = $this->list_filters(['estado' => array_keys(self::offer_statuses())]);
        $f['desde'] = self::date_param($this->src('desde'));
        $f['hasta'] = self::date_param($this->src('hasta'));
        [$rows, $data] = $this->offers_page($f);
        // Al retirar la última oferta de la última página: a la anterior.
        while (!$rows && $data !== null && $f['page'] > 1) {
            $f['page']--;
            [$rows, $data] = $this->offers_page($f);
        }

        $estados = ['' => __('Todos los estados', 'motorlan-portal')];
        foreach (self::offer_statuses() as $k => $s) {
            $estados[$k] = $s['filter'];
        }
        $context = [
            // La SPA mostraba la clave «offers.sent» como título (estaba anidada en inquiries).
            'page_title' => __('Ofertas enviadas', 'motorlan-portal'),
            'table'      => $this->table_context($url, $f, $rows, $data === null, (array) ($data['pagination'] ?? [])) + ['statuses' => $estados],
            'nonce'      => wp_create_nonce(self::PANEL_NONCE),
        ];
        $this->respond('pages/comprador/ofertas.twig', 'partials/comprador/ofertas-tabla.twig', $context, $headers);
    }

    /** [filas, respuesta] de GET /offers/sent con los filtros. */
    protected function offers_page(array $f): array
    {
        $data = Api::get(Api::route('offers/sent'), array_filter([
            'search'    => $f['q'],
            'status'    => $f['estado'],
            'date_from' => $f['desde'],
            'date_to'   => $f['hasta'] ?: $f['desde'],
            'per_page'  => $f['per_page'],
            'page'      => $f['page'],
        ]));
        $rows = [];
        foreach ((array) ($data['data'] ?? []) as $o) {
            if (is_array($o) && !empty($o['id'])) {
                $rows[] = self::offer_view($o);
            }
        }
        return [$rows, $data];
    }

    /**
     * POST action=confirm|withdraw. Confirmar crea la compra y lleva a su detalle (la API
     * bloquea la doble confirmación desde #155: relee la oferta con FOR UPDATE).
     */
    protected function offer_action(): array
    {
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $action = $this->field('action');
        if (!$this->valid_nonce() || !$id || !in_array($action, ['confirm', 'withdraw'], true)) {
            return self::toast_header(__('La página ha caducado. Recárgala e inténtalo de nuevo.', 'motorlan-portal'), 'error');
        }
        if ($action === 'confirm') {
            $r = Api::call('POST', Api::route('offers/' . $id . '/confirm'), [], []);
            if ($r['ok'] && !empty($r['data']['purchase_uuid'])) {
                $this->redirect(Urls::spa('dashboard/purchases/' . sanitize_key((string) $r['data']['purchase_uuid'])), 303);
            }
            $msg = (string) ($r['error']['message'] ?? '');
            // La SPA no importaba assertApiOk: confirmar creaba la compra pero mostraba
            // «assertApiOk is not defined» y no navegaba.
            return self::toast_header($msg !== '' ? $msg : __('No se pudo confirmar la oferta.', 'motorlan-portal'), 'error');
        }
        $r = Api::call('DELETE', Api::route('offers/' . $id));
        if (!$r['ok']) {
            $msg = (string) ($r['error']['message'] ?? '');
            return self::toast_header($msg !== '' ? $msg : __('No se pudo retirar la oferta.', 'motorlan-portal'), 'error');
        }
        return self::toast_header(__('Oferta retirada', 'motorlan-portal'));
    }

    // ── Consultas enviadas ──────────────────────────────────────────────────────────────

    protected function inquiries(): void
    {
        // Reclamar los chats hechos como invitado con el email verificado (solo al entrar;
        // los refrescos de la tabla no). Si falla, la bandeja sigue.
        if (!$this->is_htmx()) {
            Api::call('POST', Api::route('buyer/claim-guest-chats'), [], []);
        }
        $f = $this->list_filters([]);
        $data = Api::get(Api::route('buyer/inquiries'));
        $all = [];
        foreach ((array) ($data['data'] ?? []) as $it) {
            if (is_array($it) && !empty($it['product_id']) && !empty($it['room_key'])) {
                $all[] = self::inquiry_row($it);
            }
        }
        // El diálogo de chat va fuera de la tabla: se prepara aunque la búsqueda no deje filas.
        $any = $all[0] ?? null;
        if ($f['q'] !== '') {
            $q = mb_strtolower($f['q']);
            $all = array_values(array_filter($all, function ($r) use ($q) {
                return mb_strpos(mb_strtolower($r['seller'] . ' ' . $r['name']), $q) !== false;
            }));
        }
        // La API no pagina: se pagina aquí (en la SPA, en el navegador).
        $total = count($all);
        $pages = max(1, (int) ceil($total / $f['per_page']));
        $f['page'] = min($f['page'], $pages);
        $rows = array_slice($all, ($f['page'] - 1) * $f['per_page'], $f['per_page']);

        // ?room_key=… (avisos y emails): abrir esa conversación.
        $open = null;
        $want = $this->query('room_key');
        if ($want !== '') {
            foreach ($all as $r) {
                if ($r['room_key'] === $want) {
                    $open = $r;
                    break;
                }
            }
        }
        $first = $open ?: ($rows[0] ?? $any);

        $context = [
            'page_title' => __('Consultas enviadas', 'motorlan-portal'),
            'table'      => $this->table_context(Urls::spa('dashboard/purchases/inquiries'), $f, $rows, $data === null, [
                'total' => $total, 'totalPages' => $pages, 'currentPage' => $f['page'],
            ]),
            'chat'       => $first ? Chat::product($first['product_id'], $first['room_key'], [
                'title'     => $first['name'],
                'image'     => $first['image'],
                'otherName' => $first['seller'],
                'autoOpen'  => $open !== null,
            ]) : null,
        ];
        $this->respond('pages/comprador/consultas.twig', 'partials/comprador/consultas-tabla.twig', $context);
    }

    protected static function inquiry_row(array $it): array
    {
        $pid = (int) $it['product_id'];
        // product_title de la API es «título + referencia + potencia + velocidad» sin
        // unidades («… C4CA-101/B1 2 1000»): se usa el título de la publicación.
        $title = get_the_title($pid);
        $name = $title !== '' ? self::decode($title) : self::decode((string) ($it['product_title'] ?? ''));
        $image = is_array($it['product_image'] ?? null) ? Store::image_url($it['product_image'], 'thumbnail') : (is_string($it['product_image'] ?? null) ? $it['product_image'] : '');
        $seller = self::decode((string) ($it['user_name'] ?? '')) ?: __('Vendedor', 'motorlan-portal');
        $last = (string) ($it['last_at'] ?? '');
        return [
            'product_id' => $pid,
            'room_key'   => (string) $it['room_key'],
            'name'       => $name,
            'image'      => $image,
            'url'        => !empty($it['product_slug']) ? SpaPages::product_url((string) $it['product_slug']) : '',
            'seller'     => $seller,
            'avatar'     => is_string($it['user_avatar'] ?? null) ? $it['user_avatar'] : '',
            'initials'   => self::initials($seller) ?: 'V',
            'unread'     => (int) ($it['unread'] ?? 0),
            // last_at llega en UTC sin zona (la SPA lo corrigió en el navegador).
            'date'       => self::utc_short($last),
            'date_full'  => $last !== '' ? get_date_from_gmt($last, get_option('date_format') . ' ' . get_option('time_format')) : '',
        ];
    }

    // ── Mis valoraciones ────────────────────────────────────────────────────────────────

    protected function opinions(): void
    {
        $orders = [
            'recientes' => ['label' => __('Más recientes', 'motorlan-portal'), 'orderby' => 'fecha', 'order' => 'desc'],
            'antiguas'  => ['label' => __('Más antiguas', 'motorlan-portal'), 'orderby' => 'fecha', 'order' => 'asc'],
            'mejor'     => ['label' => __('Mejor valoradas', 'motorlan-portal'), 'orderby' => 'valoracion', 'order' => 'desc'],
            'peor'      => ['label' => __('Peor valoradas', 'motorlan-portal'), 'orderby' => 'valoracion', 'order' => 'asc'],
        ];
        $f = $this->list_filters(['orden' => array_keys($orders)]);
        $o = $orders[$f['orden'] ?: 'recientes'];
        $data = Api::get(Api::route('purchases/opinions'), array_filter([
            'search'   => $f['q'],
            'orderby'  => $o['orderby'],
            'order'    => $o['order'],
            'per_page' => $f['per_page'],
            'page'     => $f['page'],
        ]));
        $rows = [];
        foreach ((array) ($data['data'] ?? []) as $op) {
            if (!is_array($op)) {
                continue;
            }
            $pub = is_array($op['publicacion'] ?? null) ? self::with_brand($op['publicacion']) : null;
            $rows[] = [
                'name'     => $pub ? Publicacion::name($pub) : self::decode((string) ($op['title'] ?? '')),
                'image'    => $pub ? Store::image_url($pub['imagen_destacada'] ?? null, 'thumbnail') : '',
                'url'      => $pub && !empty($pub['slug']) ? SpaPages::product_url((string) $pub['slug']) : '',
                'target'   => ($op['target_role'] ?? '') === 'buyer' ? __('Valoración al comprador', 'motorlan-portal') : __('Valoración al vendedor', 'motorlan-portal'),
                'rating'   => max(0, min(5, (int) ($op['valoracion'] ?? 0))),
                'comment'  => (string) ($op['comentario'] ?? ''),
                'date'     => self::when($op['fecha'] ?? null, false),
                'purchase' => !empty($op['purchase_uuid']) ? Urls::spa('dashboard/purchases/' . sanitize_key((string) $op['purchase_uuid'])) : '',
            ];
        }
        $order_options = [];
        foreach ($orders as $k => $v) {
            $order_options[$k === 'recientes' ? '' : $k] = $v['label'];
        }
        $context = [
            'page_title' => __('Mis valoraciones', 'motorlan-portal'),
            'table'      => $this->table_context(Urls::spa('dashboard/purchases/opinions'), $f, $rows, $data === null, (array) ($data['pagination'] ?? [])) + ['orders' => $order_options],
            'purchases_url' => Urls::spa('dashboard/purchases/purchases'),
        ];
        $this->respond('pages/comprador/valoraciones.twig', 'partials/comprador/valoraciones-tabla.twig', $context);
    }

    // ── Comunes ─────────────────────────────────────────────────────────────────────────

    /** Página completa, o solo el parcial con HTMX. */
    protected function respond(string $page, string $partial, array $context, array $headers = []): void
    {
        if ($this->is_htmx()) {
            View::html_response(View::compile($partial, $context, true), 200, $headers + ['Vary' => 'HX-Request']);
        }
        Seo::private_page($context['page_title']);
        $this->render($page, $context);
    }

    /** Valor de GET (o del POST, que reenvía los filtros en hx-include). */
    protected function src(string $k): string
    {
        $src = $this->is_post() ? $_POST : $_GET;
        return isset($src[$k]) && is_string($src[$k]) ? trim(sanitize_text_field(wp_unslash($src[$k]))) : '';
    }

    /** q, per_page, page y los selects de $enums (nombre => valores válidos). */
    protected function list_filters(array $enums): array
    {
        $per_page = (int) $this->src('per_page');
        $f = [
            'q'        => mb_substr($this->src('q'), 0, 100),
            'per_page' => in_array($per_page, self::PER_PAGE, true) ? $per_page : 10,
            'page'     => max(1, (int) $this->src('page')),
        ];
        foreach ($enums as $name => $valid) {
            $v = $this->src($name);
            $f[$name] = in_array($v, $valid, true) ? $v : '';
        }
        return $f;
    }

    /** Contexto común de components/table.twig. */
    protected function table_context(string $url, array $f, array $rows, bool $error, array $pagination): array
    {
        $per_page = [];
        foreach (self::PER_PAGE as $n) {
            $per_page[$n] = sprintf(__('%d por página', 'motorlan-portal'), $n);
        }
        $query = [];
        foreach ($f as $k => $v) {
            if ($k === 'page' || $v === '' || $v === null || ($k === 'per_page' && (int) $v === 10)) {
                continue;
            }
            $query[$k] = $v;
        }
        return [
            'url'      => $url,
            'rows'     => $rows,
            'error'    => $error,
            'filters'  => $f,
            'page'     => (int) ($pagination['currentPage'] ?? $f['page']),
            'pages'    => max(1, (int) ($pagination['totalPages'] ?? 1)),
            'total'    => (int) ($pagination['total'] ?? count($rows)),
            'per_page' => $per_page,
            'query'    => http_build_query($query),
            'filtered' => $f['q'] !== '' || array_filter(array_diff_key($f, array_flip(['q', 'per_page', 'page']))) !== [],
        ];
    }

    /** acf.marca llega como id de término: nombre para Publicacion::name (la SPA lo perdía). */
    protected static function with_brand(array $p): array
    {
        $m = $p['acf']['marca'] ?? null;
        if (empty($p['marca_name']) && is_numeric($m) && (int) $m > 0) {
            $t = get_term((int) $m);
            if ($t instanceof \WP_Term) {
                $p['marca_name'] = $t->name;
            }
        }
        return $p;
    }

    /** Nombre y apellidos; si no hay, el nombre visible. */
    protected static function person_name(array $u): string
    {
        $full = trim((string) ($u['first_name'] ?? $u['user_firstname'] ?? '') . ' ' . (string) ($u['last_name'] ?? $u['user_lastname'] ?? ''));
        if ($full !== '') {
            return $full;
        }
        foreach (['name', 'display_name', 'user_nicename'] as $k) {
            if (!empty($u[$k]) && is_string($u[$k])) {
                return $u[$k];
            }
        }
        return '';
    }

    protected static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $out = '';
        foreach (array_slice(array_filter($parts), 0, 2) as $p) {
            $out .= mb_strtoupper(mb_substr($p, 0, 1));
        }
        return $out;
    }

    protected static function decode(string $s): string
    {
        return trim(html_entity_decode($s, ENT_QUOTES, 'UTF-8'));
    }

    /** completed_by_x → «Completed by x». */
    protected static function humanize(string $s): string
    {
        return ucfirst(trim(str_replace(['_', '-'], ' ', $s)));
    }

    /** Número de la API (también «1.234,50»), o null. */
    protected static function num($v): ?float
    {
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        if (is_string($v) && trim($v) !== '') {
            $n = str_replace(',', '.', str_replace('.', '', preg_replace('/[^\d,.-]/', '', $v)));
            return is_numeric($n) ? (float) $n : null;
        }
        return null;
    }

    /** 1234.5 → «1.234,50 €» (formatCurrency de la SPA, es-ES). */
    public static function money(float $v): string
    {
        return number_format($v, 2, ',', '.') . ' €';
    }

    /** Fecha de la API en hora local del sitio («Y-m-d H:i:s») formateada. */
    protected static function when($v, bool $time): string
    {
        if (!is_string($v) || trim($v) === '') {
            return '';
        }
        return (string) mysql2date($time ? 'j M Y, H:i' : 'j M Y', $v);
    }

    /** Fecha UTC → «14:05» si es de hoy, si no «12 nov» (formatLocalizedDate de inquiries.vue). */
    protected static function utc_short(string $utc): string
    {
        if ($utc === '') {
            return '';
        }
        $local = get_date_from_gmt($utc, 'Y-m-d H:i:s');
        return mysql2date(substr($local, 0, 10) === current_time('Y-m-d') ? 'H:i' : 'j M', $local);
    }

    /** Segundos → «5 h 20 min» (formatTimeRemaining de offers-sent.vue). */
    protected static function remaining(?int $secs): string
    {
        if ($secs === null) {
            return '';
        }
        if ($secs <= 0) {
            return __('Expirada', 'motorlan-portal');
        }
        $min = (int) ceil($secs / 60);
        $h = intdiv($min, 60);
        $m = $min % 60;
        if ($h > 0) {
            return $m > 0 ? sprintf(__('%1$d h %2$d min', 'motorlan-portal'), $h, $m) : sprintf(__('%d h', 'motorlan-portal'), $h);
        }
        return sprintf(__('%d min', 'motorlan-portal'), $m);
    }

    /** AAAA-MM-DD válida o ''. */
    protected static function date_param(string $v): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? $v : '';
    }
}
