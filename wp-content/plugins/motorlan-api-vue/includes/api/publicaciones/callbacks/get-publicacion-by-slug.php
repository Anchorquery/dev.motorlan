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

    $post_status = get_post_status($post_id);
    $post_author_id = get_post_field('post_author', $post_id);
    $is_admin = current_user_can('manage_options') || current_user_can('administrator');
    $is_owner = get_current_user_id() == $post_author_id;

    // Security: If not published, only admin or owner can see it.
    if ($post_status !== 'publish' && !$is_admin && !$is_owner) {
        return new WP_Error('not_found', 'Publicación no encontrada', ['status' => 404]);
    }

    // Check if user is admin or owner to view sensitive data (like price)
    $is_admin = current_user_can('manage_options');
    $post_author_id = get_post_field('post_author', $post_id);
    $is_owner = get_current_user_id() == $post_author_id;

    $show_sensitive = $is_admin || $is_owner;

    $publicacion_data = motorlan_get_publicacion_data($post_id, $show_sensitive);
    return new WP_REST_Response(['data' => $publicacion_data], 200);
}