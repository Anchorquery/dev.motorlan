<?php

namespace MotorlanPortal\Controllers;

use MotorlanPortal\Support\Api;
use MotorlanPortal\Support\Publicacion;
use MotorlanPortal\Support\Seo;
use MotorlanPortal\Support\Urls;
use MotorlanPortal\Support\View;

/**
 * Favoritos: /mi-cuenta/dashboard/favorites/list (pages/dashboard/favorites/list/index.vue).
 *
 * Listado con búsqueda, estado, elementos por página y paginación (GET
 * /motorlan/v1/purchases/favorites). Los filtros son un formulario GET a esta misma URL:
 * con HTMX el servidor responde solo partials/favoritos-tabla.twig; sin JS, la página.
 * Quitar un favorito: POST a esta URL (action=remove) → DELETE /motorlan/v1/favorites/{id}
 * y la tabla de nuevo con los mismos filtros.
 */
class FavoritosController extends Controller
{
    const PER_PAGE = [5, 10, 20, 25, 50];

    public function handle(string $param): void
    {
        if ($param !== 'favorites/list' && $param !== '') {
            $this->not_found();
            return;
        }

        $headers = [];
        if ($this->is_post()) {
            $headers = $this->remove();
        }

        $filters = $this->filters();
        $context = $this->context($filters);

        if ($this->is_htmx()) {
            View::html_response(View::compile('partials/favoritos-tabla.twig', $context, true), 200, $headers + ['Vary' => 'HX-Request']);
        }

        Seo::private_page($context['page_title']);
        $this->render('pages/favoritos.twig', $context);
    }

    /** Quita un favorito. Devuelve las cabeceras del aviso. */
    protected function remove(): array
    {
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        if (!$this->valid_nonce() || $this->field('action') !== 'remove' || !$id) {
            return self::toast_header(__('La página ha caducado. Recárgala e inténtalo de nuevo.', 'motorlan-portal'), 'error');
        }
        $r = Api::call('DELETE', Api::route('favorites/' . $id));
        if (!$r['ok']) {
            return self::toast_header(__('No se pudo actualizar el favorito. Inténtalo de nuevo.', 'motorlan-portal'), 'error');
        }
        return self::toast_header(__('Eliminado de favoritos', 'motorlan-portal'));
    }

    /** Filtros de la query (también los reenvía el POST de quitar, en campos ocultos). */
    protected function filters(): array
    {
        $src = $this->is_post() ? wp_unslash($_POST) : wp_unslash($_GET);
        $get = function ($k) use ($src) { return isset($src[$k]) && is_string($src[$k]) ? trim(sanitize_text_field($src[$k])) : ''; };
        $status = $get('estado');
        $per_page = (int) $get('per_page');
        return [
            'q'        => mb_substr($get('q'), 0, 100),
            'estado'   => isset(Publicacion::statuses()[$status]) ? $status : '',
            'per_page' => in_array($per_page, self::PER_PAGE, true) ? $per_page : 10,
            'page'     => max(1, (int) $get('page')),
        ];
    }

    protected function context(array $f): array
    {
        $query = array_filter([
            'search'   => $f['q'],
            'status'   => $f['estado'],
            'per_page' => $f['per_page'],
            'page'     => $f['page'],
        ]);
        $data = Api::get(Api::route('purchases/favorites'), $query);
        $error = $data === null;
        $pagination = is_array($data) ? (array) ($data['pagination'] ?? []) : [];
        $rows = [];
        foreach ((array) ($data['data'] ?? []) as $p) {
            if (is_array($p) && !empty($p['id'])) {
                $rows[] = Publicacion::row($p);
            }
        }
        // Al quitar el último de la última página: a la anterior (la SPA dejaba la página
        // vacía). Fuera de rango WP_Query da found_posts = 0, así que la API responde
        // total 0 y no sirve para saber cuál es la última: se retrocede de una en una.
        if (!$rows && !$error && $f['page'] > 1) {
            return $this->context(['page' => $f['page'] - 1] + $f);
        }

        $statuses = ['' => __('Estado', 'motorlan-portal')];
        foreach (Publicacion::statuses() as $k => $s) {
            $statuses[$k] = $s['label'];
        }
        $per_page = [];
        foreach (self::PER_PAGE as $n) {
            $per_page[$n] = sprintf(__('%d por página', 'motorlan-portal'), $n);
        }

        $base = Urls::spa('dashboard/favorites/list');
        return [
            'page_title' => __('Mis favoritos', 'motorlan-portal'),
            'table'      => [
                'url'      => $base,
                'rows'     => $rows,
                'error'    => $error,
                'filters'  => $f,
                'page'     => (int) ($pagination['currentPage'] ?? $f['page']),
                'pages'    => (int) ($pagination['totalPages'] ?? 1),
                'total'    => (int) ($pagination['total'] ?? 0),
                'statuses' => $statuses,
                'per_page' => $per_page,
                'query'    => http_build_query(array_filter(['q' => $f['q'], 'estado' => $f['estado'], 'per_page' => $f['per_page'] !== 10 ? $f['per_page'] : null])),
            ],
            'nonce'      => wp_create_nonce(self::PANEL_NONCE),
        ];
    }
}
