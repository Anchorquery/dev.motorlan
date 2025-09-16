<?php
/**
 * Callback to add a user favorite.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Handle the request to add a publication to the current user's favorites.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function motorlan_add_user_favorite(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    $params = $request->get_json_params();
    $post_id = intval($params['publicacion_id'] ?? 0);

    if (!$post_id || get_post_type($post_id) !== 'publicaciones') {
        return new WP_Error('invalid_post', 'Publicación inválida', ['status' => 400]);
    }

    $favorites = get_user_meta($user_id, 'motorlan_favorites', true);
    if (!is_array($favorites)) $favorites = [];

    if (!in_array($post_id, $favorites)) {
        $favorites[] = $post_id;
        update_user_meta($user_id, 'motorlan_favorites', $favorites);
    }

    return new WP_REST_Response(['message' => 'Agregado a favoritos'], 201);
}