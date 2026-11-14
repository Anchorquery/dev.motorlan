<?php

namespace MotorlanPortal\Controllers;

use MotorlanPortal\Support\Api;
use MotorlanPortal\Support\Chat;
use MotorlanPortal\Support\Flash;
use MotorlanPortal\Support\Reviews;
use MotorlanPortal\Support\Seo;
use MotorlanPortal\Support\SpaPages;
use MotorlanPortal\Support\Urls;
use MotorlanPortal\Support\View;

/**
 * Sección `interesados` (vendedor): dashboard/inquiries (inquiries/index.vue +
 * SellerChatModal.vue). Bandeja de conversaciones sobre sus publicaciones
 * (GET /seller/inquiries: la API no pagina, así que búsqueda y paginación van aquí),
 * chat por publicación (kit de chat) y «Registrar venta» desde el chat
 * (POST /user/sales/manual) seguida de la valoración del comprador (kit de valoración).
 * ?room_key= abre esa conversación al cargar.
 */
class InteresadosController extends Controller
{
    const PER_PAGE = [5, 10, 20, 25, 50];

    public function handle(string $param): void
    {
        if (trim($param, '/') !== 'inquiries') {
            $this->not_found();
            return;
        }
        $url = Urls::spa('dashboard/inquiries');
        if ($this->is_post()) {
            $action = $this->field('action');
            if ($action === 'sale') {
                $this->sale();
            }
            if (!$this->valid_nonce()) {
                Flash::set(__('La página ha caducado. Recárgala e inténtalo de nuevo.', 'motorlan-portal'), 'error');
            } elseif ($action === 'review') {
                [$ok, $msg] = Reviews::submit($_POST);
                Flash::set($msg, $ok ? 'success' : 'error');
            }
            $this->redirect($url, 303);
        }

        $f = $this->filters();
        [$items, $error] = $this->fetch();
        $context = $this->context($f, $items, $error, $url);
        if ($this->is_htmx()) {
            View::html_response(View::compile('partials/interesados-tabla.twig', $context, true), 200, ['Vary' => 'HX-Request']);
        }
        $room = $this->query('room_key');
        $open = null;
        if ($room !== '') {
            foreach ($items as $it) {
                if ($it['room_key'] === $room) {
                    $open = $it;
                    break;
                }
            }
        }
        $context['open_room'] = $open;
        $context['room_missing'] = $room !== '' && !$open;
        $context['chat'] = Chat::product(0, '', [
            'dialogId'  => 'ml-inq-chat',
            'emptyHint' => __('Escribe al comprador para empezar la conversación.', 'motorlan-portal'),
        ]);
        $context['flash'] = Flash::pull();
        Seo::private_page($context['page_title']);
        $this->render('pages/interesados.twig', $context);
    }

    /**
     * Venta manual desde el chat. Responde JSON (lo llama mlInquiries con fetch): la API
     * valida que el chat sea de esa publicación, que el comprador escribiera y el stock.
     */
    protected function sale(): void
    {
        if (!$this->valid_nonce()) {
            wp_send_json(['ok' => false, 'message' => __('La página ha caducado. Recárgala e inténtalo de nuevo.', 'motorlan-portal')], 403);
        }
        $pid = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $room = $this->field('room_key');
        if (!$pid || !preg_match('/^pub-\d+-viewer-[A-Za-z0-9_-]+$/', $room)) {
            wp_send_json(['ok' => false, 'message' => __('Conversación no válida.', 'motorlan-portal')], 400);
        }
        $body = ['product_id' => $pid, 'room_key' => $room];
        $raw = $this->field('amount');
        if ($raw !== '') {
            $amount = self::parse_amount($raw);
            if ($amount === null) {
                wp_send_json(['ok' => false, 'message' => __('Importe no válido', 'motorlan-portal')], 400);
            }
            $body['amount'] = $amount;
        }
        $r = Api::call('POST', Api::route('user/sales/manual'), [], $body);
        if (!$r['ok']) {
            $m = (string) ($r['error']['message'] ?? '');
            wp_send_json(['ok' => false, 'message' => $m !== '' ? $m : __('No se pudo registrar la venta.', 'motorlan-portal')], $r['status'] ?: 500);
        }
        $uuid = (string) ($r['data']['data']['uuid'] ?? '');
        wp_send_json([
            'ok'      => true,
            'uuid'    => $uuid,
            'message' => (string) ($r['data']['message'] ?? '') ?: __('Venta registrada correctamente.', 'motorlan-portal'),
            'stock'   => isset($r['data']['stock']) ? (int) $r['data']['stock'] : null,
        ]);
    }

    /** «1.234,50 €», «1500.5», «1 500» → float > 0; null si no es un importe válido. */
    public static function parse_amount(string $raw): ?float
    {
        $s = preg_replace('/[\s€\x{00A0}]/u', '', $raw);
        $s = preg_replace('/\.(?=\d{3}(?:\D|$))/', '', (string) $s);
        $s = str_replace(',', '.', (string) $s);
        if (!is_numeric($s)) {
            return null;
        }
        $n = round((float) $s, 2);
        return $n > 0 && $n < 100000000 ? $n : null;
    }

    protected function filters(): array
    {
        $src = wp_unslash($_GET);
        $q = isset($src['q']) && is_string($src['q']) ? trim(sanitize_text_field($src['q'])) : '';
        $per_page = isset($src['per_page']) ? (int) $src['per_page'] : 10;
        return [
            'q'        => mb_substr($q, 0, 100),
            'per_page' => in_array($per_page, self::PER_PAGE, true) ? $per_page : 10,
            'page'     => max(1, isset($src['page']) ? (int) $src['page'] : 1),
        ];
    }

    /** @return array{0: array, 1: bool} [conversaciones, error] */
    protected function fetch(): array
    {
        // La API avisa «Undefined variable $counts» (class-product-chat-controller.php): con
        // display_errors el aviso saldría en mitad de la página. Se descarta esa salida.
        ob_start();
        $r = Api::call('GET', Api::route('seller/inquiries'));
        ob_end_clean();
        if (!$r['ok']) {
            return [[], true];
        }
        $items = [];
        foreach ((array) ($r['data']['data'] ?? []) as $it) {
            if (is_array($it) && !empty($it['room_key']) && !empty($it['product_id'])) {
                $items[] = self::row($it);
            }
        }
        usort($items, function ($a, $b) { return $b['ts'] <=> $a['ts']; });
        return [$items, false];
    }

    public static function row(array $it): array
    {
        $name = trim((string) ($it['user_name'] ?? ''));
        // La API manda 'Y-m-d H:i:s' en UTC sin zona.
        $ts = !empty($it['last_at']) ? strtotime((string) $it['last_at'] . ' UTC') : false;
        $today = $ts && wp_date('Y-m-d', $ts) === wp_date('Y-m-d');
        $slug = (string) ($it['product_slug'] ?? '');
        $initials = '';
        foreach (array_slice(preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        return [
            'product_id' => (int) $it['product_id'],
            'room_key'   => (string) $it['room_key'],
            'title'      => html_entity_decode((string) ($it['product_title'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'image'      => self::image($it['product_image'] ?? null),
            'user'       => $name !== '' ? $name : __('Usuario', 'motorlan-portal'),
            'initials'   => $initials !== '' ? $initials : 'U',
            'avatar'     => (string) ($it['user_avatar'] ?? ''),
            'unread'     => max(0, (int) ($it['unread'] ?? 0)),
            'ts'         => $ts ?: 0,
            'date'       => $ts ? ($today ? wp_date('H:i', $ts) : wp_date('j M', $ts)) : '—',
            'datetime'   => $ts ? wp_date('j M Y, H:i', $ts) : '',
            'pub_url'    => $slug !== '' ? SpaPages::product_url($slug) : '',
        ];
    }

    /** product_image de la API (motorlan_format_image_for_frontend): cadena u objeto con sizes. */
    protected static function image($img): string
    {
        if (is_string($img)) {
            return $img;
        }
        if (!is_array($img)) {
            return '';
        }
        if (isset($img[0]) && is_array($img[0])) {
            $img = $img[0];
        }
        $thumb = $img['sizes']['thumbnail'] ?? null;
        if (is_string($thumb) && $thumb !== '') {
            return $thumb;
        }
        if (is_array($thumb) && !empty($thumb['url'])) {
            return (string) $thumb['url'];
        }
        return (string) ($img['url'] ?? ($img['src'] ?? ''));
    }

    protected function context(array $f, array $items, bool $error, string $url): array
    {
        if ($f['q'] !== '') {
            $needle = mb_strtolower($f['q']);
            $items = array_values(array_filter($items, function ($it) use ($needle) {
                return mb_strpos(mb_strtolower($it['user']), $needle) !== false || mb_strpos(mb_strtolower($it['title']), $needle) !== false;
            }));
        }
        $total = count($items);
        $pages = max(1, (int) ceil($total / $f['per_page']));
        $page = min($f['page'], $pages);
        $per_page = [];
        foreach (self::PER_PAGE as $n) {
            $per_page[$n] = sprintf(__('%d por página', 'motorlan-portal'), $n);
        }
        return [
            'page_title' => __('Interesados', 'motorlan-portal'),
            'page_url'   => $url,
            'nonce'      => wp_create_nonce(self::PANEL_NONCE),
            'table'      => [
                'url'      => $url,
                'rows'     => array_slice($items, ($page - 1) * $f['per_page'], $f['per_page']),
                'error'    => $error,
                'filters'  => $f,
                'page'     => $page,
                'pages'    => $pages,
                'total'    => $total,
                'per_page' => $per_page,
                'query'    => http_build_query(array_filter(['q' => $f['q'], 'per_page' => $f['per_page'] !== 10 ? $f['per_page'] : null])),
                'has_filters' => $f['q'] !== '',
            ],
        ];
    }
}
