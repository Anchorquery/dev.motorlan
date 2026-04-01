<?php
/**
 * Script de corrección única: arregla publicaciones con marca = -1 (mostradas como "false").
 *
 * Uso: Accede a /wp-json/motorlan/v1/admin/fix-broken-brands desde el navegador
 *      estando logueado como administrador.
 *
 * - Publicaciones con _pending_brand_name → crea el término y lo asigna.
 * - Publicaciones sin _pending_brand_name → se reportan para revisión manual.
 *
 * Este script es idempotente: puede ejecutarse varias veces sin problema.
 */

if (!defined('WPINC')) {
    die;
}

add_action('rest_api_init', function () {
    register_rest_route('motorlan/v1', '/admin/fix-broken-brands', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'motorlan_fix_broken_brands_callback',
        'permission_callback' => function () {
            return current_user_can('administrator');
        },
    ]);
});

function motorlan_fix_broken_brands_callback() {
    global $wpdb;

    // Buscar todos los posts donde el meta 'marca' es -1
    $post_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT pm.post_id FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = %s AND pm.meta_value = %s AND p.post_type = %s",
            'marca',
            '-1',
            'publicacion'
        )
    );

    if (empty($post_ids)) {
        return new WP_REST_Response([
            'message' => 'No se encontraron publicaciones con marca rota.',
            'fixed'   => 0,
            'manual'  => 0,
        ], 200);
    }

    $fixed = [];
    $needs_manual = [];

    foreach ($post_ids as $post_id) {
        $post_id = (int) $post_id;
        $post = get_post($post_id);
        if (!$post) continue;

        $pending_brand = get_post_meta($post_id, '_pending_brand_name', true);

        if (!empty($pending_brand)) {
            $brand_name = strtoupper(sanitize_text_field($pending_brand));

            // Verificar si el término ya existe
            $existing_term = get_term_by('name', $brand_name, 'marca');
            if ($existing_term) {
                $term_id = $existing_term->term_id;
            } else {
                $new_term = wp_insert_term($brand_name, 'marca');
                if (is_wp_error($new_term)) {
                    $needs_manual[] = [
                        'post_id' => $post_id,
                        'title'   => $post->post_title,
                        'status'  => $post->post_status,
                        'reason'  => 'Error al crear término: ' . $new_term->get_error_message(),
                        'pending_brand' => $brand_name,
                    ];
                    continue;
                }
                $term_id = $new_term['term_id'];
            }

            // Asignar la marca al post
            update_field('marca', $term_id, $post_id);
            delete_post_meta($post_id, '_pending_brand_name');

            $fixed[] = [
                'post_id'    => $post_id,
                'title'      => $post->post_title,
                'status'     => $post->post_status,
                'brand_name' => $brand_name,
                'term_id'    => $term_id,
            ];
        } else {
            // No hay _pending_brand_name → revisión manual
            $needs_manual[] = [
                'post_id' => $post_id,
                'title'   => $post->post_title,
                'status'  => $post->post_status,
                'reason'  => 'No tiene _pending_brand_name. Hay que asignar marca manualmente.',
            ];
        }
    }

    return new WP_REST_Response([
        'message' => sprintf(
            'Proceso completado. %d corregidas, %d requieren revisión manual.',
            count($fixed),
            count($needs_manual)
        ),
        'fixed'        => count($fixed),
        'fixed_detail' => $fixed,
        'manual'       => count($needs_manual),
        'manual_detail' => $needs_manual,
    ], 200);
}
