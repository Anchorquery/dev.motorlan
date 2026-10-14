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
    $skipped_purchases = 0;
    $is_admin = current_user_can('administrator');
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
        if (in_array($post->post_status, ['pending', 'trash'], true) && !$is_admin) {
            continue; // Skip pending/trashed items if not admin
        }

        // Con compras asociadas no se borra (el historial del comprador depende del post).
        if (!$is_admin && motorlan_publicacion_has_purchases($post->ID)) {
            $skipped_purchases++;
            continue;
        }

        // Papelera en vez de borrado definitivo (recuperable por el admin).
        if (wp_trash_post($post->ID)) {
            $deleted_count++;
        }
    }

    if ($deleted_count === 0) {
        if ($skipped_purchases > 0) {
            return new WP_Error('has_purchases', 'Las publicaciones seleccionadas tienen compras asociadas: pásalas a "Vendido" o "Pausado" en lugar de eliminarlas.', ['status' => 409]);
        }
        return new WP_Error('delete_failed', 'No se pudo eliminar ninguna publicación.', ['status' => 500]);
    }

    $message = sprintf('%d publicaciones eliminadas correctamente.', $deleted_count);
    if ($skipped_purchases > 0) {
        $message .= sprintf(' %d no se han eliminado porque tienen compras asociadas.', $skipped_purchases);
    }

    return new WP_REST_Response(['message' => $message, 'deleted' => $deleted_count, 'skipped_purchases' => $skipped_purchases], 200);
}