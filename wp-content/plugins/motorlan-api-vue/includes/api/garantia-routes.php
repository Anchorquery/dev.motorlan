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
            'permission_callback' => function () {
                return current_user_can( 'edit_posts' );
            },
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
}
add_action( 'rest_api_init', 'motorlan_register_garantia_rest_routes' );

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
    update_post_meta( $garantia_id, 'motor_id', $motor_id );

    if ( isset( $params['agencia_transporte'] ) ) {
        update_post_meta( $garantia_id, 'agencia_transporte', $params['agencia_transporte'] );
    }
    if ( isset( $params['modalidad_pago'] ) ) {
        update_post_meta( $garantia_id, 'modalidad_pago', $params['modalidad_pago'] );
    }
    if ( isset( $params['direccion_motor'] ) ) {
        update_post_meta( $garantia_id, 'direccion_motor', $params['direccion_motor'] );
    }
    if ( isset( $params['cp_motor'] ) ) {
        update_post_meta( $garantia_id, 'cp_motor', $params['cp_motor'] );
    }
    if ( isset( $params['comentarios'] ) ) {
        update_post_meta( $garantia_id, 'comentarios', $params['comentarios'] );
    }

    $response_data = array(
        'message'     => 'Solicitud de garantía creada con éxito.',
        'garantia_id' => $garantia_id,
    );

    return new WP_REST_Response( $response_data, 201 );
}
