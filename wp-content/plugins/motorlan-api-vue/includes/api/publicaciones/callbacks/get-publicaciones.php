<?php
/**
 * Callback to get a list of publicaciones with pagination and filtering.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Handle the request to get a list of publications.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function motorlan_get_publicaciones_callback($request, $options = []) {
    // list_mode: listado de la tienda (tarjetas). Omite galería y documentos de cada item.
    $list_mode = !empty($options['list_mode']);
    $args = motorlan_build_publicaciones_query_args($request->get_params());
    // Check for custom search arg
    $custom_type_search = isset($args['motorlan_custom_search_tipo']) ? $args['motorlan_custom_search_tipo'] : null;
    unset($args['motorlan_custom_search_tipo']); // Clean up so WP_Query doesn't complain

    // Define filters
    $join_filter = function($join) use ($custom_type_search) {
        global $wpdb;
        if ($custom_type_search) {
             $join .= " LEFT JOIN {$wpdb->postmeta} AS mt_custom_type ON ({$wpdb->posts}.ID = mt_custom_type.post_id AND mt_custom_type.meta_key = 'tipo_o_referencia') ";
        }
        return $join;
    };

    $where_filter = function($where) use ($custom_type_search) {
        global $wpdb;
        if ($custom_type_search) {
            // Remove ., -, /, and spaces from the DB value for comparison.
            // esc_like escapes LIKE wildcards (% and _); prepare handles quoting/escaping.
            $like = '%' . $wpdb->esc_like($custom_type_search) . '%';
            $where .= $wpdb->prepare(
                " AND REPLACE(REPLACE(REPLACE(REPLACE(mt_custom_type.meta_value, '-', ''), '.', ''), '/', ''), ' ', '') LIKE %s ",
                $like
            );
        }
        return $where;
    };

    if ($custom_type_search) {
        add_filter('posts_join', $join_filter);
        add_filter('posts_where', $where_filter);
    }

    $query = new WP_Query($args);

    if ($custom_type_search) {
        remove_filter('posts_join', $join_filter);
        remove_filter('posts_where', $where_filter);
    }

    // RENDIMIENTO: precargar autores (y sus metas) e imágenes en bloque; antes eran
    // 1-3 consultas por item. get_post_meta no consulta aquí: WP_Query ya precargó la
    // meta de las publicaciones.
    if (!empty($query->posts)) {
        cache_users(array_unique(array_map(function ($p) { return (int) $p->post_author; }, $query->posts)));
        $attachment_ids = [];
        foreach ($query->posts as $p) {
            $attachment_ids[] = (int) get_post_meta($p->ID, 'motor_image', true);
            if (!$list_mode) {
                $gallery = get_post_meta($p->ID, 'motor_gallery', true);
                if (is_array($gallery)) {
                    $attachment_ids = array_merge($attachment_ids, array_map('intval', $gallery));
                }
            }
        }
        $attachment_ids = array_values(array_filter(array_unique($attachment_ids)));
        if ($attachment_ids) {
            _prime_post_caches($attachment_ids, false, true);
        }
    }

    $publicaciones_data = [];
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $publicaciones_data[] = motorlan_get_publicacion_data(get_the_ID(), false, false, $list_mode);
        }
        wp_reset_postdata();
    }

    $pagination = [
        'total'       => (int) $query->found_posts,
        'totalPages'  => (int) $query->max_num_pages,
        'currentPage' => (int) $args['paged'],
        'perPage'     => (int) $args['posts_per_page'],
    ];

    $response_data = [
        'data'       => $publicaciones_data,
        'pagination' => $pagination,
    ];

    $response = new WP_REST_Response($response_data, 200);
    $response->header('X-WP-Total', $query->found_posts);
    $response->header('X-WP-TotalPages', $query->max_num_pages);

    return $response;
}