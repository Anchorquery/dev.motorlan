<?php
/**
require_once plugin_dir_path(__FILE__) . 'motor-helpers.php';
 * Setup for My Account REST API Routes.
 *
 * @package motorlan-api-vue
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

if ( ! function_exists( 'motorlan_get_motor_data' ) ) {
    require_once MOTORLAN_API_VUE_PATH . 'includes/api/motor-helpers.php';
}

/**
 * Register custom REST API routes for purchases.
 */
function motorlan_register_purchases_rest_routes() {
    $namespace = 'motorlan/v1';

    // Route for getting current user's purchases
    register_rest_route( $namespace, '/purchases/purchases', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_my_purchases_callback',
        'permission_callback' => 'motorlan_is_user_authenticated'
    ) );

    // Route for getting current user's questions
    register_rest_route( $namespace, '/purchases/questions', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_my_questions_callback',
        'permission_callback' => 'motorlan_is_user_authenticated'
    ) );

    // Route for getting current user's opinions
    register_rest_route( $namespace, '/purchases/opinions', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_my_opinions_callback',
        'permission_callback' => 'motorlan_is_user_authenticated'
    ) );

    // Route for getting current user's favorites
    register_rest_route( $namespace, '/purchases/favorites', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_my_favorites_callback',
        'permission_callback' => 'motorlan_is_user_authenticated'
    ) );

    // Route for creating a new purchase
    register_rest_route( $namespace, '/purchases', array(
        'methods'  => WP_REST_Server::CREATABLE,
        'callback' => 'motorlan_create_purchase_callback',
        'permission_callback' => 'motorlan_is_user_authenticated'
    ) );

    // Route for getting purchase details
    register_rest_route( $namespace, '/purchases/(?P<uuid>[\\w-]+)', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_purchase_callback',
        'permission_callback' => 'motorlan_is_user_authenticated'
    ) );

    // Route for adding an opinion to a purchase
    register_rest_route( $namespace, '/purchases/(?P<uuid>[\\w-]+)/opinion', array(
        'methods'  => WP_REST_Server::CREATABLE,
        'callback' => 'motorlan_add_purchase_opinion_callback',
        'permission_callback' => 'motorlan_is_user_authenticated'
    ) );

    // Route for removing a favorite motor
    register_rest_route( $namespace, '/purchases/favorites/(?P<motor_id>\\d+)', array(
        'methods'  => WP_REST_Server::DELETABLE,
        'callback' => 'motorlan_remove_my_favorite_callback',
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ) );
}
add_action( 'rest_api_init', 'motorlan_register_purchases_rest_routes' );

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
                'key'     => 'comprador',
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

            $motor_id = get_post_meta($post_id, 'motor', true);
            $motor_data = null;
            if ($motor_id) {
                $motor_data = motorlan_get_motor_data( (int) $motor_id );
            }

            $data[] = array(
                'uuid'         => get_field('uuid', $post_id),
                'title'        => get_the_title(),
                'fecha_compra' => get_field('fecha_compra', $post_id),
                'motor'        => $motor_data,
                'vendedor'     => get_field('vendedor', $post_id) ?: get_post_meta($post_id, 'vendedor_id', true),
                'comprador'    => get_field('comprador', $post_id) ?: get_post_meta($post_id, 'comprador_id', true),
                'estado'       => get_field('estado', $post_id) ?: get_post_meta($post_id, 'estado', true),
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
                $motor_data = motorlan_get_motor_data( $motor_post );
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
                $motor_data = motorlan_get_motor_data( $motor_post );
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

/**
 * Create a purchase for the current user.
 */
function motorlan_create_purchase_callback( WP_REST_Request $request ) {
    $user_id  = get_current_user_id();
    $motor_id = absint( $request->get_param( 'motor_id' ) );

    if ( ! $motor_id ) {
        return new WP_Error( 'no_motor', 'Motor ID is required', array( 'status' => 400 ) );
    }
    $uuid = wp_generate_uuid4();

    $motor_title = get_the_title( $motor_id );
    $buyer       = get_userdata( $user_id );
    $buyer_name  = $buyer ? $buyer->display_name : '';

    $purchase_id = wp_insert_post( array(
        'post_type'   => 'compra',
        'post_status' => 'publish',
        'post_name'  => 'Compra ' . $motor_title,
        'post_title'  => $motor_title . ' - ' . $buyer_name,
    ) );

    if ( is_wp_error( $purchase_id ) ) {
        return $purchase_id;
    }

    $seller_id = get_post_field( 'post_author', $motor_id );

    update_field( 'uuid', $uuid, $purchase_id );
    update_field( 'motor', $motor_id, $purchase_id );
    update_field( 'vendedor', $seller_id, $purchase_id );
    update_field( 'comprador', $user_id, $purchase_id );
    update_field( 'estado', 'pendiente', $purchase_id );
    update_field( 'fecha_compra', current_time( 'd/m/Y' ), $purchase_id );

    return new WP_REST_Response( array( 'uuid' => $uuid ), 201 );
}

/**
 * Get details for a single purchase.
 */
function motorlan_get_purchase_callback( WP_REST_Request $request ) {
    $uuid = sanitize_text_field( $request['uuid'] );
    $purchases = get_posts( array(
        'post_type'      => 'compra',
        'meta_key'       => 'uuid',
        'meta_value'     => $uuid,
        'posts_per_page' => 1,
    ) );

    if ( empty( $purchases ) ) {
        return new WP_Error( 'not_found', 'Purchase not found', array( 'status' => 404 ) );
    }

    $purchase_id = $purchases[0]->ID;


    $motor_post = get_field( 'motor', $purchase_id );
    $motor_data = null;
    if ( $motor_post ) {
        $motor_data = motorlan_get_motor_data( $motor_post );
    }

    $data = array(
        'uuid'         => $uuid,
        'title'        => get_the_title( $purchase_id ),
        'fecha_compra' => get_field( 'fecha_compra', $purchase_id ),
        'motor'        => $motor_data,
        'vendedor'     => get_field( 'vendedor', $purchase_id ),
        'comprador'    => get_field( 'comprador', $purchase_id ),
        'estado'       => get_field( 'estado', $purchase_id ),
    );

    return new WP_REST_Response( array( 'data' => $data ), 200 );
}

/**
 * Add an opinion for a purchase.
 */
function motorlan_add_purchase_opinion_callback( WP_REST_Request $request ) {
    $uuid       = sanitize_text_field( $request['uuid'] );
    $valoracion = absint( $request->get_param( 'valoracion' ) );
    $comentario = sanitize_textarea_field( $request->get_param( 'comentario' ) );

    $purchases = get_posts( array(
        'post_type'      => 'compra',
        'meta_key'       => 'uuid',
        'meta_value'     => $uuid,
        'posts_per_page' => 1,
    ) );

    if ( empty( $purchases ) ) {
        return new WP_Error( 'not_found', 'Purchase not found', array( 'status' => 404 ) );
    }

    $purchase_id = $purchases[0]->ID;
    $motor_post  = get_field( 'motor', $purchase_id );
    if ( ! $motor_post ) {
        return new WP_Error( 'no_motor', 'Purchase without motor', array( 'status' => 400 ) );
    }

    $opinion_id = wp_insert_post( array(
        'post_type'   => 'opinion',
        'post_status' => 'publish',
        'post_title'  => 'Opinion compra ' . $purchase_id,
    ) );

    if ( is_wp_error( $opinion_id ) ) {
        return $opinion_id;
    }

    update_field( 'usuario', get_current_user_id(), $opinion_id );
    update_field( 'motor', $motor_post->ID, $opinion_id );
    update_field( 'valoracion', $valoracion, $opinion_id );
    update_field( 'comentario', $comentario, $opinion_id );

    return new WP_REST_Response( array( 'id' => $opinion_id ), 201 );
}

/**
 * Callback function to remove a motor from the current user's favorites.
 */
function motorlan_remove_my_favorite_callback( WP_REST_Request $request ) {
    $user_id = get_current_user_id();
    $motor_id = absint( $request->get_param( 'motor_id' ) );

    if ( ! $motor_id ) {
        return new WP_Error( 'no_motor_id', 'Motor ID is required.', array( 'status' => 400 ) );
    }

    $favorite_ids = get_user_meta( $user_id, 'favorite_motors', true );

    if ( ! is_array( $favorite_ids ) ) {
        $favorite_ids = array();
    }

    $index = array_search( $motor_id, $favorite_ids, true );

    if ( $index !== false ) {
        unset( $favorite_ids[ $index ] );
        // Re-index the array to prevent issues with JSON encoding if it becomes an object.
        $favorite_ids = array_values( $favorite_ids );
        update_user_meta( $user_id, 'favorite_motors', $favorite_ids );
    }

    return new WP_REST_Response( array( 'success' => true ), 200 );
}
