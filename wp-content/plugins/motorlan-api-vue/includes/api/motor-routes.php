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
 * Register custom REST API routes for motors.
 */
function motorlan_register_motor_rest_routes() {
    register_rest_route( 'motorlan/v1', '/motors', array(
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

    // Define the list of fields that can be used for filtering.
    $filterable_fields = [
        'titulo_entrada', 'marca', 'tipo_o_referencia', 'potencia', 'velocidad', 'par_nominal', 'voltaje', 'intensidad',
        'pais', 'provincia', 'estado_del_articulo', 'posibilidad_de_alquiler', 'tipo_de_alimentacion',
        'servomotores', 'regulacion_electronica_drivers', 'precio_de_venta', 'precio_negociable'
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

    $args = array(
        'post_type'      => 'motor',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
    );

    // Only add meta_query if there are filters.
    if (count($meta_query) > 1) {
        $args['meta_query'] = $meta_query;
    }

    $query = new WP_Query( $args );
    $motors_data = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();

            $featured_image_url = get_the_post_thumbnail_url($post_id, 'full');

            $motor_item = array(
                'Titulo'           => get_the_title(),
                'Precio'           => get_field('precio_de_venta', $post_id),
                'imagen_destacada' => $featured_image_url ? $featured_image_url : null,
                'marca'            => get_field('marca', $post_id),
                'referencia'       => get_field('tipo_o_referencia', $post_id),
                'precio_de_venta'  => get_field('precio_de_venta', $post_id),
                'status'           => get_post_status( $post_id ),
            );

            $motors_data[] = $motor_item;
        }
        wp_reset_postdata();
    }

    // Create the response object.
    $response = new WP_REST_Response( $motors_data, 200 );

    // Add pagination headers for client-side rendering.
    $response->header( 'X-WP-Total', $query->found_posts );
    $response->header( 'X-WP-TotalPages', $query->max_num_pages );

    return $response;
}
