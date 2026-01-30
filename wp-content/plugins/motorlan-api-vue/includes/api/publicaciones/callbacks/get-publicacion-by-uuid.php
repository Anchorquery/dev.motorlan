<?php
/**
 * Callback to get a single publicacion by UUID.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Handle the request to get a single publication by its UUID.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function motorlan_get_publicacion_by_uuid(WP_REST_Request $request) {
    $uuid = $request->get_param('uuid');
    $post_id = motorlan_get_post_id_by_uuid($uuid);

    if (!$post_id) {
        return new WP_Error('not_found', 'Publicación no encontrada', ['status' => 404]);
    }

    // Security check: allow only the author or admins.
    $post_author_id = get_post_field('post_author', $post_id);
    $is_admin = current_user_can('manage_options');
    if (get_current_user_id() != $post_author_id && !$is_admin) {
        return new WP_Error('forbidden', 'No tienes permiso para ver esta publicación', ['status' => 403]);
    }

    $publicacion_data = motorlan_get_publicacion_data($post_id);
    return new WP_REST_Response($publicacion_data, 200);
}
