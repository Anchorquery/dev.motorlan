<?php
/**
 * Helper functions for the Publicaciones REST API endpoints.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Build WP_Query arguments from request parameters for publicaciones.
 *
 * @param array $params Request parameters.
 * @return array Arguments for WP_Query.
 */
function motorlan_build_publicaciones_query_args($params) {
    $args = [
        'post_type'      => 'publicaciones',
        'posts_per_page' => !empty($params['per_page']) ? absint($params['per_page']) : 10,
        'paged'          => !empty($params['page']) ? absint($params['page']) : 1,
        'post_status'    => 'any',
    ];

    $meta_query = ['relation' => 'AND'];
    $tax_query = ['relation' => 'AND'];

    $filterable_fields = [
        'marca', 'tipo_o_referencia', 'potencia', 'velocidad', 'par_nominal', 'voltaje', 'intensidad',
        'pais', 'provincia', 'posibilidad_de_alquiler', 'tipo_de_alimentacion',
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

    if (!empty($params['estado_del_articulo'])) {
        $tax_query[] = [
            'taxonomy' => 'estado_del_articulo',
            'field'    => 'slug',
            'terms'    => array_map('sanitize_text_field', explode(',', $params['estado_del_articulo'])),
        ];
    }

    if (count($meta_query) > 1) $args['meta_query'] = $meta_query;
    if (count($tax_query) > 1) $args['tax_query'] = $tax_query;

    if (!empty($params['search'])) {
        $args['s'] = sanitize_text_field($params['search']);
    }

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
 * Get all data for a single publicacion.
 *
 * @param int $post_id The post ID.
 * @return array The publicacion data.
 */
function motorlan_get_publicacion_data($post_id) {
    $post = get_post($post_id);
    if (!$post) return [];

    $author_id = $post->post_author;
    $user = get_userdata($author_id);

    $publicacion_item = [
        'id'           => $post_id,
        'uuid'         => get_post_meta($post_id, 'uuid', true),
        'title'        => get_the_title($post_id),
        'slug'         => $post->post_name,
        'status'       => get_field('publicar_acf', $post_id),
        'author_id'    => $author_id,
        'author'       => $user ? [
            'id' => $author_id,
            'name' => $user->display_name,
            'email' => $user->user_email,
        ] : null,
        'categories'   => motorlan_get_post_taxonomy_details($post_id, 'categoria'),
        'tipo'         => motorlan_get_post_taxonomy_details($post_id, 'tipo'),
        'acf'          => get_fields($post_id) ?: [],
        'imagen_destacada' => get_field('motor_image', $post_id, true),
    ];

    // Forzar la obtención de la marca si get_fields() no la incluye.
    if (empty($publicacion_item['acf']['marca'])) {
        $marca_id = get_field('marca', $post_id);
        if ($marca_id) {
            $publicacion_item['acf']['marca'] = $marca_id;
        }
    }

    return $publicacion_item;
}

/**
 * Get post taxonomy details.
 *
 * @param int    $post_id  The post ID.
 * @param string $taxonomy The taxonomy slug.
 * @return array
 */
function motorlan_get_post_taxonomy_details($post_id, $taxonomy) {
    $terms_details = [];
    $terms = wp_get_post_terms($post_id, $taxonomy);
    if (!is_wp_error($terms) && !empty($terms)) {
        foreach ($terms as $term) {
            $terms_details[] = [
                'id'   => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            ];
        }
    }
    return $terms_details;
}

/**
 * Get post ID by UUID.
 *
 * @param string $uuid The UUID.
 * @return int|null The post ID or null if not found.
 */
function motorlan_get_post_id_by_uuid($uuid) {
    if (empty($uuid)) {
        return null;
    }
    $args = [
        'post_type'      => 'publicaciones',
        'meta_key'       => 'uuid',
        'meta_value'     => $uuid,
        'posts_per_page' => 1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    ];
    $query = new WP_Query($args);
    return $query->have_posts() ? $query->posts[0] : null;
}

/**
 * Generic callback to get terms from a taxonomy.
 *
 * @param string $taxonomy The taxonomy slug.
 * @return WP_REST_Response
 */
function motorlan_get_taxonomy_terms_callback($taxonomy) {
    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
    if (is_wp_error($terms)) {
        return new WP_REST_Response(['message' => $terms->get_error_message()], 500);
    }
    return new WP_REST_Response($terms, 200);
}