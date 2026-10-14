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
 * Campos ACF que acepta el alta (misma lista que maneja la edición). Nunca uuid,
 * publicar_acf, tipo, imágenes ni documentación (van por FormData) ni metas arbitrarias:
 * antes el bucle escribía CUALQUIER clave recibida (p. ej. pisar el uuid de otra publicación).
 *
 * @return string[]
 */
function motorlan_create_publicacion_allowed_acf_fields() {
    return [
        'marca', 'tipo_o_referencia', 'potencia', 'velocidad', 'par_nominal', 'voltaje', 'intensidad',
        'pais', 'provincia', 'estado_del_articulo', 'descripcion', 'posibilidad_de_alquiler',
        'tipo_de_alimentacion', 'precio_de_venta', 'precio_negociable', 'stock',
    ];
}

/**
 * Borra una publicación a medias (alta interrumpida) junto con sus adjuntos.
 *
 * @param int $post_id
 * @return void
 */
function motorlan_create_publicacion_discard_incomplete($post_id) {
    $attachments = get_children([
        'post_parent' => $post_id,
        'post_type'   => 'attachment',
        'fields'      => 'ids',
        'numberposts' => -1,
    ]);
    foreach ((array) $attachments as $attachment_id) {
        wp_delete_attachment((int) $attachment_id, true);
    }
    wp_delete_post($post_id, true);
}

/**
 * Handle the request to create a new publication.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function motorlan_create_publicacion_callback(WP_REST_Request $request) {
    // Si el cuerpo supera post_max_size, PHP vacía $_POST y $_FILES: avisar con claridad
    // (antes el usuario veía "El título es obligatorio" tras rellenar todo el asistente).
    $too_large = motorlan_publicacion_payload_too_large($request);
    if ($too_large) {
        return $too_large;
    }

    // Obtener parámetros de la solicitud. Como estamos manejando `multipart/form-data`,
    // los datos de texto vendrán en `get_param` y los archivos en `get_file_params`.
    $params = $request->get_params();
    $files = $request->get_file_params();
    $is_admin = current_user_can('administrator');
    $user_id = get_current_user_id();

    // --- Idempotencia ante reintentos (doble clic, timeout del proxy...) ---
    // El front genera un id por intento de alta. Si ya se completó, devolver la existente;
    // si quedó a medias (borrador interrumpido), descartarla y crearla de nuevo.
    $req_id = isset($params['client_request_id']) ? sanitize_key($params['client_request_id']) : '';
    $req_hash = $req_id !== '' ? md5($user_id . '|' . $req_id) : '';
    $idem_key = $req_hash !== '' ? 'motorlan_pub_req_' . $req_hash : '';
    if ($idem_key) {
        $existing_id = (int) get_transient($idem_key);
        if ($existing_id && get_post($existing_id) && (int) get_post_field('post_author', $existing_id) === $user_id) {
            return new WP_REST_Response([
                'message'       => 'Publicación ya creada.',
                'id'            => $existing_id,
                'uuid'          => get_post_meta($existing_id, 'uuid', true),
                'status'        => get_post_status($existing_id),
                'upload_errors' => [],
                'duplicate'     => true,
            ], 200);
        }

        $incomplete = get_posts([
            'post_type'      => 'publicacion',
            'post_status'    => 'draft',
            'author'         => $user_id,
            'posts_per_page' => 5,
            'fields'         => 'ids',
            'meta_key'       => '_motorlan_create_incomplete',
            'meta_value'     => $req_hash,
        ]);
        foreach ($incomplete as $incomplete_id) {
            // La petición anterior puede seguir en curso (p. ej. 504 del proxy mientras PHP
            // genera miniaturas): borrarla dejaba adjuntos y avisos huérfanos
            if (time() - (int) get_post_time('U', true, $incomplete_id) < 10 * MINUTE_IN_SECONDS) {
                return new WP_Error('create_in_progress', 'La publicación se está creando todavía. Espera unos segundos y revisa "Mis publicaciones".', ['status' => 409]);
            }
            motorlan_create_publicacion_discard_incomplete((int) $incomplete_id);
        }
    }

    // --- Basic Validation ---
    // Decodificar los campos ACF que vienen como JSON string
    $acf_data = [];
    if (isset($params['acf'])) {
        $acf_data = is_string($params['acf']) ? json_decode($params['acf'], true) : $params['acf'];
    }

    if (!is_array($acf_data)) {
        $acf_data = [];
    }

    // Lista blanca de campos (ver motorlan_create_publicacion_allowed_acf_fields).
    $acf_data = array_intersect_key($acf_data, array_flip(motorlan_create_publicacion_allowed_acf_fields()));

    // Números > 0, stock entero, alquiler 'Sí'/'No', alimentación ac/dc...
    $acf_data = motorlan_publicacion_normalize_acf_values($acf_data);
    if (is_wp_error($acf_data)) {
        return $acf_data;
    }

    // La marca debe ser un término existente de "marca" (o -1 SOLO con marca_custom).
    if (array_key_exists('marca', $acf_data)) {
        $marca_id = is_numeric($acf_data['marca']) ? (int) $acf_data['marca'] : 0;
        $marca_term = $marca_id > 0 ? get_term($marca_id, 'marca') : null;
        if ($marca_term && !is_wp_error($marca_term)) {
            $acf_data['marca'] = $marca_id;
        } else {
            unset($acf_data['marca']); // -> missing_brand salvo que venga marca_custom
        }
    }

    $title = isset($params['title']) ? trim(sanitize_text_field($params['title'])) : '';
    if ($title === '') return new WP_Error('missing_title', 'El título es obligatorio', ['status' => 400]);

    // Tipo obligatorio y existente (antes tipo=[9999] saltaba la validación de motor y dejaba el post sin tipo).
    $tipo_ids = array_values(array_filter(motorlan_parse_tipo_param($params['tipo'] ?? null), function ($id) {
        $term = get_term((int) $id, 'tipo');
        return $term && !is_wp_error($term);
    }));
    if (empty($tipo_ids)) {
        return new WP_Error('missing_tipo', 'El tipo de publicación es obligatorio', ['status' => 400]);
    }
    $is_motor = motorlan_tipo_is_motor($tipo_ids);

    // Reguladores/otros no tienen especificaciones de motor: no guardar restos del asistente.
    if (!$is_motor) {
        foreach (['potencia', 'velocidad', 'par_nominal', 'voltaje', 'intensidad', 'tipo_de_alimentacion'] as $motor_field) {
            unset($acf_data[$motor_field]);
        }
    }

    // Marca personalizada. Solo se RESUELVE aquí; el término (admin) se crea después de
    // validar todo para no dejar marcas huérfanas si falla otra validación.
    $pending_brand_name = null;
    $brand_to_create = '';
    $custom_brand_name = !empty($params['marca_custom']) ? motorlan_mb_upper(trim(sanitize_text_field($params['marca_custom']))) : '';
    if ($custom_brand_name !== '') {
        // Verificar si ya existe (case-insensitive)
        $existing_term = get_term_by('name', $custom_brand_name, 'marca');
        if ($existing_term) {
            $acf_data['marca'] = $existing_term->term_id;
        } elseif ($is_admin) {
            // Admin: se creará la marca directamente sin necesidad de aprobación
            $brand_to_create = $custom_brand_name;
        } else {
            // Usuario normal: diferir a aprobación del admin
            $pending_brand_name = $custom_brand_name;
            $acf_data['marca'] = -1; // Valor temporal
        }
    }

    if (empty($acf_data['marca']) && $brand_to_create === '' && !$pending_brand_name) return new WP_Error('missing_brand', 'La marca es obligatoria', ['status' => 400]);
    if (trim((string) ($acf_data['tipo_o_referencia'] ?? '')) === '') return new WP_Error('missing_reference', 'La referencia es obligatoria', ['status' => 400]);

    // Especificaciones técnicas: solo obligatorias para motores (reguladores/otros no las tienen).
    if ($is_motor) {
        if (!isset($acf_data['velocidad']) || $acf_data['velocidad'] === '' || $acf_data['velocidad'] === null) return new WP_Error('missing_speed', 'La velocidad es obligatoria', ['status' => 400]);
        $is_ac = isset($acf_data['tipo_de_alimentacion']) && $acf_data['tipo_de_alimentacion'] === 'ac';
        if ($is_ac) {
            if (!isset($acf_data['potencia']) || $acf_data['potencia'] === '' || $acf_data['potencia'] === null) return new WP_Error('missing_power', 'La potencia es obligatoria para motores AC', ['status' => 400]);
        } else {
            $has_potencia = isset($acf_data['potencia']) && $acf_data['potencia'] !== '' && $acf_data['potencia'] !== null;
            $has_par = isset($acf_data['par_nominal']) && $acf_data['par_nominal'] !== '' && $acf_data['par_nominal'] !== null;
            if (!$has_potencia && !$has_par) return new WP_Error('missing_power_or_torque', 'Debe especificar Potencia o Par Nominal', ['status' => 400]);
        }
    }

    // --- Archivos: validar ANTES de crear nada ---
    $upload_error = motorlan_publicacion_find_upload_error($files, ['motor_image', 'motor_gallery', 'documentacion_adicional_archivos']);
    if ($upload_error) {
        return $upload_error;
    }

    $image_mimes = motorlan_publicacion_image_mimes();
    $doc_mimes = motorlan_publicacion_doc_mimes();

    // Imagen principal obligatoria (antes solo la exigía el front).
    $main_image_files = motorlan_publicacion_rearrange_files($files['motor_image'] ?? null);
    $main_image = $main_image_files[0] ?? null;
    if (!$main_image || (int) $main_image['error'] === UPLOAD_ERR_NO_FILE) {
        return new WP_Error('missing_image', 'La imagen principal es obligatoria', ['status' => 400]);
    }
    if (!motorlan_publicacion_file_type_allowed($main_image, $image_mimes)) {
        return new WP_Error('invalid_image', 'La imagen principal debe ser JPG, PNG, GIF o WebP.', ['status' => 400]);
    }

    // Galería: máximo 5 imágenes (ACF max = 5; update_field no lo aplica).
    $gallery_files = array_values(array_filter(motorlan_publicacion_rearrange_files($files['motor_gallery'] ?? null), function ($file) {
        return (int) $file['error'] !== UPLOAD_ERR_NO_FILE;
    }));
    if (count($gallery_files) > 5) {
        return new WP_Error('gallery_limit', 'La galería admite como máximo 5 imágenes.', ['status' => 400]);
    }
    foreach ($gallery_files as $file) {
        if (!motorlan_publicacion_file_type_allowed($file, $image_mimes)) {
            return new WP_Error('invalid_image', sprintf('"%s" no es una imagen admitida (JPG, PNG, GIF o WebP).', sanitize_file_name($file['name'])), ['status' => 400]);
        }
    }

    // Documentación: máximo 5 (repeater ACF) y solo PDF/JPG/PNG.
    $doc_files = motorlan_publicacion_rearrange_files($files['documentacion_adicional_archivos'] ?? null);
    $doc_files_valid = [];
    foreach ($doc_files as $key => $file) {
        if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if (!motorlan_publicacion_file_type_allowed($file, $doc_mimes)) {
            return new WP_Error('invalid_document', sprintf('"%s" no es un documento admitido (PDF, JPG o PNG).', sanitize_file_name($file['name'])), ['status' => 400]);
        }
        $doc_files_valid[$key] = $file;
    }
    if (count($doc_files_valid) > 5) {
        return new WP_Error('documents_limit', 'Se admiten como máximo 5 documentos.', ['status' => 400]);
    }

    // --- Estado ---
    $requested_status = sanitize_text_field($params['status'] ?? 'draft');
    if (!in_array($requested_status, ['publish', 'pending', 'draft'], true)) {
        $requested_status = 'draft';
    }

    // Si el usuario no es admin, forzar estado 'pending' si intentaba publicar
    if (!$is_admin && $requested_status === 'publish') {
        $requested_status = 'pending';
    }

    // Validar que no se publique con stock en 0
    if ($requested_status === 'publish' || $requested_status === 'pending') {
        $incoming_stock = isset($acf_data['stock']) && $acf_data['stock'] !== '' ? (int) $acf_data['stock'] : null;
        if ($incoming_stock === null || $incoming_stock <= 0) {
            return new WP_Error('invalid_stock_publish', 'No se puede publicar una publicación con stock en 0.', ['status' => 400]);
        }
    }

    // --- Validación completa: a partir de aquí se escribe en BD ---

    // Admin: crear la marca nueva ahora (ya validado todo lo demás).
    if ($brand_to_create !== '') {
        $new_term = wp_insert_term($brand_to_create, 'marca');
        if (is_wp_error($new_term) && $new_term->get_error_code() === 'term_exists') {
            $acf_data['marca'] = (int) $new_term->get_error_data();
        } elseif (is_wp_error($new_term)) {
            return new WP_Error('brand_create_error', 'Error al crear la marca: ' . $new_term->get_error_message(), ['status' => 500]);
        } else {
            $acf_data['marca'] = (int) $new_term['term_id'];
        }
    }

    // --- Create Post ---
    // Slug con el formato del servidor (el slug que envíe el cliente se ignora).
    $slug = motorlan_generate_publicacion_slug([
        'title' => $title,
        'acf'   => $acf_data,
        'tipo'  => $tipo_ids,
    ]);

    // Se crea como borrador y se pasa al estado final al terminar de subir archivos:
    // si la petición muere a mitad, no queda una publicación visible sin imágenes.
    $post_data = [
        'post_title'  => $title,
        'post_status' => 'draft',
        'post_type'   => 'publicacion',
        'post_author' => $user_id,
    ];

    if (!empty($slug)) {
        $post_data['post_name'] = $slug;
    }

    $post_id = wp_insert_post($post_data, true);
    if (is_wp_error($post_id)) {
        return $post_id;
    }

    if ($req_hash !== '') {
        update_post_meta($post_id, '_motorlan_create_incomplete', $req_hash);
    }

    update_field('publicar_acf', 'draft', $post_id);

    // Si hay marca pendiente, guardarla como meta para aprobación
    if ($pending_brand_name) {
        update_post_meta($post_id, '_pending_brand_name', $pending_brand_name);
    }

    // --- UUID ---
    // save_post_publicacion (cpt-setup.php) ya lo genera en wp_insert_post: reutilizarlo.
    $uuid = get_post_meta($post_id, 'uuid', true);
    if (empty($uuid)) {
        $uuid = wp_generate_uuid4();
        update_post_meta($post_id, 'uuid', $uuid);
    }

    // --- Set Taxonomies ---
    // Asignación de "categoria" DESACTIVADA: se usa "tipo".
    /*
    $categories = [];
    if (isset($params['categories'])) {
        $categories = is_string($params['categories']) ? json_decode($params['categories'], true) : $params['categories'];
    }
    if (!empty($categories)) {
        wp_set_post_terms($post_id, $categories, 'categoria');
    }
    */

    wp_set_post_terms($post_id, $tipo_ids, 'tipo');

    // --- Update ACF Fields (solo lista blanca) ---
    foreach ($acf_data as $key => $value) {
        // Sanear el valor si es un string
        if (is_string($value)) {
            $sanitized_value = ($key === 'descripcion') ? sanitize_textarea_field($value) : sanitize_text_field($value);
        } else {
            $sanitized_value = $value;
        }

        update_field($key, $sanitized_value, $post_id);
    }

    // --- Handle File Uploads ---
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $upload_errors = [];
    $main_image_ok = false;

    // Imagen principal
    $attachment_id = media_handle_upload('motor_image', $post_id, [], [
        'test_form' => false,
        'mimes'     => $image_mimes,
    ]);
    if (is_wp_error($attachment_id)) {
        $upload_errors[] = sanitize_file_name($main_image['name']) . ': ' . $attachment_id->get_error_message();
    } else {
        update_field('motor_image', $attachment_id, $post_id);
        $main_image_ok = true;
    }

    // Galería de imágenes
    if (!empty($gallery_files)) {
        $gallery_ids = [];
        foreach ($gallery_files as $file) {
            $file_array = [
                'name'     => $file['name'],
                'tmp_name' => $file['tmp_name'],
            ];
            $gallery_attachment_id = media_handle_sideload($file_array, $post_id);
            if (is_wp_error($gallery_attachment_id)) {
                $upload_errors[] = sanitize_file_name($file['name']) . ': ' . $gallery_attachment_id->get_error_message();
            } else {
                $gallery_ids[] = $gallery_attachment_id;
            }
        }
        if (!empty($gallery_ids)) {
            update_field('motor_gallery', $gallery_ids, $post_id);
        }
    }

    // Documentación adicional
    if (!empty($doc_files_valid)) {
        $docs_data = [];
        if (isset($params['documentacion_adicional_nombres'])) {
            $docs_data = is_string($params['documentacion_adicional_nombres']) ? json_decode($params['documentacion_adicional_nombres'], true) : $params['documentacion_adicional_nombres'];
        }
        $docs_data = is_array($docs_data) ? $docs_data : [];
        $uploaded_docs = [];

        foreach ($doc_files_valid as $key => $file) {
            $file_array = [
                'name'     => $file['name'],
                'tmp_name' => $file['tmp_name'],
            ];
            $doc_attachment_id = media_handle_sideload($file_array, $post_id);

            if (is_wp_error($doc_attachment_id)) {
                $upload_errors[] = sanitize_file_name($file['name']) . ': ' . $doc_attachment_id->get_error_message();
            } else {
                $doc_name = isset($docs_data[$key]['nombre']) && trim((string) $docs_data[$key]['nombre']) !== '' ? $docs_data[$key]['nombre'] : $file['name'];
                $uploaded_docs[] = [
                    'nombre' => sanitize_text_field($doc_name),
                    'archivo' => $doc_attachment_id,
                ];
            }
        }
        if (!empty($uploaded_docs)) {
            update_field('documentacion_adicional', $uploaded_docs, $post_id);
        }
    }

    // --- Estado final y título ---
    // Sin imagen principal (obligatoria) la publicación se queda en borrador y se avisa.
    $final_status = $main_image_ok ? $requested_status : 'draft';

    $final_update = ['ID' => $post_id];
    if ($final_status !== 'draft') {
        $final_update['post_status'] = $final_status;
    }
    // El título lo fija el servidor con el mismo formato que la aprobación (marca nueva incluida).
    $formatted_title = motorlan_format_motor_name($post_id);
    if ($formatted_title && $formatted_title !== $title) {
        $final_update['post_title'] = $formatted_title;
    }
    if (count($final_update) > 1) {
        wp_update_post($final_update);
    }

    // Explicitly set the ACF field for consistency
    update_field('publicar_acf', $final_status, $post_id);

    if ($final_status === 'publish') {
        motorlan_publicacion_mark_approved($post_id);
    }

    delete_post_meta($post_id, '_motorlan_create_incomplete');
    if ($idem_key) {
        set_transient($idem_key, $post_id, HOUR_IN_SECONDS);
    }

    // --- Notifications ---
    // Se envía tras guardar taxonomías y ACF para que el nombre formateado del motor esté completo.
    if ($final_status === 'pending' && !$is_admin) {
        if (class_exists('Motorlan_Notification_Manager')) {
            $brand_label = $pending_brand_name ?: '';
            if (!$brand_label && isset($acf_data['marca']) && is_numeric($acf_data['marca']) && (int) $acf_data['marca'] > 0) {
                $brand_term = get_term((int) $acf_data['marca'], 'marca');
                $brand_label = ($brand_term && !is_wp_error($brand_term)) ? $brand_term->name : '';
            }
            $notification_manager = new Motorlan_Notification_Manager();
            $admins = get_users(['role' => 'administrator']);
            foreach ($admins as $admin) {
                $notification_manager->create_notification(
                    $admin->ID,
                    'pending_approval',
                    'Nueva publicación pendiente',
                    'El usuario ' . wp_get_current_user()->display_name . ' ha creado una nueva publicación que requiere tu aprobación.',
                    [
                        'post_id'       => $post_id,
                        'author_id'     => $user_id,
                        'url'           => motorlan_account_url( 'dashboard/admin/approvals', $admin->ID ),
                        'vue_path'      => '/dashboard/admin/approvals',
                        'product_title' => motorlan_format_motor_name( $post_id ),
                        'reference'     => $acf_data['tipo_o_referencia'] ?? '',
                        'brand'         => $brand_label,
                        'power'         => (!empty($acf_data['potencia'])) ? $acf_data['potencia'] . ' KW' : ((!empty($acf_data['par_nominal'])) ? $acf_data['par_nominal'] . ' Nm' : ''),
                        'speed'         => (!empty($acf_data['velocidad'])) ? $acf_data['velocidad'] . ' RPM' : '',
                    ],
                    ['web', 'email']
                );
            }
        }
    }


    return new WP_REST_Response([
        'message'       => 'Publicación creada con éxito.',
        'id'            => $post_id,
        'uuid'          => $uuid,
        'status'        => $final_status,
        'upload_errors' => $upload_errors,
    ], 201);
}
