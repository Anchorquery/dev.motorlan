<?php
/**
 * Admin Publications Management REST API Routes.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

add_action('rest_api_init', 'motorlan_register_admin_publications_routes');

function motorlan_register_admin_publications_routes() {
    $namespace = 'motorlan/v1';

    // List all publications
    register_rest_route($namespace, '/admin/publications', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'motorlan_admin_get_publications_callback',
        'permission_callback' => 'motorlan_is_admin_user',
    ]);

    // Delete publication
    register_rest_route($namespace, '/admin/publications/(?P<id>\d+)', [
        'methods'             => WP_REST_Server::DELETABLE,
        'callback'            => 'motorlan_admin_delete_publication_callback',
        'permission_callback' => 'motorlan_is_admin_user',
    ]);

    // Update publication (status or other fields)
    register_rest_route($namespace, '/admin/publications/(?P<id>\d+)', [
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => 'motorlan_admin_update_publication_callback',
        'permission_callback' => 'motorlan_is_admin_user',
    ]);

    // Contact publisher
    register_rest_route($namespace, '/admin/publications/(?P<id>\d+)/contact', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'motorlan_admin_contact_publisher_callback',
        'permission_callback' => 'motorlan_is_admin_user',
    ]);
}

/**
 * Get all publications for admin.
 */
function motorlan_admin_get_publications_callback($request) {
    $page = $request->get_param('page') ? intval($request->get_param('page')) : 1;
    $per_page = $request->get_param('per_page') ? intval($request->get_param('per_page')) : 20;
    
    $args = [
        'post_type'      => 'publicacion',
        'post_status'    => ['publish', 'pending', 'draft', 'trash', 'paused', 'sold', 'future', 'private'],
        'posts_per_page' => $per_page,
        'paged'          => $page,
    ];

    // Search
    if ($request->get_param('search')) {
        $args['s'] = sanitize_text_field($request->get_param('search'));
    }

    // Filter by status
    if ($request->get_param('status')) {
        $status = sanitize_text_field($request->get_param('status'));
        if ($status !== 'all') {
            $args['post_status'] = $status;
        }
    }

    // Exclude author (current user or specific ID)
    if ($request->get_param('exclude_author')) {
       $exclude_id = intval($request->get_param('exclude_author'));
       if ($exclude_id > 0) {
           $args['author__not_in'] = [$exclude_id];
       }
    }

    $query = new WP_Query($args);
    $data = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            
            // Basic info
            $item = [
                'id' => $post_id,
                'uuid' => get_post_meta($post_id, 'uuid', true), // Add UUID
                'title' => get_the_title(),
                'status' => get_post_status(),
                'date' => get_the_date('Y-m-d H:i:s'),
                'link' => get_permalink(),
            ];

            // Image
            $featured_img_url = get_the_post_thumbnail_url($post_id, 'thumbnail');
            if (!$featured_img_url) {
                // Fallback to ACF motor_image
                $image_field = get_field('motor_image', $post_id);
                if ($image_field) {
                    if (is_array($image_field) && isset($image_field['sizes']['thumbnail'])) {
                        $featured_img_url = $image_field['sizes']['thumbnail'];
                    } elseif (is_array($image_field) && isset($image_field['url'])) {
                         $featured_img_url = $image_field['url'];
                    } elseif (is_numeric($image_field)) {
                        $featured_img_url = wp_get_attachment_image_url($image_field, 'thumbnail');
                    } elseif (is_string($image_field)) {
                        $featured_img_url = $image_field;
                    }
                }
            }
            $item['image'] = $featured_img_url ? $featured_img_url : '';

            // Price (ACF)
            $item['price'] = get_field('precio_de_venta', $post_id);

            // Author info
            $author_id = get_post_field('post_author', $post_id);
            $author = get_userdata($author_id);
            $item['author'] = [
                'id' => $author_id,
                'name' => $author ? $author->display_name : 'Desconocido',
                'email' => $author ? $author->user_email : '',
            ];

            $data[] = $item;
        }
        wp_reset_postdata();
    }

    return new WP_REST_Response([
        'data' => $data,
        'total' => $query->found_posts,
        'pages' => $query->max_num_pages
    ], 200);
}

/**
 * Delete a publication.
 */
function motorlan_admin_delete_publication_callback($request) {
    $post_id = $request['id'];
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'publicacion') {
        return new WP_Error('not_found', 'Publicación no encontrada', ['status' => 404]);
    }

    $force_delete = $request->get_param('force') === 'true';
    $result = wp_delete_post($post_id, $force_delete);

    if (!$result) {
        return new WP_Error('delete_failed', 'No se pudo eliminar la publicación', ['status' => 500]);
    }

    return new WP_REST_Response(['message' => 'Publicación eliminada correctamente.'], 200);
}

/**
 * Update a publication (status mainly).
 */
function motorlan_admin_update_publication_callback($request) {
    $post_id = $request['id'];
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'publicacion') {
        return new WP_Error('not_found', 'Publicación no encontrada', ['status' => 404]);
    }

    $params = $request->get_json_params();
    $update_data = ['ID' => $post_id];

    if (isset($params['status'])) {
        $allowed_statuses = ['publish', 'pending', 'draft', 'trash', 'paused', 'sold'];
        if (in_array($params['status'], $allowed_statuses)) {
            $update_data['post_status'] = sanitize_text_field($params['status']);
        }
    }

    // Can add more fields to update here if needed

    $result = wp_update_post($update_data);

    if (is_wp_error($result)) {
        return $result;
    }

    return new WP_REST_Response(['message' => 'Publicación actualizada correctamente.'], 200);
}

/**
 * Contact the publisher.
 */
function motorlan_admin_contact_publisher_callback($request) {
    $post_id = $request['id'];
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'publicacion') {
        return new WP_Error('not_found', 'Publicación no encontrada', ['status' => 404]);
    }

    $params = $request->get_json_params();
    $message = isset($params['message']) ? sanitize_textarea_field($params['message']) : '';
    $subject = isset($params['subject']) ? sanitize_text_field($params['subject']) : 'Mensaje del Administrador - Motorlan';

    if (empty($message)) {
        return new WP_Error('missing_message', 'El mensaje es obligatorio', ['status' => 400]);
    }

    $author_id = $post->post_author;
    $author = get_userdata($author_id);

    if (!$author) {
        return new WP_Error('author_not_found', 'Autor no encontrado', ['status' => 404]);
    }

    // Send Email
    $sent = wp_mail($author->user_email, $subject, $message);

    // Optional: Create an internal notification if system exists
    if (class_exists('Motorlan_Notification_Manager')) {
        $notification_manager = new Motorlan_Notification_Manager();
        $notification_manager->create_notification(
            $author_id,
            'admin_message',
            $subject,
            $message,
            ['post_id' => $post_id],
            ['web'] // Email already sent directly
        );
    }

    if (!$sent) {
        return new WP_Error('email_failed', 'No se pudo enviar el correo electrónico', ['status' => 500]);
    }

    return new WP_REST_Response(['message' => 'Mensaje enviado correctamente.'], 200);
}
