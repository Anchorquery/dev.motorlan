<?php
/**
 * Callback to duplicate a publicacion by ID.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Handle the request to duplicate a publication by its ID.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function motorlan_duplicate_publicacion(WP_REST_Request $request) {
    $original_post_id = $request->get_param('id');
    $original_post = get_post($original_post_id);

    if (!$original_post) {
        return new WP_Error('not_found', 'Publicación original no encontrada', ['status' => 404]);
    }

    // Security check: If post is pending, only admin can duplicate
    if ($original_post->post_status === 'pending' && !current_user_can('administrator')) {
        return new WP_Error('forbidden', 'No puedes duplicar una publicación que está en revisión.', ['status' => 403]);
    }

    $new_post_data = [
        'post_title'  => $original_post->post_title . ' (copia)',
        'post_status' => 'draft',
        'post_type'   => $original_post->post_type,
        'post_author' => get_current_user_id(),
    ];

    $new_post_id = wp_insert_post($new_post_data);
    if (is_wp_error($new_post_id)) {
        return $new_post_id;
    }

    $acf_fields = get_fields($original_post_id);
    if ($acf_fields) {
        foreach ($acf_fields as $name => $value) {
            update_field($name, $value, $new_post_id);
        }
    }

    $taxonomies = get_object_taxonomies($original_post->post_type);
    foreach ($taxonomies as $taxonomy) {
        $post_terms = wp_get_object_terms($original_post_id, $taxonomy, ['fields' => 'slugs']);
        wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
    }

    update_field('uuid', wp_generate_uuid4(), $new_post_id);
    update_field('publicar_acf', 'draft', $new_post_id);

    return new WP_REST_Response(['message' => 'Publicación duplicada correctamente', 'new_post_id' => $new_post_id], 200);
}