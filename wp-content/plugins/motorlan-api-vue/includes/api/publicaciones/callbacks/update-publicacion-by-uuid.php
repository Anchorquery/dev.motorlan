<?php
/**
 * Callback to update a publicacion by UUID.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Handle the request to update a publication by its UUID.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function motorlan_update_publicacion_by_uuid(WP_REST_Request $request) {
    $uuid = $request->get_param('uuid');
    $post_id = motorlan_get_post_id_by_uuid($uuid);

    if (!$post_id) {
        return new WP_Error('not_found', 'Publicación no encontrada', ['status' => 404]);
    }

    $params = $request->get_params();
    $files = $request->get_file_params();

    // --- Handle Text Data ---
    if (isset($params['title'])) {
        wp_update_post(['ID' => $post_id, 'post_title' => sanitize_text_field($params['title'])]);
    }
    if (isset($params['status'])) {
        wp_update_post([
            'ID' => $post_id,
            'post_status' => sanitize_text_field($params['status'])
        ]);
        // Also update the ACF field for consistency if it exists
        update_field('publicar_acf', sanitize_text_field($params['status']), $post_id);
    }

    // --- Handle Taxonomies ---
    $categories = isset($params['categories']) ? (is_string($params['categories']) ? json_decode($params['categories'], true) : $params['categories']) : null;
    if ($categories !== null) {
        wp_set_post_terms($post_id, $categories, 'categoria');
    }

    $tipo = isset($params['tipo']) ? (is_string($params['tipo']) ? json_decode($params['tipo'], true) : $params['tipo']) : null;
    if ($tipo !== null) {
        wp_set_post_terms($post_id, $tipo, 'tipo');
    }

    // --- Handle ACF Fields ---
    $acf_data = isset($params['acf']) ? (is_string($params['acf']) ? json_decode($params['acf'], true) : $params['acf']) : [];
    if (!empty($acf_data)) {
        foreach ($acf_data as $key => $value) {
            update_field($key, $value, $post_id);
        }
    }

    // --- Handle File Uploads ---
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    // Main Image
    if (isset($files['motor_image'])) {
        $attachment_id = media_handle_upload('motor_image', $post_id);
        if (!is_wp_error($attachment_id)) {
            update_field('motor_image', $attachment_id, $post_id);
        }
    } elseif (isset($params['motor_image_id'])) {
        update_field('motor_image', intval($params['motor_image_id']), $post_id);
    } else {
        update_field('motor_image', '', $post_id); // Remove image
    }

    // Gallery
    $gallery_ids = isset($params['motor_gallery_ids']) ? array_map('intval', explode(',', $params['motor_gallery_ids'])) : [];
    if (isset($files['motor_gallery'])) {
        $gallery_files = $files['motor_gallery'];
        $files_rearranged = [];
        if (is_array($gallery_files['name'])) {
            foreach ($gallery_files['name'] as $key => $name) {
                $files_rearranged[] = ['name' => $name, 'type' => $gallery_files['type'][$key], 'tmp_name' => $gallery_files['tmp_name'][$key], 'error' => $gallery_files['error'][$key], 'size' => $gallery_files['size'][$key]];
            }
        } else {
            $files_rearranged[] = $gallery_files;
        }
        foreach ($files_rearranged as $file) {
            $file_array = ['name' => $file['name'], 'tmp_name' => $file['tmp_name']];
            $attachment_id = media_handle_sideload($file_array, $post_id);
            if (!is_wp_error($attachment_id)) {
                $gallery_ids[] = $attachment_id;
            }
        }
    }
    update_field('motor_gallery', $gallery_ids, $post_id);

    // Additional Documentation
    $doc_ids = isset($params['documentacion_adicional_ids']) ? json_decode($params['documentacion_adicional_ids'], true) : [];
    $doc_nombres = isset($params['documentacion_adicional_nombres']) ? json_decode($params['documentacion_adicional_nombres'], true) : [];
    $uploaded_docs = [];

    // Re-associate existing docs
    foreach($doc_ids as $index => $doc) {
        $uploaded_docs[] = [
            'nombre' => sanitize_text_field($doc['nombre']),
            'archivo' => intval($doc['archivo']),
        ];
    }

    if (isset($files['documentacion_adicional_archivos'])) {
        $doc_files = $files['documentacion_adicional_archivos'];
        $files_rearranged = [];
        if (is_array($doc_files['name'])) {
             foreach ($doc_files['name'] as $key => $name) {
                $files_rearranged[] = ['name' => $name, 'type' => $doc_files['type'][$key], 'tmp_name' => $doc_files['tmp_name'][$key], 'error' => $doc_files['error'][$key], 'size' => $doc_files['size'][$key]];
            }
        } else {
            $files_rearranged[] = $doc_files;
        }

        foreach ($files_rearranged as $key => $file) {
            $file_array = ['name' => $file['name'], 'tmp_name' => $file['tmp_name']];
            $attachment_id = media_handle_sideload($file_array, $post_id);
            if (!is_wp_error($attachment_id)) {
                $uploaded_docs[] = [
                    'nombre' => sanitize_text_field($doc_nombres[$key]['nombre']),
                    'archivo' => $attachment_id,
                ];
            }
        }
    }
    update_field('documentacion_adicional', $uploaded_docs, $post_id);

    return new WP_REST_Response(['message' => 'Publicación actualizada correctamente'], 200);
}