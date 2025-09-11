<?php
/**
 * Setup for Garantia REST API Routes.
 *
 * @package motorlan-api-vue
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Register custom REST API routes for garantias.
 */
function motorlan_register_garantia_rest_routes() {
    $namespace = 'motorlan/v1';

    register_rest_route( $namespace, '/garantias', array(
        array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => 'motorlan_create_garantia_item',
            'permission_callback' => 'motorlan_is_user_authenticated',
            'args' => array(
                'motor_id' => array(
                    'required' => true,
                    'validate_callback' => function( $param ) {
                        return is_numeric( $param );
                    }
                ),
                'agencia_transporte' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'modalidad_pago' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                 'direccion_motor' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'cp_motor' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_text_field',
                ),
                'comentarios' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_textarea_field',
                ),
            ),
        ),
    ) );

    register_rest_route( $namespace, '/garantias/publicacion/(?P<uuid>[a-zA-Z0-9-]+)', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'motorlan_get_garantia_by_publicacion_uuid',
        'permission_callback' => 'motorlan_permission_callback_true',
    ) );
}
add_action( 'rest_api_init', 'motorlan_register_garantia_rest_routes' );

function motorlan_get_garantia_by_publicacion_uuid( WP_REST_Request $request ) {
    $uuid = $request->get_param('uuid');
    $post_id = motorlan_get_post_id_by_uuid($uuid);

    if ( ! $post_id ) {
        return new WP_Error( 'not_found', 'Publicación no encontrada', array( 'status' => 404 ) );
    }

    $args = array(
        'post_type' => 'garantia',
        'meta_key' => 'motor_id',
        'meta_value' => $post_id,
        'posts_per_page' => 1,
        'post_status' => 'publish',
    );

    $query = new WP_Query( $args );

    if ( ! $query->have_posts() ) {
        return new WP_REST_Response( null, 200 );
    }

    $query->the_post();
    $garantia_id = get_the_ID();
    
    $garantia_data = array(
        'id' => $garantia_id,
        'garantia_status' => get_field('garantia_status', $garantia_id),
        'direccion_motor' => get_field('direccion_motor', $garantia_id),
        'cp_motor' => get_field('cp_motor', $garantia_id),
        'agencia_transporte' => get_field('agencia_transporte', $garantia_id),
        'modalidad_pago' => get_field('modalidad_pago', $garantia_id),
        'comentarios' => get_field('comentarios', $garantia_id),
    );

    wp_reset_postdata();

    return new WP_REST_Response( $garantia_data, 200 );
}

/**
 * Callback to create a new garantia item.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response|WP_Error
 */
function motorlan_create_garantia_item( WP_REST_Request $request ) {
    $params = $request->get_params();
    $motor_id = intval( $params['motor_id'] );

    // Check if motor exists
    if ( get_post_status( $motor_id ) === false ) {
        return new WP_Error( 'motor_not_found', 'El motor especificado no existe.', array( 'status' => 404 ) );
    }

    $motor_title = get_the_title( $motor_id );
    $garantia_title = 'Garantía para Motor: ' . $motor_title;

    $new_post_data = array(
        'post_title'  => $garantia_title,
        'post_status' => 'publish',
        'post_type'   => 'garantia',
        'post_author' => get_current_user_id(),
    );

    $garantia_id = wp_insert_post( $new_post_data );

    if ( is_wp_error( $garantia_id ) ) {
        return $garantia_id;
    }

    // Update meta fields
    update_field( 'motor_id', $motor_id, $garantia_id );
    update_field( 'garantia_status', 'pendiente', $garantia_id );

    if ( isset( $params['agencia_transporte'] ) ) {
        update_field( 'agencia_transporte', $params['agencia_transporte'], $garantia_id );
    }
    if ( isset( $params['modalidad_pago'] ) ) {
        update_field( 'modalidad_pago', $params['modalidad_pago'], $garantia_id );
    }
    if ( isset( $params['direccion_motor'] ) ) {
        update_field( 'direccion_motor', $params['direccion_motor'], $garantia_id );
    }
    if ( isset( $params['cp_motor'] ) ) {
        update_field( 'cp_motor', $params['cp_motor'], $garantia_id );
    }
    if ( isset( $params['comentarios'] ) ) {
        update_field( 'comentarios', $params['comentarios'], $garantia_id );
    }

    $response_data = array(
        'message'     => 'Solicitud de garantía creada con éxito.',
        'garantia_id' => $garantia_id,
    );

    return new WP_REST_Response( $response_data, 201 );
}
