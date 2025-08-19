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
    register_rest_route( '/wp/v2/', 'motors', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_motors_callback',
    ) );
}
add_action( 'rest_api_init', 'motorlan_register_motor_rest_routes' );

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
    );

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
                            $motor_item['acf'][$field_name] = $term->name;
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
 * Register custom REST API route for motor categories.
 */
function motorlan_register_motor_categories_rest_route() {
    register_rest_route( '/wp/v2/', 'motor-categories', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_motor_categories_callback',
    ) );
}
add_action( 'rest_api_init', 'motorlan_register_motor_categories_rest_route' );

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
