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
        // array_values: array_filter conservaba las claves y se guardaba un array con huecos
        $favorites = array_values(array_filter($favorites, fn($fav) => intval($fav) !== $post_id));
        update_user_meta($user_id, 'motorlan_favorites', $favorites);
    }

    // Favoritos antiguos (meta legacy favorite_motors, IDs a veces guardados como string)
    $legacy_favorites = get_user_meta($user_id, 'favorite_motors', true);
    if (is_array($legacy_favorites)) {
        $filtered_legacy = array_values(array_filter($legacy_favorites, fn($fav) => intval($fav) !== $post_id));
        if (count($filtered_legacy) !== count($legacy_favorites)) {
            update_user_meta($user_id, 'favorite_motors', $filtered_legacy);
        }
    }

    return new WP_REST_Response(['message' => 'Eliminado de favoritos'], 200);
}