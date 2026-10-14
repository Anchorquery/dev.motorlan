<?php
/**
 * Callback to get user favorites.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Handle the request to get the current user's favorite publications.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function motorlan_get_user_favorites(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    $favorites = get_user_meta($user_id, 'motorlan_favorites', true);
    if (!is_array($favorites)) $favorites = [];

    // ?fields=ids: solo los IDs visibles (la ficha solo necesita saber si es favorito;
    // antes descargaba todos los favoritos con todos sus datos en cada visita).
    $only_ids = $request->get_param('fields') === 'ids';

    $data = [];
    foreach (array_unique(array_map('intval', $favorites)) as $post_id) {
        $post = get_post($post_id);
        // Visibles: publicadas, pausadas o vendidas, o propias (no borradores/pendientes ajenos)
        if ($post && $post->post_type ==='publicacion'
            && (in_array($post->post_status, ['publish', 'paused', 'sold'], true) || (int) $post->post_author === (int) $user_id)) {
            $data[] = $only_ids ? ['id' => (int) $post_id] : motorlan_get_publicacion_data($post_id);
        }
    }
    return new WP_REST_Response(['data' => $data], 200);
}