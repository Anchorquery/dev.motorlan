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

    // Transfer publications from one author to another
    register_rest_route($namespace, '/admin/publications/transfer-author', [
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'motorlan_admin_transfer_publications_callback',
        'permission_callback' => 'motorlan_is_admin_user',
    ]);
}

/**
 * Get all publications for admin.
 */
function motorlan_admin_get_publications_callback($request) {
    $page = $request->get_param('page') ? intval($request->get_param('page')) : 1;
    $per_page = $request->get_param('per_page') ? intval($request->get_param('per_page')) : 20;
    $per_page = max(1, min(100, $per_page));
    $page = max(1, $page);
    
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
        if ($status === 'paused' || $status === 'sold') {
            // Al agotarse el stock, ventas/ofertas marcan publicar_acf = 'sold' sin cambiar
            // post_status (la ficha pública sigue mostrando "No disponible"): incluirlas.
            $args['post_status'] = [$status, 'publish'];
            $args['meta_query'] = [
                ['key' => 'publicar_acf', 'value' => $status],
            ];
        } elseif ($status === 'publish') {
            $args['post_status'] = 'publish';
            $args['meta_query'] = [
                'relation' => 'OR',
                ['key' => 'publicar_acf', 'value' => ['paused', 'sold'], 'compare' => 'NOT IN'],
                ['key' => 'publicar_acf', 'compare' => 'NOT EXISTS'],
            ];
        } elseif ($status !== 'all') {
            $args['post_status'] = $status;
        }
    }

    // Orden: las cabeceras de la tabla envían orderby/order (antes se ignoraban).
    $orderby = sanitize_key((string) $request->get_param('orderby'));
    $order = strtoupper((string) $request->get_param('order')) === 'ASC' ? 'ASC' : 'DESC';
    if ($orderby === 'price') {
        // Cláusula con nombre + NOT EXISTS para no excluir las publicaciones sin precio.
        $sort_clause = [
            'relation' => 'OR',
            'motorlan_sort' => ['key' => 'precio_de_venta', 'compare' => 'EXISTS', 'type' => 'NUMERIC'],
            ['key' => 'precio_de_venta', 'compare' => 'NOT EXISTS'],
        ];
        // Conservar el filtro de estado (meta_query) si lo hay
        $args['meta_query'] = !empty($args['meta_query'])
            ? ['relation' => 'AND', $args['meta_query'], $sort_clause]
            : $sort_clause;
        $args['orderby'] = 'motorlan_sort';
    } elseif ($orderby === 'status') {
        $args['orderby'] = 'post_status';
    } elseif (in_array($orderby, ['title', 'date', 'author'], true)) {
        $args['orderby'] = $orderby;
    }
    if ($orderby !== '') {
        $args['order'] = $order;
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

            // Publicaciones antiguas pueden no tener uuid; sin él, el botón
            // de editar del dashboard no navega a ninguna página.
            $uuid = get_post_meta($post_id, 'uuid', true);
            if (empty($uuid)) {
                $uuid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('', true);
                update_post_meta($post_id, 'uuid', $uuid);
            }

            // Basic info
            $item = [
                'id' => $post_id,
                'uuid' => $uuid,
                'title' => get_the_title(),
                'slug' => get_post_field('post_name', $post_id),
                // Mismo estado que ve el dueño en "Mis publicaciones": publicada en WP pero
                // pausada/vendida en publicar_acf (stock agotado) se muestra como tal.
                'status' => motorlan_admin_publication_effective_status($post_id),
                'date' => get_the_date('Y-m-d H:i:s'),
                'link' => motorlan_get_store_url( $post_id ),
            ];

            $item['image'] = motorlan_format_image_for_frontend($post_id ? get_post_thumbnail_id($post_id) ?: get_field('motor_image', $post_id, false) : null);

            // Price (ACF)
            $item['price'] = get_field('precio_de_venta', $post_id);

            // ACF Data for formatting
            $marca_id = get_field('marca', $post_id);
            $marca_name = '';
            $pending_brand = get_post_meta($post_id, '_pending_brand_name', true);
            if (!empty($pending_brand)) {
                $marca_name = $pending_brand;
                $item['pending_brand'] = $pending_brand;
            } elseif ($marca_id && is_numeric($marca_id) && (int) $marca_id !== -1) {
                $term = get_term($marca_id);
                if ($term && !is_wp_error($term)) {
                    $marca_name = $term->name;
                }
            }

            $item['acf'] = [
                'marca' => $marca_name ? ['name' => $marca_name] : null,
                'tipo_o_referencia' => get_field('tipo_o_referencia', $post_id),
                'velocidad' => get_field('velocidad', $post_id),
                'potencia' => get_field('potencia', $post_id),
                'par_nominal' => get_field('par_nominal', $post_id),
                'precio_de_venta' => $item['price'], // Keep for consistency
            ];

            // Tipo Taxonomy
            $tipos = wp_get_post_terms($post_id, 'tipo');
            if (!empty($tipos) && !is_wp_error($tipos)) {
                $item['tipo'] = array_map(function($t) {
                    return ['name' => $t->name, 'slug' => $t->slug, 'id' => $t->term_id];
                }, $tipos);
            }

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
 * Estado visible de una publicación: post_status, salvo que esté publicada en WP y
 * publicar_acf diga pausada/vendida (stock agotado por una venta u oferta).
 *
 * @param int $post_id
 * @return string
 */
function motorlan_admin_publication_effective_status($post_id) {
    $post_status = get_post_status($post_id);
    $acf_status = get_post_meta($post_id, 'publicar_acf', true);
    if ($post_status === 'publish' && in_array($acf_status, ['paused', 'sold'], true)) {
        return $acf_status;
    }
    return $post_status;
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

    $params = $request->get_json_params();
    $update_data = ['ID' => $post_id];

    // pending -> publish debe pasar por el flujo de aprobación (crea la marca pendiente,
    // regenera título/slug y notifica al usuario). Antes se publicaba sin marca y el
    // usuario nunca se enteraba.
    if (isset($params['status']) && $params['status'] === 'publish' && $post->post_status === 'pending'
        && function_exists('motorlan_approve_publication_callback')) {
        $approve_request = new WP_REST_Request('POST');
        $approve_request->set_url_params(['id' => $post_id]);
        $approve_request->set_header('Content-Type', 'application/json');
        $approve_request->set_body('{}');
        $approve_result = motorlan_approve_publication_callback($approve_request);
        if (is_wp_error($approve_result)) {
            return $approve_result;
        }
        return new WP_REST_Response(['message' => 'Publicación aprobada y publicada.'], 200);
    }

    $new_status = null;
    if (isset($params['status'])) {
        $allowed_statuses = ['publish', 'pending', 'draft', 'trash', 'paused', 'sold'];
        if (!in_array($params['status'], $allowed_statuses, true)) {
            return new WP_Error('invalid_status', 'Estado de publicación no válido.', ['status' => 400]);
        }
        $new_status = sanitize_text_field($params['status']);
    }

    // Papelera con wp_trash_post: guarda _wp_trash_meta_status y dispara trashed_post
    // (antes wp_update_post a 'trash' sin metadatos).
    if ($new_status === 'trash') {
        if ($post->post_status !== 'trash' && !wp_trash_post($post_id)) {
            return new WP_Error('trash_failed', 'No se pudo enviar la publicación a la papelera.', ['status' => 500]);
        }
        update_post_meta($post_id, 'publicar_acf', 'trash');
        return new WP_REST_Response(['message' => 'Publicación enviada a la papelera.'], 200);
    }

    // Sacar de la papelera correctamente antes de asignar el nuevo estado.
    if ($new_status !== null && $post->post_status === 'trash') {
        wp_untrash_post($post_id);
    }

    // Publicar con stock 0 la dejaría "Publicada" pero invisible en la tienda (exige stock >= 1).
    if ($new_status === 'publish') {
        $current_stock = (int) (function_exists('get_field') ? get_field('stock', $post_id) : get_post_meta($post_id, 'stock', true));
        if ($current_stock <= 0) {
            if (function_exists('update_field')) {
                update_field('stock', 1, $post_id);
            }
            update_post_meta($post_id, 'stock', 1);
        }
    }

    if ($new_status !== null) {
        $update_data['post_status'] = $new_status;
    }

    // Can add more fields to update here if needed

    $result = wp_update_post($update_data);

    if (is_wp_error($result)) {
        return $result;
    }

    // Sincronizar campo ACF si se cambió el estado
    if ($new_status !== null) {
        if (function_exists('update_field')) {
            update_field('publicar_acf', $new_status, $post_id);
        }
        update_post_meta($post_id, 'publicar_acf', $new_status);

        if ($new_status === 'publish' && function_exists('motorlan_publicacion_mark_approved')) {
            motorlan_publicacion_mark_approved($post_id);
        }
    }

    return new WP_REST_Response(['message' => 'Publicación actualizada correctamente.'], 200);
}

/**
 * Contact the publisher.
 */
function motorlan_admin_contact_publisher_callback($request) {
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
    // Optional: Create an internal notification
    do_action( 'motorlan_admin_contact_publisher', $post_id, $message, $subject );

    if (!$sent) {
        return new WP_Error('email_failed', 'No se pudo enviar el correo electrónico', ['status' => 500]);
    }

    return new WP_REST_Response(['message' => 'Mensaje enviado correctamente.'], 200);
}

/**
 * Transfer all publicaciones from one author email to another.
 */
function motorlan_admin_transfer_publications_callback( $request ) {
    if ( function_exists( 'motorlan_validate_json_content_type' ) ) {
        $valid_type = motorlan_validate_json_content_type( $request );
        if ( is_wp_error( $valid_type ) ) {
            return $valid_type;
        }
    }

    $params = $request->get_json_params();
    $source_email = isset( $params['source_email'] ) ? sanitize_email( $params['source_email'] ) : '';
    $target_email = isset( $params['target_email'] ) ? sanitize_email( $params['target_email'] ) : '';
    $dry_run = ! empty( $params['dry_run'] );

    if ( ! is_email( $source_email ) || ! is_email( $target_email ) ) {
        return new WP_Error(
            'invalid_email',
            'Debes indicar source_email y target_email válidos.',
            [ 'status' => 400 ]
        );
    }

    $source_user = get_user_by( 'email', $source_email );
    $target_user = get_user_by( 'email', $target_email );

    if ( ! $source_user ) {
        return new WP_Error( 'source_not_found', 'No existe un usuario con el correo de origen.', [ 'status' => 404 ] );
    }

    if ( ! $target_user ) {
        return new WP_Error( 'target_not_found', 'No existe un usuario con el correo de destino.', [ 'status' => 404 ] );
    }

    if ( (int) $source_user->ID === (int) $target_user->ID ) {
        return new WP_Error( 'same_user', 'El correo de origen y destino pertenecen al mismo usuario.', [ 'status' => 400 ] );
    }

    $publications_query = new WP_Query( [
        'post_type'              => 'publicacion',
        // Lista explícita: 'any' excluye la papelera.
        'post_status'            => ['publish', 'pending', 'draft', 'future', 'private', 'paused', 'sold', 'trash'],
        'posts_per_page'         => -1,
        'fields'                 => 'ids',
        'author'                 => (int) $source_user->ID,
        'orderby'                => 'ID',
        'order'                  => 'ASC',
        'no_found_rows'          => true,
        'ignore_sticky_posts'    => true,
        // Sin filtros de WPML: mover las publicaciones de TODOS los idiomas.
        'suppress_filters'       => true,
    ] );

    $publication_ids = array_map( 'intval', $publications_query->posts );
    wp_reset_postdata();

    $question_ids = [];
    foreach ( $publication_ids as $publication_id ) {
        $questions_query = new WP_Query( [
            'post_type'           => 'pregunta',
            'post_status'         => 'any',
            'posts_per_page'      => -1,
            'fields'              => 'ids',
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
            'meta_query'          => [
                [
                    'key'     => 'publicacion',
                    'value'   => $publication_id,
                    'compare' => '=',
                ],
            ],
        ] );

        if ( ! empty( $questions_query->posts ) ) {
            $question_ids = array_merge( $question_ids, array_map( 'intval', $questions_query->posts ) );
        }
        wp_reset_postdata();
    }

    $question_ids = array_values( array_unique( $question_ids ) );

    if ( $dry_run ) {
        return new WP_REST_Response( [
            'dry_run'         => true,
            'source_email'    => $source_email,
            'target_email'    => $target_email,
            'source_user_id'  => (int) $source_user->ID,
            'target_user_id'  => (int) $target_user->ID,
            'publications'    => count( $publication_ids ),
            'questions'       => count( $question_ids ),
            'publication_ids' => $publication_ids,
        ], 200 );
    }

    $moved_publications = [];
    foreach ( $publication_ids as $publication_id ) {
        $updated = wp_update_post( [
            'ID'          => $publication_id,
            'post_author' => (int) $target_user->ID,
        ], true );

        if ( is_wp_error( $updated ) ) {
            return new WP_Error(
                'transfer_failed',
                sprintf( 'No se pudo reasignar la publicación %d.', $publication_id ),
                [ 'status' => 500, 'publication_id' => $publication_id ]
            );
        }

        $moved_publications[] = (int) $publication_id;
    }

    $updated_questions = 0;
    foreach ( $question_ids as $question_id ) {
        if ( function_exists( 'update_field' ) ) {
            update_field( 'publication_owner', (int) $target_user->ID, $question_id );
        }
        update_post_meta( $question_id, 'publication_owner', (int) $target_user->ID );
        $updated_questions++;
    }

    return new WP_REST_Response( [
        'success'             => true,
        'source_email'        => $source_email,
        'target_email'        => $target_email,
        'source_user_id'      => (int) $source_user->ID,
        'target_user_id'      => (int) $target_user->ID,
        'moved_publications'  => count( $moved_publications ),
        'updated_questions'   => $updated_questions,
        'publication_ids'     => $moved_publications,
    ], 200 );
}
