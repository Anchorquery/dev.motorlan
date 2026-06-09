<?php
/**
 * Callback to bulk delete publicaciones by ID.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Handle the request to bulk delete publications by their IDs.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function motorlan_bulk_delete_publicaciones(WP_REST_Request $request) {
    // Validate Content-Type
    if ( function_exists( 'motorlan_validate_json_content_type' ) ) {
        $valid_type = motorlan_validate_json_content_type( $request );
        if ( is_wp_error( $valid_type ) ) {
            return $valid_type;
        }
    }

    $params = $request->get_json_params();
    $ids = $params['ids'] ?? [];

    if (empty($ids)) {
        return new WP_Error('no_ids', 'No IDs provided', ['status' => 400]);
    }

    $deleted_count = 0;
    foreach ($ids as $id) {
        $post = get_post(intval($id));
        if (!$post) {
            continue; // Skip non-existent posts
        }

        // Security check: only the author or an admin can delete, and only publicaciones
        if ($post->post_type !== 'publicacion') {
            continue;
        }
        if ((int) $post->post_author !== get_current_user_id() && !current_user_can('administrator')) {
            continue; // Not owner and not admin
        }

        // Security check: If post is pending, only admin can delete
        if ($post->post_status === 'pending' && !current_user_can('administrator')) {
            continue; // Skip pending items if not admin
        }

        if (wp_delete_post(intval($id), true)) {
            $deleted_count++;
        }
    }

    if ($deleted_count === 0) {
        return new WP_Error('delete_failed', 'Failed to delete any publicacion', ['status' => 500]);
    }

    return new WP_REST_Response(['message' => "$deleted_count publicaciones deleted successfully"], 200);
}