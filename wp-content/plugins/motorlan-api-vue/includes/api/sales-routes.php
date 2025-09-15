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

    $args = array(
        'post_type'      => 'publicacion',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'author'         => $user_id,
        'post_status'    => 'sold',
    );

    if ( ! empty( $params['search'] ) ) {
        $args['s'] = sanitize_text_field( $params['search'] );
    }

    if ( ! empty( $params['type'] ) ) {
        $args['meta_query'][] = array(
            'key'   => 'posibilidad_de_alquiler',
            'value' => $params['type'] === 'rent' ? 'yes' : 'no',
        );
    }

    $query = new WP_Query( $args );
    $sales = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $pid = get_the_ID();
            $sales[] = array(
                'id'       => $pid,
                'publication_title' => get_the_title( $pid ),
                'publication_slug' => get_post_field( 'post_name', $pid ),
                'price' => get_field( 'precio_de_venta', $pid ),
                'date' => get_the_date( 'Y-m-d', $pid ),
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