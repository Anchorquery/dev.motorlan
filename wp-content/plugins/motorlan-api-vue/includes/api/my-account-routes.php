<?php
/**
 * Setup for My Account REST API Routes.
 *
 * @package motorlan-api-vue
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Register custom REST API routes for my account.
 */
function motorlan_register_my_account_rest_routes() {
    $namespace = 'motorlan/v1';

    // Route for getting current user's purchases
    register_rest_route( $namespace, '/my-account/purchases', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_my_purchases_callback',
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ) );

    // Route for getting current user's questions
    register_rest_route( $namespace, '/my-account/questions', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_my_questions_callback',
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ) );

    // Route for getting current user's opinions
    register_rest_route( $namespace, '/my-account/opinions', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_my_opinions_callback',
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ) );

    // Route for getting current user's favorites
    register_rest_route( $namespace, '/my-account/favorites', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_my_favorites_callback',
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ) );
}
add_action( 'rest_api_init', 'motorlan_register_my_account_rest_routes' );

/**
 * Callback function to get a list of current user's purchases.
 */
function motorlan_get_my_purchases_callback( $request ) {
    $user_id = get_current_user_id();
    $page = $request->get_param( 'page' ) ? absint( $request->get_param( 'page' ) ) : 1;
    $per_page = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : 10;
    $search = $request->get_param( 'search' );

    $args = array(
        'post_type'      => 'compra',
        's'              => $search,
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'meta_query' => array(
            array(
                'key'     => 'usuario',
                'value'   => $user_id,
                'compare' => '=',
            ),
        ),
    );

    $query = new WP_Query( $args );
    $data = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();

            $motor_post = get_field('motor', $post_id);
            $motor_data = null;
            if ($motor_post) {
                $motor_data = motorlan_get_motor_data($motor_post->ID);
            }

            $data[] = array(
                'id'           => $post_id,
                'title'        => get_the_title(),
                'fecha_compra' => get_field('fecha_compra', $post_id),
                'motor'        => $motor_data,
            );
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
        'data'      => $data,
        'pagination' => $pagination,
    );

    return new WP_REST_Response( $response_data, 200 );
}

/**
 * Callback function to get a list of current user's questions.
 */
function motorlan_get_my_questions_callback( $request ) {
    $user_id = get_current_user_id();
    $page = $request->get_param( 'page' ) ? absint( $request->get_param( 'page' ) ) : 1;
    $per_page = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : 10;
    $search = $request->get_param( 'search' );

    $args = array(
        'post_type'      => 'pregunta',
        's'              => $search,
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'meta_query' => array(
            array(
                'key'     => 'usuario',
                'value'   => $user_id,
                'compare' => '=',
            ),
        ),
    );

    $query = new WP_Query( $args );
    $data = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();

            $motor_post = get_field('motor', $post_id);
            $motor_data = null;
            if ($motor_post) {
                $motor_data = motorlan_get_motor_data($motor_post->ID);
            }

            $data[] = array(
                'id'        => $post_id,
                'title'     => get_the_title(),
                'pregunta'  => get_field('pregunta', $post_id),
                'respuesta' => get_field('respuesta', $post_id),
                'motor'     => $motor_data,
            );
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
        'data'      => $data,
        'pagination' => $pagination,
    );

    return new WP_REST_Response( $response_data, 200 );
}

/**
 * Callback function to get a list of current user's opinions.
 */
function motorlan_get_my_opinions_callback( $request ) {
    $user_id = get_current_user_id();
    $page = $request->get_param( 'page' ) ? absint( $request->get_param( 'page' ) ) : 1;
    $per_page = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : 10;
    $search = $request->get_param( 'search' );

    $args = array(
        'post_type'      => 'opinion',
        's'              => $search,
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'meta_query' => array(
            array(
                'key'     => 'usuario',
                'value'   => $user_id,
                'compare' => '=',
            ),
        ),
    );

    $query = new WP_Query( $args );
    $data = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();

            $motor_post = get_field('motor', $post_id);
            $motor_data = null;
            if ($motor_post) {
                $motor_data = motorlan_get_motor_data($motor_post->ID);
            }

            $data[] = array(
                'id'         => $post_id,
                'title'      => get_the_title(),
                'valoracion' => get_field('valoracion', $post_id),
                'comentario' => get_field('comentario', $post_id),
                'motor'      => $motor_data,
            );
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
        'data'      => $data,
        'pagination' => $pagination,
    );

    return new WP_REST_Response( $response_data, 200 );
}

/**
 * Callback function to get a list of current user's favorites.
 */
function motorlan_get_my_favorites_callback( $request ) {
    $user_id = get_current_user_id();
    $page = $request->get_param( 'page' ) ? absint( $request->get_param( 'page' ) ) : 1;
    $per_page = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : 10;

    $favorite_ids = get_user_meta( $user_id, 'favorite_motors', true );

    if ( empty( $favorite_ids ) ) {
        return new WP_REST_Response( array( 'data' => [], 'pagination' => [ 'total' => 0 ] ), 200 );
    }

    $args = array(
        'post_type'      => 'motor',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'post__in'       => $favorite_ids,
    );

    $query = new WP_Query( $args );
    $data = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();
            $data[] = motorlan_get_motor_data( $post_id );
        }
        wp_reset_postdata();
    }

    $total_favorites = count($favorite_ids);
    $max_num_pages = ceil($total_favorites / $per_page);

    $pagination = array(
        'total'     => (int) $total_favorites,
        'totalPages' => (int) $max_num_pages,
        'currentPage'    => (int) $page,
        'perPage'   => (int) $per_page,
    );

    $response_data = array(
        'data'      => $data,
        'pagination' => $pagination,
    );

    return new WP_REST_Response( $response_data, 200 );
}
