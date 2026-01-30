<?php
/**
 * Callback to update publicacion status by ID.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Handle the request to update a publication's status by its ID.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function motorlan_update_publicacion_status(WP_REST_Request $request) {
    $post_id = $request->get_param('id');
    $params = $request->get_json_params();
    $new_status = $params['status'] ?? '';
    $is_admin = current_user_can('administrator');

    if (empty($new_status)) {
        return new WP_Error('no_status', 'Status not provided', ['status' => 400]);
    }

    // Si el usuario no es admin y quiere publicar, forzar 'pending'
    if (!$is_admin && $new_status === 'publish') {
        $new_status = 'pending';
    }

    // Si se intenta publicar/pedir aprobación y el stock actual es 0, establecerlo a 1
    if ($new_status === 'publish' || $new_status === 'pending') {
        $current_stock_raw = function_exists('get_field') ? get_field('stock', $post_id) : get_post_meta($post_id, 'stock', true);
        $current_stock = (int) $current_stock_raw;

        if ($current_stock <= 0) {
            if (function_exists('update_field')) {
                update_field('stock', 1, $post_id);
            }
            update_post_meta($post_id, 'stock', 1);
        }
    }

    // Actualizar el estado del post en WordPress directamente
    wp_update_post([
        'ID'          => $post_id,
        'post_status' => $new_status,
    ]);

    // Actualizar también el campo ACF por si se usa para filtrado o lógica de tema
    if (function_exists('update_field')) {
        update_field('publicar_acf', $new_status, $post_id);
    }
    update_post_meta($post_id, 'publicar_acf', $new_status);

    // Notificar a los admins si el estado es pending
    if ($new_status === 'pending' && !$is_admin) {
        if (class_exists('Motorlan_Notification_Manager')) {
            $notification_manager = new Motorlan_Notification_Manager();
            $admins = get_users(['role' => 'administrator']);
            foreach ($admins as $admin) {
                $notification_manager->create_notification(
                    $admin->ID,
                    'pending_approval',
                    'Solicitud de publicación',
                    'El usuario ' . wp_get_current_user()->display_name . ' ha solicitado publicar "' . get_the_title($post_id) . '".',
                    [
                        'post_id' => $post_id,
                        'url' => '/dashboard/admin/approvals',
                    ],
                    ['web', 'email']
                );
            }
        }
    }

    return new WP_REST_Response(['message' => 'Status updated successfully', 'new_status' => $new_status], 200);
}
