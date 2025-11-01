<?php
/**
 * Callback to update publicacion status by ID.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Handle the request to update a publication's status by its ID.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function motorlan_update_publicacion_status(WP_REST_Request $request) {
    $post_id = $request->get_param('id');
    $params = $request->get_json_params();
    $new_status = $params['status'] ?? '';

    if (empty($new_status)) {
        return new WP_Error('no_status', 'Status not provided', ['status' => 400]);
    }

    // Si se intenta publicar y el stock actual es 0, establecerlo a 1
    if ($new_status === 'publish') {
        $current_stock_raw = function_exists('get_field') ? get_field('stock', $post_id) : get_post_meta($post_id, 'stock', true);
        $current_stock = (int) $current_stock_raw;

        if ($current_stock <= 0) {
            if (function_exists('update_field')) {
                update_field('stock', 1, $post_id);
            }
            update_post_meta($post_id, 'stock', 1);
        }
    }

    update_field('publicar_acf', sanitize_text_field($new_status), $post_id);

    return new WP_REST_Response(['message' => 'Publicacion status updated successfully'], 200);
}
