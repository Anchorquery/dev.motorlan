<?php
/**
 * Callback to get a single publicacion by slug.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Handle the request to get a single publication by its slug.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function motorlan_get_publicacion_by_slug(WP_REST_Request $request) {
    $slug = $request->get_param('slug');
    if (empty($slug)) {
        return new WP_Error('no_slug', 'Slug not provided', ['status' => 400]);
    }

    $args = [
        'post_type'      => 'publicacion',
        'name'           => $slug,
        'posts_per_page' => 1,
        'post_status'    => 'any',
    ];
    $query = new WP_Query($args);

    // URLs antiguas: WordPress guarda el slug anterior en _wp_old_slug al cambiarlo
    // (edición de marca/referencia/potencia, aprobación, /refresh-slugs).
    if (!$query->have_posts()) {
        $query = new WP_Query([
            'post_type'      => 'publicacion',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'meta_key'       => '_wp_old_slug',
            'meta_value'     => sanitize_title($slug),
        ]);
    }

    if (!$query->have_posts()) {
        return new WP_Error('not_found', 'Publicación no encontrada', ['status' => 404]);
    }

    $query->the_post();
    $post_id = get_the_ID();
    wp_reset_postdata();

    $post_status = get_post_status($post_id);
    $post_author_id = (int) get_post_field('post_author', $post_id);
    $is_admin = current_user_can('manage_options');
    $is_owner = get_current_user_id() && get_current_user_id() === $post_author_id;

    // Security: If not published, only admin or owner can see it.
    // Pausadas y vendidas siguen visibles (la ficha muestra "No disponible")
    if (!in_array($post_status, ['publish', 'paused', 'sold'], true) && !$is_admin && !$is_owner) {
        return new WP_Error('not_found', 'Publicación no encontrada', ['status' => 404]);
    }

    // Publicada pero pendiente de aprobación o en borrador en ACF: tampoco es pública.
    // (Pausada o vendida sí se devuelve: la ficha muestra "No disponible".)
    $publicar_acf = get_post_meta($post_id, 'publicar_acf', true);
    if (in_array($publicar_acf, ['draft', 'pending'], true) && !$is_admin && !$is_owner) {
        return new WP_Error('not_found', 'Publicación no encontrada', ['status' => 404]);
    }

    // Dueño o admin ven datos sensibles (precio de referencia)
    $show_sensitive = $is_admin || $is_owner;

    $publicacion_data = motorlan_get_publicacion_data($post_id, $show_sensitive);

    // Ficha: reputación completa del vendedor, con número de ventas completadas
    if (!empty($publicacion_data['author'])) {
        $publicacion_data['author']['acf'] = motorlan_get_seller_reputation($post_author_id, true);
    }

    return new WP_REST_Response(['data' => $publicacion_data], 200);
}