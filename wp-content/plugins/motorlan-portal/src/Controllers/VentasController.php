<?php

namespace MotorlanPortal\Controllers;

use MotorlanPortal\Support\Api;
use MotorlanPortal\Support\Chat;
use MotorlanPortal\Support\Flash;
use MotorlanPortal\Support\Publicacion;
use MotorlanPortal\Support\Reviews;
use MotorlanPortal\Support\Seo;
use MotorlanPortal\Support\SpaPages;
use MotorlanPortal\Support\Store;
use MotorlanPortal\Support\Urls;
use MotorlanPortal\Support\View;

/**
 * Sección `ventas` (vendedor):
 *  - publications/sales         Ventas realizadas (sales/index.vue): GET /user/sales con
 *                               búsqueda, tipo, estado (por defecto todas), fechas y orden.
 *  - publications/sales/{uuid}  Detalle (sales/[uuid].vue): GET /user/sale-details/{uuid},
 *                               chat de la compra (kit de chat) y «Valorar comprador»
 *                               (kit de valoración → POST /reviews).
 */
class VentasController extends Controller
{
    const PER_PAGE = [5, 10, 20, 25, 50];

    const ORDERS = [
        ''           => ['date', 'desc'],
        'date-asc'   => ['date', 'asc'],
        'price-desc' => ['price', 'desc'],
        'price-asc'  => ['price', 'asc'],
    ];

    public function handle(string $param): void
    {
        $route = trim($param, '/');
        if ($route === 'publications/sales') {
            $this->list();
            return;
        }
        if (preg_match('#^publications/sales/([A-Za-z0-9-]+)$#', $route, $m)) {
            $this->detail($m[1]);
            return;
        }
        $this->not_found();
    }

    /** Estados de compra (campo ACF `estado`: pending / completed / cancelled). */
    public static function statuses(): array
    {
        return [
            'completed' => ['label' => __('Completada', 'motorlan-portal'), 'color' => 'success'],
            // pending = venta registrada a mano desde el chat, aún sin cerrar.
            'pending'   => ['label' => __('Venta registrada', 'motorlan-portal'), 'color' => 'warning'],
            'cancelled' => ['label' => __('Cancelada', 'motorlan-portal'), 'color' => 'error'],
        ];
    }

    public static function status(string $s): array
    {
        $s = strtolower($s);
        $s = ['pendiente' => 'pending', 'canceled' => 'cancelled', 'complete' => 'completed'][$s] ?? $s;
        return self::statuses()[$s] ?? ['label' => $s !== '' ? mb_strtoupper($s) : __('Desconocido', 'motorlan-portal'), 'color' => 'secondary'];
    }

    // ── Listado ──────────────────────────────────────────────────────────────────────────

    protected function list(): void
    {
        $f = $this->filters();
        [$orderby, $order] = self::ORDERS[$f['orden']];
        $data = Api::get(Api::route('user/sales'), array_filter([
            'search'    => $f['q'],
            'type'      => $f['tipo'],
            'status'    => $f['estado'] !== '' ? $f['estado'] : 'all',
            'date_from' => $f['desde'],
            'date_to'   => $f['hasta'],
            'orderby'   => $orderby,
            'order'     => $order,
            'page'      => $f['page'],
            'per_page'  => $f['per_page'],
        ]));
        $rows = [];
        foreach ((array) ($data['data'] ?? []) as $s) {
            if (is_array($s) && !empty($s['uuid'])) {
                $rows[] = self::row($s);
            }
        }
        $pag = (array) ($data['pagination'] ?? []);

        $statuses = ['' => __('Todos los estados', 'motorlan-portal')];
        foreach (self::statuses() as $k => $s) {
            $statuses[$k] = $s['label'];
        }
        $per_page = [];
        foreach (self::PER_PAGE as $n) {
            $per_page[$n] = sprintf(__('%d por página', 'motorlan-portal'), $n);
        }
        $query = array_filter(['q' => $f['q'], 'tipo' => $f['tipo'], 'estado' => $f['estado'], 'desde' => $f['desde'], 'hasta' => $f['hasta'],
            'orden' => $f['orden'], 'per_page' => $f['per_page'] !== 10 ? $f['per_page'] : null]);
        $context = [
            'page_title' => __('Ventas realizadas', 'motorlan-portal'),
            'table'      => [
                'url'      => Urls::spa('dashboard/publications/sales'),
                'rows'     => $rows,
                'error'    => $data === null,
                'filters'  => $f,
                'page'     => (int) ($pag['current_page'] ?? $f['page']),
                'pages'    => (int) ($pag['total_pages'] ?? 1),
                'total'    => (int) ($pag['total'] ?? 0),
                'statuses' => $statuses,
                'types'    => ['' => __('Tipo', 'motorlan-portal'), 'sale' => __('Venta', 'motorlan-portal'), 'rent' => __('Alquiler', 'motorlan-portal')],
                'orders'   => ['' => __('Más recientes', 'motorlan-portal'), 'date-asc' => __('Más antiguas', 'motorlan-portal'),
                    'price-desc' => __('Precio: mayor a menor', 'motorlan-portal'), 'price-asc' => __('Precio: menor a mayor', 'motorlan-portal')],
                'per_page' => $per_page,
                'query'    => http_build_query($query),
                'has_filters' => (bool) array_filter([$f['q'], $f['tipo'], $f['estado'], $f['desde'], $f['hasta']]),
            ],
        ];
        if ($this->is_htmx()) {
            View::html_response(View::compile('partials/ventas-tabla.twig', $context, true), 200, ['Vary' => 'HX-Request']);
        }
        Seo::private_page($context['page_title']);
        $this->render('pages/ventas.twig', $context);
    }

    protected function filters(): array
    {
        $get = function ($k) { return $this->query($k); };
        $date = function ($k) use ($get) { $v = $get($k); return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : ''; };
        $per_page = (int) $get('per_page');
        $tipo = $get('tipo');
        $estado = $get('estado');
        $orden = $get('orden');
        $desde = $date('desde');
        $hasta = $date('hasta');
        if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        return [
            'q'        => mb_substr($get('q'), 0, 100),
            'tipo'     => in_array($tipo, ['sale', 'rent'], true) ? $tipo : '',
            'estado'   => isset(self::statuses()[$estado]) ? $estado : '',
            'desde'    => $desde,
            'hasta'    => $hasta,
            'orden'    => isset(self::ORDERS[$orden]) ? $orden : '',
            'per_page' => in_array($per_page, self::PER_PAGE, true) ? $per_page : 10,
            'page'     => max(1, (int) $get('page')),
        ];
    }

    public static function row(array $s): array
    {
        $pub = isset($s['publicacion']) && is_array($s['publicacion']) ? $s['publicacion'] : [];
        $slug = (string) ($pub['slug'] ?? $s['publication_slug'] ?? '');
        $title = (string) ($s['publication_title'] ?? '');
        return [
            'uuid'       => (string) $s['uuid'],
            'title'      => $title !== '' ? html_entity_decode($title, ENT_QUOTES, 'UTF-8') : ($pub ? Publicacion::name($pub) : '—'),
            'reference'  => is_scalar($pub['acf']['tipo_o_referencia'] ?? null) ? (string) $pub['acf']['tipo_o_referencia'] : '',
            'image'      => self::image($pub),
            'buyer'      => (string) ($s['buyer_name'] ?? ''),
            'type'       => ($s['type'] ?? '') === 'rent' ? __('Alquiler', 'motorlan-portal') : __('Venta', 'motorlan-portal'),
            'price'      => is_numeric($s['price_value'] ?? null) && (float) $s['price_value'] > 0 ? PublicacionesController::money((float) $s['price_value']) : '—',
            'date'       => self::date((string) ($s['date'] ?? ''), (string) ($s['date_label'] ?? '')),
            'chip'       => self::status((string) ($s['status'] ?? '')),
            'detail_url' => Urls::spa('dashboard/publications/sales/' . $s['uuid']),
            // La SPA abría /{slug} en la raíz del sitio (404): la ficha vive en la tienda.
            'pub_url'    => $slug !== '' ? SpaPages::product_url($slug) : '',
        ];
    }

    protected static function image(array $pub): string
    {
        $img = $pub['imagen_destacada'] ?? null;
        if (is_array($img) && isset($img[0]) && is_array($img[0])) {
            $img = $img[0];
        }
        $url = Store::image_url($img, 'thumbnail');
        return $url !== '' ? $url : Store::image_url($pub['acf']['motor_image'] ?? null, 'thumbnail');
    }

    public static function date(string $iso, string $fallback = ''): string
    {
        $ts = $iso !== '' ? strtotime($iso) : false;
        if (!$ts) {
            return $fallback !== '' ? $fallback : '—';
        }
        // Las compras guardan solo la fecha (00:00): sin hora en ese caso.
        $local = get_date_from_gmt(gmdate('Y-m-d H:i:s', $ts), 'Y-m-d H:i:s');
        $has_time = gmdate('H:i:s', $ts) !== '00:00:00';
        return date_i18n($has_time ? 'j M Y, H:i' : 'j M Y', strtotime($has_time ? $local : gmdate('Y-m-d', $ts)));
    }

    // ── Detalle ──────────────────────────────────────────────────────────────────────────

    protected function detail(string $uuid): void
    {
        $url = Urls::spa('dashboard/publications/sales/' . $uuid);
        if ($this->is_post()) {
            if (!$this->valid_nonce()) {
                Flash::set(__('La página ha caducado. Recárgala e inténtalo de nuevo.', 'motorlan-portal'), 'error');
            } elseif ($this->field('action') === 'review') {
                [$ok, $msg] = Reviews::submit($_POST);
                Flash::set($msg, $ok ? 'success' : 'error');
            }
            $this->redirect($url, 303);
        }

        $r = Api::call('GET', Api::route('user/sale-details/' . $uuid));
        $sale = $r['ok'] ? (array) ($r['data']['data'] ?? []) : [];
        $context = [
            'page_title' => __('Detalle de la venta', 'motorlan-portal'),
            'list_url'   => Urls::spa('dashboard/publications/sales'),
            'page_url'   => $url,
            'nonce'      => wp_create_nonce(self::PANEL_NONCE),
            'flash'      => Flash::pull(),
            'hide_mobile_nav' => true,
            'error'      => !$r['ok'] && $r['status'] !== 404 && $r['status'] !== 403,
            'sale'       => null,
        ];
        if ($sale && !empty($sale['uuid'])) {
            $row = self::row($sale);
            $offer = isset($sale['offer']) && is_array($sale['offer']) ? $sale['offer'] : null;
            $pub = isset($sale['publicacion']) && is_array($sale['publicacion']) ? $sale['publicacion'] : [];
            $buyer = isset($sale['buyer']) && is_array($sale['buyer']) ? $sale['buyer'] : [];
            $status = strtolower((string) ($sale['status'] ?? ''));
            $acf = (array) ($pub['acf'] ?? []);
            $brand = Publicacion::brand($pub);
            if ($brand === '' && is_numeric($acf['marca'] ?? null)) {
                $term = get_term((int) $acf['marca']);
                $brand = $term && !is_wp_error($term) ? html_entity_decode($term->name, ENT_QUOTES, 'UTF-8') : '';
            }
            $context['sale'] = $row + [
                'id'         => (int) ($sale['id'] ?? 0),
                'status_raw' => $status,
                'mode'       => $offer ? __('Venta por oferta', 'motorlan-portal') : (($sale['type'] ?? '') === 'rent' ? __('Alquiler', 'motorlan-portal') : __('Venta directa', 'motorlan-portal')),
                'notes'      => trim((string) ($sale['payment_meta']['notas'] ?? $sale['notes'] ?? '')),
                'buyer_name' => (string) ($buyer['name'] ?? $sale['buyer_name'] ?? ''),
                'buyer_email'=> (string) ($buyer['email'] ?? $sale['buyer_email'] ?? ''),
                'buyer_phone'=> (string) ($buyer['phone'] ?? ''),
                'brand'      => $brand,
                'power'      => is_scalar($acf['potencia'] ?? null) && (string) $acf['potencia'] !== '' ? $acf['potencia'] . ' kW' : '',
                'image_large'=> self::image($pub),
                'offer'      => $offer ? [
                    'amount' => is_numeric($offer['offer_amount'] ?? null) ? PublicacionesController::money((float) $offer['offer_amount']) : '—',
                    'status' => OfertasController::status((string) ($offer['status'] ?? '')),
                    'message'=> trim((string) ($offer['justification'] ?? '')),
                ] : null,
                // Como la SPA: se valora una vez y nunca una compra anulada.
                'can_rate'   => empty($sale['review_done']) && !in_array($status, ['cancelled', 'canceled'], true),
            ];
            $context['page_title'] = sprintf(__('Venta: %s', 'motorlan-portal'), $row['title']);
            $context['chat'] = Chat::purchase((string) $sale['uuid'], [
                'dialogId'  => 'ml-sale-chat',
                'title'     => $row['title'],
                'image'     => $row['image'],
                'otherName' => $context['sale']['buyer_name'],
            ]);
        }
        Seo::private_page($context['page_title']);
        $this->render('pages/venta.twig', $context);
    }
}
