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
    $post_id = $request['id'];
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'publicacion') {
        return new WP_Error('not_found', 'Publicación no encontrada', ['status' => 404]);
    }

    $result = wp_update_post([
        'ID'          => $post_id,
        'post_status' => 'publish',
    ]);

    if (is_wp_error($result)) {
        return $result;
    }

    // Notificar al usuario
    if (class_exists('Motorlan_Notification_Manager')) {
        $notification_manager = new Motorlan_Notification_Manager();
        $notification_manager->create_notification(
            $post->post_author,
            'publication_approved',
            'Tu producto ha sido aprobado',
            'Felicidades, tu publicación "' . $post->post_title . '" ya está visible en la tienda.',
            [
                'post_id' => $post_id,
                'url' => '/dashboard/publications/list',
            ],
            ['web', 'email']
        );
    }

    return new WP_REST_Response(['message' => 'Publicación aprobada con éxito.'], 200);
}

/**
 * Reject a publication.
 */
function motorlan_reject_publication_callback($request) {
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

    // Notificar al usuario con el motivo
    if (class_exists('Motorlan_Notification_Manager')) {
        $notification_manager = new Motorlan_Notification_Manager();
        $notification_manager->create_notification(
            $post->post_author,
            'publication_rejected',
            'Tu producto requiere cambios',
            'Tu publicación "' . $post->post_title . '" no ha sido aprobada. Motivo: ' . $reason,
            [
                'post_id' => $post_id,
                'reason' => $reason,
                'url' => '/dashboard/publications/list',
            ],
            ['web', 'email']
        );
    }

    return new WP_REST_Response(['message' => 'Publicación rechazada con éxito.'], 200);
}
