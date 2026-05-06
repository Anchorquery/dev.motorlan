<?php
/**
 * Admin Approval REST API Routes.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

add_action('rest_api_init', 'motorlan_register_admin_approval_routes');

function motorlan_register_admin_approval_routes() {
    $namespace = 'motorlan/v1';

    register_rest_route($namespace, '/admin/pending-publications', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'motorlan_get_pending_publications_callback',
        'permission_callback' => 'motorlan_is_admin_user',
    ]);

    register_rest_route($namespace, '/admin/approve-publication/(?P<id>\d+)', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'motorlan_approve_publication_callback',
        'permission_callback' => 'motorlan_is_admin_user',
    ]);

    register_rest_route($namespace, '/admin/reject-publication/(?P<id>\d+)', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'motorlan_reject_publication_callback',
        'permission_callback' => 'motorlan_is_admin_user',
    ]);

    register_rest_route($namespace, '/admin/update-pending-brand/(?P<id>\d+)', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'motorlan_update_pending_brand_callback',
        'permission_callback' => 'motorlan_is_admin_user',
    ]);
}

/**
 * Permission callback for admin routes.
 */
function motorlan_is_admin_user() {
    return current_user_can('administrator');
}

/**
 * Get all pending publications.
 */
function motorlan_get_pending_publications_callback($request) {
    $args = [
        'post_type'   => 'publicacion',
        'post_status' => 'pending',
        'posts_per_page' => -1,
    ];

    $query = new WP_Query($args);
    $data = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            
            // Reutilizar el formateador existente si es posible (motorlan_format_publication_item)
            // Está en includes/api/my-publications-routes.php
            if (function_exists('motorlan_format_publication_item')) {
                $item = motorlan_format_publication_item($post_id);
            } else {
                $item = [
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'author_name' => get_the_author(),
                ];
            }
            
            // Añadir información del autor para que el admin sepa quién solicita
            $author_id = get_post_field('post_author', $post_id);
            $author = get_userdata($author_id);
            $item['author_info'] = [
                'id' => $author_id,
                'name' => $author ? $author->display_name : 'Desconocido',
                'email' => $author ? $author->user_email : '',
            ];

            // Marca pendiente de aprobación
            $pending_brand = get_post_meta($post_id, '_pending_brand_name', true);
            if (!empty($pending_brand)) {
                $item['pending_brand'] = $pending_brand;
            }

            $data[] = $item;
        }
        wp_reset_postdata();
    }

    return new WP_REST_Response($data, 200);
}

/**
 * Approve a publication.
 */
function motorlan_approve_publication_callback($request) {
    // Validate Content-Type
    if ( function_exists( 'motorlan_validate_json_content_type' ) ) {
        $valid_type = motorlan_validate_json_content_type( $request );
        if ( is_wp_error( $valid_type ) ) {
            return $valid_type;
        }
    }

    $post_id = $request['id'];
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'publicacion') {
        return new WP_Error('not_found', 'Publicación no encontrada', ['status' => 404]);
    }

    // Si hay marca pendiente, crearla como término de taxonomía
    $pending_brand = get_post_meta($post_id, '_pending_brand_name', true);

    // Permitir que el admin envíe un nombre editado al aprobar
    $params = $request->get_json_params();
    if (!empty($params['brand_name'])) {
        $pending_brand = strtoupper(sanitize_text_field($params['brand_name']));
    }

    if (!empty($pending_brand)) {
        $existing_term = get_term_by('name', $pending_brand, 'marca');
        if ($existing_term) {
            $brand_term_id = $existing_term->term_id;
        } else {
            $new_term = wp_insert_term($pending_brand, 'marca');
            if (is_wp_error($new_term)) {
                return new WP_Error('brand_create_error', 'Error al crear la marca: ' . $new_term->get_error_message(), ['status' => 500]);
            }
            $brand_term_id = $new_term['term_id'];
        }
        // Asignar la marca al post
        if (function_exists('update_field')) {
            update_field('marca', $brand_term_id, $post_id);
        }
        delete_post_meta($post_id, '_pending_brand_name');
    }

    // Regenerar slug y título para reflejar la marca aprobada
    $new_slug  = motorlan_generate_slug_by_post_id($post_id);
    $new_title = motorlan_format_motor_name($post_id);

    $update_data = [
        'ID'          => $post_id,
        'post_status' => 'publish',
    ];
    if ($new_slug) {
        $update_data['post_name'] = $new_slug;
    }
    if ($new_title) {
        $update_data['post_title'] = $new_title;
    }

    $result = wp_update_post($update_data);

    if (is_wp_error($result)) {
        return $result;
    }

    // Sincronizar campo ACF
    if (function_exists('update_field')) {
        update_field('publicar_acf', 'publish', $post_id);
    }
    update_post_meta($post_id, 'publicar_acf', 'publish');

    // Notificar al usuario
    // Notificar al usuario
    do_action( 'motorlan_publication_approved', $post_id );

    return new WP_REST_Response(['message' => 'Publicación aprobada con éxito.'], 200);
}

/**
 * Update pending brand name before approval.
 */
function motorlan_update_pending_brand_callback($request) {
    if (function_exists('motorlan_validate_json_content_type')) {
        $valid_type = motorlan_validate_json_content_type($request);
        if (is_wp_error($valid_type)) {
            return $valid_type;
        }
    }

    $post_id = $request['id'];
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'publicacion') {
        return new WP_Error('not_found', 'Publicación no encontrada', ['status' => 404]);
    }

    $pending_brand = get_post_meta($post_id, '_pending_brand_name', true);
    if (empty($pending_brand)) {
        return new WP_Error('no_pending_brand', 'Esta publicación no tiene una marca pendiente de aprobación.', ['status' => 400]);
    }

    $params = $request->get_json_params();
    if (empty($params['brand_name'])) {
        return new WP_Error('missing_brand_name', 'El nombre de la marca es obligatorio.', ['status' => 400]);
    }

    $new_brand_name = strtoupper(sanitize_text_field($params['brand_name']));
    update_post_meta($post_id, '_pending_brand_name', $new_brand_name);

    return new WP_REST_Response([
        'message' => 'Marca pendiente actualizada correctamente.',
        'brand_name' => $new_brand_name,
    ], 200);
}

/**
 * Reject a publication.
 */
function motorlan_reject_publication_callback($request) {
    // Validate Content-Type
    if ( function_exists( 'motorlan_validate_json_content_type' ) ) {
        $valid_type = motorlan_validate_json_content_type( $request );
        if ( is_wp_error( $valid_type ) ) {
            return $valid_type;
        }
    }

    $post_id = $request['id'];
    $params = $request->get_json_params();
    $reason = !empty($params['reason']) ? sanitize_text_field($params['reason']) : 'No cumple con las normas de la comunidad.';

    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'publicacion') {
        return new WP_Error('not_found', 'Publicación no encontrada', ['status' => 404]);
    }

    // Cambiar a draft o trash? El usuario dijo "responder a la solicitud".
    // "Rechazar" suele significar que no se publica. Lo pondremos en draft para que el usuario pueda corregirlo.
    $result = wp_update_post([
        'ID'          => $post_id,
        'post_status' => 'draft',
    ]);

    if (is_wp_error($result)) {
        return $result;
    }

    // Sincronizar campo ACF
    if (function_exists('update_field')) {
        update_field('publicar_acf', 'draft', $post_id);
    }
    update_post_meta($post_id, 'publicar_acf', 'draft');

    // Notificar al usuario con el motivo
    // Notificar al usuario con el motivo
    do_action( 'motorlan_publication_rejected', $post_id, $reason );

    return new WP_REST_Response(['message' => 'Publicación rechazada con éxito.'], 200);
}
