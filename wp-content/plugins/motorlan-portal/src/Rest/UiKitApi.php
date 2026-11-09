<?php

namespace MotorlanPortal\Rest;

use MotorlanPortal\Sections;
use MotorlanPortal\Support\View;

/**
 * Parciales HTMX del catálogo (namespace motorlan-portal/v1). Ejemplo del patrón que
 * usarán las pantallas: tabla con filtros y paginación que se recarga por HTMX, con la
 * sesión de la cookie + X-WP-Nonce. Datos de ejemplo fijos (no lee ni escribe en la BD).
 */
class UiKitApi
{
    const NS = 'motorlan-portal/v1';
    const PER_PAGE = 5;

    public function register()
    {
        if (!Sections::is_enabled('ui-kit')) {
            return;
        }
        add_action('rest_api_init', function () {
            register_rest_route(self::NS, '/ui-kit/table', [
                'methods'             => 'GET',
                'callback'            => [self::class, 'table'],
                'permission_callback' => function () {
                    return current_user_can('manage_options');
                },
                'args' => [
                    'q'      => ['type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_text_field'],
                    'estado' => ['type' => 'string', 'default' => '', 'sanitize_callback' => 'sanitize_key'],
                    'page'   => ['type' => 'integer', 'default' => 1, 'minimum' => 1],
                ],
            ]);
        });
    }

    public static function table(\WP_REST_Request $request)
    {
        $ctx  = self::table_context($request->get_params());
        $html = View::compile('partials/ui-kit-table.twig', ['table' => $ctx, 'table_url' => rest_url(self::NS . '/ui-kit/table')]);
        View::html_response($html);
    }

    public static function table_context(array $params): array
    {
        $q      = isset($params['q']) && is_string($params['q']) ? mb_strtolower(trim(sanitize_text_field($params['q']))) : '';
        $estado = isset($params['estado']) && is_string($params['estado']) ? sanitize_key($params['estado']) : '';
        $estados = self::estados();
        if (!isset($estados[$estado])) {
            $estado = '';
        }
        $page   = max(1, (int) ($params['page'] ?? 1));

        $rows = array_values(array_filter(self::rows(), function ($r) use ($q, $estado) {
            return ($q === '' || strpos(mb_strtolower($r['titulo'] . ' ' . $r['marca']), $q) !== false)
                && ($estado === '' || $r['estado'] === $estado);
        }));

        $total = count($rows);
        $pages = max(1, (int) ceil($total / self::PER_PAGE));
        $page  = min($page, $pages);

        return [
            'rows'    => array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'total'   => $total,
            'page'    => $page,
            'pages'   => $pages,
            'filters' => ['q' => $q, 'estado' => $estado],
            'estados' => $estados,
        ];
    }

    protected static function estados(): array
    {
        return [
            'publish' => ['label' => 'Publicado', 'color' => 'success'],
            'pending' => ['label' => 'Pendiente', 'color' => 'warning'],
            'draft'   => ['label' => 'Borrador', 'color' => 'secondary'],
            'sold'    => ['label' => 'Vendido', 'color' => 'info'],
        ];
    }

    protected static function rows(): array
    {
        $data = [
            ['Motor trifásico 5,5 kW 1500 rpm', 'Siemens', 'publish', 640],
            ['Servomotor 1FK7 con freno', 'Siemens', 'pending', 1290],
            ['Variador de frecuencia ACS580 7,5 kW', 'ABB', 'publish', 980],
            ['Motorreductor coaxial 0,75 kW', 'SEW', 'draft', 410],
            ['Motor brushless 400 W', 'Delta', 'sold', 260],
            ['Motor IE3 11 kW B3', 'WEG', 'publish', 870],
            ['Encoder incremental 1024 ppr', 'Heidenhain', 'pending', 190],
            ['Servodrive Lexium 32', 'Schneider', 'publish', 1150],
            ['Motor paso a paso NEMA 23', 'Oriental Motor', 'draft', 85],
            ['Bomba centrífuga 3 kW', 'Grundfos', 'sold', 720],
            ['Motor de corriente continua 2,2 kW', 'Leroy-Somer', 'publish', 530],
            ['Reductor sinfín 1:30', 'Bonfiglioli', 'publish', 340],
        ];
        $rows = [];
        foreach ($data as $i => $r) {
            $rows[] = ['id' => 1001 + $i, 'titulo' => $r[0], 'marca' => $r[1], 'estado' => $r[2], 'precio' => $r[3]];
        }
        return $rows;
    }
}
