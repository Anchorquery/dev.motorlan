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

    // Security check: Only author or admin can delete
    $post = get_post($post_id);
    // Solo publicaciones: antes se podía borrar (definitivamente) cualquier post propio
    // por ID, p. ej. compras, opiniones o preguntas.
    if (!$post || $post->post_type !== 'publicacion') {
        return new WP_Error('not_found', 'Publicación no encontrada', ['status' => 404]);
    }
    if ($post->post_author != get_current_user_id() && !current_user_can('administrator')) {
        return new WP_Error('forbidden', 'No tienes permisos para eliminar esta publicación.', ['status' => 403]);
    }

    // Security check: If post is pending, only admin can delete
    $post_status = get_post_status($post_id);
    $is_admin = current_user_can('administrator');
    if ($post_status === 'trash' && !$is_admin) {
        return new WP_Error('not_found', 'Publicación no encontrada', ['status' => 404]);
    }
    if ($post_status === 'pending' && !$is_admin) {
        return new WP_Error('forbidden', 'No puedes eliminar una publicación que está en revisión.', ['status' => 403]);
    }

    // No borrar si hay compras asociadas: el historial del comprador depende del post.
    if (!$is_admin && motorlan_publicacion_has_purchases($post_id)) {
        return new WP_Error('has_purchases', 'Esta publicación tiene compras asociadas: pásala a "Vendido" o "Pausado" en lugar de eliminarla.', ['status' => 409]);
    }

    // Papelera en vez de borrado definitivo: recuperable por el admin y sin dejar
    // compras/ofertas/chats apuntando a un post inexistente.
    $result = wp_trash_post($post_id);

    if ($result === false) {
        return new WP_Error('delete_failed', 'No se pudo eliminar la publicación.', ['status' => 500]);
    }

    return new WP_REST_Response(['message' => 'Publicación eliminada correctamente.'], 200);
}