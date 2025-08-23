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
 * Register custom REST API routes for publicaciones.
 */
function motorlan_register_publicaciones_rest_routes() {
    $namespace = 'motorlan/v1';

    // Route for getting a list of publicaciones
    register_rest_route( $namespace, '/publicaciones', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_publicaciones_callback',
        'permission_callback' => '__return_true',
    ) );

    // Route for getting and updating a single publicacion by UUID
    register_rest_route($namespace, '/publicaciones/uuid/(?P<uuid>[a-zA-Z0-9-]+)', array(
        array(
            'methods' => 'GET',
            'callback' => 'motorlan_get_publicacion_by_uuid',
            'permission_callback' => '__return_true'
        ),
        array(
            'methods' => 'POST',
            'callback' => 'motorlan_update_publicacion_by_uuid',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ),
    ));

    // Route for getting a single publicacion by slug
    register_rest_route($namespace, '/publicaciones/(?P<slug>[a-zA-Z0-9-]+)', array(
        'methods'  =>  'GET',
        'callback' => 'motorlan_get_publicacion_by_slug',
        'permission_callback' => '__return_true',
    ) );

    // Route for deleting a publicacion by ID
    register_rest_route($namespace, '/publicaciones/(?P<id>\\d+)', array(
        'methods' => 'DELETE',
        'callback' => 'motorlan_delete_publicacion',
        'permission_callback' => function () {
            return current_user_can('delete_posts');
        }
    ));

    // Route for duplicating a publicacion by ID
    register_rest_route($namespace, '/publicaciones/duplicate/(?P<id>\\d+)', array(
        'methods' => 'GET',
        'callback' => 'motorlan_duplicate_publicacion',
    ));

    // Route for updating publicacion status by ID
    register_rest_route($namespace, '/publicaciones/(?P<id>\\d+)/status', array(
        'methods' => 'POST',
        'callback' => 'motorlan_update_publicacion_status',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));

    // Route for getting publicacion categories
    register_rest_route( $namespace, '/publicacion-categories', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_publicacion_categories_callback',
        'permission_callback' => '__return_true',
    ) );

    // Route for getting publicacion tipos
    register_rest_route( $namespace, '/tipos', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_tipos_callback',
        'permission_callback' => '__return_true',
    ) );

    // Route for getting brands
    register_rest_route( $namespace, '/marcas', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_marcas_callback',
        'permission_callback' => '__return_true',
    ) );
}
add_action( 'rest_api_init', 'motorlan_register_publicaciones_rest_routes' );


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
        'precio_negociable', 'motor_image', 'motor_gallery', 'informe_de_reparacion', 'stock', 'documentacion_adicional'
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

    // Update ACF fields
    if (isset($params['acf']) && is_array($params['acf'])) {
        foreach ($params['acf'] as $key => $value) {
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
    if ( ! empty( $params['s'] ) ) {
        $args['s'] = sanitize_text_field( $params['s'] );
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
