<?php
/**
 * Callback to refresh slugs for all 'publicacion' posts.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Refresh slugs for publicaciones.
 *
 * Accepts:
 * - dry_run (bool) default true
 * - limit (int) default 100
 * - offset (int) default 0
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function motorlan_refresh_slugs_callback( $request ) {
    // TEMPORARY: Public access enabled by user request. 
    // Usually should be restricted to 'edit_posts'.

    $dry_run = $request->get_param( 'dry_run' );
    $dry_run = is_null( $dry_run ) ? true : filter_var( $dry_run, FILTER_VALIDATE_BOOLEAN );
    
    $limit_param = $request->get_param( 'limit' );
    $limit = is_null( $limit_param ) ? 100 : (int) $limit_param;
    $offset = absint( $request->get_param( 'offset' ) ) ?: 0;

    $query_args = [
        'post_type'      => 'publicacion',
        'posts_per_page' => $limit,
        'offset'         => $offset,
        'post_status'    => 'any',
        'fields'         => 'ids',
    ];

    $posts = get_posts( $query_args );
    $processed = [];

    foreach ( $posts as $post_id ) {
        $old_slug = get_post_field( 'post_name', $post_id );
        $new_slug = motorlan_generate_slug_by_post_id( $post_id );

        $item = [
            'id' => $post_id,
            'title' => get_the_title( $post_id ),
            'old_slug' => $old_slug,
            'new_slug' => $new_slug,
        ];

        if ( $old_slug !== $new_slug ) {
            if ( ! $dry_run ) {
                wp_update_post( [
                    'ID' => $post_id,
                    'post_name' => $new_slug
                ] );
                $item['status'] = 'updated';
            } else {
                $item['status'] = 'would_update';
            }
        } else {
            $item['status'] = 'no_change';
        }

        $processed[] = $item;
    }

    $total_posts = wp_count_posts( 'publicacion' )->publish + wp_count_posts( 'publicacion' )->draft + wp_count_posts( 'publicacion' )->pending;

    return new WP_REST_Response( [
        'dry_run' => $dry_run,
        'count' => count( $processed ),
        'limit' => $limit,
        'offset' => $offset,
        'total_publications' => (int) $total_posts,
        'processed' => $processed
    ], 200 );
}
