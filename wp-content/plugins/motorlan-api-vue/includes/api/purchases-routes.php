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

    $post_types = ['purchases', 'questions', 'reviews', 'favorites'];

    foreach ($post_types as $post_type) {
        register_rest_route($namespace, "/{$post_type}", array(
            'methods'  => WP_REST_Server::READABLE,
            'callback' => 'motorlan_get_user_posts_callback',
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args' => [
                'post_type' => [
                    'default' => rtrim($post_type, 's'), // e.g., 'purchases' -> 'purchase'
                ],
            ],
        ));
    }
}
add_action( 'rest_api_init', 'motorlan_register_user_data_rest_routes' );

/**
 * Generic callback function to get a list of posts for the current user,
 * with pagination, filtering, and sorting.
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

    // Get pagination parameters from the request, with defaults.
    $page = $request->get_param( 'page' ) ? absint( $request->get_param( 'page' ) ) : 1;
    $per_page = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : 10;

    // Get sorting parameters
    $orderby = $request->get_param( 'orderby' ) ? sanitize_key( $request->get_param( 'orderby' ) ) : 'date';
    $order = $request->get_param( 'order' ) ? strtoupper( sanitize_key( $request->get_param( 'order' ) ) ) : 'DESC';

    // Get search query
    $search = $request->get_param( 'search' ) ? sanitize_text_field( $request->get_param( 'search' ) ) : '';


    $args = array(
        'post_type'      => $post_type,
        'author'         => $user_id,
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'post_status'    => 'publish',
        'orderby'        => $orderby,
        'order'          => $order,
        's'              => $search,
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
                'status'  => get_post_status(),
                'acf'     => function_exists('get_fields') ? get_fields($post_id) : [],
            );

            $posts_data[] = $post_item;
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
        'data'      => $posts_data,
        'pagination' => $pagination,
    );

    $response = new WP_REST_Response( $response_data, 200 );

    return $response;
}
