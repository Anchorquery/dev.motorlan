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
                'validate_callback' => function($param) { return is_numeric($param); },
                'sanitize_callback' => 'absint',
                'default'           => 1,
            ],
            'per_page' => [
                'validate_callback' => function($param) { return is_numeric($param); },
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

    // "Marcar como no leídas" (la campana lo ofrecía pero no hacía nada)
    register_rest_route( 'motorlan/v1', '/notifications/unread', [
        'methods'             => WP_REST_Server::EDITABLE,
        'callback'            => 'motorlan_mark_notifications_as_unread',
        'permission_callback' => 'motorlan_is_user_authenticated',
        'args'                => [
            'notification_ids' => [
                'required'          => true,
                'validate_callback' => function( $param ) {
                    return is_array( $param ) && count( $param ) > 0 && count( $param ) <= 100;
                },
                'sanitize_callback' => function( $param ) {
                    return array_map( 'absint', $param );
                },
            ],
        ],
    ] );

    // "Eliminar" una notificación (antes solo se quitaba en el navegador y reaparecía)
    register_rest_route( 'motorlan/v1', '/notifications/(?P<id>\d+)', [
        'methods'             => WP_REST_Server::DELETABLE,
        'callback'            => 'motorlan_delete_notification',
        'permission_callback' => 'motorlan_is_user_authenticated',
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
    // Límites (page >= 1, per_page 1-50): también se aplican en get_user_notifications()
    $args = [
        'page'     => max( 1, (int) $request['page'] ),
        'per_page' => max( 1, min( 50, (int) $request['per_page'] ) ),
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
    // Validate Content-Type
    if ( function_exists( 'motorlan_validate_json_content_type' ) ) {
        $valid_type = motorlan_validate_json_content_type( $request );
        if ( is_wp_error( $valid_type ) ) {
            return $valid_type;
        }
    }

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
 * Marca notificaciones como no leídas.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function motorlan_mark_notifications_as_unread( WP_REST_Request $request ) {
    if ( function_exists( 'motorlan_validate_json_content_type' ) ) {
        $valid_type = motorlan_validate_json_content_type( $request );
        if ( is_wp_error( $valid_type ) ) {
            return $valid_type;
        }
    }

    $notification_manager = new Motorlan_Notification_Manager();
    if ( ! $notification_manager->mark_as_unread( $request['notification_ids'], get_current_user_id() ) ) {
        return new WP_Error( 'update_failed', __( 'No se pudieron actualizar las notificaciones.', 'motorlan-api-vue' ), [ 'status' => 500 ] );
    }

    return new WP_REST_Response( [ 'success' => true ], 200 );
}

/**
 * Elimina una notificación del usuario actual.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function motorlan_delete_notification( WP_REST_Request $request ) {
    $notification_manager = new Motorlan_Notification_Manager();
    if ( ! $notification_manager->delete_notifications( [ absint( $request['id'] ) ], get_current_user_id() ) ) {
        return new WP_Error( 'delete_failed', __( 'No se pudo eliminar la notificación.', 'motorlan-api-vue' ), [ 'status' => 500 ] );
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
    // created_at viene de CURRENT_TIMESTAMP (zona de MySQL): se pasa a UTC con el desfase
    // real de MySQL. Antes se comparaba con current_time() (zona de WordPress) y se
    // añadía ' ago' en inglés ("5 minutos ago").
    $created_utc = 0;
    if ( ! empty( $notification['created_at'] ) ) {
        $parsed = strtotime( $notification['created_at'] . ' UTC' );
        if ( $parsed ) {
            $created_utc = $parsed - Motorlan_Notification_Manager::get_db_utc_offset();
        }
    }

    return [
        'id'       => (int) $notification['id'],
        'title'    => $notification['title'],
        'subtitle' => $notification['message'],
        /* translators: %s: tiempo transcurrido (p. ej. "5 minutos") */
        'time'     => $created_utc ? sprintf( __( 'hace %s', 'motorlan-api-vue' ), human_time_diff( $created_utc, time() ) ) : '',
        // ISO UTC para que el front formatee la fecha relativa en el idioma del usuario
        'created_at' => $created_utc ? gmdate( 'Y-m-d\TH:i:s\Z', $created_utc ) : null,
        'isSeen'   => (bool) $notification['is_read'],
        'icon'     => 'tabler-bell', // Default icon, can be customized based on type
        'color'    => 'primary',     // Default color
        'data'     => $notification['data'] ?? null,
    ];
}
