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

    // Security check: If post is pending, only admin can delete
    $post_status = get_post_status($post_id);
    if ($post_status === 'pending' && !current_user_can('administrator')) {
        return new WP_Error('forbidden', 'No puedes eliminar una publicación que está en revisión.', ['status' => 403]);
    }

    $result = wp_delete_post($post_id, true);

    if ($result === false) {
        return new WP_Error('delete_failed', 'Failed to delete publicacion', ['status' => 500]);
    }

    return new WP_REST_Response(['message' => 'Publicacion deleted successfully'], 200);
}