<?php

namespace MotorlanPortal\Controllers;

use MotorlanPortal\Support\Api;
use MotorlanPortal\Support\Publicacion;
use MotorlanPortal\Support\Seo;
use MotorlanPortal\Support\SpaPages;
use MotorlanPortal\Support\Store;
use MotorlanPortal\Support\Urls;
use MotorlanPortal\Support\View;

/**
 * Administración del panel (sección `admin`, auth admin = manage_options; la API exige
 * además el rol administrator en cada endpoint):
 *
 *  - /mi-cuenta/dashboard/admin/publications (admin/publications/index.vue +
 *    ContactPublisherModal.vue): todas las publicaciones salvo las del propio admin
 *    (GET /admin/publications), con búsqueda, estado, orden y paginación por HTMX.
 *    Cambiar estado (PUT; «Papelera» es recuperable), eliminar (DELETE: definitivo, con
 *    confirmación) y escribir al anunciante
 *    (POST …/contact): POST a la propia URL con `action` y la tabla de nuevo.
 *  - /mi-cuenta/dashboard/admin/approvals (admin/approvals/index.vue): pendientes
 *    (GET /admin/pending-publications), aprobar y rechazar (con motivo y confirmación).
 *  - /mi-cuenta/dashboard/admin/approvals/preview/{uuid} (preview/[uuid].vue): la ficha
 *    (GET /publicaciones/uuid/{uuid}) con los mismos parciales que la tienda, aprobar,
 *    rechazar y corregir la marca propuesta (POST /admin/update-pending-brand/{id}).
 *    Sin barra inferior en móvil, como la SPA.
 *
 * Formularios: nonce `_mlnonce` (o X-WP-Nonce con HTMX). Con HTMX responde el parcial y
 * un aviso en HX-Trigger (X-Ml-Done: 1 si salió bien, para cerrar el diálogo); sin JS la
 * página entera. Aprobar/rechazar desde la vista previa vuelve al listado con ?ok=….
 */
class AdminController extends Controller
{
    const PER_PAGE = [10, 20, 25, 50];
    const PENDING_PER_PAGE = 10;
    const DEFAULT_SUBJECT = 'Mensaje del Administrador - Motorlan';

    /** Orden de la tabla (cabeceras ordenables de la SPA: título, autor, precio, estado, fecha). */
    const SORTS = [
        'date-desc'  => ['date', 'DESC'],
        'date-asc'   => ['date', 'ASC'],
        'title-asc'  => ['title', 'ASC'],
        'title-desc' => ['title', 'DESC'],
        'price-desc' => ['price', 'DESC'],
        'price-asc'  => ['price', 'ASC'],
        'author-asc' => ['author', 'ASC'],
        'status-asc' => ['status', 'ASC'],
    ];

    public function handle(string $param): void
    {
        if ($param === 'admin/publications') {
            $this->publications();
            return;
        }
        if ($param === 'admin/approvals') {
            $this->approvals();
            return;
        }
        if (preg_match('#^admin/approvals/preview/([A-Za-z0-9-]+)$#', $param, $m)) {
            $this->preview($m[1]);
            return;
        }
        $this->not_found();
    }

    /**
     * Estados con su chip, como la tabla de la SPA (admin_publications.status_options y
     * resolveStatus). Incluye la papelera, que el resto del panel no enseña.
     */
    public static function statuses(): array
    {
        return [
            'publish' => ['label' => __('Publicado', 'motorlan-portal'), 'color' => 'success'],
            'pending' => ['label' => __('Pendiente', 'motorlan-portal'), 'color' => 'warning'],
            'draft'   => ['label' => __('Borrador', 'motorlan-portal'), 'color' => 'secondary'],
            'paused'  => ['label' => __('Pausado', 'motorlan-portal'), 'color' => 'info'],
            'sold'    => ['label' => __('Vendido', 'motorlan-portal'), 'color' => 'success'],
            'trash'   => ['label' => __('Papelera', 'motorlan-portal'), 'color' => 'error'],
        ];
    }

    protected static function status_chip(string $status): array
    {
        return self::statuses()[$status] ?? ['label' => $status !== '' ? $status : '—', 'color' => 'primary'];
    }

    // ── Publicaciones ───────────────────────────────────────────────────────────────────

    protected function publications(): void
    {
        $result = $this->is_post() ? $this->publication_action() : null;
        $context = $this->publications_context($this->publication_filters()) + ['flash' => $result];

        if ($this->is_htmx()) {
            View::html_response(View::compile('partials/admin-publicaciones-tabla.twig', $context, true), 200, $this->result_headers($result) + ['Vary' => 'HX-Request']);
        }

        Seo::private_page($context['page_title']);
        $this->render('pages/admin-publicaciones.twig', $context);
    }

    /** Cambiar estado, eliminar o escribir al anunciante. */
    protected function publication_action(): array
    {
        $action = $this->field('action');
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$this->valid_nonce() || !$id || !in_array($action, ['status', 'delete', 'contact'], true)) {
            return self::fail(__('La página ha caducado. Recárgala e inténtalo de nuevo.', 'motorlan-portal'));
        }
        $route = Api::route('admin/publications/' . $id);

        if ($action === 'status') {
            $status = $this->field('status');
            if (!isset(self::statuses()[$status])) {
                return self::fail(__('Elige un estado válido.', 'motorlan-portal'));
            }
            $r = Api::call('PUT', $route, [], ['status' => $status]);
            return $r['ok'] ? self::ok(__('Estado actualizado', 'motorlan-portal'))
                : self::fail(__('Error al cambiar el estado', 'motorlan-portal'), $r);
        }

        if ($action === 'delete') {
            $r = Api::call('DELETE', $route);
            return $r['ok'] ? self::ok(__('Publicación eliminada', 'motorlan-portal'))
                : self::fail(__('Error al eliminar la publicación', 'motorlan-portal'), $r);
        }

        $subject = mb_substr($this->field('subject'), 0, 200);
        $message = isset($_POST['message']) && is_string($_POST['message']) ? trim(sanitize_textarea_field(wp_unslash($_POST['message']))) : '';
        if ($message === '') {
            return self::fail(__('El mensaje es obligatorio', 'motorlan-portal'));
        }
        $r = Api::call('POST', $route . '/contact', [], [
            'subject' => $subject !== '' ? $subject : self::DEFAULT_SUBJECT,
            'message' => mb_substr($message, 0, 5000),
        ]);
        return $r['ok'] ? self::ok(__('Mensaje enviado al anunciante', 'motorlan-portal'))
            : self::fail(__('Error al enviar el mensaje', 'motorlan-portal'), $r);
    }

    /** Filtros de la query (el POST de las acciones los reenvía con hx-include). */
    protected function publication_filters(): array
    {
        $src = $this->is_post() ? wp_unslash($_POST) : wp_unslash($_GET);
        $get = function ($k) use ($src) { return isset($src[$k]) && is_string($src[$k]) ? trim(sanitize_text_field($src[$k])) : ''; };
        $status = $get('estado');
        $sort = $get('orden');
        $per_page = (int) $get('per_page');
        return [
            'q'        => mb_substr($get('q'), 0, 100),
            'estado'   => isset(self::statuses()[$status]) ? $status : '',
            'orden'    => isset(self::SORTS[$sort]) ? $sort : 'date-desc',
            'per_page' => in_array($per_page, self::PER_PAGE, true) ? $per_page : 10,
            'page'     => max(1, (int) $get('page')),
        ];
    }

    protected function publications_context(array $f): array
    {
        [$orderby, $order] = self::SORTS[$f['orden']];
        $data = Api::get(Api::route('admin/publications'), array_filter([
            'search'         => $f['q'],
            'status'         => $f['estado'],
            'page'           => $f['page'],
            'per_page'       => $f['per_page'],
            'orderby'        => $orderby,
            'order'          => $order,
            // Como la SPA: el admin no ve aquí sus propias publicaciones (están en «Mis publicaciones»).
            'exclude_author' => get_current_user_id(),
        ]));
        $error = !is_array($data);
        $rows = [];
        foreach ((array) ($data['data'] ?? []) as $p) {
            if (is_array($p) && !empty($p['id'])) {
                $rows[] = self::publication_row($p);
            }
        }
        // Al eliminar el último de la última página: a la anterior (fuera de rango la API
        // responde total 0, igual que en favoritos).
        if (!$rows && !$error && $f['page'] > 1) {
            return $this->publications_context(['page' => $f['page'] - 1] + $f);
        }

        $statuses = ['' => __('Todos los estados', 'motorlan-portal')];
        foreach (self::statuses() as $k => $s) {
            $statuses[$k] = $s['label'];
        }
        $per_page = [];
        foreach (self::PER_PAGE as $n) {
            $per_page[$n] = sprintf(__('%d por página', 'motorlan-portal'), $n);
        }
        $sorts = [
            'date-desc'  => __('Más recientes', 'motorlan-portal'),
            'date-asc'   => __('Más antiguas', 'motorlan-portal'),
            'title-asc'  => __('Título (A-Z)', 'motorlan-portal'),
            'title-desc' => __('Título (Z-A)', 'motorlan-portal'),
            'price-desc' => __('Precio: mayor a menor', 'motorlan-portal'),
            'price-asc'  => __('Precio: menor a mayor', 'motorlan-portal'),
            'author-asc' => __('Autor', 'motorlan-portal'),
            'status-asc' => __('Estado', 'motorlan-portal'),
        ];

        return [
            'page_title' => __('Administrar publicaciones', 'motorlan-portal'),
            'table'      => [
                'url'      => Urls::spa('dashboard/admin/publications'),
                'rows'     => $rows,
                'error'    => $error,
                'filters'  => $f,
                'page'     => $f['page'],
                'pages'    => max(1, (int) ($data['pages'] ?? 1)),
                'total'    => (int) ($data['total'] ?? 0),
                'statuses' => $statuses,
                'sorts'    => $sorts,
                'per_page' => $per_page,
                'query'    => http_build_query(array_filter([
                    'q'        => $f['q'],
                    'estado'   => $f['estado'],
                    'orden'    => $f['orden'] !== 'date-desc' ? $f['orden'] : null,
                    'per_page' => $f['per_page'] !== 10 ? $f['per_page'] : null,
                ])),
            ],
            'status_options'  => array_map(function ($s) { return $s['label']; }, self::statuses()),
            'default_subject' => self::DEFAULT_SUBJECT,
            'nonce'           => wp_create_nonce(self::PANEL_NONCE),
        ];
    }

    /** Fila de GET /admin/publications. */
    protected static function publication_row(array $p): array
    {
        $status = (string) ($p['status'] ?? '');
        $author = isset($p['author']) && is_array($p['author']) ? $p['author'] : [];
        $uuid = (string) ($p['uuid'] ?? '');
        $time = !empty($p['date']) ? strtotime((string) $p['date']) : false;
        return [
            'id'     => (int) $p['id'],
            'name'   => Publicacion::name($p),
            'image'  => Store::image_url($p['image'] ?? null, 'thumbnail'),
            'author' => ['name' => (string) ($author['name'] ?? ''), 'email' => (string) ($author['email'] ?? '')],
            'price'  => self::price_label($p['price'] ?? null),
            'status' => $status,
            'chip'   => self::status_chip($status),
            'date'   => $time ? date_i18n('d/m/Y', $time) : '—',
            // «Ver» solo si está publicada (como la SPA); la ficha pública de una pausada o
            // vendida existe, pero dice «No disponible».
            'url'    => $status === 'publish' && !empty($p['slug']) ? SpaPages::product_url((string) $p['slug']) : '',
            'edit'   => $uuid !== '' ? Urls::spa('dashboard/publications/publication/edit/' . $uuid) : '',
        ];
    }

    // ── Aprobaciones ────────────────────────────────────────────────────────────────────

    protected function approvals(): void
    {
        $result = null;
        if ($this->is_post()) {
            $result = $this->approval_action();
        } else {
            // Vuelta desde la vista previa (aprobar/rechazar redirige aquí).
            $ok = $this->query('ok');
            if ($ok === 'approved') {
                $result = self::ok(__('Publicación aprobada con éxito', 'motorlan-portal'));
            } elseif ($ok === 'rejected') {
                $result = self::ok(__('Publicación rechazada', 'motorlan-portal'), 'info');
            }
        }
        $src = $this->is_post() ? wp_unslash($_POST) : wp_unslash($_GET);
        $page = isset($src['page']) && is_string($src['page']) ? max(1, (int) $src['page']) : 1;
        $context = $this->approvals_context($page) + ['flash' => $result];

        if ($this->is_htmx()) {
            View::html_response(View::compile('partials/admin-aprobaciones-tabla.twig', $context, true), 200, $this->result_headers($result) + ['Vary' => 'HX-Request']);
        }

        Seo::private_page($context['page_title']);
        $this->render('pages/admin-aprobaciones.twig', $context);
    }

    /** Aprobar o rechazar (desde el listado o desde la vista previa). */
    protected function approval_action(): array
    {
        $action = $this->field('action');
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$this->valid_nonce() || !$id || !in_array($action, ['approve', 'reject'], true)) {
            return self::fail(__('La página ha caducado. Recárgala e inténtalo de nuevo.', 'motorlan-portal'));
        }
        if ($action === 'approve') {
            $r = Api::call('POST', Api::route('admin/approve-publication/' . $id), [], []);
            return $r['ok'] ? self::ok(__('Publicación aprobada con éxito', 'motorlan-portal'))
                : self::fail(__('Error al aprobar la publicación', 'motorlan-portal'), $r);
        }
        $reason = mb_substr($this->field('reason'), 0, 500);
        if ($reason === '') {
            return self::fail(__('Indica el motivo del rechazo.', 'motorlan-portal'));
        }
        $r = Api::call('POST', Api::route('admin/reject-publication/' . $id), [], ['reason' => $reason]);
        return $r['ok'] ? self::ok(__('Publicación rechazada', 'motorlan-portal'), 'info')
            : self::fail(__('Error al rechazar la publicación', 'motorlan-portal'), $r);
    }

    protected function approvals_context(int $page): array
    {
        $data = Api::get(Api::route('admin/pending-publications'));
        $error = !is_array($data);
        $all = [];
        foreach ($error ? [] : $data as $p) {
            if (is_array($p) && !empty($p['id'])) {
                $all[] = self::pending_row($p);
            }
        }
        // La API no pagina: 10 por página, como la VDataTable de la SPA.
        $pages = max(1, (int) ceil(count($all) / self::PENDING_PER_PAGE));
        $page = min($page, $pages);

        return [
            'page_title' => __('Aprobaciones pendientes', 'motorlan-portal'),
            'table'      => [
                'url'   => Urls::spa('dashboard/admin/approvals'),
                'rows'  => array_slice($all, ($page - 1) * self::PENDING_PER_PAGE, self::PENDING_PER_PAGE),
                'error' => $error,
                'page'  => $page,
                'pages' => $pages,
                'total' => count($all),
            ],
            'nonce'      => wp_create_nonce(self::PANEL_NONCE),
        ];
    }

    /** Fila de GET /admin/pending-publications (motorlan_format_publication_item + author_info). */
    protected static function pending_row(array $p): array
    {
        $id = (int) $p['id'];
        $acf = isset($p['acf']) && is_array($p['acf']) ? $p['acf'] : [];
        // La API no devuelve precio_negociable en este listado: la SPA nunca enseñaba
        // «Consultar precio» aquí. Se lee del campo.
        if (!array_key_exists('precio_negociable', $acf) && function_exists('get_field')) {
            $acf['precio_negociable'] = get_field('precio_negociable', $id);
        }
        $author = isset($p['author_info']) && is_array($p['author_info']) ? $p['author_info'] : [];
        $uuid = (string) ($p['uuid'] ?? '');
        return [
            'id'            => $id,
            'name'          => Publicacion::name($p),
            'brand'         => Publicacion::brand($p),
            'pending_brand' => is_string($p['pending_brand'] ?? null) ? $p['pending_brand'] : '',
            'reference'     => is_scalar($acf['tipo_o_referencia'] ?? null) ? (string) $acf['tipo_o_referencia'] : '',
            'image'         => Store::image_url($p['imagen_destacada'] ?? null, 'thumbnail'),
            'author'        => ['name' => (string) ($author['name'] ?? ''), 'email' => (string) ($author['email'] ?? '')],
            'consult'       => Store::is_consult_price($acf),
            'price'         => self::price_label($acf['precio_de_venta'] ?? null),
            'preview'       => $uuid !== '' ? Urls::spa('dashboard/admin/approvals/preview/' . $uuid) : '',
            'edit'          => $uuid !== '' ? Urls::spa('dashboard/publications/publication/edit/' . $uuid) : '',
        ];
    }

    // ── Vista previa ────────────────────────────────────────────────────────────────────

    protected function preview(string $uuid): void
    {
        $list = Urls::spa('dashboard/admin/approvals');
        $self = Urls::spa('dashboard/admin/approvals/preview/' . $uuid);
        $result = null;

        if ($this->is_post()) {
            if ($this->field('action') === 'brand') {
                $result = $this->brand_action();
                if ($result['ok']) {
                    $this->redirect($self . '?ok=brand', 303);
                }
            } else {
                $result = $this->approval_action();
                if ($result['ok']) {
                    $this->redirect($list . '?ok=' . ($this->field('action') === 'approve' ? 'approved' : 'rejected'), 303);
                }
            }
        } elseif ($this->query('ok') === 'brand') {
            $result = self::ok(__('Nombre de marca actualizado', 'motorlan-portal'));
        }

        $r = Api::call('GET', Api::route('publicaciones/uuid/' . rawurlencode($uuid)));
        $p = $r['ok'] && is_array($r['data']) && !empty($r['data']['id']) ? $r['data'] : null;

        $context = [
            'page_title'      => __('Vista previa', 'motorlan-portal'),
            'hide_mobile_nav' => true,
            'list_url'        => $list,
            'self_url'        => $self,
            'flash'           => $result,
            'nonce'           => wp_create_nonce(self::PANEL_NONCE),
            'product'         => null,
        ];
        Seo::private_page($context['page_title']);

        if (!$p) {
            status_header(404);
            $this->render('pages/admin-vista-previa.twig', $context);
            return;
        }

        $product = Store::product($p, get_current_user_id());
        // El admin revisa: ve el precio aunque sea «Consultar precio» (como referencia, igual
        // que el dueño). La SPA se lo ocultaba. Sin acciones de comprador (favorito, chat).
        $product['show_price'] = $product['price'] !== null;
        $product['reference'] = $product['consult'];
        $product['actions'] = false;

        // La marca propuesta no viene en GET /publicaciones/uuid/{uuid} (la SPA leía
        // `pending_brand` de esa respuesta y el aviso no salía nunca): se lee del meta.
        $pending = is_string($p['pending_brand'] ?? null) ? $p['pending_brand'] : (string) get_post_meta($product['id'], '_pending_brand_name', true);
        if ($pending !== '' && $product['brand'] === '-') {
            $product['brand'] = mb_strtoupper($pending);
        }
        $status = (string) ($p['status'] ?? '');

        $this->render('pages/admin-vista-previa.twig', [
            'page_title'    => $product['title'] !== '' ? $product['title'] : $context['page_title'],
            'product'       => $product,
            'pending_brand' => $pending,
            'status'        => ['key' => $status, 'chip' => self::status_chip($status)],
            'edit_url'      => Urls::spa('dashboard/publications/publication/edit/' . $uuid),
        ] + $context);
    }

    /** Corregir el nombre de la marca propuesta antes de aprobar. */
    protected function brand_action(): array
    {
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $name = mb_strtoupper(mb_substr($this->field('brand_name'), 0, 100));
        if (!$this->valid_nonce() || !$id) {
            return self::fail(__('La página ha caducado. Recárgala e inténtalo de nuevo.', 'motorlan-portal'));
        }
        if ($name === '') {
            return self::fail(__('Escribe el nombre de la marca.', 'motorlan-portal'));
        }
        $r = Api::call('POST', Api::route('admin/update-pending-brand/' . $id), [], ['brand_name' => $name]);
        return $r['ok'] ? self::ok(__('Nombre de marca actualizado', 'motorlan-portal'))
            : self::fail(__('Error al actualizar la marca', 'motorlan-portal'), $r);
    }

    // ── Comunes ─────────────────────────────────────────────────────────────────────────

    protected static function price_label($price): string
    {
        if (!is_numeric($price) || (float) $price <= 0) {
            return '';
        }
        $v = (float) $price;
        return number_format_i18n($v, floor($v) == $v ? 0 : 2) . ' €';
    }

    protected static function ok(string $text, string $color = 'success'): array
    {
        return ['ok' => true, 'text' => $text, 'color' => $color];
    }

    /** Error con el motivo de la API si lo da (p. ej. «Error al crear la marca: …»). */
    protected static function fail(string $text, ?array $api = null): array
    {
        $msg = $api && !empty($api['error']['message']) ? (string) $api['error']['message'] : '';
        return ['ok' => false, 'text' => $msg !== '' ? $text . ': ' . $msg : $text, 'color' => 'error'];
    }

    /** Aviso (HX-Trigger) y, si salió bien, X-Ml-Done para que el diálogo se cierre. */
    protected function result_headers(?array $result): array
    {
        if (!$result) {
            return [];
        }
        return self::toast_header($result['text'], $result['color']) + ($result['ok'] ? ['X-Ml-Done' => '1'] : []);
    }
}
