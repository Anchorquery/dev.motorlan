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
    $params = $request->get_json_params();
    $ids = $params['ids'] ?? [];

    if (empty($ids)) {
        return new WP_Error('no_ids', 'No IDs provided', ['status' => 400]);
    }

    $deleted_count = 0;
    foreach ($ids as $id) {
        if (wp_delete_post(intval($id), true)) {
            $deleted_count++;
        }
    }

    if ($deleted_count === 0) {
        return new WP_Error('delete_failed', 'Failed to delete any publicacion', ['status' => 500]);
    }

    return new WP_REST_Response(['message' => "$deleted_count publicaciones deleted successfully"], 200);
}