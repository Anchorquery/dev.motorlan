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
function motorlan_get_publicaciones_callback($request) {
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
        if ($custom_type_search) {
            // Remove ., -, /, and spaces from the DB value for comparison
            $where .= " AND REPLACE(REPLACE(REPLACE(REPLACE(mt_custom_type.meta_value, '-', ''), '.', ''), '/', ''), ' ', '') LIKE '%" . esc_sql($custom_type_search) . "%' ";
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

    $publicaciones_data = [];
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $publicaciones_data[] = motorlan_get_publicacion_data(get_the_ID());
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