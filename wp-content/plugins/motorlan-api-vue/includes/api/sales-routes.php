<?php
/**
 * REST API routes for sales.
 *
 * @package motorlan-api-vue
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

function motorlan_register_sales_rest_routes() {
    $namespace = 'motorlan/v1';

    register_rest_route( $namespace, '/user/sales', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_user_sales_callback',
        'permission_callback' => 'motorlan_is_user_authenticated',
    ) );
}
add_action( 'rest_api_init', 'motorlan_register_sales_rest_routes' );

function motorlan_get_user_sales_callback( WP_REST_Request $request ) {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return new WP_Error( 'rest_not_logged_in', 'Sorry, you are not allowed to do that.', array( 'status' => 401 ) );
    }

    $params = $request->get_params();
    $per_page = isset( $params['per_page'] ) ? (int) $params['per_page'] : 10;
    $page = isset( $params['page'] ) ? (int) $params['page'] : 1;

    $meta_query = array(
        array(
            'key'   => 'vendedor_id',
            'value' => $user_id,
            'compare' => '=',
        ),
        array(
            'key'   => 'estado',
            'value' => 'completed',
            'compare' => '=',
        ),
    );

    if ( ! empty( $params['type'] ) ) {
        $meta_query[] = array(
            'key'   => 'tipo_venta',
            'value' => $params['type'],
            'compare' => '=',
        );
    }

    $args = array(
        'post_type'      => 'compra',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'meta_query'     => $meta_query,
    );

    if ( ! empty( $params['search'] ) ) {
        $args['s'] = sanitize_text_field( $params['search'] );
    }

    $query = new WP_Query( $args );
    $sales = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $purchase_id = get_the_ID();

            $motor_post = get_field( 'motor', $purchase_id );
            $publication_id = null;
            if ( is_object( $motor_post ) && isset( $motor_post->ID ) ) {
                $publication_id = $motor_post->ID;
            } elseif ( is_numeric( $motor_post ) ) {
                $publication_id = (int) $motor_post;
            }

            $price = get_field( 'precio_compra', $purchase_id );
            if ( '' === $price ) {
                $price = get_post_meta( $purchase_id, 'precio_compra', true );
            }
            if ( '' === $price && $publication_id ) {
                $price = get_field( 'precio_de_venta', $publication_id );
            }

            $sales[] = array(
                'id'       => $purchase_id,
                'publication_title' => $publication_id ? get_the_title( $publication_id ) : get_the_title( $purchase_id ),
                'publication_slug'  => $publication_id ? get_post_field( 'post_name', $publication_id ) : '',
                'price'             => $price,
                'date'              => get_field( 'fecha_compra', $purchase_id ) ?: get_the_date( 'Y-m-d', $purchase_id ),
            );
        }
        wp_reset_postdata();
    }

    $total_sales = $query->found_posts;

    $response = array(
        'data' => $sales,
        'pagination' => array(
            'total' => (int) $total_sales,
        ),
    );

    return new WP_REST_Response( $response, 200 );
}
