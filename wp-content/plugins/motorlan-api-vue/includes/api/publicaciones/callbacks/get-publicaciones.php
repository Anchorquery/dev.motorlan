<?php
/**
 * Callback to get a list of publicaciones with pagination and filtering.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Handle the request to get a list of publications.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function motorlan_get_publicaciones_callback($request) {
    $args = motorlan_build_publicaciones_query_args($request->get_params());
    $query = new WP_Query($args);

    $publicaciones_data = [];
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $publicaciones_data[] = motorlan_get_publicacion_data(get_the_ID());
        }
        wp_reset_postdata();
    }

    $pagination = [
        'total'       => (int) $query->found_posts,
        'totalPages'  => (int) $query->max_num_pages,
        'currentPage' => (int) $args['paged'],
        'perPage'     => (int) $args['posts_per_page'],
    ];

    $response_data = [
        'data'       => $publicaciones_data,
        'pagination' => $pagination,
    ];

    $response = new WP_REST_Response($response_data, 200);
    $response->header('X-WP-Total', $query->found_posts);
    $response->header('X-WP-TotalPages', $query->max_num_pages);

    return $response;
}