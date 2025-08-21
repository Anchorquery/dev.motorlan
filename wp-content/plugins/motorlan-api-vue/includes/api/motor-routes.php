<?php
/**
 * Setup for Motor REST API Routes.
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
    foreach ( $terms as $term ) {
        $terms_details[] = array(
            'id'   => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
        );
    }
    return $terms_details;
}

/**
 * Register custom REST API routes for motors.
 */
function motorlan_register_motor_rest_routes() {
    $namespace = 'motorlan/v1';

    // Route for getting a list of motors
    register_rest_route( $namespace, '/motors', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_motors_callback',
        'permission_callback' => '__return_true',
    ) );

    // Route for getting and updating a single motor by UUID
    register_rest_route($namespace, '/motors/uuid/(?P<uuid>[a-zA-Z0-9-]+)', array(
        array(
            'methods' => 'GET',
            'callback' => 'motorlan_get_motor_by_uuid',
            'permission_callback' => '__return_true'
        ),
        array(
            'methods' => 'POST',
            'callback' => 'motorlan_update_motor_by_uuid',
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ),
    ));

    // Route for deleting a motor by ID
    register_rest_route($namespace, '/motors/(?P<id>\\d+)', array(
        'methods' => 'DELETE',
        'callback' => 'motorlan_delete_motor',
        'permission_callback' => function () {
            return current_user_can('delete_posts');
        }
    ));

    // Route for duplicating a motor by ID
    register_rest_route($namespace, '/motors/duplicate/(?P<id>\\d+)', array(
        'methods' => 'GET',
        'callback' => 'motorlan_duplicate_motor',
        // 'permission_callback' => function () {
        //     return current_user_can('edit_posts');
        // }
    ));

    // Route for updating motor status by ID
    register_rest_route($namespace, '/motors/(?P<id>\\d+)/status', array(
        'methods' => 'POST',
        'callback' => 'motorlan_update_motor_status',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        }
    ));

    // Route for getting motor categories
    register_rest_route( $namespace, '/motor-categories', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_motor_categories_callback',
        'permission_callback' => '__return_true',
    ) );

    // Route for getting motor brands
    register_rest_route( $namespace, '/marcas', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_motor_marcas_callback',
        'permission_callback' => '__return_true',
    ) );
}
add_action( 'rest_api_init', 'motorlan_register_motor_rest_routes' );


/**
 * Get a single motor by UUID.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error The response object or error.
 */
function motorlan_get_motor_by_uuid(WP_REST_Request $request) {
    $uuid = $request->get_param('uuid');

    if (empty($uuid)) {
        return new WP_Error('no_uuid', 'UUID not provided', array('status' => 400));
    }

    $args = array(
        'post_type' => 'motor',
        'meta_key' => 'uuid',
        'meta_value' => $uuid,
        'posts_per_page' => 1,
        'post_status' => 'any',
    );

    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        return new WP_Error('not_found', 'Motor not found', array('status' => 404));
    }

    $query->the_post();
    $post_id = get_the_ID();
    $motor_data = motorlan_get_motor_data($post_id);

    wp_reset_postdata();

    return new WP_REST_Response($motor_data, 200);
}

/**
 * Helper function to get all motor data.
 *
 * @param int $post_id The post ID.
 * @return array The motor data.
 */
function motorlan_get_motor_data($post_id) {
    $motor_item = array(
        'id'           => $post_id,
        'uuid'         => get_post_meta($post_id, 'uuid', true),
        'title'        => get_the_title($post_id),
        'slug'         => get_post_field('post_name', $post_id),
        'status'       => get_field('publicar_acf', $post_id),
        'author_id'    => get_post_field('post_author', $post_id),
        'categories'   => motorlan_get_post_taxonomy_details($post_id, 'categoria'),
        'acf'          => array(),
    );

    if (function_exists('get_fields')) {
        $fields = get_fields($post_id);
        if ($fields) {
            $motor_item['acf'] = $fields;
        }
    } else {
        $motor_item['acf_error'] = 'Advanced Custom Fields plugin is not active.';
    }

    // Ensure essential fields are present, even if they have no value, to avoid issues in the frontend
    $essential_fields = [
        'marca', 'tipo_o_referencia', 'estado_del_articulo', 'descripcion',
        'precio_de_venta', 'potencia', 'velocidad', 'par_nominal', 'voltaje',
        'intensidad', 'pais', 'provincia', 'posibilidad_de_alquiler',
        'tipo_de_alimentacion', 'servomotores', 'regulacion_electronica_drivers',
        'precio_negociable', 'motor_image', 'motor_gallery', 'informe_de_reparacion'
    ];

    foreach ($essential_fields as $field) {
        if (!isset($motor_item['acf'][$field])) {
            $motor_item['acf'][$field] = null;
        }
    }

    // For 'marca' (brand), which is a taxonomy, return the full term object
    if (!empty($motor_item['acf']['marca'])) {
        $term_id = $motor_item['acf']['marca'];
        // Check if it's an array (already processed) or a term ID
        if (is_numeric($term_id)) {
            $term = get_term($term_id, 'marca');
            if ($term && !is_wp_error($term)) {
                $motor_item['acf']['marca'] = array(
                    'id' => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                );
            }
        }
    }

    return $motor_item;
}


/**
 * Update a motor by UUID.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error The response object or error.
 */
function motorlan_update_motor_by_uuid(WP_REST_Request $request) {
    $uuid = $request->get_param('uuid');
    if (empty($uuid)) {
        return new WP_Error('no_uuid', 'UUID not provided', array('status' => 400));
    }

    $args = array(
        'post_type' => 'motor',
        'meta_key' => 'uuid',
        'meta_value' => $uuid,
        'posts_per_page' => 1,
        'post_status' => 'any',
    );
    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        return new WP_Error('not_found', 'Motor not found', array('status' => 404));
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

    // Update ACF fields
    if (isset($params['acf']) && is_array($params['acf'])) {
        foreach ($params['acf'] as $key => $value) {
            // A basic sanitization, you might need more specific sanitization based on the field type
            if (is_string($value)) {
                $value = sanitize_text_field($value);
            }
            update_field($key, $value, $post_id);
        }
    }

    return new WP_REST_Response(array('message' => 'Motor updated successfully'), 200);
}


/**
 * Delete a motor by ID.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error The response object or error.
 */
function motorlan_delete_motor(WP_REST_Request $request) {
    $post_id = $request->get_param('id');
    $result = wp_delete_post($post_id, true); // true to force delete

    if ($result === false) {
        return new WP_Error('delete_failed', 'Failed to delete motor', array('status' => 500));
    }

    return new WP_REST_Response(array('message' => 'Motor deleted successfully'), 200);
}


/**
 * Duplicate a motor by ID.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error The response object or error.
 */
function motorlan_duplicate_motor(WP_REST_Request $request) {
    $original_post_id = $request->get_param('id');
    $original_post = get_post($original_post_id);

    if (!$original_post) {
        return new WP_Error('not_found', 'Original motor not found', array('status' => 404));
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


    return new WP_REST_Response(array('message' => 'Motor duplicated successfully', 'new_post_id' => $new_post_id), 200);
}


/**
 * Update motor status by ID.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error The response object or error.
 */
function motorlan_update_motor_status(WP_REST_Request $request) {
    $post_id = $request->get_param('id');
    $params = $request->get_json_params();
    $new_status = isset($params['status']) ? sanitize_text_field($params['status']) : '';

    if (empty($new_status)) {
        return new WP_Error('no_status', 'Status not provided', array('status' => 400));
    }

    update_field('publicar_acf', $new_status, $post_id);

    return new WP_REST_Response(array('message' => 'Motor status updated successfully'), 200);
}

/**
 * Callback function to get a list of motors with pagination and filtering, using ACF.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response The response object.
 */
function motorlan_get_motors_callback( $request ) {
    // Get pagination parameters from the request, with defaults.
    $page = $request->get_param( 'page' ) ? absint( $request->get_param( 'page' ) ) : 1;
    $per_page = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : 10;

    // --- FILTERING LOGIC ---
    $params = $request->get_params();
    $meta_query = array('relation' => 'AND');
    $tax_query = array();

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
                'compare' => '=',
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
        $tax_query[] = array(
            'taxonomy' => 'categoria',
            'field'    => 'slug',
            'terms'    => sanitize_text_field($params['category']),
        );
    }

    $args = array(
        'post_type'      => 'motor',
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
    if (!empty($tax_query)) {
        $args['tax_query'] = $tax_query;
    }

    $query = new WP_Query( $args );
    $motors_data = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();

            $motor_item = array(
                'id'           => $post_id,
                'uuid'         => get_post_meta( $post_id, 'uuid', true ),
                'title'        => get_the_title(),
                'slug'         => get_post_field( 'post_name', $post_id ),
                'status'       => get_field('publicar_acf', $post_id),
                // saco de acf motor_image
                'imagen_destacada' =>  get_field('motor_image', $post_id ,true ) ,
                'author_id'    => get_post_field( 'post_author', $post_id ),
                'categories'   => motorlan_get_post_taxonomy_details( $post_id, 'categoria' ),

                'acf'          => array(),
            );

            // Populate ACF fields if ACF is active
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
                            $motor_item['acf'][$field_name] = array(
                                'id' => $term->term_id,
                                'name' => $term->name
                            );
                            

                        } else {
                            $motor_item['acf'][$field_name] = null;
                        }
                    } else {
                        $motor_item['acf'][$field_name] = $value;
                    }
                }
            } else {
                 $motor_item['acf_error'] = 'Advanced Custom Fields plugin is not active.';
            }

            $motors_data[] = $motor_item;
        }
        wp_reset_postdata();
    }

    // Pagination data.
    $pagination = array(
        'total'     => (int) $query->found_posts,
        'totalPages' => (int) $query->max_num_pages,
        'currentPage'    => (int) $page,
        'perPage'   => (int) $per_page,
    );

    // Prepare the data for the response.
    $response_data = array(
        'data'      => $motors_data,
        'pagination' => $pagination,
    );

    // Create the response object.
    $response = new WP_REST_Response( $response_data, 200 );

    // Add pagination headers for client-side rendering (optional, but good practice).
    $response->header( 'X-WP-Total', $query->found_posts );
    $response->header( 'X-WP-TotalPages', $query->max_num_pages );

    return $response;
}

/**
 * Callback function to get a list of motor categories.
 *
 * @return WP_REST_Response The response object.
 */
function motorlan_get_motor_categories_callback() {
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
 * Callback function to get a list of motor brands.
 *
 * @return WP_REST_Response The response object.
 */
function motorlan_get_motor_marcas_callback() {
    $terms = get_terms( array(
        'taxonomy'   => 'marca',
        'hide_empty' => false,
    ) );

    if ( is_wp_error( $terms ) ) {
        return new WP_REST_Response( array( 'message' => $terms->get_error_message() ), 500 );
    }

    return new WP_REST_Response( $terms, 200 );
}
