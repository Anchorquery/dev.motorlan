<?php
/**
 * REST API routes for offers.
 *
 * @package motorlan-api-vue
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

function motorlan_register_offer_rest_routes() {
    $namespace = 'motorlan/v1';

    register_rest_route( $namespace, '/publicaciones/(?P<publicacion_id>\d+)/offers', array(
        array(
            'methods'  => WP_REST_Server::READABLE,
            'callback' => 'motorlan_get_offer_callback',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ),
        array(
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => 'motorlan_create_offer_callback',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ),
    ) );

    register_rest_route( $namespace, '/offers/(?P<id>\d+)', array(
        'methods'  => WP_REST_Server::DELETABLE,
        'callback' => 'motorlan_delete_offer_callback',
        'permission_callback' => function ( WP_REST_Request $request ) {
            $offer_id = (int) $request['id'];
            $author   = (int) get_field( 'usuario', $offer_id );
            return get_current_user_id() === $author || current_user_can( 'delete_posts' );
        },
    ) );
}
add_action( 'rest_api_init', 'motorlan_register_offer_rest_routes' );

function motorlan_get_offer_callback( WP_REST_Request $request ) {
    $publicacion_id = (int) $request['publicacion_id'];
    $user_id  = get_current_user_id();

    $args = array(
        'post_type'      => 'oferta',
        'posts_per_page' => 1,
        'meta_query'     => array(
            array(
                'key'   => 'publicacion',
                'value' => $publicacion_id,
            ),
            array(
                'key'   => 'usuario',
                'value' => $user_id,
            ),
        ),
    );

    $query = new WP_Query( $args );
    if ( $query->have_posts() ) {
        $query->the_post();
        $id  = get_the_ID();
        $monto = get_field( 'monto', $id );
        $just = get_field( 'justificacion', $id );
        wp_reset_postdata();
        return array(
            'id'           => $id,
            'monto'        => $monto,
            'justificacion'=> $just,
        );
    }

    return array();
}

function motorlan_create_offer_callback( WP_REST_Request $request ) {
    $publicacion_id     = (int) $request['publicacion_id'];
    $user_id      = get_current_user_id();
    $monto        = floatval( $request->get_param( 'monto' ) );
    $justificacion= sanitize_text_field( $request->get_param( 'justificacion' ) );

    $existing = motorlan_get_offer_callback( $request );
    if ( ! empty( $existing ) ) {
        $offer_id = $existing['id'];
    } else {
        $offer_id = wp_insert_post( array(
            'post_type'   => 'oferta',
            'post_status' => 'publish',
            'post_title'  => 'Oferta de ' . $user_id,
        ) );
    }

    if ( is_wp_error( $offer_id ) ) {
        return new WP_Error( 'cannot_create', 'Cannot create offer', array( 'status' => 500 ) );
    }

    update_field( 'usuario', $user_id, $offer_id );
    update_field( 'publicacion', $publicacion_id, $offer_id );
    update_field( 'monto', $monto, $offer_id );
    update_field( 'justificacion', $justificacion, $offer_id );

    return array(
        'id'           => $offer_id,
        'monto'        => $monto,
        'justificacion'=> $justificacion,
    );
}

function motorlan_delete_offer_callback( WP_REST_Request $request ) {
    $offer_id = (int) $request['id'];
    wp_delete_post( $offer_id, true );
    return array( 'success' => true );
}
