<?php

add_action('rest_api_init', 'motorlan_register_my_publications_routes');

/**
 * Register the REST API route for fetching the current user's publications.
 */
function motorlan_register_my_publications_routes() {
    register_rest_route('motorlan/v1', '/my-publications', [
        'methods'             => 'GET',
        'callback'            => 'motorlan_get_my_publications_callback',
        'permission_callback' => 'motorlan_is_user_authenticated',
    ]);
}

/**
 * Callback function to handle the API request for user's publications.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response The response object.
 */
function motorlan_get_my_publications_callback($request) {
    $params = $request->get_params();
    $args = motorlan_build_my_publications_query_args($params);

    $query = new WP_Query($args);
    $publicaciones_data = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $publicaciones_data[] = motorlan_format_publication_item(get_the_ID());
        }
        wp_reset_postdata();
    }

    $pagination = [
        'total'       => (int) $query->found_posts,
        'totalPages'  => (int) $query->max_num_pages,
        'currentPage' => (int) ($args['paged'] ?? 1),
        'perPage'     => (int) ($args['posts_per_page'] ?? 10),
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

/**
 * Build the WP_Query arguments based on request parameters.
 *
 * @param array $params The request parameters.
 * @return array The arguments for WP_Query.
 */
function motorlan_build_my_publications_query_args($params) {
    $page = !empty($params['page']) ? absint($params['page']) : 1;
    $per_page = !empty($params['per_page']) ? absint($params['per_page']) : 10;

    $args = [
        'post_type'      => 'publicacion',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'post_status'    => 'any',
        'author'         => get_current_user_id(),
    ];

    // --- Filtering ---
    $meta_query = ['relation' => 'AND'];
    $tax_query = ['relation' => 'AND'];

    $filterable_fields = [
        'marca', 'tipo_o_referencia', 'potencia', 'velocidad', 'par_nominal', 'voltaje', 'intensidad',
        'pais', 'provincia', 'estado_del_articulo', 'posibilidad_de_alquiler', 'tipo_de_alimentacion',
        'servomotores', 'regulacion_electronica_drivers', 'precio_de_venta', 'precio_negociable', 'uuid'
    ];

    foreach ($filterable_fields as $field) {
        if (!empty($params[$field])) {
            $meta_query[] = [
                'key'     => $field,
                'value'   => sanitize_text_field($params[$field]),
                'compare' => $field === 'tipo_o_referencia' ? 'LIKE' : '=',
            ];
        }
    }

    if (!empty($params['status'])) {
        $meta_query[] = [
            'key'     => 'publicar_acf',
            'value'   => sanitize_text_field($params['status']),
            'compare' => '=',
        ];
    }

    if (!empty($params['category'])) {
        $tax_query[] = [
            'taxonomy' => 'categoria',
            'field'    => 'slug',
            'terms'    => array_map('sanitize_text_field', explode(',', $params['category'])),
        ];
    }

    if (!empty($params['tipo'])) {
        $tax_query[] = [
            'taxonomy' => 'tipo',
            'field'    => 'slug',
            'terms'    => array_map('sanitize_text_field', explode(',', $params['tipo'])),
        ];
    }

    if (count($meta_query) > 1) {
        $args['meta_query'] = $meta_query;
    }
    if (count($tax_query) > 1) {
        $args['tax_query'] = $tax_query;
    }

    // --- Search ---
    if (!empty($params['search'])) {
        $args['s'] = sanitize_text_field($params['search']);
    }

    // --- Sorting ---
    if (!empty($params['orderby'])) {
        $orderby = sanitize_text_field($params['orderby']);
        if ($orderby === 'price') {
            $args['meta_key'] = 'precio_de_venta';
            $args['orderby'] = 'meta_value_num';
        } else {
            $args['orderby'] = $orderby;
        }
    }

    if (!empty($params['order']) && in_array(strtoupper($params['order']), ['ASC', 'DESC'])) {
        $args['order'] = strtoupper(sanitize_text_field($params['order']));
    }

    return $args;
}

/**
 * Format a single publication post into a structured array for the API response.
 *
 * @param int $post_id The ID of the post to format.
 * @return array The formatted publication data.
 */
function motorlan_format_publication_item($post_id) {
    $publication_item = [
        'id'               => $post_id,
        'uuid'             => get_post_meta($post_id, 'uuid', true),
        'title'            => get_the_title($post_id),
        'slug'             => get_post_field('post_name', $post_id),
        'status'           => get_field('publicar_acf', $post_id),
        'imagen_destacada' => get_field('motor_image', $post_id, true),
        'author_id'        => get_post_field('post_author', $post_id),
        'categories'       => motorlan_get_post_taxonomy_details($post_id, 'categoria'),
        'tipo'             => motorlan_get_post_taxonomy_details($post_id, 'tipo'),
        'acf'              => [],
    ];

    if (function_exists('get_field')) {
        $acf_fields = [
            'marca', 'tipo_o_referencia', 'estado_del_articulo', 'descripcion', 'precio_de_venta',
        ];

        foreach ($acf_fields as $field_name) {
            $value = get_field($field_name, $post_id);
            if ($field_name === 'marca' && $value) {
                $term = get_term($value, 'marca');
                if ($term && !is_wp_error($term)) {
                    $publication_item['acf'][$field_name] = [
                        'id'   => $term->term_id,
                        'name' => $term->name,
                    ];
                } else {
                    $publication_item['acf'][$field_name] = null;
                }
            } else {
                $publication_item['acf'][$field_name] = $value;
            }
        }
    } else {
        $publication_item['acf_error'] = 'Advanced Custom Fields plugin is not active.';
    }

    return $publication_item;
}