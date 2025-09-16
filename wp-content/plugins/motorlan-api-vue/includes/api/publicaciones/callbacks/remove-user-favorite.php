<?php
/**
 * Callback to remove a user favorite.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Handle the request to remove a publication from the current user's favorites.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function motorlan_remove_user_favorite(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    $post_id = intval($request->get_param('id'));
    $favorites = get_user_meta($user_id, 'motorlan_favorites', true);

    if (is_array($favorites)) {
        $favorites = array_filter($favorites, fn($fav) => intval($fav) !== $post_id);
        update_user_meta($user_id, 'motorlan_favorites', $favorites);
    }

    return new WP_REST_Response(['message' => 'Eliminado de favoritos'], 200);
}