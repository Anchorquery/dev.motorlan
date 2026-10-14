<?php
/**
 * Callback for the public store endpoint.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Handle the request for the public store endpoint.
 * Forces status to 'publish' and removes any author filtering.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function motorlan_get_public_publicaciones_callback($request) {
    $params = $request->get_params();
    $params['status'] = 'publish';
    unset($params['author'], $params['post_status'], $params['stock_min']);
    // Aplanar arrays (?tipo[]=...) igual que el builder, para que la clave de caché coincida
    foreach ($params as $param_key => $param_value) {
        if (is_array($param_value)) {
            $params[$param_key] = implode(',', array_map('strval', array_filter($param_value, 'is_scalar')));
        }
    }

    $cache_params = motorlan_normalize_store_publicaciones_cache_params($params);
    $should_cache = motorlan_should_cache_store_publicaciones_request($cache_params);

    // Solo cachear si todos los parámetros forman parte de la clave de caché. Si no,
    // un filtro no contemplado (uuid, pais, precio...) se guardaba bajo la clave de la
    // portada y todos los visitantes veían esa tienda filtrada durante el TTL.
    if ($should_cache) {
        $ignored_keys = ['_locale', 'lang', 'rest_route', '_', '_wpnonce'];
        foreach (array_keys($params) as $param_key) {
            if (!array_key_exists($param_key, $cache_params) && !in_array($param_key, $ignored_keys, true)) {
                $should_cache = false;
                break;
            }
        }
        if ((int) ($cache_params['per_page'] ?? 12) > 100) {
            $should_cache = false;
        }
        // Texto libre = claves ilimitadas (2 filas en wp_options por variante que solo
        // limpia el cron diario): ?search=<aleatorio> inflaba la BD. Solo filtros cerrados.
        if (!empty($cache_params['search']) || !empty($cache_params['tipo_o_referencia'])) {
            $should_cache = false;
        }
    }

    if ($should_cache) {
        $cache_key = motorlan_build_store_publicaciones_cache_key($cache_params);
        $cached = get_transient($cache_key);
        if (false !== $cached && is_array($cached) && isset($cached['response'])) {
            $response = new WP_REST_Response($cached['response'], 200);
            if (is_array($cached['headers']) && array_key_exists('X-WP-Total', $cached['headers'])) {
                $response->header('X-WP-Total', (int) $cached['headers']['X-WP-Total']);
            }
            if (is_array($cached['headers']) && array_key_exists('X-WP-TotalPages', $cached['headers'])) {
                $response->header('X-WP-TotalPages', (int) $cached['headers']['X-WP-TotalPages']);
            }
            $response->header('X-Motorlan-Cache', 'HIT');
            return $response;
        }
    }

    // Create a new request object to avoid modifying the original one.
    $new_request = new WP_REST_Request($request->get_method(), $request->get_route());
    $new_request->set_query_params($params);

    // list_mode: las tarjetas solo usan imagen_destacada (sin galería ni documentos)
    $response = motorlan_get_publicaciones_callback($new_request, ['list_mode' => true]);

    if ($should_cache && $response instanceof WP_REST_Response) {
        $response_data = $response->get_data();
        $payload = [
            'response' => $response_data,
            'headers' => [
                'X-WP-Total' => $response->get_headers()['X-WP-Total'] ?? null,
                'X-WP-TotalPages' => $response->get_headers()['X-WP-TotalPages'] ?? null,
            ],
        ];

        set_transient($cache_key, $payload, motorlan_get_store_publicaciones_cache_ttl());
        $response->header('X-Motorlan-Cache', 'MISS');
    }

    return $response;
}
