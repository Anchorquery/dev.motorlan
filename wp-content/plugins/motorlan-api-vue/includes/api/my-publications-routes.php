<?php

function get_my_publications_routes() {
    register_rest_route('motorlan/v1', '/my-publications', [
        'methods'             => 'GET',
        'callback'            => 'get_my_publications_callback',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
    ]);
}

function get_my_publications_callback($request) {
    $user_id = get_current_user_id();

    if (empty($user_id)) {
        return new WP_Error('rest_not_logged_in', 'Usuario no autenticado.', ['status' => 401]);
    }

    $args = [
        'author'         => $user_id,
        'post_type'      => 'publicacion',
        'posts_per_page' => -1,
    ];

    $query = new WP_Query($args);

    if (empty($query->posts)) {
        return new WP_REST_Response(['data' => [], 'pagination' => ['total' => 0]], 200);
    }

    $controller = new WP_REST_Posts_Controller('publicacion');
    $posts      = [];

    foreach ($query->posts as $post) {
        $data    = $controller->prepare_item_for_response($post, $request);
        $posts[] = $controller->prepare_response_for_collection($data);
    }

    $response = [
        'data'       => $posts,
        'pagination' => [
            'total' => (int) $query->found_posts,
        ],
    ];

    return new WP_REST_Response($response, 200);
}

add_action('rest_api_init', 'get_my_publications_routes');