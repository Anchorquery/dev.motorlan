<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'motorlan/v1', '/notifications', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'motorlan_get_notifications',
        'permission_callback' => 'motorlan_is_user_authenticated',
        'args'                => [
            'page'     => [
                'validate_callback' => 'is_numeric',
                'sanitize_callback' => 'absint',
                'default'           => 1,
            ],
            'per_page' => [
                'validate_callback' => 'is_numeric',
                'sanitize_callback' => 'absint',
                'default'           => 20,
            ],
            'status'   => [
                'validate_callback' => function( $param ) {
                    return in_array( $param, [ 'all', 'read', 'unread' ] );
                },
                'sanitize_callback' => 'sanitize_key',
                'default'           => 'all',
            ],
        ],
    ] );

    register_rest_route( 'motorlan/v1', '/notifications/unread-count', [
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'motorlan_get_notifications_unread_count',
        'permission_callback' => 'motorlan_is_user_authenticated',
    ] );

    register_rest_route( 'motorlan/v1', '/notifications/read', [

        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => 'motorlan_mark_notifications_as_read',
        'permission_callback' => 'motorlan_is_user_authenticated',
        'args'                => [
            'notification_ids' => [
                'required'          => true,
                'validate_callback' => function( $param ) {
                    return is_array( $param ) && count( $param ) > 0;
                },
                'sanitize_callback' => function( $param ) {
                    return array_map( 'absint', $param );
                },
            ],
        ],
    ] );
} );

/**
 * Callback to get user notifications.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function motorlan_get_notifications( WP_REST_Request $request ) {
    $user_id = get_current_user_id();
    $args = [
        'page'     => $request['page'],
        'per_page' => $request['per_page'],
        'status'   => $request['status'],
    ];

    $notification_manager = new Motorlan_Notification_Manager();
    $notifications = $notification_manager->get_user_notifications( $user_id, $args );

    // Here we would map the database fields to the structure expected by the frontend.
    $formatted_notifications = array_map( 'motorlan_format_notification_for_api', $notifications );

    return new WP_REST_Response( $formatted_notifications, 200 );
}

/**
 * Callback to get unread notifications count.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function motorlan_get_notifications_unread_count( WP_REST_Request $request ) {
    $user_id = get_current_user_id();
    
    $notification_manager = new Motorlan_Notification_Manager();
    $count = $notification_manager->get_unread_count( $user_id );

    return new WP_REST_Response( [ 'count' => $count ], 200 );
}

/**
 * Callback to mark notifications as read.

 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function motorlan_mark_notifications_as_read( WP_REST_Request $request ) {
    $user_id = get_current_user_id();
    $notification_ids = $request['notification_ids'];

    $notification_manager = new Motorlan_Notification_Manager();
    $success = $notification_manager->mark_as_read( $notification_ids, $user_id );

    if ( ! $success ) {
        return new WP_Error( 'update_failed', 'Could not mark notifications as read.', [ 'status' => 500 ] );
    }

    return new WP_REST_Response( [ 'success' => true ], 200 );
}

/**
 * Formats a notification from the database to match the frontend's expected structure.
 *
 * @param array $notification
 * @return array
 */
function motorlan_format_notification_for_api( $notification ) {
    return [
        'id'       => (int) $notification['id'],
        'title'    => $notification['title'],
        'subtitle' => $notification['message'],
        'time'     => ( ! empty( $notification['created_at'] ) && strtotime( $notification['created_at'] ) ) ? human_time_diff( strtotime( $notification['created_at'] ), current_time( 'timestamp' ) ) . ' ago' : '',
        'isSeen'   => (bool) $notification['is_read'],
        'icon'     => 'tabler-bell', // Default icon, can be customized based on type
        'color'    => 'primary',     // Default color
        'data'     => $notification['data'] ?? null,
    ];
}
