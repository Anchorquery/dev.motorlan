<?php
/**
 * Admin UI and CSV import helpers for legacy motores.
 *
 * @package motorlan-api-vue
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Register the migration menu page.
 */
function motorlan_register_migration_menu() {
    add_menu_page(
        __( 'Migración motores', 'motorlan-api-vue' ),
        __( 'Migración motores', 'motorlan-api-vue' ),
        'manage_options',
        'motorlan-migration',
        'motorlan_render_migration_page',
        'dashicons-upload',
        6
    );
}
add_action( 'admin_menu', 'motorlan_register_migration_menu' );

/**
 * Enqueue admin scripts/styles for the migration page.
 *
 * @param string $hook_suffix Admin page hook suffix.
 */
function motorlan_enqueue_migration_assets( $hook_suffix ) {
    if ( 'toplevel_page_motorlan-migration' !== $hook_suffix ) {
        return;
    }

    wp_enqueue_style(
        'motorlan-migration-admin',
        MOTORLAN_API_VUE_URL . 'assets/css/admin-migration.css',
        [],
        MOTORLAN_API_VUE_VERSION
    );

    wp_enqueue_script(
        'motorlan-migration-admin',
        MOTORLAN_API_VUE_URL . 'assets/js/admin-migration.js',
        [],
        MOTORLAN_API_VUE_VERSION,
        true
    );

    wp_localize_script(
        'motorlan-migration-admin',
        'motorlanMigration',
        [
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'motorlan_migration_action' ),
            'chunkLimit'  => 10,
            'maxChunk'    => 50,
            'restNonce'   => wp_create_nonce( 'wp_rest' ),
            'restRoot'    => esc_url_raw( rest_url() ),
            'endpoint'    => [
                'upload' => rest_url( 'motorlan/v1/migration/upload' ),
                'chunk'  => rest_url( 'motorlan/v1/migration/chunk' ),
            ],
            'strings'     => [
                'fileRequired' => __( 'Selecciona un fichero CSV.', 'motorlan-api-vue' ),
                'alreadyRunning' => __( 'Hay una importación en curso. Espera a que termine.', 'motorlan-api-vue' ),
            ],
        ]
    );
}
add_action( 'admin_enqueue_scripts', 'motorlan_enqueue_migration_assets' );

/**
 * Render the migration admin page.
 */
function motorlan_render_migration_page() {
    ?>
    <div class="wrap motorlan-migration-wrap">
        <h1><?php esc_html_e( 'Migración de motores legacy', 'motorlan-api-vue' ); ?></h1>
        <p><?php esc_html_e( 'Sube un CSV exportado de la web antigua y el plugin creará Publicaciones con los campos mapeados automáticamente.', 'motorlan-api-vue' ); ?></p>
        <form id="motorlan-migration-form" enctype="multipart/form-data">
            <?php wp_nonce_field( 'motorlan_migration_action', 'motorlan_migration_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th><label for="motorlan-csv-file"><?php esc_html_e( 'CSV Legacy', 'motorlan-api-vue' ); ?></label></th>
                    <td>
                        <input type="file" id="motorlan-csv-file" name="motorlan_csv" accept=".csv" required>
                        <p class="description"><?php esc_html_e( 'Encabezado esperado: ID, Title, Content, Date, Categorías, marca, tipo_o_referencia, URL, etc.', 'motorlan-api-vue' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="motorlan-chunk-size"><?php esc_html_e( 'Filas por bloque', 'motorlan-api-vue' ); ?></label></th>
                    <td>
                        <input type="number" id="motorlan-chunk-size" name="chunk_size" min="1" max="100" value="10" required>
                        <p class="description"><?php esc_html_e( 'Bloques pequeños hacen que el progreso se actualice más rápido.', 'motorlan-api-vue' ); ?></p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Subir y comenzar migración', 'motorlan-api-vue' ); ?></button>
                <span id="motorlan-migration-status" class="spinner"></span>
            </p>
        </form>
        <div id="motorlan-migration-progress" class="motorlan-migration-progress">
            <div class="motorlan-progress-bar">
                <div class="motorlan-progress-fill" style="width: 0%;"></div>
            </div>
            <p id="motorlan-migration-progress-text"><?php esc_html_e( 'Sin acciones todavía.', 'motorlan-api-vue' ); ?></p>
            <ul id="motorlan-migration-log"></ul>
        </div>
    </div>
    <?php
}

/**
 * Handle the CSV upload step.
 */
function motorlan_handle_migration_upload() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Sin permisos suficientes.', 'motorlan-api-vue' ), 403 );
    }

    if ( ! check_ajax_referer( 'motorlan_migration_action', 'security', false ) ) {
        wp_send_json_error( __( 'Nonce inválido.', 'motorlan-api-vue' ), 403 );
    }

    $file = $_FILES['motorlan_csv'] ?? [];
    $result = motorlan_migration_handle_uploaded_csv( $file );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message(), 400 );
    }

    wp_send_json_success( $result );
}
add_action( 'wp_ajax_motorlan_import_csv_upload', 'motorlan_handle_migration_upload' );

/**
 * Process a chunk of rows from the uploaded CSV.
 */
function motorlan_handle_migration_chunk() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Sin permisos suficientes.', 'motorlan-api-vue' ), 403 );
    }

    if ( ! check_ajax_referer( 'motorlan_migration_action', 'security', false ) ) {
        wp_send_json_error( __( 'Nonce inválido.', 'motorlan-api-vue' ), 403 );
    }

    $import_id = sanitize_text_field( wp_unslash( $_POST['import_id'] ?? '' ) );
    $offset    = max( 0, intval( $_POST['offset'] ?? 0 ) );
    $limit     = max( 1, min( 100, intval( $_POST['limit'] ?? 10 ) ) );

    $result = motorlan_migration_process_chunk_request_data( $import_id, $offset, $limit );
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message(), 404 );
    }

    wp_send_json_success( $result );
}

/**
 * Handle the uploaded CSV and store transient.
 *
 * @param array $file Uploaded file array.
 * @return array|WP_Error
 */
function motorlan_migration_handle_uploaded_csv( $file ) {
    if ( empty( $file['tmp_name'] ) ) {
        return new WP_Error( 'missing_csv', __( 'Falta el archivo CSV.', 'motorlan-api-vue' ), 400 );
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';

    $overrides = [
        'test_form' => false,
        'mimes'     => [ 'csv' => 'text/csv' ],
    ];

    $upload = wp_handle_upload( $file, $overrides );

    if ( isset( $upload['error'] ) ) {
        return new WP_Error( 'upload_failed', $upload['error'], 400 );
    }

    $delimiter = motorlan_migration_detect_delimiter( $upload['file'] );
    $headers   = motorlan_migration_extract_headers( $upload['file'], $delimiter );
    $total     = motorlan_migration_count_rows( $upload['file'], $delimiter );

    return motorlan_migration_store_import_payload( $upload['file'], $delimiter, $headers, $total );
}

/**
 * Store the migration payload.
 *
 * @param string $file_path
 * @param string $delimiter
 * @param array  $headers
 * @param int    $total
 * @return array
 */
function motorlan_migration_store_import_payload( $file_path, $delimiter, $headers, $total ) {
    $import_id = wp_hash( $file_path . time() );
    $payload   = [
        'path'       => $file_path,
        'delimiter'  => $delimiter,
        'headers'    => $headers,
        'total_rows' => $total,
    ];
    set_transient( 'motorlan_migration_' . $import_id, $payload, HOUR_IN_SECONDS );

    return [
        'import_id' => $import_id,
        'rows'      => $total,
        'headers'   => $headers,
    ];
}

/**
 * Process chunk request data.
 *
 * @param string $import_id
 * @param int    $offset
 * @param int    $limit
 * @return array|WP_Error
 */
function motorlan_migration_process_chunk_request_data( $import_id, $offset, $limit ) {
    $payload = get_transient( 'motorlan_migration_' . $import_id );
    if ( false === $payload || empty( $payload['path'] ) || ! file_exists( $payload['path'] ) ) {
        return new WP_Error( 'missing_payload', __( 'Datos de importación no encontrados.', 'motorlan-api-vue' ), 404 );
    }

    $result = motorlan_migration_process_chunk(
        $payload['path'],
        $payload['delimiter'],
        $payload['headers'],
        $offset,
        $limit
    );

    $finished = ( $offset + $result['processed'] ) >= $payload['total_rows'];
    if ( $finished ) {
        motorlan_migration_cleanup( $import_id, $payload['path'] );
        delete_transient( 'motorlan_migration_' . $import_id );
    }

    return [
        'chunk'     => $result,
        'offset'    => $offset,
        'limit'     => $limit,
        'total'     => $payload['total_rows'],
        'finished'  => $finished,
    ];
}
add_action( 'wp_ajax_motorlan_import_csv_chunk', 'motorlan_handle_migration_chunk' );

/**
 * Delete temporary CSV and additional data.
 *
 * @param string $import_id Import identifier.
 * @param string $path      File path.
 */
function motorlan_migration_cleanup( $import_id, $path ) {
    if ( file_exists( $path ) ) {
        @unlink( $path );
    }
    delete_transient( 'motorlan_migration_' . $import_id );
}

/**
 * Detect CSV delimiter based on first line.
 *
 * @param string $path File path.
 * @return string
 */
function motorlan_migration_detect_delimiter( $path ) {
    $handle = fopen( $path, 'rb' );
    if ( ! $handle ) {
        return ',';
    }

    $first = fgets( $handle );
    fclose( $handle );

    $delimiters = [ ';', ',', '|' ];
    $counts     = [];
    foreach ( $delimiters as $delimiter ) {
        $counts[ $delimiter ] = substr_count( $first, $delimiter );
    }

    arsort( $counts );
    return key( $counts );
}

/**
 * Extract header row from the CSV.
 *
 * @param string $path File path.
 * @param string $delimiter CSV delimiter.
 * @return array
 */
function motorlan_migration_extract_headers( $path, $delimiter ) {
    $file = new SplFileObject( $path );
    $file->setFlags( SplFileObject::READ_CSV );
    $file->setCsvControl( $delimiter );
    $headers = $file->fgetcsv();
    if ( ! is_array( $headers ) ) {
        return [];
    }
    $clean = [];
    foreach ( $headers as $header ) {
        if ( is_null( $header ) ) {
            continue;
        }
        $trimmed = trim( $header );
        $trimmed = str_replace( "\xEF\xBB\xBF", '', $trimmed );
        if ( '' === $trimmed ) {
            continue;
        }
        $clean[] = motorlan_migration_normalize_header( $trimmed );
    }
    return $clean;
}

/**
 * Count the number of data rows in the CSV (excluding header).
 *
 * @param string $path File path.
 * @param string $delimiter CSV delimiter.
 * @return int
 */
function motorlan_migration_count_rows( $path, $delimiter ) {
    $file = new SplFileObject( $path );
    $file->setFlags( SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY );
    $file->setCsvControl( $delimiter );
    $file->rewind();
    $file->fgetcsv(); // header
    $count = 0;
    while ( ! $file->eof() ) {
        $row = $file->fgetcsv();
        if ( is_array( $row ) && array_filter( $row ) ) {
            $count++;
        }
    }
    return $count;
}

/**
 * Process a subset of rows from the CSV.
 *
 * @param string $path File path.
 * @param string $delimiter CSV delimiter.
 * @param array  $headers Header names.
 * @param int    $offset Row offset (zero-based).
 * @param int    $limit Number of rows to process.
 * @return array
 */
function motorlan_migration_process_chunk( $path, $delimiter, $headers, $offset, $limit ) {
    $file = new SplFileObject( $path );
    $file->setFlags( SplFileObject::READ_CSV );
    $file->setCsvControl( $delimiter );
    $file->rewind();
    $file->fgetcsv(); // skip header

    $current = 0;
    $processed = 0;
    $result = [
        'processed' => 0,
        'created'   => 0,
        'updated'   => 0,
        'errors'    => [],
    ];

    while ( ! $file->eof() && $processed < $limit ) {
        $row = $file->fgetcsv();
        if ( ! is_array( $row ) || ! array_filter( $row ) ) {
            continue;
        }

        if ( $current++ < $offset ) {
            continue;
        }

        $row_assoc = motorlan_migration_pair_row_with_headers( $headers, $row );

        $outcome = motorlan_migration_process_row( $row_assoc );

        if ( 'error' === $outcome['status'] ) {
            $result['errors'][] = $outcome['message'];
        } else {
            $result[ $outcome['status'] ]++;
        }

        $result['processed']++;
        $processed++;
    }

    return $result;
}

/**
 * Map a single row into a publicacion.
 *
 * @param array $row Row data keyed by column headers.
 * @return array
 */
function motorlan_migration_process_row( array $row ) {
    $legacy_id     = sanitize_text_field( motorlan_migration_get_field_value( $row, 'id' ) );
    $title         = sanitize_text_field( motorlan_migration_get_field_value( $row, 'title' ) );
    $content       = motorlan_migration_prepare_content( motorlan_migration_get_field_value( $row, 'content' ) );
    $excerpt       = sanitize_text_field( motorlan_migration_get_field_value( $row, 'excerpt' ) );
    $date          = sanitize_text_field( motorlan_migration_get_field_value( $row, 'date' ) );
    $wpml_code     = strtolower( sanitize_text_field( motorlan_migration_get_field_value( $row, 'wpml_language_code' ) ) );
    $author_email_raw = motorlan_migration_get_field_value( $row, 'author_email' );
    $author_login_raw = motorlan_migration_get_field_value( $row, 'author_username' );
    $author_email  = sanitize_email( $author_email_raw );
    $author_login  = sanitize_user( $author_login_raw, true );
    $categories    = motorlan_migration_split_list( motorlan_migration_get_field_value( $row, 'categorias' ) );
    $marca         = sanitize_text_field( motorlan_migration_get_field_value( $row, 'marca' ) );
    $tipo_ref      = sanitize_text_field( motorlan_migration_get_field_value( $row, 'tipo_o_referencia' ) );
    $numerics      = [
        'potencia'        => motorlan_migration_get_field_value( $row, 'potencia' ),
        'velocidad'       => motorlan_migration_get_field_value( $row, 'velocidad' ),
        'par_nominal'     => motorlan_migration_get_field_value( $row, 'par_nominal' ),
        'voltaje'         => motorlan_migration_get_field_value( $row, 'voltaje' ),
        'intensidad'      => motorlan_migration_get_field_value( $row, 'intensidad' ),
        'precio_de_venta' => motorlan_migration_get_field_value( $row, 'precio_de_venta' ),
    ];
    $pais          = motorlan_migration_get_field_value( $row, 'localizacion', motorlan_migration_get_field_value( $row, 'pais' ) );
    $provincia     = motorlan_migration_get_field_value( $row, 'provincia' );
    $estado        = motorlan_migration_get_field_value( $row, 'estado_del_articulo' );
    $raw_media_sources = array_merge(
        motorlan_migration_extract_urls( motorlan_migration_get_field_all_values( $row, 'url' ) ),
        motorlan_migration_extract_urls( motorlan_migration_get_field_value( $row, 'featured' ) ),
        motorlan_migration_extract_urls( motorlan_migration_get_field_value( $row, 'garantia_old' ) ),
        motorlan_migration_extract_urls( motorlan_migration_get_field_value( $row, 'documentacion' ) ),
        motorlan_migration_extract_urls( motorlan_migration_get_field_value( $row, 'garantia_motorlan' ) ),
        motorlan_migration_extract_urls( motorlan_migration_get_field_value( $row, 'image_url' ) ),
        motorlan_migration_extract_urls( motorlan_migration_get_field_value( $row, 'motor_image' ) ),
        motorlan_migration_extract_urls( motorlan_migration_get_field_value( $row, 'imagen1' ) ),
        motorlan_migration_extract_urls( motorlan_migration_get_field_value( $row, 'imagen2' ) ),
        motorlan_migration_extract_urls( motorlan_migration_get_field_value( $row, 'imagen3' ) )
    );
    $raw_media_sources = array_values( array_unique( $raw_media_sources ) );
    $document_urls     = array_slice( array_values( array_filter( $raw_media_sources, 'motorlan_migration_url_is_document' ) ), 0, 7 );

    $featured_urls = array_slice(
        array_values(
            array_unique(
                motorlan_migration_extract_urls( motorlan_migration_get_field_value( $row, 'motor_image' ) )
            )
        ),
        0,
        1
    );

    $gallery_sources = array_merge(
        motorlan_migration_extract_urls( motorlan_migration_get_field_value( $row, 'imagen1' ) ),
        motorlan_migration_extract_urls( motorlan_migration_get_field_value( $row, 'imagen2' ) ),
        motorlan_migration_extract_urls( motorlan_migration_get_field_value( $row, 'imagen3' ) ),
        motorlan_migration_extract_urls( motorlan_migration_get_field_value( $row, 'image_url' ) )
    );

    $additional_images = array_values( array_filter( $raw_media_sources, 'motorlan_migration_url_is_image' ) );

    if ( empty( $featured_urls ) && ! empty( $additional_images ) ) {
        $featured_urls = [ array_shift( $additional_images ) ];
    }

    $gallery_urls = array_values(
        array_unique(
            array_diff(
                array_merge( $gallery_sources, $additional_images ),
                $featured_urls
            )
        )
    );
    $gallery_urls = array_slice( $gallery_urls, 0, 6 );
    $descripcion   = motorlan_migration_prepare_content( motorlan_migration_get_field_value( $row, 'descripcion' ) );
    $alquiler      = motorlan_migration_get_field_value( $row, 'posibilidad_de_alquiler' );
    $alimentacion  = motorlan_migration_get_field_value( $row, 'tipo_de_alimentacion' );
    $servos_raw    = motorlan_migration_get_field_value( $row, 'servomotores' );
    $drivers_raw   = motorlan_migration_get_field_value( $row, 'regulacion-electronica-drivers' );
    $precio_neg    = motorlan_migration_get_field_value( $row, 'precio_negociable' );
    $publicar_raw  = motorlan_migration_get_field_value( $row, 'publicar' );

    if ( empty( $title ) ) {
        return [
            'status'  => 'error',
            'message' => sprintf( __( 'Fila sin título (ID: %s).', 'motorlan-api-vue' ), $legacy_id ),
        ];
    }

    $slug_source = motorlan_migration_get_field_value( $row, '_wp_old_slug', $title );
    $slug        = sanitize_title( $slug_source );
    if ( empty( $slug ) ) {
        $slug = sanitize_title( $title );
    }

    $normalized_publicar = motorlan_migration_normalize_publicar_status( $publicar_raw, 'publish' );
    $post_status         = in_array( $normalized_publicar, [ 'draft', 'paused' ], true ) ? 'draft' : 'publish';

    $existing = motorlan_find_publicacion_by_legacy( $legacy_id, $slug );
    $author_id = motorlan_migration_resolve_author( $author_email, $author_login );
    $post_data = [
        'post_title'   => $title,
        'post_content' => $content ?: $descripcion,
        'post_excerpt' => $excerpt,
        'post_status'  => $post_status,
        'post_type'    => 'publicacion',
        'post_name'    => $slug,
        'post_date'    => $date ?: current_time( 'mysql' ),
        'post_author'  => $author_id,
    ];

    if ( $existing ) {
        $post_data['ID'] = $existing;
        $post_id = wp_update_post( $post_data, true );
        $action  = 'updated';
    } else {
        $post_id = wp_insert_post( $post_data, true );
        $action  = 'created';
    }

    if ( is_wp_error( $post_id ) ) {
        return [
            'status'  => 'error',
            'message' => $post_id->get_error_message(),
        ];
    }

    update_post_meta( $post_id, 'legacy_motor_id', $legacy_id );
    update_post_meta( $post_id, 'legacy_slug', $slug );
    if ( $wpml_code ) {
        update_post_meta( $post_id, 'wpml_language_code', $wpml_code );
        update_field( 'wpml_language_code', $wpml_code, $post_id );
    }
    if ( $author_email_raw ) {
        update_post_meta( $post_id, 'legacy_author_email', sanitize_text_field( $author_email_raw ) );
    }
    if ( $author_login_raw ) {
        update_post_meta( $post_id, 'legacy_author_username', sanitize_text_field( $author_login_raw ) );
    }
    update_post_meta( $post_id, 'legacy_author_user_id', $author_id );

    // Categorías y tipo.
    motorlan_assign_terms( $post_id, $categories, 'categoria' );
    motorlan_assign_any_term( $post_id, [ 'Motor' ], 'tipo', 'Motor' );

    // Marca.
    if ( $marca ) {
        $marca_term = motorlan_get_or_create_term( $marca, 'marca' );
        if ( $marca_term ) {
            wp_set_post_terms( $post_id, [ $marca_term->term_id ], 'marca', false );
            update_field( 'marca', $marca_term->term_id, $post_id );
        }
    }

    if ( $tipo_ref ) {
        $tipo_ref_clean = sanitize_text_field( $tipo_ref );
        update_field( 'tipo_o_referencia', $tipo_ref_clean, $post_id );
        update_post_meta( $post_id, 'legacy_tipo_o_referencia', $tipo_ref_clean );
    } else {
        delete_post_meta( $post_id, 'legacy_tipo_o_referencia' );
    }

    // Valores numéricos.
    foreach ( $numerics as $key => $value ) {
        if ( '' === trim( $value ) ) {
            continue;
        }
        update_field( $key, floatval( str_replace( ',', '.', $value ) ), $post_id );
    }

    // Localización.
    $country = motorlan_migration_normalize_country( $pais );
    if ( $country ) {
        update_field( 'pais', $country, $post_id );
    }

    if ( $provincia ) {
        update_field( 'provincia', sanitize_text_field( $provincia ), $post_id );
    }

    // Estado del artículo.
    update_field( 'estado_del_articulo', motorlan_migration_map_estado( $estado ), $post_id );

    // Documentos e im?genes.
    $has_images = ! empty( $featured_urls ) || ! empty( $gallery_urls );
    if ( $has_images ) {
        motorlan_migration_reset_post_images( $post_id );
    }

    $featured_attachment = null;
    if ( ! empty( $featured_urls ) ) {
        $downloaded_featured = motorlan_migration_download_media( $featured_urls, $post_id, 'image' );
        if ( ! empty( $downloaded_featured ) ) {
            $featured_attachment = (int) $downloaded_featured[0];
        }
    }

    $gallery_images = ! empty( $gallery_urls ) ? motorlan_migration_download_media( $gallery_urls, $post_id, 'image' ) : [];

    if ( ! $featured_attachment && ! empty( $gallery_images ) ) {
        $featured_attachment = array_shift( $gallery_images );
    }

    if ( $featured_attachment ) {
        update_field( 'motor_image', $featured_attachment, $post_id );
        set_post_thumbnail( $post_id, $featured_attachment );
    }

    if ( ! empty( $gallery_images ) ) {
        $gallery_ids = array_slice( $gallery_images, 0, 5 );
        update_field( 'motor_gallery', $gallery_ids, $post_id );
    }

    $docs = motorlan_migration_download_media( $document_urls, $post_id, 'document' );
    if ( ! empty( $docs ) ) {
        $informe = array_shift( $docs );
        if ( $informe ) {
            update_field( 'informe_de_reparacion', $informe, $post_id );
        }

        $adjunta = $docs ? array_shift( $docs ) : null;
        if ( $adjunta ) {
            update_field( 'documentacion_adjunta', $adjunta, $post_id );
        } elseif ( $informe ) {
            update_field( 'documentacion_adjunta', $informe, $post_id );
        }

        $repeater = [];
        $additional_docs = array_slice( $docs, 0, 5 );
        foreach ( $additional_docs as $index => $attach_id ) {
            $repeater[] = [
                'archivo' => $attach_id,
                'nombre'  => sprintf( __( 'Documento %d', 'motorlan-api-vue' ), $index + 1 ),
            ];
        }
        update_field( 'documentacion_adicional', $repeater, $post_id );
    }

    if ( $descripcion ) {
        update_field( 'descripcion', $descripcion, $post_id );
    }

    if ( $alquiler ) {
        update_field( 'posibilidad_de_alquiler', motorlan_migration_normalize_yesno( $alquiler ), $post_id );
    }

    if ( $alimentacion ) {
        update_field( 'tipo_de_alimentacion', sanitize_text_field( $alimentacion ), $post_id );
    }

    $servos = motorlan_parse_checkbox_values( $servos_raw );
    if ( $servos ) {
        update_field( 'servomotores', $servos, $post_id );
    }

    $drivers = motorlan_parse_checkbox_values( $drivers_raw );
    if ( $drivers ) {
        update_field( 'regulacion_electronica_drivers', $drivers, $post_id );
    }

    if ( $precio_neg ) {
        update_field( 'precio_negociable', motorlan_migration_normalize_yesno( $precio_neg ), $post_id );
    }

    $acf_status = $normalized_publicar ?: $post_status;
    update_field( 'publicar_acf', $acf_status, $post_id );

    update_field( 'stock', 1, $post_id );

    $text_field_map = [
        'direccion_motor'    => 'direccion_motor',
        'cp_motor'           => 'cp_motor',
        'agencia_transporte' => 'agencia_transporte',
        'modalidad_pago'     => 'modalidad_pago',
        'titulo_entrada'     => 'titulo_entrada',
    ];
    foreach ( $text_field_map as $column => $field_name ) {
        motorlan_migration_update_optional_field(
            $post_id,
            $field_name,
            motorlan_migration_get_field_value( $row, $column )
        );
    }

    motorlan_migration_update_optional_field(
        $post_id,
        'email_contacto',
        motorlan_migration_get_field_value( $row, 'email_contacto' ),
        'sanitize_email'
    );

    return [
        'status'  => $action,
        'post_id' => $post_id,
    ];
}

/**
 * Normalize a yes/no string to the plugin path choices.
 *
 * @param string $value Value from CSV.
 * @return string
 */
function motorlan_migration_normalize_yesno( $value ) {
    $value = strtolower( remove_accents( wp_strip_all_tags( (string) $value ) ) );
    if ( '' === $value ) {
        return 'No';
    }
    if ( false !== strpos( $value, 'si' ) || false !== strpos( $value, 'yes' ) ) {
        return 'Sí';
    }
    return 'No';
}


/**
 * Parse serialized or plain checkbox values.
 *
 * @param string $value Raw CSV value.
 * @return array
 */
function motorlan_parse_checkbox_values( $value ) {
    if ( empty( $value ) ) {
        return [];
    }

    $maybe = maybe_unserialize( $value );
    $values = [];
    if ( is_array( $maybe ) ) {
        foreach ( $maybe as $item ) {
            if ( is_string( $item ) && trim( $item ) ) {
                $values[] = sanitize_text_field( $item );
            }
        }
    } elseif ( is_string( $value ) ) {
        $parts = preg_split( '/[|,;]+/', $value );
        foreach ( $parts as $part ) {
            $clean = trim( $part, " \"\t\n\r\0\x0B" );
            if ( $clean ) {
                $values[] = sanitize_text_field( $clean );
            }
        }
    }

    return array_filter( array_unique( $values ) );
}

/**
 * Normalize country limited to España/Portugal/Francia.
 *
 * @param string $value Country name.
 * @return string
 */
function motorlan_migration_normalize_country( $value ) {
    $value = sanitize_text_field( $value );
    if ( '' === $value ) {
        return '';
    }
    $key = strtolower( remove_accents( $value ) );
    $mapping = [
        'espana'  => 'España',
        'spain'   => 'España',
        'portugal' => 'Portugal',
        'francia' => 'Francia',
        'france'  => 'Francia',
    ];
    return $mapping[ $key ] ?? 'España';
}


/**
 * Normalize estado_del_articulo values to new set.
 *
 * @param string $value Legacy value.
 * @return string
 */
function motorlan_migration_map_estado( $value ) {
    $value = strtolower( sanitize_text_field( $value ) );
    if ( false !== strpos( $value, 'restaurado' ) ) {
        return 'restored';
    }
    if ( false !== strpos( $value, 'usado' ) ) {
        return 'used';
    }
    return 'new';
}

/**
 * Download media URLs and attach them.
 *
 * @param array  $urls URLs to download.
 * @param int    $post_id Parent post.
 * @param string $type Type hint.
 * @return array
 */
function motorlan_migration_download_media( $urls, $post_id, $type = 'image' ) {
    if ( empty( $urls ) ) {
        return [];
    }

    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $attachments          = [];
    $should_disable_sizes = ( 'image' === $type );

    if ( $should_disable_sizes ) {
        add_filter( 'intermediate_image_sizes_advanced', 'motorlan_migration_disable_image_sizes', 99 );
        add_filter( 'big_image_size_threshold', '__return_false', 99 );
        wp_raise_memory_limit( 'image' );
    }

    try {
        foreach ( $urls as $url ) {
            $url = esc_url_raw( $url );
            if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
                continue;
            }

            $tmp = download_url( $url );
            if ( is_wp_error( $tmp ) ) {
                continue;
            }

            $file_array = [
                'name'     => motorlan_migration_build_filename_from_url( $url, $type ),
                'tmp_name' => $tmp,
            ];

            $attach_id = media_handle_sideload( $file_array, $post_id );
            if ( is_wp_error( $attach_id ) ) {
                @unlink( $file_array['tmp_name'] );
                continue;
            }

            if ( 'image' === $type ) {
                $alt_text = get_the_title( $post_id );
                if ( $alt_text ) {
                    update_post_meta( $attach_id, '_wp_attachment_image_alt', $alt_text );
                }
            }

            $attachments[] = $attach_id;
        }
    } finally {
        if ( $should_disable_sizes ) {
            remove_filter( 'intermediate_image_sizes_advanced', 'motorlan_migration_disable_image_sizes', 99 );
            remove_filter( 'big_image_size_threshold', '__return_false', 99 );
        }
    }

    return array_unique( array_filter( $attachments ) );
}

/**
 * Determine if URL is likely an image.
 *
 * @param string $url URL.
 * @return bool
 */
function motorlan_migration_url_is_image( $url ) {
    $ext = strtolower( pathinfo( $url, PATHINFO_EXTENSION ) );
    return in_array( $ext, [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ], true );
}

/**
 * Determine if URL is likely a document.
 *
 * @param string $url URL.
 * @return bool
 */
function motorlan_migration_url_is_document( $url ) {
    $ext = strtolower( pathinfo( $url, PATHINFO_EXTENSION ) );
    return in_array( $ext, [ 'pdf', 'doc', 'docx' ], true );
}

/**
 * Extract URLs from a field.
 *
 * @param string $value Field value.
 * @return array
 */
function motorlan_migration_extract_urls( $value ) {
    if ( empty( $value ) ) {
        return [];
    }

    $values = is_array( $value ) ? $value : [ $value ];
    $urls   = [];

    foreach ( $values as $chunk ) {
        if ( empty( $chunk ) ) {
            continue;
        }

        $parts = preg_split( '/[|,\r\n]+/', $chunk );
        foreach ( $parts as $part ) {
            $clean = trim( $part, " \"\t" );
            if ( empty( $clean ) ) {
                continue;
            }
            if ( false === stripos( $clean, 'http' ) ) {
                continue;
            }
            $urls[] = $clean;
        }
    }

    return array_values( array_unique( $urls ) );
}

/**
 * Normalize a CSV header to a safe field key.
 *
 * @param string $value Header value.
 * @return string
 */
function motorlan_migration_normalize_header( $value ) {
    $value = remove_accents( $value );
    $value = strtolower( $value );
    $value = preg_replace( '/[^a-z0-9_]+/', '_', $value );
    $value = preg_replace( '/_{2,}/', '_', $value );
    return trim( $value, '_' );
}

/**
 * Pair a CSV row with the normalized headers while keeping duplicate columns.
 *
 * @param array $headers Normalized headers.
 * @param array $row     CSV row data.
 * @return array
 */
function motorlan_migration_pair_row_with_headers( $headers, $row ) {
    $assoc        = [];
    $header_count = count( $headers );
    $row          = array_slice( array_pad( $row, $header_count, '' ), 0, $header_count );

    foreach ( $headers as $index => $header ) {
        if ( '' === $header ) {
            continue;
        }

        $value = $row[ $index ] ?? '';

        if ( isset( $assoc[ $header ] ) ) {
            if ( is_array( $assoc[ $header ] ) ) {
                $assoc[ $header ][] = $value;
            } else {
                $assoc[ $header ] = [ $assoc[ $header ], $value ];
            }
        } else {
            $assoc[ $header ] = $value;
        }
    }

    return $assoc;
}

/**
 * Return the first non-empty value stored under the given key.
 *
 * @param array  $row     Row data.
 * @param string $key     Key to read.
 * @param string $default Default fallback.
 * @return string
 */
function motorlan_migration_get_field_value( array $row, $key, $default = '' ) {
    if ( ! isset( $row[ $key ] ) ) {
        return $default;
    }

    $value = $row[ $key ];
    if ( is_array( $value ) ) {
        foreach ( $value as $candidate ) {
            $candidate = trim( (string) $candidate );
            if ( '' !== $candidate ) {
                return $candidate;
            }
        }
        return $default;
    }

    $value = trim( (string) $value );
    return '' === $value ? $default : $value;
}

/**
 * Return all non-empty values for a column.
 *
 * @param array  $row Row data.
 * @param string $key Column key.
 * @return array
 */
function motorlan_migration_get_field_all_values( array $row, $key ) {
    if ( ! isset( $row[ $key ] ) ) {
        return [];
    }

    $value  = $row[ $key ];
    $values = is_array( $value ) ? $value : [ $value ];
    $clean  = [];
    foreach ( $values as $entry ) {
        $entry = trim( (string) $entry );
        if ( '' !== $entry ) {
            $clean[] = $entry;
        }
    }

    return $clean;
}

/**
 * Convert pipe/comma separated string into an array.
 *
 * @param string $value Raw value.
 * @return array
 */
function motorlan_migration_split_list( $value ) {
    if ( empty( $value ) ) {
        return [];
    }
    $parts = preg_split( '/[|,]+/', (string) $value );
    $clean = [];
    foreach ( $parts as $part ) {
        $part = trim( $part );
        if ( '' !== $part ) {
            $clean[] = $part;
        }
    }
    return $clean;
}

/**
 * Sanitize legacy content while allowing basic HTML.
 *
 * @param string $value Raw content.
 * @return string
 */
function motorlan_migration_prepare_content( $value ) {
    if ( empty( $value ) ) {
        return '';
    }
    return wp_kses_post( $value );
}

/**
 * Normalize legacy publish status values to the new ACF choices.
 *
 * @param string $value    Legacy value.
 * @param string $fallback Default fallback.
 * @return string
 */
function motorlan_migration_normalize_publicar_status( $value, $fallback = 'publish' ) {
    $value = strtolower( sanitize_text_field( $value ) );
    if ( '' === $value ) {
        return $fallback;
    }

    $map = [
        'publish'   => [ 'publish', 'publicado', 'publicada' ],
        'draft'     => [ 'draft', 'borrador' ],
        'paused'    => [ 'paused', 'pausado', 'pausada', 'pause' ],
        'sold'      => [ 'sold', 'vendido', 'vendida' ],
    ];

    foreach ( $map as $normalized => $candidates ) {
        if ( in_array( $value, $candidates, true ) ) {
            return $normalized;
        }
    }

    return $fallback;
}

/**
 * REST upload callback.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function motorlan_rest_migration_upload( WP_REST_Request $request ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return new WP_Error( 'rest_forbidden', __( 'Sin permisos suficientes.', 'motorlan-api-vue' ), [ 'status' => 403 ] );
    }

    $file = $request->get_file_params()['motorlan_csv'] ?? [];
    $result = motorlan_migration_handle_uploaded_csv( $file );
    if ( is_wp_error( $result ) ) {
        return $result;
    }
    return rest_ensure_response( $result );
}

/**
 * REST chunk callback.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function motorlan_rest_migration_chunk( WP_REST_Request $request ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return new WP_Error( 'rest_forbidden', __( 'Sin permisos suficientes.', 'motorlan-api-vue' ), [ 'status' => 403 ] );
    }

    $import_id = sanitize_text_field( $request->get_param( 'import_id' ) ?? '' );
    $offset    = max( 0, intval( $request->get_param( 'offset' ) ?? 0 ) );
    $limit     = max( 1, min( 100, intval( $request->get_param( 'limit' ) ?? 10 ) ) );

    $result = motorlan_migration_process_chunk_request_data( $import_id, $offset, $limit );
    if ( is_wp_error( $result ) ) {
        return $result;
    }

    return rest_ensure_response( $result );
}

/**
 * Register REST routes for migration.
 */
function motorlan_register_migration_rest_routes() {
    register_rest_route(
        'motorlan/v1',
        '/migration/upload',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'motorlan_rest_migration_upload',
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ]
    );

    register_rest_route(
        'motorlan/v1',
        '/migration/chunk',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'motorlan_rest_migration_chunk',
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ]
    );
}
add_action( 'rest_api_init', 'motorlan_register_migration_rest_routes' );

/**
 * Assign many terms by name.
 *
 * @param int    $post_id Post ID.
 * @param array  $names Names list.
 * @param string $taxonomy Taxonomy slug.
 */
function motorlan_assign_terms( $post_id, $names, $taxonomy ) {
    $term_ids = [];
    foreach ( $names as $name ) {
        $name = sanitize_text_field( $name );
        if ( ! $name ) {
            continue;
        }
        $term = motorlan_get_or_create_term( $name, $taxonomy );
        if ( $term && $term->term_id ) {
            $term_ids[] = $term->term_id;
        }
    }
    if ( $term_ids ) {
        wp_set_post_terms( $post_id, $term_ids, $taxonomy, false );
    }
}

/**
 * Ensure one term exists and assign optional default.
 *
 * @param int    $post_id Post ID.
 * @param array  $names Names list.
 * @param string $taxonomy Taxonomy slug.
 * @param string $fallback Fallback term name.
 */
function motorlan_assign_any_term( $post_id, $names, $taxonomy, $fallback = '' ) {
    $names = (array) $names;
    foreach ( $names as $name ) {
        $name = sanitize_text_field( $name );
        if ( $name ) {
            $term = motorlan_get_or_create_term( $name, $taxonomy );
            if ( $term ) {
                wp_set_post_terms( $post_id, [ $term->term_id ], $taxonomy, false );
                return;
            }
        }
    }
    if ( $fallback ) {
        $term = motorlan_get_or_create_term( $fallback, $taxonomy );
        if ( $term ) {
            wp_set_post_terms( $post_id, [ $term->term_id ], $taxonomy, false );
        }
    }
}

/**
 * Get or create term.
 *
 * @param string $name Term name.
 * @param string $taxonomy Taxonomy slug.
 * @return WP_Term|null
 */
function motorlan_get_or_create_term( $name, $taxonomy ) {
    $name = sanitize_text_field( $name );
    if ( '' === $name ) {
        return null;
    }

    $existing = term_exists( $name, $taxonomy );
    if ( $existing && ! is_wp_error( $existing ) ) {
        $term_id = is_array( $existing ) ? $existing['term_id'] : $existing;
        return get_term( $term_id, $taxonomy );
    }

    $similar = motorlan_migration_find_similar_term( $name, $taxonomy );
    if ( $similar ) {
        return $similar;
    }

    $result = wp_insert_term( $name, $taxonomy );
    if ( is_wp_error( $result ) ) {
        return null;
    }
    return get_term( $result['term_id'], $taxonomy );
}

/**
 * Find existing publicacion by legacy metadata.
 *
 * @param string $legacy_id Legacy ID.
 * @param string $slug Legacy slug.
 * @return int|null
 */
function motorlan_find_publicacion_by_legacy( $legacy_id, $slug ) {
    if ( $legacy_id ) {
        $query = new WP_Query(
            [
                'post_type'  => 'publicacion',
                'meta_key'   => 'legacy_motor_id',
                'meta_value' => $legacy_id,
                'fields'     => 'ids',
            ]
        );
        if ( $query->have_posts() ) {
            return (int) $query->posts[0];
        }
    }

    if ( $slug ) {
        $post = get_page_by_path( $slug, OBJECT, 'publicacion' );
        if ( $post ) {
            return $post->ID;
        }
    }
    return null;
}

/**
 * Resolve an author ID based on CSV data.
 *
 * @param string $email   Author email.
 * @param string $login   Author login/username.
 * @return int
 */
function motorlan_migration_resolve_author( $email, $login ) {
    static $cache = [];

    $email = sanitize_email( $email );
    $login = sanitize_user( $login, true );
    $cache_key = md5( strtolower( (string) $email ) . '|' . strtolower( (string) $login ) );

    if ( isset( $cache[ $cache_key ] ) ) {
        return $cache[ $cache_key ];
    }

    $email_candidates = motorlan_migration_build_email_candidates( $email, $login );
    $login_candidates = motorlan_migration_build_login_candidates( $login, $email );

    $user = motorlan_migration_find_user_by_emails( $email_candidates );

    if ( ! $user && $login_candidates ) {
        $user = motorlan_migration_find_user_by_logins( $login_candidates );
    }

    if ( ! $user && $email_candidates ) {
        $user = motorlan_migration_find_user_by_meta_emails( $email_candidates );
    }

    if ( ! $user && $login_candidates ) {
        $user = motorlan_migration_find_user_by_meta_logins( $login_candidates );
    }

    $resolved = $user ? (int) $user->ID : get_current_user_id();
    $cache[ $cache_key ] = $resolved;

    return $resolved;
}

/**
 * Update optional ACF/meta field only when a value is present.
 *
 * @param int         $post_id  Post ID.
 * @param string      $field    Field/meta name.
 * @param string      $value    Value to store.
 * @param string|null $sanitize Sanitize callback name.
 */
function motorlan_migration_update_optional_field( $post_id, $field, $value, $sanitize = 'sanitize_text_field' ) {
    if ( '' === trim( (string) $value ) ) {
        return;
    }

    if ( $sanitize && is_callable( $sanitize ) ) {
        $value = call_user_func( $sanitize, $value );
    }

    if ( '' === trim( (string) $value ) ) {
        return;
    }

    update_field( $field, $value, $post_id );
}

/**
 * Attempt to find an existing term using a loose search across the target taxonomy.
 *
 * @param string $name Term name to match.
 * @param string $taxonomy Taxonomy slug.
 * @return WP_Term|null
 */
function motorlan_migration_find_similar_term( $name, $taxonomy ) {
    $normalized_target = motorlan_migration_normalize_term_name( $name );
    if ( '' === $normalized_target ) {
        return null;
    }

    $query = new WP_Term_Query(
        [
            'taxonomy'   => $taxonomy,
            'name__like' => $name,
            'hide_empty' => false,
            'number'     => 10,
        ]
    );

    if ( ! empty( $query->terms ) ) {
        foreach ( $query->terms as $term ) {
            $normalized_existing = motorlan_migration_normalize_term_name( $term->name );
            if ( $normalized_existing === $normalized_target ) {
                return $term;
            }
        }
        return $query->terms[0];
    }

    $slug_target = sanitize_title( $name );
    if ( $slug_target ) {
        $term = get_term_by( 'slug', $slug_target, $taxonomy );
        if ( $term && ! is_wp_error( $term ) ) {
            return $term;
        }
    }

    $all_terms = get_terms(
        [
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
        ]
    );

    if ( is_wp_error( $all_terms ) || empty( $all_terms ) ) {
        return null;
    }

    foreach ( $all_terms as $term ) {
        $normalized_existing = motorlan_migration_normalize_term_name( $term->name );
        if ( false !== strpos( $normalized_existing, $normalized_target ) || false !== strpos( $normalized_target, $normalized_existing ) ) {
            return $term;
        }
    }

    return null;
}

/**
 * Normalize term names for comparison.
 *
 * @param string $value Raw value.
 * @return string
 */
function motorlan_migration_normalize_term_name( $value ) {
    $value = strtolower( remove_accents( (string) $value ) );
    $value = preg_replace( '/[^a-z0-9]+/', ' ', $value );
    $value = trim( preg_replace( '/\s+/', ' ', $value ) );
    return $value;
}

/**
 * Build a set of candidate emails from the CSV row.
 *
 * @param string $email Primary email.
 * @param string $login Username value (could contain @).
 * @return array
 */
function motorlan_migration_build_email_candidates( $email, $login ) {
    $candidates = [];
    if ( $email ) {
        $candidates[] = $email;
    }
    if ( $login && false !== strpos( $login, '@' ) ) {
        $candidates[] = $login;
    }
    return array_values(
        array_filter(
            array_unique(
                array_map(
                    'sanitize_email',
                    $candidates
                )
            )
        )
    );
}

/**
 * Build a set of candidate logins from the CSV row.
 *
 * @param string $login Primary login.
 * @param string $email Primary email.
 * @return array
 */
function motorlan_migration_build_login_candidates( $login, $email ) {
    $candidates = [];
    if ( $login ) {
        $candidates[] = $login;
        $candidates[] = sanitize_user( $login, false );
    }
    if ( $email && false !== strpos( $email, '@' ) ) {
        $candidates[] = substr( $email, 0, strpos( $email, '@' ) );
    }
    if ( $login && false !== strpos( $login, '@' ) ) {
        $candidates[] = substr( $login, 0, strpos( $login, '@' ) );
    }

    return array_values(
        array_filter(
            array_unique(
                array_map(
                    function ( $value ) {
                        return sanitize_user( $value, true );
                    },
                    $candidates
                )
            )
        )
    );
}

/**
 * Attempt to find a user given a list of email candidates.
 *
 * @param array $emails Candidate emails.
 * @return WP_User|null
 */
function motorlan_migration_find_user_by_emails( array $emails ) {
    foreach ( $emails as $candidate ) {
        $candidate = sanitize_email( $candidate );
        if ( ! $candidate ) {
            continue;
        }
        $user = get_user_by( 'email', $candidate );
        if ( $user ) {
            return $user;
        }
    }
    return null;
}

/**
 * Attempt to find a user given a list of login candidates.
 *
 * @param array $logins Candidate logins.
 * @return WP_User|null
 */
function motorlan_migration_find_user_by_logins( array $logins ) {
    foreach ( $logins as $candidate ) {
        $candidate = sanitize_user( $candidate, true );
        if ( ! $candidate ) {
            continue;
        }
        $user = get_user_by( 'login', $candidate );
        if ( $user ) {
            return $user;
        }

        $user = get_user_by( 'slug', sanitize_title( $candidate ) );
        if ( $user ) {
            return $user;
        }
    }
    return null;
}

/**
 * Try to match a user via known email meta fields.
 *
 * @param array $emails Candidate emails.
 * @return WP_User|null
 */
function motorlan_migration_find_user_by_meta_emails( array $emails ) {
    $meta_keys = apply_filters(
        'motorlan_migration_author_email_meta_keys',
        [
            'company_email_contacto',
            'company_email',
            'email_contacto',
            'contact_email',
            'billing_email',
        ]
    );

    if ( empty( $meta_keys ) ) {
        return null;
    }

    foreach ( $emails as $candidate ) {
        $candidate = sanitize_email( $candidate );
        if ( ! $candidate ) {
            continue;
        }

        $meta_query = [ 'relation' => 'OR' ];
        foreach ( $meta_keys as $meta_key ) {
            $meta_query[] = [
                'key'   => $meta_key,
                'value' => $candidate,
            ];
        }

        $query = new WP_User_Query(
            [
                'number'     => 1,
                'fields'     => 'ids',
                'meta_query' => $meta_query,
            ]
        );

        if ( ! empty( $query->results ) ) {
            return get_user_by( 'id', (int) $query->results[0] );
        }
    }

    return null;
}

/**
 * Try to match a user via known login/meta identifiers.
 *
 * @param array $logins Candidate logins.
 * @return WP_User|null
 */
function motorlan_migration_find_user_by_meta_logins( array $logins ) {
    $meta_keys = apply_filters(
        'motorlan_migration_author_login_meta_keys',
        [
            'legacy_motor_username',
            'legacy_user_login',
        ]
    );

    if ( empty( $meta_keys ) ) {
        return null;
    }

    foreach ( $logins as $candidate ) {
        $candidate = sanitize_user( $candidate, true );
        if ( ! $candidate ) {
            continue;
        }

        $meta_query = [ 'relation' => 'OR' ];
        foreach ( $meta_keys as $meta_key ) {
            $meta_query[] = [
                'key'   => $meta_key,
                'value' => $candidate,
            ];
        }

        $query = new WP_User_Query(
            [
                'number'     => 1,
                'fields'     => 'ids',
                'meta_query' => $meta_query,
            ]
        );

        if ( ! empty( $query->results ) ) {
            return get_user_by( 'id', (int) $query->results[0] );
        }
    }

    return null;
}

/**
 * Generate a safe filename for a remote asset.
 *
 * @param string $url  Asset URL.
 * @param string $type Asset type hint.
 * @return string
 */
function motorlan_migration_build_filename_from_url( $url, $type = 'image' ) {
    $path     = wp_basename( parse_url( $url, PHP_URL_PATH ) ?? '' );
    $filename = sanitize_file_name( $path );

    if ( ! $filename ) {
        $uuid      = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( '', true );
        $extension = ( 'document' === $type ) ? '.pdf' : '.jpg';
        $filename  = sprintf( 'motorlan-%s-%s%s', $type, $uuid, $extension );
    }

    return $filename;
}

/**
 * Remove the current images attached to a post before re-importing them.
 *
 * @param int $post_id Post ID.
 */
function motorlan_migration_reset_post_images( $post_id ) {
    $attachment_ids = [];

    $featured_id = get_post_thumbnail_id( $post_id );
    if ( $featured_id ) {
        $attachment_ids[] = (int) $featured_id;
    }

    if ( function_exists( 'get_field' ) ) {
        $acf_featured = (int) get_field( 'motor_image', $post_id );
        if ( $acf_featured ) {
            $attachment_ids[] = $acf_featured;
        }

        $gallery_ids = get_field( 'motor_gallery', $post_id );
        if ( is_array( $gallery_ids ) ) {
            $attachment_ids = array_merge( $attachment_ids, array_map( 'intval', $gallery_ids ) );
        }
    }

    motorlan_migration_delete_attachments( array_unique( array_filter( $attachment_ids ) ) );

    if ( function_exists( 'delete_field' ) ) {
        delete_field( 'motor_image', $post_id );
        delete_field( 'motor_gallery', $post_id );
    }
    delete_post_thumbnail( $post_id );
}

/**
 * Delete a list of attachment IDs safely.
 *
 * @param array $attachment_ids Attachment IDs.
 */
function motorlan_migration_delete_attachments( array $attachment_ids ) {
    foreach ( $attachment_ids as $attachment_id ) {
        $attachment_id = (int) $attachment_id;
        if ( $attachment_id > 0 ) {
            wp_delete_attachment( $attachment_id, true );
        }
    }
}

/**
 * Disable intermediate image sizes during migration sideloads.
 *
 * @param array $sizes Registered sizes.
 * @return array
 */
function motorlan_migration_disable_image_sizes( $sizes ) {
    return [];
}
