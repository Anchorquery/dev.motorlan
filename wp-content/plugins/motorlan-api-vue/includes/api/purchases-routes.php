<?php
/**
 * Setup for Purchases, Questions, Reviews, and Favorites REST API Routes.
 *
 * @package motorlan-api-vue
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Register custom REST API routes for user-related data.
 */
function motorlan_register_user_data_rest_routes() {
    $namespace = 'motorlan/v1';

    // Route for getting a list of purchases for the current user
    register_rest_route( $namespace, '/purchases', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_user_posts_callback',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'args' => [ 'post_type' => [ 'default' => 'purchase' ] ],
    ) );

    // Route for getting a list of questions for the current user
    register_rest_route( $namespace, '/questions', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_user_posts_callback',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'args' => [ 'post_type' => [ 'default' => 'question' ] ],
    ) );

    // Route for getting a list of reviews for the current user
    register_rest_route( $namespace, '/reviews', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_user_posts_callback',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'args' => [ 'post_type' => [ 'default' => 'review' ] ],
    ) );

    // Route for getting a list of favorites for the current user
    register_rest_route( $namespace, '/favorites', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_user_posts_callback',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'args' => [ 'post_type' => [ 'default' => 'favorite' ] ],
    ) );
}
add_action( 'rest_api_init', 'motorlan_register_user_data_rest_routes' );

/**
 * Generic callback function to get a list of posts for the current user.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response The response object.
 */
function motorlan_get_user_posts_callback( $request ) {
    $post_type = $request->get_param('post_type');
    $user_id = get_current_user_id();

    if ( ! $user_id ) {
        return new WP_Error( 'not_logged_in', 'User is not logged in.', array( 'status' => 401 ) );
    }

    $args = array(
        'post_type'      => $post_type,
        'author'         => $user_id,
        'posts_per_page' => -1, // Get all posts
        'post_status'    => 'publish',
    );

    $query = new WP_Query( $args );
    $posts_data = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();

            $post_item = array(
                'id'      => $post_id,
                'title'   => get_the_title(),
                'content' => get_the_content(),
                'date'    => get_the_date(),
                'acf'     => function_exists('get_fields') ? get_fields($post_id) : [],
            );

            $posts_data[] = $post_item;
        }
        wp_reset_postdata();
    }

    $response = new WP_REST_Response( $posts_data, 200 );

    return $response;
}
