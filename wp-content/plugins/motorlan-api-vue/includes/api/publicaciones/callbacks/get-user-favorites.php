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

    $data = [];
    foreach ($favorites as $post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_type ==='publicacion') {
            $data[] = motorlan_get_publicacion_data($post_id);
        }
    }
    return new WP_REST_Response(['data' => $data], 200);
}