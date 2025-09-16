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

    $publicacion_data = motorlan_get_publicacion_data($post_id);
    return new WP_REST_Response($publicacion_data, 200);
}