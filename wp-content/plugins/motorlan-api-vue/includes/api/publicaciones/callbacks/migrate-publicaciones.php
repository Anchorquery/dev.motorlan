<?php
/**
 * Callback to migrate old post type 'publicaciones' to 'publicacion'.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Migrate publicaciones.
 *
 * Accepts:
 * - dry_run (bool) default true
 * - limit (int) default 100
 * - offset (int) default 0
 * - ids (array|string) optional list of post IDs to migrate
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function motorlan_migrate_publicaciones_callback( $request ) {
    // Basic capability check - only users able to edit posts can run this.
    if ( ! function_exists( 'current_user_can' ) || ! current_user_can( 'edit_posts' ) ) {
        return new WP_REST_Response( [ 'message' => 'Acceso denegado' ], 403 );
    }

    $dry_run = $request->get_param( 'dry_run' );
    if ( is_null( $dry_run ) ) {
        $dry_run = true;
    } else {
        $dry_run = filter_var( $dry_run, FILTER_VALIDATE_BOOLEAN );
    }

    $limit = absint( $request->get_param( 'limit' ) ) ?: 100;
    $offset = absint( $request->get_param( 'offset' ) ) ?: 0;

    $ids_param = $request->get_param( 'ids' );
    $post_ids = [];
    if ( ! empty( $ids_param ) ) {
        if ( is_array( $ids_param ) ) {
            $post_ids = array_map( 'absint', $ids_param );
        } else {
            $post_ids = array_map( 'absint', explode( ',', $ids_param ) );
        }
    }

    $query_args = [
        'post_type'      => 'publicaciones',
        'posts_per_page' => $limit,
        'offset'         => $offset,
        'post_status'    => 'any',
        'fields'         => 'ids',
    ];

    if ( ! empty( $post_ids ) ) {
        $query_args['post__in'] = $post_ids;
        $query_args['orderby'] = 'post__in';
    }

    $posts = get_posts( $query_args );

    $results = [
        'requested' => [
            'dry_run' => (bool) $dry_run,
            'limit' => $limit,
            'offset' => $offset,
            'ids' => $post_ids,
        ],
        'processed' => [],
        'count' => count( $posts ),
    ];

    foreach ( $posts as $post_id ) {
        $item = [
            'id' => $post_id,
            'title' => get_the_title( $post_id ),
            'current_post_type' => get_post_type( $post_id ),
        ];

        if ( $dry_run ) {
            $item['action'] = 'would_change_post_type_to_publicacion';
            $item['status'] = 'dry_run';
        } else {
            $updated = wp_update_post( [ 'ID' => $post_id, 'post_type' => 'publicacion' ], true );
            if ( is_wp_error( $updated ) ) {
                $item['status'] = 'error';
                $item['error'] = $updated->get_error_message();
            } else {
                $item['status'] = 'changed';
            }
        }

        $results['processed'][] = $item;
    }

    return new WP_REST_Response( $results, 200 );
}