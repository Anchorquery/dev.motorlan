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
    // Validate Content-Type
    if ( function_exists( 'motorlan_validate_json_content_type' ) ) {
        $valid_type = motorlan_validate_json_content_type( $request );
        if ( is_wp_error( $valid_type ) ) {
            return $valid_type;
        }
    }

    $post_id = $request->get_param('id');
    $params = $request->get_json_params();
    $new_status = $params['status'] ?? '';
    $is_admin = current_user_can('administrator');

    if (empty($new_status)) {
        return new WP_Error('no_status', 'Status not provided', ['status' => 400]);
    }

    // Security check: Only author or admin can change status
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'publicacion') {
        return new WP_Error('not_found', 'Publicación no encontrada', ['status' => 404]);
    }
    if (!in_array($new_status, ['publish', 'pending', 'draft', 'paused', 'sold'], true)) {
        return new WP_Error('invalid_status', 'Estado de publicación no válido.', ['status' => 400]);
    }
    if ($post->post_author != get_current_user_id() && !current_user_can('administrator')) {
        return new WP_Error('forbidden', 'No tienes permisos para editar esta publicación.', ['status' => 403]);
    }

    // Security check: If current post is pending, only admin can change status
    $current_status = get_post_status($post_id);
    // Papelera: solo el admin (antes el dueño podía sacarla de la papelera cambiando el estado).
    if ($current_status === 'trash' && !$is_admin) {
        return new WP_Error('not_found', 'Publicación no encontrada', ['status' => 404]);
    }
    if ($current_status === 'pending' && !current_user_can('administrator')) {
        return new WP_Error('forbidden', 'No puedes cambiar el estado de una publicación que está en revisión.', ['status' => 403]);
    }

    // Si estaba publicada (aprobada), recordarlo antes de pausarla/venderla para poder
    // reactivarla después sin volver a la cola de revisión.
    if ($current_status === 'publish') {
        motorlan_publicacion_mark_approved($post_id);
    }

    // Si el usuario no es admin y quiere publicar, forzar 'pending' salvo que la publicación
    // ya hubiera sido aprobada (p. ej. pausada o agotada que se reactiva tras reponer stock).
    // Una marca nueva pendiente siempre requiere revisión.
    if (!$is_admin && $new_status === 'publish'
        && (!motorlan_publicacion_was_approved($post_id) || get_post_meta($post_id, '_pending_brand_name', true))) {
        $new_status = 'pending';
    }

    // Si se intenta publicar/pedir aprobación y el stock actual es 0, establecerlo a 1
    if ($new_status === 'publish' || $new_status === 'pending') {
        $current_stock_raw = function_exists('get_field') ? get_field('stock', $post_id) : get_post_meta($post_id, 'stock', true);
        $current_stock = (int) $current_stock_raw;

        if ($current_stock <= 0) {
            if (function_exists('update_field')) {
                update_field('stock', 1, $post_id);
            }
            update_post_meta($post_id, 'stock', 1);
        }
    }

    // Desde la papelera: restaurar con wp_untrash_post (si no, queda el slug "__trashed")
    if (get_post_status($post_id) === 'trash') {
        wp_untrash_post($post_id);
    }

    // Actualizar el estado del post en WordPress directamente
    wp_update_post([
        'ID'          => $post_id,
        'post_status' => $new_status,
    ]);

    // Actualizar también el campo ACF por si se usa para filtrado o lógica de tema
    if (function_exists('update_field')) {
        update_field('publicar_acf', $new_status, $post_id);
    }
    update_post_meta($post_id, 'publicar_acf', $new_status);

    if ($new_status === 'publish') {
        motorlan_publicacion_mark_approved($post_id);
    }

    // Notificar a los admins si el estado es pending
    if ($new_status === 'pending' && !$is_admin) {
        do_action( 'motorlan_publication_pending_approval', $post_id );
    }

    if ($new_status === 'sold') {
        do_action( 'motorlan_publication_sold_manually', $post_id );
    }

    return new WP_REST_Response(['message' => 'Status updated successfully', 'new_status' => $new_status], 200);
}
