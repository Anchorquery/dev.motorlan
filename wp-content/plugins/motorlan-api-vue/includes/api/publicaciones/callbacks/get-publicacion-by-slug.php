<?php
/**
 * Callback to get a single publicacion by slug.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Handle the request to get a single publication by its slug.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function motorlan_get_publicacion_by_slug(WP_REST_Request $request) {
    $slug = $request->get_param('slug');
    if (empty($slug)) {
        return new WP_Error('no_slug', 'Slug not provided', ['status' => 400]);
    }

    $args = [
        'post_type'      => 'publicacion',
        'name'           => $slug,
        'posts_per_page' => 1,
        'post_status'    => 'any',
    ];
    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        return new WP_Error('not_found', 'Publicación no encontrada', ['status' => 404]);
    }

    $query->the_post();
    $post_id = get_the_ID();
    wp_reset_postdata();

    $publicacion_data = motorlan_get_publicacion_data($post_id);
    return new WP_REST_Response(['data' => $publicacion_data], 200);
}