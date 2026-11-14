<?php

namespace MotorlanPortal\Controllers;

use MotorlanPortal\Support\Api;
use MotorlanPortal\Support\Flash;
use MotorlanPortal\Support\Seo;
use MotorlanPortal\Support\SpaPages;
use MotorlanPortal\Support\Urls;
use MotorlanPortal\Support\View;

/**
 * Sección `ofertas` (vendedor): publications/offers-received (offers-received.vue).
 * GET /offers/received con búsqueda, estado, fechas y paginación; aceptar o rechazar con
 * POST /offers/{id}/status (las reglas, el stock y el plazo de 24 h los aplica la API).
 * ?offer_id=N abre el detalle de esa oferta y se quita de la URL (los avisos y correos
 * enlazan así). La SPA solo la abría si estaba en la página cargada; aquí se busca.
 */
class OfertasController extends Controller
{
    const PER_PAGE = [5, 10, 20, 25, 50];

    public function handle(string $param): void
    {
        if (trim($param, '/') !== 'publications/offers-received') {
            $this->not_found();
            return;
        }
        $headers = [];
        if ($this->is_post()) {
            $headers = $this->action();
            if (!$this->is_htmx()) {
                $toast = json_decode($headers['HX-Trigger'] ?? '', true)['ml:toast'] ?? null;
                if ($toast) {
                    Flash::set((string) $toast['text'], (string) $toast['color']);
                }
                $this->redirect(Urls::spa('dashboard/publications/offers-received'), 303);
            }
        }
        $f = $this->filters();
        $context = $this->context($f);
        if ($this->is_htmx()) {
            View::html_response(View::compile('partials/ofertas-tabla.twig', $context, true), 200, $headers + ['Vary' => 'HX-Request']);
        }
        $open = absint($this->query('offer_id'));
        $context['open_offer'] = $open ? $this->find($open, $context['table']['rows']) : null;
        $context['offer_missing'] = $open && !$context['open_offer'];
        $context['flash'] = Flash::pull();
        Seo::private_page($context['page_title']);
        $this->render('pages/ofertas.twig', $context);
    }

    public static function statuses(): array
    {
        return [
            'pending'                       => ['label' => __('Pendiente', 'motorlan-portal'), 'color' => 'warning', 'filter' => __('Pendientes', 'motorlan-portal')],
            'accepted_pending_confirmation' => ['label' => __('Esperando confirmación', 'motorlan-portal'), 'color' => 'info', 'filter' => __('Esperando confirmación', 'motorlan-portal')],
            'confirmed'                     => ['label' => __('Confirmada', 'motorlan-portal'), 'color' => 'success', 'filter' => __('Confirmadas', 'motorlan-portal')],
            'rejected'                      => ['label' => __('Rechazada', 'motorlan-portal'), 'color' => 'error', 'filter' => __('Rechazadas', 'motorlan-portal')],
            'expired'                       => ['label' => __('Expirada', 'motorlan-portal'), 'color' => 'warning', 'filter' => __('Expiradas', 'motorlan-portal')],
        ];
    }

    public static function status(string $s): array
    {
        $all = self::statuses();
        $st = $all[$s] ?? $all['pending'];
        return ['label' => $st['label'], 'color' => $st['color']];
    }

    protected function action(): array
    {
        if (!$this->valid_nonce()) {
            return self::toast_header(__('La página ha caducado. Recárgala e inténtalo de nuevo.', 'motorlan-portal'), 'error');
        }
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $action = $this->field('action');
        if (!$id || !in_array($action, ['accept', 'reject'], true)) {
            return self::toast_header(__('Acción no válida.', 'motorlan-portal'), 'error');
        }
        $r = Api::call('POST', Api::route('offers/' . $id . '/status'), [], ['status' => $action === 'accept' ? 'accepted' : 'rejected']);
        if (!$r['ok']) {
            $m = (string) ($r['error']['message'] ?? '');
            return self::toast_header($m !== '' ? $m : __('No fue posible actualizar la oferta.', 'motorlan-portal'), 'error');
        }
        $m = (string) ($r['data']['message'] ?? '');
        return self::toast_header($m !== '' ? $m : ($action === 'accept' ? __('Oferta aceptada', 'motorlan-portal') : __('Oferta rechazada', 'motorlan-portal')));
    }

    protected function filters(): array
    {
        $src = $this->is_post() ? wp_unslash($_POST) : wp_unslash($_GET);
        $get = function ($k) use ($src) { return isset($src[$k]) && is_string($src[$k]) ? trim(sanitize_text_field($src[$k])) : ''; };
        $date = function ($k) use ($get) { $v = $get($k); return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : ''; };
        $estado = $get('estado');
        $per_page = (int) $get('per_page');
        $desde = $date('desde');
        $hasta = $date('hasta');
        if ($desde !== '' && $hasta !== '' && $desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        return [
            'q'        => mb_substr($get('q'), 0, 100),
            'estado'   => isset(self::statuses()[$estado]) ? $estado : '',
            'desde'    => $desde,
            'hasta'    => $hasta,
            'per_page' => in_array($per_page, self::PER_PAGE, true) ? $per_page : 10,
            'page'     => max(1, (int) $get('page')),
        ];
    }

    protected function context(array $f): array
    {
        $data = Api::get(Api::route('offers/received'), array_filter([
            'search'    => $f['q'],
            'status'    => $f['estado'],
            'date_from' => $f['desde'],
            'date_to'   => $f['hasta'],
            'page'      => $f['page'],
            'per_page'  => $f['per_page'],
        ]));
        $rows = [];
        foreach ((array) ($data['data'] ?? []) as $o) {
            if (is_array($o) && !empty($o['id'])) {
                $rows[] = self::row($o);
            }
        }
        $pag = (array) ($data['pagination'] ?? []);
        $statuses = ['' => __('Todos', 'motorlan-portal')];
        foreach (self::statuses() as $k => $s) {
            $statuses[$k] = $s['filter'];
        }
        $per_page = [];
        foreach (self::PER_PAGE as $n) {
            $per_page[$n] = sprintf(__('%d por página', 'motorlan-portal'), $n);
        }
        $query = array_filter(['q' => $f['q'], 'estado' => $f['estado'], 'desde' => $f['desde'], 'hasta' => $f['hasta'], 'per_page' => $f['per_page'] !== 10 ? $f['per_page'] : null]);
        return [
            'page_title' => __('Ofertas recibidas', 'motorlan-portal'),
            'table'      => [
                'url'      => Urls::spa('dashboard/publications/offers-received'),
                'rows'     => $rows,
                'error'    => $data === null,
                'filters'  => $f,
                'page'     => (int) ($pag['currentPage'] ?? $f['page']),
                'pages'    => (int) ($pag['totalPages'] ?? 1),
                'total'    => (int) ($pag['total'] ?? 0),
                'statuses' => $statuses,
                'per_page' => $per_page,
                'query'    => http_build_query($query),
                'has_filters' => (bool) array_filter([$f['q'], $f['estado'], $f['desde'], $f['hasta']]),
            ],
            'nonce'      => wp_create_nonce(self::PANEL_NONCE),
        ];
    }

    public static function row(array $o): array
    {
        $status = (string) ($o['status'] ?? 'pending');
        $pid = (int) ($o['publication_id'] ?? 0);
        $slug = $pid ? (string) get_post_field('post_name', $pid) : '';
        // Antes 'Y-m-d H:i:s' en hora local; desde el PR #158, ISO 8601 con zona. Se aceptan ambos.
        $raw = (string) ($o['offer_date'] ?? '');
        $ts = $raw === '' ? false : strtotime(preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $raw) ? get_gmt_from_date($raw) . ' UTC' : $raw);
        $secs = isset($o['time_to_expire']) && is_numeric($o['time_to_expire']) ? (int) $o['time_to_expire'] : null;
        $can_accept = !empty($o['can_accept']) && in_array($status, ['pending', 'expired'], true);
        return [
            'id'          => (int) $o['id'],
            'title'       => html_entity_decode((string) ($o['publication_title'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'reference'   => $pid && function_exists('get_field') ? (string) (get_field('tipo_o_referencia', $pid) ?: '') : '',
            'user'        => (string) ($o['user_name'] ?? ''),
            'amount'      => is_numeric($o['offer_amount'] ?? null) ? number_format_i18n((float) $o['offer_amount'], 2) . ' €' : '—',
            'date'        => $ts ? wp_date('j M Y', $ts) : '—',
            'datetime'    => $ts ? wp_date('j M Y, H:i', $ts) : '—',
            'status'      => $status,
            'chip'        => self::status($status),
            'message'     => trim((string) ($o['justification'] ?? '')),
            'remaining'   => $status === 'accepted_pending_confirmation' ? self::remaining($secs) : '',
            'can_accept'  => $can_accept,
            // La API rechaza cualquier oferta salvo las confirmadas (y no tiene sentido repetir).
            'can_reject'  => !in_array($status, ['confirmed', 'rejected'], true),
            'blocked'     => !$can_accept && in_array($status, ['pending', 'expired'], true)
                ? ((string) ($o['accept_disabled_reason'] ?? '') ?: __('Sin disponibilidad de stock o publicación inactiva.', 'motorlan-portal')) : '',
            'pub_url'     => $slug !== '' ? SpaPages::product_url($slug) : '',
        ];
    }

    protected static function remaining(?int $secs): string
    {
        if ($secs === null) {
            return '—';
        }
        if ($secs <= 0) {
            return __('Expirada', 'motorlan-portal');
        }
        $min = (int) ceil($secs / 60);
        $h = intdiv($min, 60);
        $m = $min % 60;
        return $h > 0 ? ($m > 0 ? sprintf('%d h %d min', $h, $m) : sprintf('%d h', $h)) : sprintf('%d min', $m);
    }

    /** Oferta de ?offer_id: en la página cargada o buscándola (100 por página, 5 páginas). */
    protected function find(int $id, array $rows): ?array
    {
        foreach ($rows as $r) {
            if ($r['id'] === $id) {
                return $r;
            }
        }
        for ($page = 1; $page <= 5; $page++) {
            $data = Api::get(Api::route('offers/received'), ['page' => $page, 'per_page' => 100]);
            foreach ((array) ($data['data'] ?? []) as $o) {
                if (is_array($o) && (int) ($o['id'] ?? 0) === $id) {
                    return self::row($o);
                }
            }
            if ($data === null || $page >= (int) ($data['pagination']['totalPages'] ?? 1)) {
                break;
            }
        }
        return null;
    }
}
