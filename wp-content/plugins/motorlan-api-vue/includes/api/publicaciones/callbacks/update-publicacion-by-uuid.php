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

    if (isset($params['slug'])) {
        $sanitized_slug = sanitize_title($params['slug']);
        if (!empty($sanitized_slug)) {
            wp_update_post([
                'ID' => $post_id,
                'post_name' => $sanitized_slug,
            ]);
        }
    }
    // --- Handle Post Status ---
    // This is critical for the post to be 'published', 'draft', etc.
    if (isset($params['status'])) {
        $new_status = sanitize_text_field($params['status']);

        // Si se intenta publicar, validar/balancear stock segun reglas
        if ($new_status === 'publish') {
            // Inspeccionar si el usuario envió stock en el payload
            $payload_acf = [];
            if (isset($params['acf'])) {
                $payload_acf = is_string($params['acf']) ? json_decode($params['acf'], true) : $params['acf'];
                $payload_acf = is_array($payload_acf) ? $payload_acf : [];
            }

            $payload_has_stock = array_key_exists('stock', $payload_acf);
            $payload_stock_val = $payload_has_stock ? (int) $payload_acf['stock'] : null;

            // Leer stock actual
            $current_stock_raw = function_exists('get_field') ? get_field('stock', $post_id) : get_post_meta($post_id, 'stock', true);
            $current_stock = (int) $current_stock_raw;

            if ($payload_has_stock) {
                // Si el usuario intenta publicar con stock 0, bloquear
                if ($payload_stock_val <= 0) {
                    return new WP_Error('invalid_stock_publish', 'No se puede publicar una publicacin con stock en 0.', ['status' => 400]);
                }
            } else {
                // Si el usuario no lo cambió y el stock actual es 0, setear a 1
                if ($current_stock <= 0) {
                    if (function_exists('update_field')) {
                        update_field('stock', 1, $post_id);
                    }
                    update_post_meta($post_id, 'stock', 1);
                }
            }
        }

        // Create an array with the data to update the post
        $post_update_data = [
            'ID'          => $post_id,
            'post_status' => $new_status,
        ];

        // Update the post in the database
        wp_update_post($post_update_data);

        // Also update the ACF field for consistency in the UI
        update_field('publicar_acf', $new_status, $post_id);
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
    
    // --- Update ACF Fields Individually ---
    if (!empty($acf_data)) {
        if (isset($acf_data['marca'])) update_field('marca', $acf_data['marca'], $post_id);
        if (isset($acf_data['tipo_o_referencia'])) update_field('tipo_o_referencia', sanitize_text_field($acf_data['tipo_o_referencia']), $post_id);
        if (isset($acf_data['potencia'])) update_field('potencia', $acf_data['potencia'], $post_id);
        if (isset($acf_data['velocidad'])) update_field('velocidad', $acf_data['velocidad'], $post_id);
        if (isset($acf_data['par_nominal'])) update_field('par_nominal', $acf_data['par_nominal'], $post_id);
        if (isset($acf_data['voltaje'])) update_field('voltaje', $acf_data['voltaje'], $post_id);
        if (isset($acf_data['intensidad'])) update_field('intensidad', $acf_data['intensidad'], $post_id);
        if (isset($acf_data['pais'])) update_field('pais', sanitize_text_field($acf_data['pais']), $post_id);
        if (isset($acf_data['provincia'])) update_field('provincia', sanitize_text_field($acf_data['provincia']), $post_id);
        if (isset($acf_data['estado_del_articulo'])) update_field('estado_del_articulo', sanitize_text_field($acf_data['estado_del_articulo']), $post_id);
        if (isset($acf_data['informe_de_reparacion'])) update_field('informe_de_reparacion', $acf_data['informe_de_reparacion'], $post_id);
        if (isset($acf_data['descripcion'])) update_field('descripcion', sanitize_textarea_field($acf_data['descripcion']), $post_id);
        if (isset($acf_data['posibilidad_de_alquiler'])) update_field('posibilidad_de_alquiler', sanitize_text_field($acf_data['posibilidad_de_alquiler']), $post_id);
        if (isset($acf_data['tipo_de_alimentacion'])) update_field('tipo_de_alimentacion', sanitize_text_field($acf_data['tipo_de_alimentacion']), $post_id);
        if (isset($acf_data['servomotores'])) update_field('servomotores', $acf_data['servomotores'], $post_id);
        if (isset($acf_data['regulacion_electronica_drivers'])) update_field('regulacion_electronica_drivers', $acf_data['regulacion_electronica_drivers'], $post_id);
        if (isset($acf_data['precio_de_venta'])) update_field('precio_de_venta', $acf_data['precio_de_venta'], $post_id);
        if (isset($acf_data['precio_negociable'])) update_field('precio_negociable', sanitize_text_field($acf_data['precio_negociable']), $post_id);
        if (isset($acf_data['documentacion_adjunta'])) update_field('documentacion_adjunta', $acf_data['documentacion_adjunta'], $post_id);
        if (isset($acf_data['stock'])) update_field('stock', intval($acf_data['stock']), $post_id);
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
