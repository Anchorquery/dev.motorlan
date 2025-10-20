<?php
/**
 * Callback to create a new publicacion.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Handle the request to create a new publication.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function motorlan_create_publicacion_callback(WP_REST_Request $request) {
    // Obtener parámetros de la solicitud. Como estamos manejando `multipart/form-data`,
    // los datos de texto vendrán en `get_param` y los archivos en `get_file_params`.
    $params = $request->get_params();
    $files = $request->get_file_params();

    // --- Basic Validation ---
    // Decodificar los campos ACF que vienen como JSON string
    $acf_data = [];
    if (isset($params['acf'])) {
        $acf_data = is_string($params['acf']) ? json_decode($params['acf'], true) : $params['acf'];
    }

    if (empty($params['title'])) return new WP_Error('missing_title', 'El título es obligatorio', ['status' => 400]);
    if (empty($acf_data['marca'])) return new WP_Error('missing_brand', 'La marca es obligatoria', ['status' => 400]);
    if (empty($acf_data['tipo_o_referencia'])) return new WP_Error('missing_reference', 'La referencia es obligatoria', ['status' => 400]);

    // --- Create Post ---
    $slug = '';
    if (!empty($params['slug'])) {
        $slug = sanitize_title($params['slug']);
    }

    $post_data = [
        'post_title'  => sanitize_text_field($params['title']),
        'post_status' => sanitize_text_field($params['status'] ?? 'draft'),
        'post_type'   =>'publicacion',
        'post_author' => get_current_user_id(),
    ];

    if (!empty($slug)) {
        $post_data['post_name'] = $slug;
    }

    $post_id = wp_insert_post($post_data);
    if (is_wp_error($post_id)) {
        return $post_id;
    }

    // --- Assign UUID ---
    $uuid = wp_generate_uuid4();
    update_post_meta($post_id, 'uuid', $uuid);

    // --- Set Taxonomies ---
    // Decodificar si es necesario
    $categories = [];
    if (isset($params['categories'])) {
        $categories = is_string($params['categories']) ? json_decode($params['categories'], true) : $params['categories'];
    }
    if (!empty($categories)) {
        wp_set_post_terms($post_id, $categories, 'categoria');
    }

    $tipo = [];
    if (isset($params['tipo'])) {
        $tipo = is_string($params['tipo']) ? json_decode($params['tipo'], true) : $params['tipo'];
    }
    if (!empty($tipo)) {
        wp_set_post_terms($post_id, $tipo, 'tipo');
    }

    // --- Update ACF Fields ---
    // --- Update ACF Fields ---
    // Asegurarse de que los campos requeridos y opcionales se guarden correctamente.
    // El bucle foreach ya debería manejar esto, pero vamos a ser explícitos para los campos clave.
    foreach ($acf_data as $key => $value) {
        // Sanear el valor si es un string
        $sanitized_value = is_string($value) ? sanitize_text_field($value) : $value;
        update_field($key, $sanitized_value, $post_id);
    }

    // --- Handle File Uploads ---
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    // Imagen principal
    if (isset($files['motor_image'])) {
        $attachment_id = media_handle_upload('motor_image', $post_id);
        if (!is_wp_error($attachment_id)) {
            update_field('motor_image', $attachment_id, $post_id);
        }
    }

    // Galería de imágenes
    if (isset($files['motor_gallery'])) {
        $gallery_ids = [];
        $gallery_files = $files['motor_gallery'];

        // Reorganizar el array de archivos si es necesario
        $files_rearranged = [];
        if (is_array($gallery_files['name'])) {
            foreach ($gallery_files['name'] as $key => $name) {
                $files_rearranged[] = [
                    'name'     => $name,
                    'type'     => $gallery_files['type'][$key],
                    'tmp_name' => $gallery_files['tmp_name'][$key],
                    'error'    => $gallery_files['error'][$key],
                    'size'     => $gallery_files['size'][$key],
                ];
            }
        } else {
            $files_rearranged[] = $gallery_files;
        }

        foreach ($files_rearranged as $file) {
            // Crear un nombre de archivo temporal para `media_handle_sideload`
            $tmp = $file['tmp_name'];
            $file_array = [
                'name'     => $file['name'],
                'tmp_name' => $tmp,
            ];
            $attachment_id = media_handle_sideload($file_array, $post_id);
            if (!is_wp_error($attachment_id)) {
                $gallery_ids[] = $attachment_id;
            }
        }
        if (!empty($gallery_ids)) {
            update_field('motor_gallery', $gallery_ids, $post_id);
        }
    }

    // Documentación adicional
    if (isset($files['documentacion_adicional_archivos'])) {
        $docs_data = [];
        if (isset($params['documentacion_adicional_nombres'])) {
            $docs_data = is_string($params['documentacion_adicional_nombres']) ? json_decode($params['documentacion_adicional_nombres'], true) : $params['documentacion_adicional_nombres'];
        }
        $doc_files = $files['documentacion_adicional_archivos'];
        $uploaded_docs = [];

        $files_rearranged = [];
        if (is_array($doc_files['name'])) {
            foreach ($doc_files['name'] as $key => $name) {
                 $files_rearranged[] = [
                    'name'     => $name,
                    'type'     => $doc_files['type'][$key],
                    'tmp_name' => $doc_files['tmp_name'][$key],
                    'error'    => $doc_files['error'][$key],
                    'size'     => $doc_files['size'][$key],
                ];
            }
        } else {
            $files_rearranged[] = $doc_files;
        }

        foreach ($files_rearranged as $key => $file) {
             $file_array = [
                'name'     => $file['name'],
                'tmp_name' => $file['tmp_name'],
            ];
            $attachment_id = media_handle_sideload($file_array, $post_id);

            if (!is_wp_error($attachment_id)) {
                $uploaded_docs[] = [
                    'nombre' => sanitize_text_field($docs_data[$key]['nombre']),
                    'archivo' => $attachment_id,
                ];
            }
        }
        if (!empty($uploaded_docs)) {
            update_field('documentacion_adicional', $uploaded_docs, $post_id);
        }
    }


    return new WP_REST_Response([
        'message' => 'Publicación creada con éxito.',
        'id'      => $post_id,
        'uuid'    => $uuid,
    ], 201);
}
