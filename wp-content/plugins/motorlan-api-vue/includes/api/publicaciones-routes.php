<?php
/**
 * Setup for Publicaciones REST API Routes.
 *
 * @package motorlan-api-vue
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Get post taxonomy details.
 *
 * @param int    $post_id  The post ID.
 * @param string $taxonomy The taxonomy.
 * @return array
 */
function motorlan_get_post_taxonomy_details( $post_id, $taxonomy ) {
    $terms_details = [];
    $terms = wp_get_post_terms( $post_id, $taxonomy );
    if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
        foreach ( $terms as $term ) {
            $terms_details[] = array(
                'id'   => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            );
        }
    }
    return $terms_details;
}

/**
 * Get post ID by UUID.
 *
 * @param string $uuid The UUID.
 * @return int|null The post ID or null if not found.
 */
function motorlan_get_post_id_by_uuid( $uuid ) {
    if ( empty( $uuid ) ) {
        return null;
    }

    $args = array(
        'post_type'      => 'publicaciones',
        'meta_key'       => 'uuid',
        'meta_value'     => $uuid,
        'posts_per_page' => 1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    );

    $query = new WP_Query( $args );

    if ( ! $query->have_posts() ) {
        return null;
    }

    return $query->posts[0];
}

/**
 * Register custom REST API routes for publicaciones.
 */
function motorlan_register_publicaciones_rest_routes() {
    $namespace = 'motorlan/v1';

    // Route for getting a list of publicaciones
    register_rest_route( $namespace, '/publicaciones', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_publicaciones_callback',
        'permission_callback' => 'motorlan_permission_callback_true',
    ) );

    // Route for creating a new publicacion
    register_rest_route( $namespace, '/publicaciones', array(
        'methods'  => WP_REST_Server::CREATABLE,
        'callback' => 'motorlan_create_publicacion_callback',
        //'permission_callback' => 'motorlan_is_user_authenticated',
    ) );

    // Route for getting and updating a single publicacion by UUID
    register_rest_route($namespace, '/publicaciones/uuid/(?P<uuid>[a-zA-Z0-9-]+)', array(
        array(
            'methods' => 'GET',
            'callback' => 'motorlan_get_publicacion_by_uuid',
            'permission_callback' => 'motorlan_permission_callback_true'
        ),
        array(
            'methods' => 'POST',
            'callback' => 'motorlan_update_publicacion_by_uuid',
            'permission_callback' => 'motorlan_is_user_authenticated'
        ),
    ));

    // Route for getting a single publicacion by slug
    register_rest_route($namespace, '/publicaciones/(?P<slug>[a-zA-Z0-9-]+)', array(
        'methods'  =>  'GET',
        'callback' => 'motorlan_get_publicacion_by_slug',
        'permission_callback' => 'motorlan_permission_callback_true',
    ) );

    // Route for deleting a publicacion by ID
    register_rest_route($namespace, '/publicaciones/(?P<id>\\d+)', array(
        'methods' => 'DELETE',
        'callback' => 'motorlan_delete_publicacion',
        'permission_callback' => 'motorlan_is_user_authenticated'
    ));

    // Route for duplicating a publicacion by ID
    register_rest_route($namespace, '/publicaciones/duplicate/(?P<id>\\d+)', array(
        'methods' => 'POST',
        'callback' => 'motorlan_duplicate_publicacion',
        'permission_callback' => 'motorlan_is_user_authenticated'
    ));

    // Route for updating publicacion status by ID
    register_rest_route($namespace, '/publicaciones/(?P<id>\\d+)/status', array(
        'methods' => 'POST',
        'callback' => 'motorlan_update_publicacion_status',
        'permission_callback' => 'motorlan_is_user_authenticated'
    ));

    // Route for bulk deleting publicaciones
    register_rest_route($namespace, '/publicaciones/bulk-delete', array(
        'methods' => 'POST',
        'callback' => 'motorlan_bulk_delete_publicaciones',
        'permission_callback' => 'motorlan_is_user_authenticated',
    ));

    // Route for getting publicacion categories
    register_rest_route( $namespace, '/publicacion-categories', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_publicacion_categories_callback',
        'permission_callback' => 'motorlan_permission_callback_true',
    ) );

    // Route for getting publicacion tipos
    register_rest_route( $namespace, '/tipos', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_tipos_callback',
        'permission_callback' => 'motorlan_permission_callback_true',
    ) );

    // Route for getting brands
    register_rest_route( $namespace, '/marcas', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_marcas_callback',
        'permission_callback' => 'motorlan_permission_callback_true',
    ) );
    // -----------------------------
    // Favoritos endpoints
    // -----------------------------
    register_rest_route( $namespace, '/favorites', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_user_favorites',
        'permission_callback' => 'motorlan_is_user_authenticated'
    ) );

    register_rest_route( $namespace, '/favorites', array(
        'methods'  => WP_REST_Server::CREATABLE,
        'callback' => 'motorlan_add_user_favorite',
        'permission_callback' => 'motorlan_is_user_authenticated'
    ) );

    register_rest_route( $namespace, '/favorites/(?P<id>\\d+)', array(
        'methods'  => 'DELETE',
        'callback' => 'motorlan_remove_user_favorite',
        'permission_callback' => 'motorlan_is_user_authenticated'
    ) );
    // Route for getting a list of publicaciones for the public store
    register_rest_route( $namespace, '/store/publicaciones', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_public_publicaciones_callback',
        'permission_callback' => '__return_true',
    ) );
}
add_action( 'rest_api_init', 'motorlan_register_publicaciones_rest_routes' );

/**
 * Callback function to get a list of public publications for the store.
 * This is a public endpoint and should only return published posts.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response The response object.
 */
function motorlan_get_public_publicaciones_callback( $request ) {
    // Use the existing callback but force the status to 'publish'
    // and remove any author filtering.
    $request->set_param('status', 'publish');

    // Unset author if it was passed, to ensure we get all users' publications
    $params = $request->get_params();
    if (isset($params['author'])) {
        unset($params['author']);
        $request->set_param('author', null);
    }
    
    return motorlan_get_publicaciones_callback($request);
}

/**
 * Obtener favoritos del usuario autenticado
 */
function motorlan_get_user_favorites(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    if (!$user_id) {
        return new WP_Error('not_logged_in', 'Debes iniciar sesión', ['status' => 401]);
    }

    $favorites = get_user_meta($user_id, 'motorlan_favorites', true);
    if (!is_array($favorites)) {
        $favorites = [];
    }

    $data = [];
    foreach ($favorites as $post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_type === 'publicaciones') {
            $data[] = motorlan_get_publicacion_data($post_id);
        }
    }

    return new WP_REST_Response(['data' => $data], 200);
}

/**
 * Agregar favorito
 */
function motorlan_add_user_favorite(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    if (!$user_id) {
        return new WP_Error('not_logged_in', 'Debes iniciar sesión', ['status' => 401]);
    }

    $params = $request->get_json_params();
    $post_id = intval($params['publicacion_id'] ?? 0);
    if (!$post_id || get_post_type($post_id) !== 'publicaciones') {
        return new WP_Error('invalid_post', 'Publicación inválida', ['status' => 400]);
    }

    $favorites = get_user_meta($user_id, 'motorlan_favorites', true);
    if (!is_array($favorites)) {
        $favorites = [];
    }

    if (!in_array($post_id, $favorites)) {
        $favorites[] = $post_id;
        update_user_meta($user_id, 'motorlan_favorites', $favorites);
    }

    return new WP_REST_Response(['message' => 'Agregado a favoritos'], 201);
}

/**
 * Eliminar favorito
 */
function motorlan_remove_user_favorite(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    if (!$user_id) {
        return new WP_Error('not_logged_in', 'Debes iniciar sesión', ['status' => 401]);
    }

    $post_id = intval($request->get_param('id'));
    $favorites = get_user_meta($user_id, 'motorlan_favorites', true);
    if (!is_array($favorites)) {
        $favorites = [];
    }

    $favorites = array_filter($favorites, function($fav) use ($post_id) {
        return intval($fav) !== $post_id;
    });

    update_user_meta($user_id, 'motorlan_favorites', $favorites);

    return new WP_REST_Response(['message' => 'Eliminado de favoritos'], 200);
}


/**
 * Get a single publicacion by UUID.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error The response object or error.
 */
function motorlan_get_publicacion_by_uuid(WP_REST_Request $request) {
    $uuid = $request->get_param('uuid');

    if (empty($uuid)) {
        return new WP_Error('no_uuid', 'UUID not provided', array('status' => 400));
    }

    $args = array(
        'post_type' => 'publicaciones',
        'meta_key' => 'uuid',
        'meta_value' => $uuid,
        'posts_per_page' => 1,
        'post_status' => 'any',
    );

    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        return new WP_Error('not_found', 'Publicación no encontrada', array('status' => 404));
    }

    $query->the_post();
    $post_id = get_the_ID();
    $publicacion_data = motorlan_get_publicacion_data($post_id);

    wp_reset_postdata();

    return new WP_REST_Response($publicacion_data, 200);
}

/**
 * Get a single publicacion by slug.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error The response object or error.
 */
function motorlan_get_publicacion_by_slug(WP_REST_Request $request) {
    $slug = $request->get_param('slug');

    if (empty($slug)) {
        return new WP_Error('no_slug', 'Slug not provided', array('status' => 400));
    }
    $args = array(
        'post_type' => 'publicaciones',
        'name' => $slug,
        'posts_per_page' => 1,
        'post_status' => 'any',
    );

    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        return new WP_Error('not_found', 'Publicación no encontrada', array('status' => 404));
    }

    $query->the_post();
    $post_id = get_the_ID();
    $publicacion_data = motorlan_get_publicacion_data($post_id);

    wp_reset_postdata();

    return new WP_REST_Response(array('data' => $publicacion_data), 200);
}


/**
 * Helper function to get all publicacion data.
 *
 * @param int $post_id The post ID.
 * @return array The publicacion data.
 */
function motorlan_get_publicacion_data($post_id) {
    $publicacion_item = array(
        'id'           => $post_id,
        'uuid'         => get_post_meta($post_id, 'uuid', true),
        'title'        => get_the_title($post_id),
        'slug'         => get_post_field('post_name', $post_id),
        'status'       => get_field('publicar_acf', $post_id),
        'author_id'    => get_post_field('post_author', $post_id),
        'author'       => (function() use ($post_id) {
            $author_id = get_post_field('post_author', $post_id);
            $user = get_userdata($author_id);
            if($user) {
                return [
                    'id' => $author_id,
                    'name' => $user->display_name,
                    'email' => $user->user_email,
                ];
            }
            return null;
        })(),
        'categories'   => motorlan_get_post_taxonomy_details($post_id, 'categoria'),
        'tipo'         => motorlan_get_post_taxonomy_details($post_id, 'tipo'),
        'acf'          => array(),
    );

    if (function_exists('get_fields')) {
        $fields = get_fields($post_id);
        if ($fields) {
            $publicacion_item['acf'] = $fields;
        }
    } else {
        $publicacion_item['acf_error'] = 'Advanced Custom Fields plugin is not active.';
    }

    // Ensure essential fields are present, even if they have no value, to avoid issues in the frontend
    $essential_fields = [
        'marca', 'tipo_o_referencia', 'estado_del_articulo', 'descripcion',
        'precio_de_venta', 'potencia', 'velocidad', 'par_nominal', 'voltaje',
        'intensidad', 'pais', 'provincia', 'posibilidad_de_alquiler',
        'tipo_de_alimentacion', 'servomotores', 'regulacion_electronica_drivers',
        'precio_negociable', 'motor_image', 'motor_gallery', 'informe_de_reparacion', 'stock', 'documentacion', 'documentacion_adjunta'
    ];

    foreach ($essential_fields as $field) {
        if (!isset($publicacion_item['acf'][$field])) {
            $publicacion_item['acf'][$field] = null;
        }
    }

    // For 'marca' (brand), which is a taxonomy, return the full term object
    if (!empty($publicacion_item['acf']['marca'])) {
        $term_id = $publicacion_item['acf']['marca'];
        if (is_numeric($term_id)) {
            $term = get_term($term_id, 'marca');
            if ($term && !is_wp_error($term)) {
                $publicacion_item['acf']['marca'] = array(
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                );
            }
        }
    }

    // For 'tipo', which is a taxonomy, return the full term object
    if (!empty($publicacion_item['acf']['tipo'])) {
        $term_id = $publicacion_item['acf']['tipo'];
        if (is_numeric($term_id)) {
            $term = get_term($term_id, 'tipo');
            if ($term && !is_wp_error($term)) {
                $publicacion_item['acf']['tipo'] = array(
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                );
            }
        }
    }

    $publicacion_item['imagen_destacada'] = get_field('motor_image', $post_id, true);
    $publicacion_item['acf']['motor_gallery'] = get_field('motor_gallery', $post_id, true);
    $publicacion_item['acf']['documentacion'] = get_field('documentacion', $post_id, true);
    $publicacion_item['acf']['documentacion_adjunta'] = get_field('documentacion_adjunta', $post_id, true);

    return $publicacion_item;
}


/**
 * Update a publicacion by UUID.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error The response object or error.
 */
function motorlan_update_publicacion_by_uuid(WP_REST_Request $request) {
    $uuid = $request->get_param('uuid');
    if (empty($uuid)) {
        return new WP_Error('no_uuid', 'UUID not provided', array('status' => 400));
    }

    $args = array(
        'post_type' => 'publicaciones',
        'meta_key' => 'uuid',
        'meta_value' => $uuid,
        'posts_per_page' => 1,
        'post_status' => 'any',
    );
    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        return new WP_Error('not_found', 'Publicación no encontrada', array('status' => 404));
    }

    $query->the_post();
    $post_id = get_the_ID();
    wp_reset_postdata();

    $params = $request->get_json_params();

    // Update post title
    if (isset($params['title'])) {
        wp_update_post(array('ID' => $post_id, 'post_title' => sanitize_text_field($params['title'])));
    }

    // Update post categories
    if (isset($params['categories'])) {
        wp_set_post_terms($post_id, $params['categories'], 'categoria', false);
    }

    // Update post tipo
    if (isset($params['tipo'])) {
        wp_set_post_terms($post_id, $params['tipo'], 'tipo', false);
    }

    // Update status/publicar_acf
    if (isset($params['status'])) {
        update_field('publicar_acf', sanitize_text_field($params['status']), $post_id);
    }

    // Update ACF fields
    if (isset($params['acf']) && is_array($params['acf'])) {
        foreach ($params['acf'] as $key => $value) {
            // Si es marca o tipo y es array, extraer el ID
            if (in_array($key, ['marca', 'tipo']) && is_array($value) && isset($value['id'])) {
                $value = $value['id'];
            }
            // Si es imagen principal, soportar id directo o array con id
            if ($key === 'motor_image') {
                if (is_array($value) && isset($value['id'])) {
                    $value = intval($value['id']);
                }
                if (is_numeric($value)) {
                    $value = intval($value);
                }
            }
            // Si es galería, asegurarse de que quede como array de IDs
            if ($key === 'motor_gallery' && is_array($value)) {
                $value = array_map(function($item) {
                    if (is_array($item) && isset($item['id'])) {
                        return intval($item['id']);
                    }
                    return is_numeric($item) ? intval($item) : $item;
                }, $value);
            }
            // Si es documentación adicional, extraer IDs de archivos
            if ($key === 'documentacion_adicional' && is_array($value)) {
                foreach ($value as &$doc) {
                    if (isset($doc['archivo']) && is_array($doc['archivo']) && isset($doc['archivo']['id'])) {
                        $doc['archivo'] = $doc['archivo']['id'];
                    }
                }
                unset($doc);
            }
            if (is_string($value)) {
                $value = sanitize_text_field($value);
            }
            update_field($key, $value, $post_id);
        }
    }

    return new WP_REST_Response(array('message' => 'Publicación actualizada correctamente'), 200);
}


/**
 * Delete a publicacion by ID.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error The response object or error.
 */
function motorlan_delete_publicacion(WP_REST_Request $request) {
    $post_id = $request->get_param('id');
    $result = wp_delete_post($post_id, true); // true to force delete

    if ($result === false) {
        return new WP_Error('delete_failed', 'Failed to delete publicacion', array('status' => 500));
    }

    return new WP_REST_Response(array('message' => 'Publicacion deleted successfully'), 200);
}


/**
 * Bulk delete publicaciones by ID.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error The response object or error.
 */
function motorlan_bulk_delete_publicaciones(WP_REST_Request $request) {
    $params = $request->get_json_params();
    $ids = isset($params['ids']) ? $params['ids'] : array();

    if (empty($ids)) {
        return new WP_Error('no_ids', 'No IDs provided', array('status' => 400));
    }

    $deleted_count = 0;
    foreach ($ids as $id) {
        $result = wp_delete_post(intval($id), true);
        if ($result !== false) {
            $deleted_count++;
        }
    }

    if ($deleted_count === 0) {
        return new WP_Error('delete_failed', 'Failed to delete any publicacion', array('status' => 500));
    }

    return new WP_REST_Response(array('message' => $deleted_count . ' publicaciones deleted successfully'), 200);
}


/**
 * Duplicate a publicacion by ID.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error The response object or error.
 */
function motorlan_duplicate_publicacion(WP_REST_Request $request) {
    $original_post_id = $request->get_param('id');
    $original_post = get_post($original_post_id);

    if (!$original_post) {
        return new WP_Error('not_found', 'Publicación original no encontrada', array('status' => 404));
    }

    $new_post_data = array(
        'post_title' => $original_post->post_title . ' (copia)',
        'post_status' => 'draft',
        'post_type' => $original_post->post_type,
        'post_author' => get_current_user_id(),
    );

    $new_post_id = wp_insert_post($new_post_data);

    if (is_wp_error($new_post_id)) {
        return $new_post_id;
    }

    // Duplicate ACF fields
    $acf_fields = get_fields($original_post_id);
    if ($acf_fields) {
        foreach ($acf_fields as $name => $value) {
            update_field($name, $value, $new_post_id);
        }
    }

    // Duplicate taxonomies
    $taxonomies = get_object_taxonomies($original_post->post_type);
    foreach ($taxonomies as $taxonomy) {
        $post_terms = wp_get_object_terms($original_post_id, $taxonomy, array('fields' => 'slugs'));
        wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
    }

    // Assign a new UUID and set the status to draft
    update_field('uuid', wp_generate_uuid4(), $new_post_id);
    update_field('publicar_acf', 'draft', $new_post_id);


    return new WP_REST_Response(array('message' => 'Publicación duplicada correctamente', 'new_post_id' => $new_post_id), 200);
}


/**
 * Update publicacion status by ID.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error The response object or error.
 */
function motorlan_update_publicacion_status(WP_REST_Request $request) {
    $post_id = $request->get_param('id');
    $params = $request->get_json_params();
    $new_status = isset($params['status']) ? sanitize_text_field($params['status']) : '';

    if (empty($new_status)) {
        return new WP_Error('no_status', 'Status not provided', array('status' => 400));
    }

    update_field('publicar_acf', $new_status, $post_id);

    return new WP_REST_Response(array('message' => 'Publicación status updated successfully'), 200);
}

/**
 * Callback function to get a list of publicaciones with pagination and filtering, using ACF.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response The response object.
 */
function motorlan_get_publicaciones_callback( $request ) {
    // Get pagination parameters from the request, with defaults.
    $page = $request->get_param( 'page' ) ? absint( $request->get_param( 'page' ) ) : 1;
    $per_page = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : 10;

    // --- FILTERING LOGIC ---
    $params = $request->get_params();
    $meta_query = array('relation' => 'AND');
    $tax_query = array('relation' => 'AND');

    // Define the list of fields that can be used for filtering.
    $filterable_fields = [
        'marca', 'tipo_o_referencia', 'potencia', 'velocidad', 'par_nominal', 'voltaje', 'intensidad',
        'pais', 'provincia', 'estado_del_articulo', 'posibilidad_de_alquiler', 'tipo_de_alimentacion',
        'servomotores', 'regulacion_electronica_drivers', 'precio_de_venta', 'precio_negociable', 'uuid'
    ];

    // Build the meta_query dynamically based on request parameters.
    foreach ($filterable_fields as $field_name) {
        if ( !empty($params[$field_name]) ) {
            $meta_query[] = array(
                'key'     => $field_name,
                'value'   => sanitize_text_field($params[$field_name]),
                'compare' => $field_name === 'tipo_o_referencia' ? 'LIKE' : '=',
            );
        }
    }

    // Filter by status (publicar_acf field)
    if ( !empty($params['status']) ) {
        $meta_query[] = array(
            'key'     => 'publicar_acf',
            'value'   => sanitize_text_field($params['status']),
            'compare' => '=',
        );
    }

    // Filter by category (categoria taxonomy)
    if ( !empty($params['category']) ) {
        $terms = array_map( 'sanitize_text_field', explode( ',', $params['category'] ) );
        $tax_query[] = array(
            'taxonomy' => 'categoria',
            'field'    => 'slug',
            'terms'    => $terms,
        );
    }

    // Filter by tipo (tipo taxonomy)
    if ( !empty($params['tipo']) ) {
        $terms = array_map( 'sanitize_text_field', explode( ',', $params['tipo'] ) );
        $tax_query[] = array(
            'taxonomy' => 'tipo',
            'field'    => 'slug',
            'terms'    => $terms,
        );
    }

    $args = array(
        'post_type'      => 'publicaciones',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'post_status'    => 'any',
    );

    // Add search parameter
    if ( ! empty( $params['search'] ) ) {
        $args['s'] = sanitize_text_field( $params['search'] );
    }

    // Add sorting parameters
    if ( ! empty( $params['orderby'] ) ) {
        $orderby = sanitize_text_field( $params['orderby'] );
        if ( $orderby === 'price' ) {
            $args['meta_key'] = 'precio_de_venta';
            $args['orderby'] = 'meta_value_num';
        } else {
            $args['orderby'] = $orderby;
        }
    }

    if ( ! empty( $params['order'] ) ) {
        $order = sanitize_text_field( $params['order'] );
        if (in_array(strtoupper($order), ['ASC', 'DESC'])) {
            $args['order'] = $order;
        }
    }

    // Only add meta_query if there are filters.
    if (count($meta_query) > 1) {
        $args['meta_query'] = $meta_query;
    }

    // Only add tax_query if there are filters.
    if (count($tax_query) > 1) {
        $args['tax_query'] = $tax_query;
    }

    $query = new WP_Query( $args );
    $publicaciones_data = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();

            $publicacion_item = array(
                'id'           => $post_id,
                'uuid'         => get_post_meta( $post_id, 'uuid', true ),
                'title'        => get_the_title(),
                'slug'         => get_post_field( 'post_name', $post_id ),
                'status'       => get_field('publicar_acf', $post_id),
                'imagen_destacada' =>  get_field('motor_image', $post_id ,true ) ,
                'author_id'    => get_post_field( 'post_author', $post_id ),
                'categories'   => motorlan_get_post_taxonomy_details( $post_id, 'categoria' ),
                'tipo'         => motorlan_get_post_taxonomy_details( $post_id, 'tipo' ),
                'acf'          => array(),
            );

            if ( function_exists('get_field') ) {
                $acf_fields = [
                    'marca', 'tipo_o_referencia','estado_del_articulo','descripcion',
                    'precio_de_venta',
                ];

                foreach($acf_fields as $field_name) {
                    $value = get_field($field_name, $post_id);
                    if ($field_name === 'marca' && $value) {
                        $term = get_term($value, 'marca');
                        if ($term && !is_wp_error($term)) {
                            $publicacion_item['acf'][$field_name] = array(
                                'id' => $term->term_id,
                                'name' => $term->name
                            );
                        } else {
                            $publicacion_item['acf'][$field_name] = null;
                        }
                    } else {
                        $publicacion_item['acf'][$field_name] = $value;
                    }
                }
            } else {
                 $publicacion_item['acf_error'] = 'Advanced Custom Fields plugin is not active.';
            }

            $publicaciones_data[] = $publicacion_item;
        }
        wp_reset_postdata();
    }

    $pagination = array(
        'total'     => (int) $query->found_posts,
        'totalPages' => (int) $query->max_num_pages,
        'currentPage'    => (int) $page,
        'perPage'   => (int) $per_page,
    );

    $response_data = array(
        'data'      => $publicaciones_data,
        'pagination' => $pagination,
    );

    $response = new WP_REST_Response( $response_data, 200 );

    $response->header( 'X-WP-Total', $query->found_posts );
    $response->header( 'X-WP-TotalPages', $query->max_num_pages );

    return $response;
}

/**
 * Callback function to get a list of publicacion categories.
 *
 * @return WP_REST_Response The response object.
 */
function motorlan_get_publicacion_categories_callback() {
    $terms = get_terms( array(
        'taxonomy'   => 'categoria',
        'hide_empty' => false,
    ) );

    if ( is_wp_error( $terms ) ) {
        return new WP_REST_Response( array( 'message' => $terms->get_error_message() ), 500 );
    }

    return new WP_REST_Response( $terms, 200 );
}

/**
 * Callback function to get a list of tipos.
 *
 * @return WP_REST_Response The response object.
 */
function motorlan_get_tipos_callback() {
    $terms = get_terms( array(
        'taxonomy'   => 'tipo',
        'hide_empty' => false,
    ) );

    if ( is_wp_error( $terms ) ) {
        return new WP_REST_Response( array( 'message' => $terms->get_error_message() ), 500 );
    }

    return new WP_REST_Response( $terms, 200 );
}

/**
 * Callback function to get a list of brands.
 *
 * @return WP_REST_Response The response object.
 */
function motorlan_get_marcas_callback() {
    $terms = get_terms( array(
        'taxonomy'   => 'marca',
        'hide_empty' => false,
    ) );

    if ( is_wp_error( $terms ) ) {
        return new WP_REST_Response( array( 'message' => $terms->get_error_message() ), 500 );
    }

    return new WP_REST_Response( $terms, 200 );
}

/**
 * Callback to create a new publicacion item.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error
 */
function motorlan_create_publicacion_callback(WP_REST_Request $request) {
    $params = $request->get_json_params();

    // Fallback si vienen como form-data o urlencoded
    if (empty($params)) {
        $params = $request->get_params();
    }

    if (empty($params)) {
        return new WP_Error('no_params', 'No se recibieron parámetros válidos', ['status' => 400]);
    }

    // Validaciones básicas
    if (empty($params['title'])) {
        return new WP_Error('missing_title', 'El título es obligatorio', ['status' => 400]);
    }
    if (empty($params['acf']['marca'])) {
        return new WP_Error('missing_brand', 'La marca es obligatoria', ['status' => 400]);
    }
    if (empty($params['acf']['tipo_o_referencia'])) {
        return new WP_Error('missing_reference', 'La referencia es obligatoria', ['status' => 400]);
    }
    if (empty($params['acf']['descripcion'])) {
        return new WP_Error('missing_description', 'La descripción es obligatoria', ['status' => 400]);
    }

    $post_data = array(
        'post_title'  => sanitize_text_field($params['title']),
        'post_status' => sanitize_text_field($params['status']),
        'post_type'   => 'publicaciones',
        'post_author' => get_current_user_id(),
    );

    $post_id = wp_insert_post($post_data);

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    // Assign a UUID
    $uuid = wp_generate_uuid4();
    update_post_meta($post_id, 'uuid', $uuid);

    // Set categories
    if (!empty($params['categories'])) {
        wp_set_post_terms($post_id, $params['categories'], 'categoria');
    }

    // Set tipo
    if (!empty($params['tipo'])) {
        wp_set_post_terms($post_id, $params['tipo'], 'tipo');
    }

    // Update ACF fields
    if (isset($params['acf']) && is_array($params['acf'])) {
        foreach ($params['acf'] as $key => $value) {
            // Si el campo es 'marca' y es string, busca el ID
            if ($key === 'marca' && !empty($value) && !is_numeric($value)) {
                // Buscar por nombre
                $term = get_term_by('name', $value, 'marca');
                // Si no se encuentra por nombre, intentar por slug
                if (!$term) {
                    $term = get_term_by('slug', sanitize_title($value), 'marca');
                }
                if ($term) {
                    $value = $term->term_id;
                }
            }
            // Si el campo es 'tipo' y es string, busca el ID
            if ($key === 'tipo' && !empty($value) && !is_numeric($value)) {
                $term = get_term_by('name', $value, 'tipo');
                if (!$term) {
                    $term = get_term_by('slug', sanitize_title($value), 'tipo');
                }
                if ($term) {
                    $value = $term->term_id;
                }
            }
            // Sanitización de strings / números
            if (is_string($value)) {
                $value = sanitize_text_field($value);
            }
            if (in_array($key, ['potencia','velocidad','par_nominal','voltaje','intensidad','precio_de_venta','stock'])) {
                $value = is_numeric($value) ? floatval($value) : null;
            }
            // Normalizar campo 'pais'
            if ($key === 'pais' && !empty($value)) {
                $value = sanitize_text_field(strtolower($value));
            }
            update_field($key, $value, $post_id);
        }
    }

    $response_data = array(
        'message' => 'Publicación creada con éxito.',
        'id'      => $post_id,
        'uuid'    => $uuid,
    );

    return new WP_REST_Response($response_data, 201);
}

/**
 * Crear una garantía asociada a un motor y al usuario actual.
 */
function motorlan_create_garantia_callback(WP_REST_Request $request) {
    $params = $request->get_json_params();
    if (empty($params)) {
        $params = $request->get_params();
    }

    if (empty($params['motor_id'])) {
        return new WP_Error('missing_motor', 'El ID de motor es obligatorio', ['status' => 400]);
    }

    $current_user = get_current_user_id();
    if (!$current_user) {
        return new WP_Error('not_logged_in', 'Debes iniciar sesión para crear la garantía', ['status' => 401]);
    }

    // Crear post de tipo "garantia"
    $post_data = [
        'post_type'   => 'garantia',
        'post_status' => 'publish',
        'post_author' => $current_user,
        'post_title'  => 'Garantía Motor #' . intval($params['motor_id']),
    ];

    $garantia_id = wp_insert_post($post_data);
    if (is_wp_error($garantia_id)) {
        return $garantia_id;
    }

    // Guardar relación con motor y usuario + demás campos
    update_field('motor_id', intval($params['motor_id']), $garantia_id);
    update_field('usuario_id', $current_user, $garantia_id);
    update_field('is_same_address', sanitize_text_field($params['is_same_address'] ?? 'yes'), $garantia_id);
    update_field('direccion_motor', sanitize_text_field($params['direccion_motor'] ?? ''), $garantia_id);
    update_field('cp_motor', sanitize_text_field($params['cp_motor'] ?? ''), $garantia_id);
    update_field('agencia_transporte', sanitize_text_field($params['agencia_transporte'] ?? ''), $garantia_id);
    update_field('modalidad_pago', sanitize_text_field($params['modalidad_pago'] ?? ''), $garantia_id);
    update_field('comentarios', sanitize_textarea_field($params['comentarios'] ?? ''), $garantia_id);

    return new WP_REST_Response([
        'message' => 'Garantía creada con éxito',
        'garantia_id' => $garantia_id,
        'motor_id' => intval($params['motor_id']),
        'usuario_id' => $current_user,
    ], 201);
}
