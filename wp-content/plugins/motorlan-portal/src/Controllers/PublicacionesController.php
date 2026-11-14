<?php

namespace MotorlanPortal\Controllers;

use MotorlanPortal\Support\Api;
use MotorlanPortal\Support\Flash;
use MotorlanPortal\Support\Publicacion;
use MotorlanPortal\Support\PublicacionForm;
use MotorlanPortal\Support\Seo;
use MotorlanPortal\Support\SpaPages;
use MotorlanPortal\Support\Store;
use MotorlanPortal\Support\Urls;
use MotorlanPortal\Support\View;

/**
 * Sección `publicaciones` (vendedor): /mi-cuenta/dashboard/publications/publication/…
 *
 *  - list         Mis publicaciones (publication/list/index.vue): GET /my-publications con
 *                 búsqueda, estado, tipo, orden, por página y página (formulario GET; con
 *                 HTMX solo el parcial). Acciones por POST a esta URL: cambiar estado
 *                 (POST /publicaciones/{id}/status), borrar (DELETE /publicaciones/{id}),
 *                 borrar seleccionadas (POST /publicaciones/bulk-delete), duplicar
 *                 (POST /publicaciones/duplicate/{id} → editor de la copia). ?export=csv
 *                 descarga las publicaciones del filtro (todas las páginas).
 *  - add          Asistente de alta (publication/add/index.vue): POST multipart a esta URL →
 *                 POST /publicaciones con Api::multipart(). Queda en revisión (la API pasa
 *                 publish → pending a quien no es admin).
 *  - edit/{uuid}  Editor por pestañas (publication/edit/[uuid].vue): GET y POST multipart
 *                 /publicaciones/uuid/{uuid}. El permiso (autor o admin) lo decide la API.
 *
 * Alta y edición pintan los mismos bloques (views/partials/publicacion/*.twig). Con JS
 * (mlPubForm, assets/js/vendedor.js) se valida por paso, se reducen las fotos y se avisa de
 * los límites de subida; la respuesta es JSON. Sin JS el formulario se envía tal cual
 * (todos los pasos visibles) y se responde la página.
 */
class PublicacionesController extends Controller
{
    const PER_PAGE = [5, 10, 20, 25, 50];

    /** Orden (cabeceras ordenables de la SPA) → orderby/order de /my-publications. */
    const ORDERS = [
        ''           => ['', ''],
        'title-asc'  => ['title', 'asc'],
        'title-desc' => ['title', 'desc'],
        'ref-asc'    => ['tipo_o_referencia', 'asc'],
        'price-asc'  => ['price', 'asc'],
        'price-desc' => ['price', 'desc'],
        'status-asc' => ['post_status', 'asc'],
    ];

    public function handle(string $param): void
    {
        $route = trim($param, '/');
        if ($route === 'publications/publication/list') {
            $this->list();
            return;
        }
        if ($route === 'publications/publication/add') {
            $this->form('add', '');
            return;
        }
        if (preg_match('#^publications/publication/edit/([A-Za-z0-9-]+)$#', $route, $m)) {
            $this->form('edit', $m[1]);
            return;
        }
        $this->not_found();
    }

    // ── Listado ──────────────────────────────────────────────────────────────────────────

    protected function list(): void
    {
        $headers = [];
        if ($this->is_post()) {
            $headers = $this->list_action();
        }
        $f = $this->filters();
        if (!$this->is_post() && $this->query('export') === 'csv') {
            $this->export($f);
        }
        if ($this->is_post() && !$this->is_htmx()) {
            // Sin JS: aviso + redirección (POST/redirect/GET), con los filtros que venían.
            $toast = json_decode($headers['HX-Trigger'] ?? '', true)['ml:toast'] ?? null;
            if ($toast) {
                Flash::set((string) $toast['text'], (string) $toast['color']);
            }
            $this->redirect($this->list_url($f), 303);
        }
        $context = $this->list_context($f);
        if ($this->is_htmx()) {
            View::html_response(View::compile('partials/publicaciones-tabla.twig', $context, true), 200, $headers + ['Vary' => 'HX-Request']);
        }
        Seo::private_page($context['page_title']);
        $this->render('pages/publicaciones.twig', $context);
    }

    /** Acciones del listado. Devuelve las cabeceras del aviso (o redirige al duplicar). */
    protected function list_action(): array
    {
        if (!$this->valid_nonce()) {
            return self::toast_header(__('La página ha caducado. Recárgala e inténtalo de nuevo.', 'motorlan-portal'), 'error');
        }
        $action = $this->field('action');
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

        if ($action === 'status' && $id) {
            $status = $this->field('status');
            if (!in_array($status, ['publish', 'paused', 'draft'], true)) {
                return self::toast_header(__('Estado no válido.', 'motorlan-portal'), 'error');
            }
            $r = Api::call('POST', Api::route('publicaciones/' . $id . '/status'), [], ['status' => $status]);
            if (!$r['ok']) {
                return self::toast_header($this->api_message($r, __('No se pudo completar la acción', 'motorlan-portal')), 'error');
            }
            if ($status === 'publish' && ($r['data']['new_status'] ?? '') === 'pending') {
                return self::toast_header(__('Enviada a revisión. Quedará visible cuando un administrador la apruebe.', 'motorlan-portal'), 'info');
            }
            $done = ['publish' => __('Publicación publicada', 'motorlan-portal'), 'paused' => __('Publicación pausada', 'motorlan-portal'), 'draft' => __('Publicación movida a borradores', 'motorlan-portal')];
            return self::toast_header($done[$status]);
        }

        if ($action === 'delete' && $id) {
            $r = Api::call('DELETE', Api::route('publicaciones/' . $id));
            return $r['ok']
                ? self::toast_header(__('Publicación eliminada', 'motorlan-portal'))
                : self::toast_header($this->api_message($r, __('No se pudo eliminar la publicación', 'motorlan-portal')), 'error');
        }

        if ($action === 'bulk-delete') {
            $ids = array_values(array_unique(array_filter(array_map('absint', (array) ($_POST['ids'] ?? [])))));
            if (!$ids) {
                return self::toast_header(__('No hay publicaciones seleccionadas.', 'motorlan-portal'), 'warning');
            }
            $r = Api::call('POST', Api::route('publicaciones/bulk-delete'), [], ['ids' => array_slice($ids, 0, 100)]);
            if (!$r['ok']) {
                return self::toast_header($this->api_message($r, __('No se pudieron eliminar las publicaciones', 'motorlan-portal')), 'error');
            }
            // Algunas no se borran si tienen compras: el mensaje del backend lo explica.
            if ((int) ($r['data']['skipped_purchases'] ?? 0) > 0) {
                return self::toast_header((string) ($r['data']['message'] ?? ''), 'warning');
            }
            return self::toast_header(sprintf(_n('%d publicación eliminada', '%d publicaciones eliminadas', count($ids), 'motorlan-portal'), (int) ($r['data']['deleted'] ?? count($ids))));
        }

        if ($action === 'duplicate' && $id) {
            // La API exige Content-Type: application/json (con cuerpo vacío daba «Content-Type must be application/json»).
            $r = Api::call('POST', Api::route('publicaciones/duplicate/' . $id), [], []);
            $uuid = $r['ok'] ? (string) ($r['data']['uuid'] ?? '') : '';
            if ($uuid === '') {
                return self::toast_header($this->api_message($r, __('No se pudo duplicar la publicación', 'motorlan-portal')), 'error');
            }
            Flash::set(__('Copia creada como borrador. Revísala antes de publicarla.', 'motorlan-portal'));
            $this->redirect(Urls::spa('dashboard/publications/publication/edit/' . $uuid), 303);
        }

        return self::toast_header(__('Acción no válida.', 'motorlan-portal'), 'error');
    }

    protected function filters(): array
    {
        $src = $this->is_post() ? wp_unslash($_POST) : wp_unslash($_GET);
        $get = function ($k) use ($src) { return isset($src[$k]) && is_string($src[$k]) ? trim(sanitize_text_field($src[$k])) : ''; };
        $status = $get('estado');
        $per_page = (int) $get('per_page');
        $orden = $get('orden');
        return [
            'q'        => mb_substr($get('q'), 0, 100),
            'estado'   => isset(Publicacion::statuses()[$status]) ? $status : '',
            'tipo'     => sanitize_title($get('tipo')),
            'orden'    => isset(self::ORDERS[$orden]) ? $orden : '',
            'per_page' => in_array($per_page, self::PER_PAGE, true) ? $per_page : 10,
            'page'     => max(1, (int) $get('page')),
        ];
    }

    protected function api_query(array $f, int $page, int $per_page): array
    {
        [$orderby, $order] = self::ORDERS[$f['orden']];
        return array_filter([
            'search'   => $f['q'],
            'status'   => $f['estado'],
            'tipo'     => $f['tipo'],
            'orderby'  => $orderby,
            'order'    => $order,
            'page'     => $page,
            'per_page' => $per_page,
        ]);
    }

    protected function list_context(array $f): array
    {
        $data = Api::get(Api::route('my-publications'), $this->api_query($f, $f['page'], $f['per_page']));
        $error = $data === null;
        $pagination = is_array($data) ? (array) ($data['pagination'] ?? []) : [];
        $rows = [];
        foreach ((array) ($data['data'] ?? []) as $p) {
            if (is_array($p) && !empty($p['id'])) {
                $rows[] = self::list_row($p);
            }
        }
        // Al borrar lo último de la última página, a la anterior (la SPA la dejaba vacía).
        if (!$rows && !$error && $f['page'] > 1) {
            return $this->list_context(['page' => $f['page'] - 1] + $f);
        }

        $statuses = ['' => __('Estado', 'motorlan-portal')];
        foreach (Publicacion::statuses() as $k => $s) {
            $statuses[$k] = $s['label'];
        }
        $tipos = ['' => __('Tipo', 'motorlan-portal')];
        foreach ((array) Api::get(Api::route('tipos')) as $t) {
            if (is_array($t) && !empty($t['slug'])) {
                $tipos[(string) $t['slug']] = (string) ($t['name'] ?? $t['slug']);
            }
        }
        $orders = [
            ''           => __('Más recientes', 'motorlan-portal'),
            'title-asc'  => __('Publicación (A-Z)', 'motorlan-portal'),
            'title-desc' => __('Publicación (Z-A)', 'motorlan-portal'),
            'ref-asc'    => __('Referencia', 'motorlan-portal'),
            'price-asc'  => __('Precio: menor a mayor', 'motorlan-portal'),
            'price-desc' => __('Precio: mayor a menor', 'motorlan-portal'),
            'status-asc' => __('Estado', 'motorlan-portal'),
        ];
        $per_page = [];
        foreach (self::PER_PAGE as $n) {
            $per_page[$n] = sprintf(__('%d por página', 'motorlan-portal'), $n);
        }
        $base = Urls::spa('dashboard/publications/publication/list');
        $query = self::list_query($f);

        return [
            'page_title' => __('Publicaciones', 'motorlan-portal'),
            'table'      => [
                'url'      => $base,
                'rows'     => $rows,
                'error'    => $error,
                'filters'  => $f,
                'page'     => (int) ($pagination['currentPage'] ?? $f['page']),
                'pages'    => (int) ($pagination['totalPages'] ?? 1),
                'total'    => (int) ($pagination['total'] ?? 0),
                'statuses' => $statuses,
                'tipos'    => $tipos,
                'orders'   => $orders,
                'per_page' => $per_page,
                'query'    => http_build_query($query),
                'add_url'  => Urls::spa('dashboard/publications/publication/add'),
            ],
            'urls_vendor' => [
                'add'    => Urls::spa('dashboard/publications/publication/add'),
                'export' => $base . '?' . http_build_query($query + ['export' => 'csv']),
            ],
            'is_admin'   => current_user_can('manage_options'),
            'nonce'      => wp_create_nonce(self::PANEL_NONCE),
            'flash'      => $this->is_htmx() ? null : Flash::pull(),
        ];
    }

    protected static function list_query(array $f): array
    {
        return array_filter(['q' => $f['q'], 'estado' => $f['estado'], 'tipo' => $f['tipo'], 'orden' => $f['orden'], 'per_page' => $f['per_page'] !== 10 ? $f['per_page'] : null]);
    }

    protected function list_url(array $f): string
    {
        $q = self::list_query($f) + ($f['page'] > 1 ? ['page' => $f['page']] : []);
        return Urls::spa('dashboard/publications/publication/list') . ($q ? '?' . http_build_query($q) : '');
    }

    /** Fila del listado: la de Publicacion::row() + precio, editor y acciones posibles. */
    public static function list_row(array $p): array
    {
        $row = Publicacion::row($p);
        $acf = isset($p['acf']) && is_array($p['acf']) ? $p['acf'] : [];
        $price = isset($acf['precio_de_venta']) && is_scalar($acf['precio_de_venta']) ? trim((string) $acf['precio_de_venta']) : '';
        $is_admin = current_user_can('manage_options');
        $status = $row['status'];
        // En revisión solo la toca el admin (canEdit de la SPA; la API también lo impide).
        $can_edit = $is_admin || $status !== 'pending';
        $actions = [];
        if ($can_edit) {
            if ($status !== 'publish') {
                $actions[] = 'publish';
            }
            if ($status !== 'paused') {
                $actions[] = 'paused';
            }
            if ($status !== 'draft') {
                $actions[] = 'draft';
            }
        }
        return $row + [
            'consult'  => Store::is_consult_price($acf),
            'price'    => $price !== '' && is_numeric($price) ? self::money((float) $price) : '',
            'edit_url' => $row['uuid'] !== '' ? Urls::spa('dashboard/publications/publication/edit/' . $row['uuid']) : '',
            'can_edit' => $can_edit,
            'actions'  => $actions,
            'link'     => (string) ($p['link'] ?? ''),
        ];
    }

    public static function money(float $v): string
    {
        $dec = abs($v - round($v)) > 0.001 ? 2 : 0;
        return number_format_i18n($v, $dec) . ' €';
    }

    /** CSV de las publicaciones del filtro (exportPublicaciones de la SPA): todas las páginas. */
    protected function export(array $f): void
    {
        $rows = [];
        $page = 1;
        $pages = 1;
        do {
            $data = Api::get(Api::route('my-publications'), $this->api_query($f, $page, 100));
            foreach ((array) ($data['data'] ?? []) as $p) {
                if (is_array($p) && !empty($p['id'])) {
                    $rows[] = $p;
                }
            }
            $pages = (int) ($data['pagination']['totalPages'] ?? 1);
            $page++;
        } while ($data !== null && $page <= $pages && $page <= 50);

        $cell = function ($v) {
            $s = $v === null ? '' : (string) $v;
            // Evita que Excel interprete fórmulas (=, +, -, @) en los textos del usuario.
            if ($s !== '' && strpos('=+-@', $s[0]) !== false && !is_numeric($s)) {
                $s = "'" . $s;
            }
            return preg_match('/[";\n\r]/', $s) ? '"' . str_replace('"', '""', $s) . '"' : $s;
        };
        $lines = [implode(';', array_map($cell, ['ID', __('Publicación', 'motorlan-portal'), __('Referencia', 'motorlan-portal'), __('Marca', 'motorlan-portal'), __('Precio', 'motorlan-portal'), __('Estado', 'motorlan-portal'), 'URL']))];
        foreach ($rows as $p) {
            $r = Publicacion::row($p);
            $acf = (array) ($p['acf'] ?? []);
            $lines[] = implode(';', array_map($cell, [$r['id'], $r['name'], $r['reference'], $r['brand'],
                is_scalar($acf['precio_de_venta'] ?? null) ? $acf['precio_de_venta'] : '', $r['chip']['label'],
                $r['status'] === 'publish' ? ($p['link'] ?? $r['url']) : '']));
        }
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="publicaciones-' . gmdate('Y-m-d') . '.csv"');
        echo "\xEF\xBB\xBF" . implode("\r\n", $lines);
        exit;
    }

    // ── Alta y edición ───────────────────────────────────────────────────────────────────

    protected function form(string $mode, string $uuid): void
    {
        $catalogs = PublicacionForm::catalogs();
        $pub = null;
        if ($mode === 'edit') {
            $r = Api::call('GET', Api::route('publicaciones/uuid/' . $uuid));
            if (!$r['ok'] || empty($r['data']['id'])) {
                // 403 (no es tuya) o 404: la SPA avisaba y volvía al listado.
                Flash::set($r['status'] === 403 ? __('No tienes permisos para editar esta publicación.', 'motorlan-portal') : __('Publicación no encontrada', 'motorlan-portal'), 'error');
                $this->redirect(Urls::spa('dashboard/publications/publication/list'));
            }
            $pub = $r['data'];
        }
        $values = $pub ? PublicacionForm::from_publication($pub) : PublicacionForm::defaults();
        $result = null;
        if ($this->is_post()) {
            $result = $this->submit($mode, $uuid, $catalogs, $values);
            $values = $result['values'];
        }
        $context = $this->form_context($mode, $uuid, $pub, $values, $catalogs, $result);
        Seo::private_page($context['page_title']);
        $this->render('pages/publicacion-form.twig', $context);
    }

    /**
     * Envía el formulario a la API. Con JS (Accept: application/json) responde JSON y sale;
     * sin JS devuelve el resultado para volver a pintar la página (o redirige si fue bien).
     */
    protected function submit(string $mode, string $uuid, array $catalogs, array $values): array
    {
        $json = isset($_SERVER['HTTP_ACCEPT']) && strpos((string) $_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
        $fail = function (string $message, string $code = '', int $status = 400) use ($json, $values) {
            if ($json) {
                wp_send_json(['ok' => false, 'message' => $message, 'code' => $code, 'step' => PublicacionForm::step_for_error($code)], $status);
            }
            return ['ok' => false, 'message' => $message, 'code' => $code, 'step' => PublicacionForm::step_for_error($code), 'values' => $values];
        };

        if (PublicacionForm::payload_truncated()) {
            $max = size_format(PublicacionForm::upload_limits()['maxPost']);
            return $fail(sprintf(__('Los archivos superan el tamaño máximo permitido (%s en total). Reduce el número o el tamaño de las imágenes. No se ha guardado nada.', 'motorlan-portal'), $max), 'payload_too_large', 413);
        }
        if (!$this->valid_nonce()) {
            return $fail(__('La página ha caducado. Recárgala e inténtalo de nuevo.', 'motorlan-portal'), 'stale_nonce', 403);
        }
        $post = wp_unslash($_POST);
        $values = PublicacionForm::from_post($post, $values);

        if ($mode === 'add') {
            $status = 'publish'; // la API lo deja en revisión si no eres admin
        } else {
            // «Guardar borrador» o «Actualizar» (pausadas/vendidas siguen igual; si no, publicar → revisión).
            $current = (string) ($values['status'] ?? '');
            $status = $this->field('submit_status') === 'draft' ? 'draft' : (in_array($current, ['paused', 'sold'], true) ? $current : 'publish');
        }
        [$fields, $files] = PublicacionForm::api_request($post, $_FILES, $mode, $catalogs, $status);
        $route = $mode === 'add' ? 'publicaciones' : 'publicaciones/uuid/' . $uuid;
        $r = Api::multipart(Api::route($route), $fields, $files);

        if (!$r['ok']) {
            $prefix = $mode === 'add' ? __('Error al crear la publicación', 'motorlan-portal') : __('Error al guardar la publicación', 'motorlan-portal');
            $reason = (string) ($r['error']['message'] ?? '');
            $res = $fail($reason !== '' ? $prefix . ': ' . $reason : $prefix, (string) ($r['error']['code'] ?? ''), $r['status'] ?: 400);
            $res['values'] = $values;
            return $res;
        }

        $out = (array) $r['data'];
        $errors = array_values(array_filter((array) ($out['upload_errors'] ?? []), 'is_string'));
        if ($mode === 'add') {
            if (($out['status'] ?? '') === 'draft') {
                [$msg, $color] = [__('No se pudo subir la imagen principal: se ha guardado como borrador. Edítala para añadirla y enviarla a revisión.', 'motorlan-portal'), 'warning'];
            } elseif ($errors) {
                [$msg, $color] = [__('Publicación creada, pero algunos archivos no se subieron. Puedes añadirlos editándola.', 'motorlan-portal'), 'warning'];
            } elseif (($out['status'] ?? '') === 'pending') {
                [$msg, $color] = [__('Publicación enviada. Quedará visible cuando un administrador la apruebe.', 'motorlan-portal'), 'success'];
            } else {
                [$msg, $color] = [__('Publicación creada con éxito', 'motorlan-portal'), 'success'];
            }
            $to = Urls::spa('dashboard/publications/publication/list');
        } else {
            if ($errors) {
                [$msg, $color] = [sprintf(__('Publicación actualizada, pero algunos archivos no se subieron: %s', 'motorlan-portal'), implode(' · ', $errors)), 'warning'];
            } elseif (($out['status'] ?? '') === 'pending' && $status !== 'pending') {
                [$msg, $color] = [__('Cambios guardados. La publicación queda en revisión hasta que un administrador la apruebe.', 'motorlan-portal'), 'info'];
            } else {
                [$msg, $color] = [__('Publicación actualizada', 'motorlan-portal'), 'success'];
            }
            // Se vuelve a cargar el editor: imágenes y documentos con sus ids nuevos.
            $to = Urls::spa('dashboard/publications/publication/edit/' . $uuid);
        }
        Flash::set($msg, $color);
        if ($json) {
            wp_send_json(['ok' => true, 'redirect' => $to, 'status' => $out['status'] ?? '']);
        }
        wp_safe_redirect($to, 303);
        exit;
    }

    protected function form_context(string $mode, string $uuid, ?array $pub, array $values, array $catalogs, ?array $result): array
    {
        $is_admin = current_user_can('manage_options');
        $read_only = $mode === 'edit' && !$is_admin && ($values['status'] ?? '') === 'pending';
        $types = PublicacionForm::ui_types();
        $marcas = $catalogs['marcas'];
        $brand_options = ['' => __('Selecciona una marca', 'motorlan-portal'), (string) PublicacionForm::OTHER_BRAND => __('Otros', 'motorlan-portal')];
        foreach ($marcas as $id => $name) {
            $brand_options[(string) $id] = $name;
        }
        $title = $mode === 'add' ? __('Nueva publicación', 'motorlan-portal') : __('Editar publicación', 'motorlan-portal');
        if ($mode === 'edit' && isset($types[$values['tipo_ui']])) {
            $title .= ': ' . $types[$values['tipo_ui']]['title'];
        }
        $acf = (array) ($pub['acf'] ?? []);
        return [
            'page_title' => $title,
            'mode'       => $mode,
            'uuid'       => $uuid,
            'action_url' => Urls::current(),
            'list_url'   => Urls::spa('dashboard/publications/publication/list'),
            'values'     => $values,
            'result'     => $result,
            'read_only'  => $read_only,
            'is_admin'   => $is_admin,
            'types'      => $types,
            'brands'     => $brand_options,
            'countries'  => ['' => __('Selecciona un país', 'motorlan-portal')] + $catalogs['countries'],
            'conditions' => PublicacionForm::conditions(),
            'status_chip'=> Publicacion::status((string) ($values['status'] ?? '')),
            'pub'        => $pub ? [
                'id'   => (int) $pub['id'],
                'url'  => ($values['status'] ?? '') === 'publish' && !empty($pub['slug']) ? SpaPages::product_url((string) $pub['slug']) : '',
                'name' => Publicacion::name($pub),
            ] : null,
            'steps'      => [
                ['title' => __('Tipo', 'motorlan-portal'), 'icon' => 'category-2'],
                ['title' => __('Info básica', 'motorlan-portal'), 'icon' => 'info-circle'],
                ['title' => __('Especificaciones', 'motorlan-portal'), 'icon' => 'list-details'],
                ['title' => __('Multimedia', 'motorlan-portal'), 'icon' => 'photo'],
                ['title' => __('Detalles', 'motorlan-portal'), 'icon' => 'map-pin'],
                ['title' => __('Documentación', 'motorlan-portal'), 'icon' => 'file'],
            ],
            'form_cfg'   => [
                'mode'      => $mode,
                'userId'    => get_current_user_id(),
                'brands'    => (object) array_map('strval', $marcas),
                'limits'    => PublicacionForm::upload_limits(),
                'maxGallery'=> PublicacionForm::MAX_GALLERY,
                'maxDocs'   => PublicacionForm::MAX_DOCS,
                'values'    => $values,
                'step'      => $result && isset($result['step']) && $result['step'] >= 0 ? (int) $result['step'] : 0,
                'hasResult' => $result !== null,
                'slugKey'   => [$values['marca'], $values['marca_custom'], $values['tipo_o_referencia'], $values['potencia'], $values['par_nominal'], $values['tipo_ui']],
                'i18n'      => self::form_i18n(),
            ],
            'request_id' => wp_generate_uuid4(),
            'nonce'      => wp_create_nonce(self::PANEL_NONCE),
            'flash'      => Flash::pull(),
            'hide_mobile_nav' => $mode === 'edit',
        ];
    }

    /** Textos de mlPubForm (validación por paso, avisos de subida). */
    protected static function form_i18n(): array
    {
        return [
            'stepOf'        => __('Paso %1$d de %2$d', 'motorlan-portal'),
            'required'      => __('Este campo es obligatorio', 'motorlan-portal'),
            'typeRequired'  => __('Selecciona el tipo de publicación', 'motorlan-portal'),
            'brandRequired' => __('Selecciona una marca', 'motorlan-portal'),
            'brandCustom'   => __('El nombre de la marca es obligatorio', 'motorlan-portal'),
            'positive'      => __('Debe ser un número mayor que 0', 'motorlan-portal'),
            'powerOrTorque' => __('Indica la potencia o el par nominal', 'motorlan-portal'),
            'priceNegative' => __('El precio no puede ser negativo', 'motorlan-portal'),
            'stockPositive' => __('El stock debe ser mayor a 0', 'motorlan-portal'),
            'stockInteger'  => __('El stock debe ser un número entero', 'motorlan-portal'),
            'mainImage'     => __('La imagen principal es obligatoria', 'motorlan-portal'),
            'onlyImages'    => __('Solo se admiten imágenes (JPG, PNG, GIF o WebP).', 'motorlan-portal'),
            'maxImages'     => __('Máximo %d imágenes.', 'motorlan-portal'),
            'docType'       => __('Solo se admiten archivos PDF, JPG o PNG.', 'motorlan-portal'),
            'fileTooBig'    => __('«%1$s» supera el tamaño máximo permitido (%2$s).', 'motorlan-portal'),
            'totalTooBig'   => __('Los archivos superan en total el máximo permitido (%s). Quita alguna imagen o documento.', 'motorlan-portal'),
            'fixErrors'     => __('Por favor, rellena los campos obligatorios', 'motorlan-portal'),
            'draftRestored' => __('Hemos recuperado los datos que estabas rellenando. Vuelve a añadir las imágenes y los documentos.', 'motorlan-portal'),
            'leave'         => __('Tienes una publicación a medias. Si sales se perderán los datos. ¿Quieres salir?', 'motorlan-portal'),
            'network'       => __('Error de red. Revisa tu conexión.', 'motorlan-portal'),
            'previewNone'   => __('Vista previa no disponible', 'motorlan-portal'),
        ];
    }

    protected function api_message(array $r, string $fallback): string
    {
        $m = (string) ($r['error']['message'] ?? '');
        return $m !== '' ? $m : $fallback;
    }
}
