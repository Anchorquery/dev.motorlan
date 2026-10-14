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
 * IDs de adjuntos que la publicación ya referencia (imagen, galería, documentos, informes).
 * Se usan para aceptar adjuntos legacy (p. ej. importados por un admin) al reenviar el formulario.
 *
 * @param int $post_id
 * @return int[]
 */
function motorlan_update_publicacion_linked_attachment_ids($post_id) {
    $ids = [];

    foreach (['motor_image', 'informe_de_reparacion', 'documentacion_adjunta'] as $single_field) {
        $value = get_post_meta($post_id, $single_field, true);
        if (is_numeric($value)) {
            $ids[] = (int) $value;
        }
    }

    $gallery = get_post_meta($post_id, 'motor_gallery', true);
    if (is_array($gallery)) {
        foreach ($gallery as $gallery_id) {
            $ids[] = (int) $gallery_id;
        }
    }

    $doc_rows = (int) get_post_meta($post_id, 'documentacion_adicional', true);
    for ($i = 0; $i < $doc_rows; $i++) {
        $doc_id = get_post_meta($post_id, 'documentacion_adicional_' . $i . '_archivo', true);
        if (is_numeric($doc_id)) {
            $ids[] = (int) $doc_id;
        }
    }

    return array_values(array_filter(array_unique($ids)));
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

    // Security check: Only author or admin can update
    $post = get_post($post_id);
    if ($post->post_author != get_current_user_id() && !current_user_can('administrator')) {
        return new WP_Error('forbidden', 'No tienes permisos para editar esta publicación.', ['status' => 403]);
    }

    $is_admin = current_user_can('administrator');
    $current_post_status = get_post_status($post_id);

    // Security check: una publicación en la papelera solo la gestiona el admin
    // (antes el dueño podía "resucitarla" editándola).
    if ($current_post_status === 'trash' && !$is_admin) {
        return new WP_Error('not_found', 'Publicación no encontrada', ['status' => 404]);
    }

    // Security check: If post is pending, only admin can update
    if ($current_post_status === 'pending' && !$is_admin) {
        return new WP_Error('forbidden', 'No puedes editar una publicación que está en revisión.', ['status' => 403]);
    }

    // Envío truncado por post_max_size: $_POST/$_FILES vacíos. Antes se interpretaba como
    // "borrar imagen, galería y documentos" y se respondía 200.
    $too_large = motorlan_publicacion_payload_too_large($request);
    if ($too_large) {
        return $too_large;
    }

    $params = $request->get_params();
    $files = $request->get_file_params();
    $notify_admins_pending = false;

    // Cuerpo vacío = petición truncada o cliente roto: nunca tocar nada.
    if (!isset($params['acf']) && !isset($params['status']) && !isset($params['title'])) {
        return new WP_Error('empty_payload', 'No se recibieron datos (¿archivos demasiado grandes?). No se ha guardado nada.', ['status' => 400]);
    }

    // Errores de subida de PHP (tamaño, subida parcial...) → avisar en vez de ignorarlos.
    $upload_error = motorlan_publicacion_find_upload_error($files, ['motor_image', 'motor_gallery', 'documentacion_adicional_archivos']);
    if ($upload_error) {
        return $upload_error;
    }

    // ---------------------------------------------------------------------
    // 1) Parsear payload y VALIDAR antes de escribir nada en BD.
    //    (Antes se cambiaban título/slug/estado/tipo y luego fallaba la
    //    validación ACF, dejando la publicación guardada a medias.)
    // ---------------------------------------------------------------------
    $acf_data = isset($params['acf']) ? (is_string($params['acf']) ? json_decode($params['acf'], true) : $params['acf']) : [];
    if (!is_array($acf_data)) {
        $acf_data = [];
    }

    $tipo_ids = null;
    if (array_key_exists('tipo', $params)) {
        $raw_tipo_ids = motorlan_parse_tipo_param($params['tipo']);
        $tipo_ids = array_values(array_filter($raw_tipo_ids, function ($id) {
            $term = get_term((int) $id, 'tipo');
            return $term && !is_wp_error($term);
        }));
        if (!empty($raw_tipo_ids) && empty($tipo_ids)) {
            return new WP_Error('invalid_tipo', 'El tipo de publicación no es válido.', ['status' => 400]);
        }
    }
    $current_tipo_ids = wp_get_post_terms($post_id, 'tipo', ['fields' => 'ids']);
    $current_tipo_ids = is_wp_error($current_tipo_ids) ? [] : array_map('intval', $current_tipo_ids);
    $effective_tipo_ids = !empty($tipo_ids) ? $tipo_ids : $current_tipo_ids;
    $is_motor = motorlan_tipo_is_motor($effective_tipo_ids);

    // Reguladores/otros no muestran las specs de motor en el formulario: un valor legacy
    // inválido (0, texto) no debe bloquear la edición sin que el usuario pueda corregirlo.
    if (!$is_motor) {
        foreach (['potencia', 'velocidad', 'par_nominal', 'voltaje', 'intensidad'] as $spec_field) {
            if (array_key_exists($spec_field, $acf_data) && $acf_data[$spec_field] !== null && $acf_data[$spec_field] !== ''
                && (!is_numeric(str_replace(',', '.', (string) $acf_data[$spec_field])) || (float) str_replace(',', '.', (string) $acf_data[$spec_field]) <= 0)) {
                $acf_data[$spec_field] = null;
            }
        }
    }

    // Números > 0, stock entero, alquiler 'Sí'/'No', alimentación ac/dc...
    $acf_data = motorlan_publicacion_normalize_acf_values($acf_data);
    if (is_wp_error($acf_data)) {
        return $acf_data;
    }

    $new_status = null;
    $approve_after = false;
    if (isset($params['status'])) {
        $new_status = sanitize_text_field($params['status']);
        if (!in_array($new_status, ['publish', 'draft', 'pending', 'paused', 'sold'], true)) {
            return new WP_Error('invalid_status', 'Estado de publicación no válido.', ['status' => 400]);
        }
        // Igual que en /status y al crear: un no-admin no puede publicar sin aprobación.
        // Si ya estaba publicada (o ya fue aprobada y está pausada/vendida), se mantiene publicada.
        if (!$is_admin && $new_status === 'publish' && !motorlan_publicacion_was_approved($post_id)) {
            $new_status = 'pending';
        }
        // Admin publicando una publicación en revisión: pasar por el flujo de aprobación
        // (crea la marca pendiente, regenera título/slug y notifica al usuario).
        if ($is_admin && $new_status === 'publish' && $current_post_status === 'pending') {
            $approve_after = true;
            $new_status = null;
        }
    }

    // Marca personalizada: solo se RESUELVE aquí (las escrituras van tras validar).
    $custom_brand_name = !empty($params['marca_custom']) ? motorlan_mb_upper(trim(sanitize_text_field($params['marca_custom']))) : '';
    $brand_existing_term_id = 0;
    $brand_to_create = '';
    $brand_pending = false;
    if ($custom_brand_name !== '') {
        $existing_term = get_term_by('name', $custom_brand_name, 'marca');
        if ($existing_term) {
            $brand_existing_term_id = (int) $existing_term->term_id;
        } elseif ($is_admin) {
            $brand_to_create = $custom_brand_name;
        } else {
            $brand_pending = true;
        }
    }

    // La marca debe ser un término existente (o -1 = pendiente de aprobación).
    if (array_key_exists('marca', $acf_data) && $acf_data['marca'] !== null && $acf_data['marca'] !== '' && $custom_brand_name === '') {
        $marca_id = is_numeric($acf_data['marca']) ? (int) $acf_data['marca'] : 0;
        if ($marca_id === -1 && get_post_meta($post_id, '_pending_brand_name', true)) {
            $acf_data['marca'] = -1;
        } else {
            $marca_term = $marca_id > 0 ? get_term($marca_id, 'marca') : null;
            if (!$marca_term || is_wp_error($marca_term)) {
                return new WP_Error('invalid_marca', 'La marca no es válida.', ['status' => 400]);
            }
            $acf_data['marca'] = $marca_id;
        }
    }

    // Marca nueva de un no-admin = requiere revisión aunque ya estuviera publicada
    // (antes seguía publicada, sin término de marca y fuera de Aprobaciones).
    if ($brand_pending && ($new_status === 'publish' || ($new_status === null && $current_post_status === 'publish'))) {
        $new_status = 'pending';
    }

    // Campos obligatorios comunes (solo si vienen en el payload)
    if (array_key_exists('marca', $acf_data) && ($acf_data['marca'] === null || $acf_data['marca'] === '') && $custom_brand_name === '') {
        return new WP_Error('missing_marca', 'La marca es obligatoria.', ['status' => 400]);
    }
    if (array_key_exists('tipo_o_referencia', $acf_data) && trim((string) $acf_data['tipo_o_referencia']) === '') {
        return new WP_Error('missing_tipo_o_referencia', 'La referencia es obligatoria.', ['status' => 400]);
    }

    // Especificaciones técnicas: solo obligatorias para motores
    if ($is_motor) {
        $is_empty = function ($v) { return $v === null || $v === '' || $v === false; };

        $current_velocidad = array_key_exists('velocidad', $acf_data) ? $acf_data['velocidad'] : get_field('velocidad', $post_id);
        if ($is_empty($current_velocidad)) {
            return new WP_Error('missing_velocidad', 'La velocidad es obligatoria.', ['status' => 400]);
        }

        $tipo_alimentacion = array_key_exists('tipo_de_alimentacion', $acf_data) ? $acf_data['tipo_de_alimentacion'] : get_field('tipo_de_alimentacion', $post_id);
        $current_potencia = array_key_exists('potencia', $acf_data) ? $acf_data['potencia'] : get_field('potencia', $post_id);
        $current_par = array_key_exists('par_nominal', $acf_data) ? $acf_data['par_nominal'] : get_field('par_nominal', $post_id);

        if ($tipo_alimentacion === 'ac') {
            if ($is_empty($current_potencia)) {
                return new WP_Error('missing_power', 'La potencia es obligatoria para motores AC.', ['status' => 400]);
            }
        } elseif ($is_empty($current_potencia) && $is_empty($current_par)) {
            return new WP_Error('missing_power_or_torque', 'Debe especificar Potencia o Par Nominal.', ['status' => 400]);
        }
    }

    // Stock al publicar o pedir aprobación (un no-admin que publica acaba en 'pending')
    $set_default_stock = false;
    if ($approve_after || $new_status === 'publish' || ($new_status === 'pending' && $current_post_status !== 'pending')) {
        if (array_key_exists('stock', $acf_data) && $acf_data['stock'] !== null && $acf_data['stock'] !== '') {
            if ((int) $acf_data['stock'] <= 0) {
                return new WP_Error('invalid_stock_publish', 'No se puede publicar una publicación con stock en 0.', ['status' => 400]);
            }
        } else {
            $current_stock = (int) (function_exists('get_field') ? get_field('stock', $post_id) : get_post_meta($post_id, 'stock', true));
            $set_default_stock = $current_stock <= 0;
        }
    }

    // --- Adjuntos: validar tipos de los archivos nuevos y la propiedad de los IDs existentes ---
    $image_mimes = motorlan_publicacion_image_mimes();
    $doc_mimes = motorlan_publicacion_doc_mimes();
    $linked_attachment_ids = motorlan_update_publicacion_linked_attachment_ids($post_id);
    $attachment_ok = function ($attachment_id) use ($post_id, $linked_attachment_ids) {
        $attachment_id = (int) $attachment_id;
        if ($attachment_id <= 0) {
            return false;
        }
        if (in_array($attachment_id, $linked_attachment_ids, true)) {
            return get_post_type($attachment_id) === 'attachment';
        }
        return motorlan_publicacion_attachment_usable($attachment_id, $post_id);
    };

    $main_image_files = array_values(array_filter(motorlan_publicacion_rearrange_files($files['motor_image'] ?? null), function ($file) {
        return (int) $file['error'] !== UPLOAD_ERR_NO_FILE;
    }));
    $main_image_file = $main_image_files[0] ?? null;
    if ($main_image_file && !motorlan_publicacion_file_type_allowed($main_image_file, $image_mimes)) {
        return new WP_Error('invalid_image', 'La imagen principal debe ser JPG, PNG, GIF o WebP.', ['status' => 400]);
    }
    if (!$main_image_file && isset($params['motor_image_id']) && $params['motor_image_id'] !== '' && !$attachment_ok($params['motor_image_id'])) {
        return new WP_Error('invalid_image', 'La imagen principal no es válida.', ['status' => 400]);
    }

    $gallery_files = array_values(array_filter(motorlan_publicacion_rearrange_files($files['motor_gallery'] ?? null), function ($file) {
        return (int) $file['error'] !== UPLOAD_ERR_NO_FILE;
    }));
    foreach ($gallery_files as $file) {
        if (!motorlan_publicacion_file_type_allowed($file, $image_mimes)) {
            return new WP_Error('invalid_image', sprintf('"%s" no es una imagen admitida (JPG, PNG, GIF o WebP).', sanitize_file_name($file['name'])), ['status' => 400]);
        }
    }
    $update_gallery = isset($params['motor_gallery_ids']) || !empty($gallery_files);
    $kept_gallery_ids = [];
    if (isset($params['motor_gallery_ids'])) {
        // explode(',', '') devuelve [0]: filtrar para no guardar un ID 0.
        $kept_gallery_ids = array_values(array_filter(array_map('intval', explode(',', (string) $params['motor_gallery_ids'])), $attachment_ok));
    }
    if (!empty($gallery_files) && count($kept_gallery_ids) + count($gallery_files) > 5) {
        return new WP_Error('gallery_limit', 'La galería admite como máximo 5 imágenes.', ['status' => 400]);
    }

    $doc_files = [];
    foreach (motorlan_publicacion_rearrange_files($files['documentacion_adicional_archivos'] ?? null) as $key => $file) {
        if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if (!motorlan_publicacion_file_type_allowed($file, $doc_mimes)) {
            return new WP_Error('invalid_document', sprintf('"%s" no es un documento admitido (PDF, JPG o PNG).', sanitize_file_name($file['name'])), ['status' => 400]);
        }
        $doc_files[$key] = $file;
    }
    $update_docs = isset($params['documentacion_adicional_ids']) || !empty($doc_files);
    $kept_docs = [];
    if (isset($params['documentacion_adicional_ids'])) {
        $doc_ids = json_decode((string) $params['documentacion_adicional_ids'], true);
        foreach ((is_array($doc_ids) ? $doc_ids : []) as $doc) {
            if (!is_array($doc) || empty($doc['archivo']) || !$attachment_ok($doc['archivo'])) {
                continue;
            }
            $kept_docs[] = [
                'nombre'  => sanitize_text_field((string) ($doc['nombre'] ?? '')),
                'archivo' => (int) $doc['archivo'],
            ];
        }
    }
    if (!empty($doc_files) && count($kept_docs) + count($doc_files) > 5) {
        return new WP_Error('documents_limit', 'Se admiten como máximo 5 documentos.', ['status' => 400]);
    }

    // ¿Cambian los datos que forman el slug? (calcular ANTES de escribir los campos)
    $slug_changed = !empty($tipo_ids) && array_diff($tipo_ids, $current_tipo_ids) !== [];
    $marca_for_slug = $brand_existing_term_id ? $brand_existing_term_id : ($brand_to_create !== '' ? 'new' : ($brand_pending ? -1 : null));
    if ($marca_for_slug !== null && (string) $marca_for_slug !== (string) get_post_meta($post_id, 'marca', true)) {
        $slug_changed = true;
    }
    foreach (['marca', 'tipo_o_referencia', 'potencia', 'par_nominal'] as $slug_key) {
        if (!array_key_exists($slug_key, $acf_data) || ($slug_key === 'marca' && $custom_brand_name !== '')) {
            continue;
        }
        $incoming = $acf_data[$slug_key];
        $stored = get_post_meta($post_id, $slug_key, true);
        $same = (is_numeric($incoming) && is_numeric($stored))
            ? (float) $incoming === (float) $stored
            : trim((string) $incoming) === trim((string) $stored);
        if (!$same) {
            $slug_changed = true;
        }
    }

    // ---------------------------------------------------------------------
    // 2) Marca personalizada
    // ---------------------------------------------------------------------
    if ($brand_existing_term_id) {
        $acf_data['marca'] = $brand_existing_term_id;
        delete_post_meta($post_id, '_pending_brand_name');
    } elseif ($brand_to_create !== '') {
        // Admin: crear la marca directamente sin necesidad de aprobación
        $new_term = wp_insert_term($brand_to_create, 'marca');
        if (is_wp_error($new_term) && $new_term->get_error_code() === 'term_exists') {
            $acf_data['marca'] = (int) $new_term->get_error_data();
        } elseif (is_wp_error($new_term)) {
            return new WP_Error('brand_create_error', 'Error al crear la marca: ' . $new_term->get_error_message(), ['status' => 500]);
        } else {
            $acf_data['marca'] = (int) $new_term['term_id'];
        }
        delete_post_meta($post_id, '_pending_brand_name');
    } elseif ($brand_pending) {
        // Usuario normal: diferir a aprobación del admin
        update_post_meta($post_id, '_pending_brand_name', $custom_brand_name);
        $acf_data['marca'] = -1; // Valor temporal
    }

    // Si marca es un valor válido (> 0), limpiar cualquier _pending_brand_name residual
    if (isset($acf_data['marca']) && is_numeric($acf_data['marca']) && (int)$acf_data['marca'] > 0) {
        delete_post_meta($post_id, '_pending_brand_name');
    }

    // ---------------------------------------------------------------------
    // 3) Post: título y estado. El slug se regenera al final (paso 7).
    //    El slug que envíe el cliente se ignora: el formato lo decide el servidor
    //    (antes cada edición reescribía la URL con la referencia duplicada).
    // ---------------------------------------------------------------------
    if (isset($params['title']) && trim((string) $params['title']) !== '') {
        wp_update_post(['ID' => $post_id, 'post_title' => sanitize_text_field($params['title'])]);
    }

    // Si estaba publicada (aprobada) y pasa a pausada/vendida, recordarlo para poder
    // reactivarla sin volver a revisión.
    if ($current_post_status === 'publish') {
        motorlan_publicacion_mark_approved($post_id);
    }

    if ($new_status !== null) {
        if ($set_default_stock) {
            if (function_exists('update_field')) {
                update_field('stock', 1, $post_id);
            }
            update_post_meta($post_id, 'stock', 1);
        }

        // WP vacía post_name al pasar a 'pending' sin capacidad de publicar y solo guarda
        // _wp_old_slug al publicar: guardarlo ya para que la URL indexada haga 301 tras aprobar
        $post_before = get_post($post_id);
        if ($new_status === 'pending' && $current_post_status === 'publish' && $post_before && $post_before->post_name
            && !in_array($post_before->post_name, (array) get_post_meta($post_id, '_wp_old_slug'), true)) {
            add_post_meta($post_id, '_wp_old_slug', $post_before->post_name);
        }
        // Desde la papelera: restaurar con wp_untrash_post (si no, queda el slug "__trashed")
        if ($current_post_status === 'trash') {
            wp_untrash_post($post_id);
        }

        wp_update_post(['ID' => $post_id, 'post_status' => $new_status]);

        // Also update the ACF field for consistency in the UI
        update_field('publicar_acf', $new_status, $post_id);

        if ($new_status === 'publish') {
            motorlan_publicacion_mark_approved($post_id);
        }

        if ($new_status === 'pending' && $current_post_status !== 'pending' && !$is_admin) {
            $notify_admins_pending = true;
        }
    }

    // ---------------------------------------------------------------------
    // 4) Taxonomías ("categoria" desactivada: se usa "tipo")
    // ---------------------------------------------------------------------
    // Un array vacío no borra el tipo existente.
    if (!empty($tipo_ids)) {
        wp_set_post_terms($post_id, $tipo_ids, 'tipo');
    }

    // ---------------------------------------------------------------------
    // 5) Campos ACF
    // ---------------------------------------------------------------------
    $checkbox_acf_fields = ['servomotores', 'regulacion_electronica_drivers'];
    foreach ($checkbox_acf_fields as $checkbox_field) {
        if (array_key_exists($checkbox_field, $acf_data)) {
            $acf_data[$checkbox_field] = motorlan_normalize_checkbox_acf_value($post_id, $checkbox_field, $acf_data[$checkbox_field]);
        }
    }

    // Campos que se pueden vaciar (null/'' borra el valor). Con isset() era
    // imposible quitar p. ej. el precio o el par nominal.
    $clearable_fields = ['potencia', 'velocidad', 'par_nominal', 'voltaje', 'intensidad', 'precio_de_venta'];
    foreach ($clearable_fields as $field) {
        if (array_key_exists($field, $acf_data)) {
            $value = $acf_data[$field];
            update_field($field, ($value === '' ? null : $value), $post_id);
        }
    }

    // Adjuntos sueltos (informe, ficha): solo IDs propios o ya enlazados (antes cualquier ID).
    foreach (['informe_de_reparacion', 'documentacion_adjunta'] as $file_field) {
        if (!array_key_exists($file_field, $acf_data)) {
            continue;
        }
        $value = $acf_data[$file_field];
        if (is_array($value)) {
            $value = $value['ID'] ?? ($value['id'] ?? null);
        }
        if ($value === null || $value === '' || $value === false) {
            update_field($file_field, null, $post_id);
        } elseif ($attachment_ok($value)) {
            update_field($file_field, (int) $value, $post_id);
        }
    }

    // 'mostrar_precio' ya no se guarda: no existe en ACF (creaba metas sueltas).
    $text_fields = ['tipo_o_referencia', 'pais', 'provincia', 'estado_del_articulo', 'posibilidad_de_alquiler', 'tipo_de_alimentacion', 'precio_negociable'];
    foreach ($text_fields as $field) {
        if (array_key_exists($field, $acf_data) && $acf_data[$field] !== null) {
            update_field($field, sanitize_text_field((string) $acf_data[$field]), $post_id);
        }
    }

    if (array_key_exists('descripcion', $acf_data)) update_field('descripcion', sanitize_textarea_field((string) ($acf_data['descripcion'] ?? '')), $post_id);
    if (isset($acf_data['marca'])) update_field('marca', $acf_data['marca'], $post_id);
    if (isset($acf_data['servomotores'])) update_field('servomotores', $acf_data['servomotores'], $post_id);
    if (isset($acf_data['regulacion_electronica_drivers'])) update_field('regulacion_electronica_drivers', $acf_data['regulacion_electronica_drivers'], $post_id);
    if (isset($acf_data['stock']) && $acf_data['stock'] !== '') update_field('stock', intval($acf_data['stock']), $post_id);

    // ---------------------------------------------------------------------
    // 6) Archivos. La AUSENCIA de un parámetro ya no significa "borrar":
    //    solo se toca lo que el cliente envía explícitamente.
    // ---------------------------------------------------------------------
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');

    $upload_errors = [];

    // Main Image
    if ($main_image_file) {
        $attachment_id = media_handle_upload('motor_image', $post_id, [], [
            'test_form' => false,
            'mimes'     => $image_mimes,
        ]);
        if (is_wp_error($attachment_id)) {
            // Se conserva la imagen anterior
            $upload_errors[] = sanitize_file_name($main_image_file['name']) . ': ' . $attachment_id->get_error_message();
        } else {
            update_field('motor_image', $attachment_id, $post_id);
        }
    } elseif (isset($params['motor_image_id'])) {
        // motor_image_id = '' → borrado explícito desde el formulario
        update_field('motor_image', $params['motor_image_id'] === '' ? '' : (int) $params['motor_image_id'], $post_id);
    }

    // Gallery
    if ($update_gallery) {
        $gallery_ids = $kept_gallery_ids;
        foreach ($gallery_files as $file) {
            $file_array = ['name' => $file['name'], 'tmp_name' => $file['tmp_name']];
            $gallery_attachment_id = media_handle_sideload($file_array, $post_id);
            if (is_wp_error($gallery_attachment_id)) {
                $upload_errors[] = sanitize_file_name($file['name']) . ': ' . $gallery_attachment_id->get_error_message();
            } else {
                $gallery_ids[] = $gallery_attachment_id;
            }
        }
        update_field('motor_gallery', $gallery_ids, $post_id);
    }

    // Additional Documentation
    if ($update_docs) {
        $doc_nombres = isset($params['documentacion_adicional_nombres']) ? json_decode((string) $params['documentacion_adicional_nombres'], true) : [];
        $doc_nombres = is_array($doc_nombres) ? $doc_nombres : [];
        $uploaded_docs = $kept_docs;

        $doc_index = 0;
        foreach ($doc_files as $file) {
            $file_array = ['name' => $file['name'], 'tmp_name' => $file['tmp_name']];
            $doc_attachment_id = media_handle_sideload($file_array, $post_id);
            if (is_wp_error($doc_attachment_id)) {
                $upload_errors[] = sanitize_file_name($file['name']) . ': ' . $doc_attachment_id->get_error_message();
            } else {
                $doc_name = isset($doc_nombres[$doc_index]['nombre']) && trim((string) $doc_nombres[$doc_index]['nombre']) !== '' ? $doc_nombres[$doc_index]['nombre'] : $file['name'];
                $uploaded_docs[] = [
                    'nombre' => sanitize_text_field($doc_name),
                    'archivo' => $doc_attachment_id,
                ];
            }
            $doc_index++;
        }
        update_field('documentacion_adicional', $uploaded_docs, $post_id);
    }

    // ---------------------------------------------------------------------
    // 7) Slug: mismo formato que el alta, solo si cambian marca/referencia/potencia/par/tipo.
    //    WordPress guarda el anterior en _wp_old_slug si estaba publicada.
    // ---------------------------------------------------------------------
    if ($slug_changed) {
        $new_slug = motorlan_generate_slug_by_post_id($post_id);
        if ($new_slug && $new_slug !== get_post_field('post_name', $post_id)) {
            wp_update_post(['ID' => $post_id, 'post_name' => $new_slug]);
        }
    }

    // Admin publicando una publicación en revisión: flujo de aprobación completo.
    if ($approve_after && function_exists('motorlan_approve_publication_callback')) {
        $approve_request = new WP_REST_Request('POST');
        $approve_request->set_url_params(['id' => $post_id]);
        $approve_request->set_header('Content-Type', 'application/json');
        $approve_request->set_body('{}');
        $approve_result = motorlan_approve_publication_callback($approve_request);
        if (is_wp_error($approve_result)) {
            return $approve_result;
        }
    }

    // Notificar tras guardar todos los campos (el email usa nombre/potencia/etc.)
    if (!empty($notify_admins_pending)) {
        do_action('motorlan_publication_pending_approval', $post_id);
    }

    $updated_post = get_post($post_id);
    return new WP_REST_Response([
        'message'       => 'Publicación actualizada correctamente',
        'slug'          => $updated_post->post_name,
        'status'        => $updated_post->post_status,
        'upload_errors' => $upload_errors,
    ], 200);
}
