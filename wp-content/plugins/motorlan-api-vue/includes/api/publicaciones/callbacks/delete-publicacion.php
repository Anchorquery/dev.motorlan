<?php
/**
 * Callback to delete a publicacion by ID.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Handle the request to delete a publication by its ID.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function motorlan_delete_publicacion(WP_REST_Request $request) {
    $post_id = $request->get_param('id');
    $result = wp_delete_post($post_id, true);

    if ($result === false) {
        return new WP_Error('delete_failed', 'Failed to delete publicacion', ['status' => 500]);
    }

    return new WP_REST_Response(['message' => 'Publicacion deleted successfully'], 200);
}