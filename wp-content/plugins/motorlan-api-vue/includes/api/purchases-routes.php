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
 * Normalize different ACF return formats into a numeric user ID.
 *
 * @param mixed $user_value Value returned by ACF or stored in meta.
 *
 * @return int
 */
function motorlan_normalize_user_id( $user_value ) {
    if ( is_numeric( $user_value ) ) {
        return (int) $user_value;
    }

    if ( $user_value instanceof WP_User ) {
        return (int) $user_value->ID;
    }

    if ( is_object( $user_value ) && isset( $user_value->ID ) ) {
        return (int) $user_value->ID;
    }

    if ( is_array( $user_value ) && isset( $user_value['ID'] ) ) {
        return (int) $user_value['ID'];
    }

    return 0;
}

/**
 * Locate a purchase post by its UUID.
 *
 * @param string $uuid Purchase uuid.
 *
 * @return WP_Post|null
 */
function motorlan_find_purchase_by_uuid( $uuid ) {
    $purchases = get_posts( array(
        'post_type'      => 'compra',
        'meta_key'       => 'uuid',
        'meta_value'     => $uuid,
        'posts_per_page' => 1,
        'no_found_rows'  => true,
    ) );

    if ( empty( $purchases ) ) {
        return null;
    }

    return $purchases[0];
}

/**
 * Get normalized buyer and seller IDs for a purchase.
 *
 * @param int $purchase_id Purchase post ID.
 *
 * @return array{buyer_id:int,seller_id:int}
 */
function motorlan_get_purchase_participants( $purchase_id ) {
    $seller = get_field( 'vendedor', $purchase_id );
    if ( ! $seller ) {
        $seller = get_post_meta( $purchase_id, 'vendedor', true );
    }
    if ( ! $seller ) {
        $seller = get_post_meta( $purchase_id, 'vendedor_id', true );
    }

    $buyer = get_field( 'comprador', $purchase_id );
    if ( ! $buyer ) {
        $buyer = get_post_meta( $purchase_id, 'comprador', true );
    }
    if ( ! $buyer ) {
        $buyer = get_post_meta( $purchase_id, 'comprador_id', true );
    }

    return array(
        'seller_id' => motorlan_normalize_user_id( $seller ),
        'buyer_id'  => motorlan_normalize_user_id( $buyer ),
    );
}

/**
 * Determine whether a user can view or modify a purchase.
 *
 * @param int $purchase_id Purchase post ID.
 * @param int $user_id     Current user ID.
 *
 * @return bool
 */
function motorlan_user_can_access_purchase( $purchase_id, $user_id ) {
    if ( ! $user_id ) {
        return false;
    }

    if ( user_can( $user_id, 'manage_options' ) ) {
        return true;
    }

    $participants = motorlan_get_purchase_participants( $purchase_id );

    return in_array( $user_id, $participants, true );
}

/**
 * Resolve configuration values from constants or environment.
 *
 * @param array|string $keys    Candidate keys.
 * @param mixed        $default Default value if none found.
 *
 * @return mixed|null
 */
function motorlan_get_config_value( $keys, $default = null ) {
    foreach ( (array) $keys as $key ) {
        if ( defined( $key ) ) {
            $value = constant( $key );
            if ( $value !== '' && $value !== null ) {
                return $value;
            }
        }

        $env = getenv( $key );
        if ( false !== $env && $env !== '' ) {
            return $env;
        }
    }

    return $default;
}

/**
 * Retrieve configuration for realtime (Pusher) integration.
 *
 * @return array|null
 */
function motorlan_get_pusher_config() {
    $app_id = motorlan_get_config_value( array( 'MOTORLAN_PUSHER_APP_ID', 'PUSHER_APP_ID' ) );
    $key    = motorlan_get_config_value( array( 'MOTORLAN_PUSHER_APP_KEY', 'PUSHER_APP_KEY' ) );
    $secret = motorlan_get_config_value( array( 'MOTORLAN_PUSHER_APP_SECRET', 'PUSHER_APP_SECRET' ) );

    if ( ! $app_id || ! $key || ! $secret ) {
        return null;
    }

    $config = array(
        'app_id' => trim( (string) $app_id ),
        'key'    => trim( (string) $key ),
        'secret' => trim( (string) $secret ),
    );

    $cluster = motorlan_get_config_value( array( 'MOTORLAN_PUSHER_APP_CLUSTER', 'PUSHER_APP_CLUSTER' ) );
    if ( $cluster ) {
        $config['cluster'] = trim( (string) $cluster );
    }

    $host = motorlan_get_config_value( array( 'MOTORLAN_PUSHER_HOST', 'PUSHER_HOST' ) );
    if ( $host ) {
        $config['host'] = trim( (string) $host );
    }

    $port = motorlan_get_config_value( array( 'MOTORLAN_PUSHER_PORT', 'PUSHER_PORT' ) );
    if ( null !== $port && '' !== $port ) {
        $config['port'] = (int) $port;
    }

    $wss_port = motorlan_get_config_value( array( 'MOTORLAN_PUSHER_WSS_PORT', 'PUSHER_WSS_PORT' ) );
    if ( null !== $wss_port && '' !== $wss_port ) {
        $config['wss_port'] = (int) $wss_port;
    }

    $scheme = motorlan_get_config_value( array( 'MOTORLAN_PUSHER_SCHEME', 'PUSHER_SCHEME' ) );
    if ( $scheme ) {
        $config['scheme'] = trim( (string) $scheme );
    }

    $force_tls = motorlan_get_config_value( array( 'MOTORLAN_PUSHER_FORCE_TLS', 'PUSHER_FORCE_TLS' ) );
    if ( null !== $force_tls ) {
        $config['force_tls'] = ! in_array( strtolower( (string) $force_tls ), array( '0', 'false', 'off', 'no' ), true );
    }

    return $config;
}

/**
 * Publish an event to the purchase realtime channel.
 *
 * @param string $uuid    Purchase uuid.
 * @param string $event   Event name.
 * @param array  $payload Event payload.
 *
 * @return void
 */
function motorlan_trigger_purchase_event( $uuid, $event, array $payload ) {
    $config = motorlan_get_pusher_config();

    if ( ! $config ) {
        return;
    }

    $app_id = $config['app_id'];

    $event_body = array(
        'name'     => $event,
        'channels' => array( "private-purchase-{$uuid}" ),
        'data'     => wp_json_encode( $payload ),
    );

    $body = wp_json_encode( $event_body );
    if ( false === $body ) {
        return;
    }

    $query = array(
        'auth_key'       => $config['key'],
        'auth_timestamp' => time(),
        'auth_version'   => '1.0',
        'body_md5'       => md5( $body ),
    );

    $path            = "/apps/{$app_id}/events";
    $query_to_sign   = http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
    $string_to_sign  = "POST\n{$path}\n{$query_to_sign}";
    $query['auth_signature'] = hash_hmac( 'sha256', $string_to_sign, $config['secret'] );

    $scheme = isset( $config['scheme'] ) && $config['scheme'] ? $config['scheme'] : ( ! empty( $config['force_tls'] ) ? 'https' : 'http' );
    $host   = isset( $config['host'] ) && $config['host'] ? $config['host'] : ( ! empty( $config['cluster'] ) ? "api-{$config['cluster']}.pusher.com" : 'api.pusherapp.com' );

    $port = null;
    if ( isset( $config['port'] ) && ! empty( $config['port'] ) ) {
        $port = (int) $config['port'];
    }
    elseif ( isset( $config['wss_port'] ) && ! empty( $config['wss_port'] ) ) {
        $port = (int) $config['wss_port'];
    }

    $url = "{$scheme}://{$host}";
    if ( $port ) {
        $url .= ':' . $port;
    }
    $url .= $path . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );

    $response = wp_remote_post( $url, array(
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
        'timeout' => 5,
        'body'    => $body,
    ) );

    if ( is_wp_error( $response ) ) {
        error_log( '[motorlan] Failed to publish realtime event: ' . $response->get_error_message() );
    }
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

    // Routes for purchase messaging
    register_rest_route( $namespace, '/purchases/(?P<uuid>[\\w-]+)/messages', array(
        array(
            'methods'  => WP_REST_Server::READABLE,
            'callback' => 'motorlan_get_purchase_messages_callback',
            'permission_callback' => 'motorlan_is_user_authenticated',
        ),
        array(
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => 'motorlan_add_purchase_message_callback',
            'permission_callback' => 'motorlan_is_user_authenticated',
            'args'     => array(
                'message' => array(
                    'type'     => 'string',
                    'required' => true,
                ),
            ),
        ),
    ) );

    // Realtime authentication endpoint
    register_rest_route( $namespace, '/purchases/pusher/auth', array(
        'methods'  => WP_REST_Server::CREATABLE,
        'callback' => 'motorlan_pusher_auth_callback',
        'permission_callback' => 'motorlan_is_user_authenticated',
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

    $participants     = motorlan_get_purchase_participants( $purchase_id );
    $current_user_id  = get_current_user_id();
    $viewer_role      = ( $participants['seller_id'] === $current_user_id ) ? 'seller' : 'buyer';

    $quantity_raw = function_exists( 'get_field' ) ? get_field( 'cantidad', $purchase_id ) : get_post_meta( $purchase_id, 'cantidad', true );
    $quantity     = (int) $quantity_raw;
    if ( $quantity <= 0 ) {
        $quantity = 1;
    }

    $purchase_price_raw = function_exists( 'get_field' ) ? get_field( 'precio_compra', $purchase_id ) : get_post_meta( $purchase_id, 'precio_compra', true );
    $purchase_price     = null;
    if ( '' !== $purchase_price_raw && null !== $purchase_price_raw ) {
        $purchase_price = (float) $purchase_price_raw;
    }

    $tipo_venta = get_post_meta( $purchase_id, 'tipo_venta', true );

    $offer_id_raw = get_post_meta( $purchase_id, 'offer_id', true );
    $offer_id     = $offer_id_raw ? (int) $offer_id_raw : 0;
    $offer_data   = null;

    if ( $offer_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'motorlan_offers';
        $offer_row  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $offer_id ) );

        if ( $offer_row ) {
            if ( function_exists( 'motorlan_offers_prepare_offer_item' ) ) {
                $offer_data = motorlan_offers_prepare_offer_item( $offer_row );
            } else {
                $offer_data = array_map(
                    static function ( $value ) {
                        return $value;
                    },
                    get_object_vars( $offer_row )
                );
            }
        }
    }

    $published_price = null;
    if ( is_array( $motor_data ) && isset( $motor_data['acf']['precio_de_venta'] ) && '' !== $motor_data['acf']['precio_de_venta'] ) {
        $published_price = (float) $motor_data['acf']['precio_de_venta'];
    }

    $data = array(
        'uuid'         => $uuid,
        'title'        => get_the_title( $purchase_id ),
        'fecha_compra' => get_field( 'fecha_compra', $purchase_id ),
        'motor'        => $motor_data,
        'vendedor'     => get_field( 'vendedor', $purchase_id ),
        'comprador'    => get_field( 'comprador', $purchase_id ),
        'estado'       => get_field( 'estado', $purchase_id ),
        'vendedor_id'  => $participants['seller_id'],
        'comprador_id' => $participants['buyer_id'],
        'viewer_role'  => $viewer_role,
        'cantidad'     => $quantity,
        'precio_compra'=> $purchase_price,
        'tipo_venta'   => $tipo_venta ? $tipo_venta : 'direct',
        'offer_id'     => $offer_id,
        'offer'        => $offer_data,
        'precio_publicado' => $published_price,
    );

    return new WP_REST_Response( array( 'data' => $data ), 200 );
}

/**
 * Provide an authentication signature for realtime messaging channels.
 *
 * @param WP_REST_Request $request REST request.
 *
 * @return WP_REST_Response|WP_Error
 */
function motorlan_pusher_auth_callback( WP_REST_Request $request ) {
    $config = motorlan_get_pusher_config();

    if ( ! $config ) {
        return new WP_Error( 'pusher_not_configured', 'Realtime messaging is not available.', array( 'status' => 503 ) );
    }

    $params = $request->get_body_params();
    if ( empty( $params ) ) {
        $params = $request->get_params();
    }

    $channel_name = isset( $params['channel_name'] ) ? sanitize_text_field( wp_unslash( $params['channel_name'] ) ) : '';
    $socket_id    = isset( $params['socket_id'] ) ? sanitize_text_field( wp_unslash( $params['socket_id'] ) ) : '';

    if ( '' === $channel_name || '' === $socket_id ) {
        return new WP_Error( 'invalid_request', 'Missing authentication parameters.', array( 'status' => 400 ) );
    }

    if ( 0 !== strpos( $channel_name, 'private-purchase-' ) ) {
        return new WP_Error( 'invalid_channel', 'Channel not allowed.', array( 'status' => 403 ) );
    }

    $uuid          = substr( $channel_name, strlen( 'private-purchase-' ) );
    $purchase_post = motorlan_find_purchase_by_uuid( $uuid );

    if ( ! $purchase_post ) {
        return new WP_Error( 'not_found', 'Purchase not found.', array( 'status' => 404 ) );
    }

    $current_user_id = get_current_user_id();
    if ( ! motorlan_user_can_access_purchase( $purchase_post->ID, $current_user_id ) ) {
        return new WP_Error( 'forbidden', 'You are not allowed to subscribe to this channel.', array( 'status' => 403 ) );
    }

    $string_to_sign = $socket_id . ':' . $channel_name;
    $signature      = hash_hmac( 'sha256', $string_to_sign, $config['secret'] );

    return new WP_REST_Response( array(
        'auth' => $config['key'] . ':' . $signature,
    ), 200 );
}

/**
 * Retrieve messages for a purchase conversation.
 *
 * @param WP_REST_Request $request REST request.
 *
 * @return WP_REST_Response|WP_Error
 */
function motorlan_get_purchase_messages_callback( WP_REST_Request $request ) {
    $uuid          = sanitize_text_field( $request['uuid'] );
    $purchase_post = motorlan_find_purchase_by_uuid( $uuid );

    if ( ! $purchase_post ) {
        return new WP_Error( 'not_found', 'Purchase not found', array( 'status' => 404 ) );
    }

    $purchase_id     = $purchase_post->ID;
    $current_user_id = get_current_user_id();

    if ( ! motorlan_user_can_access_purchase( $purchase_id, $current_user_id ) ) {
        return new WP_Error( 'forbidden', 'You are not allowed to access these messages.', array( 'status' => 403 ) );
    }

    $raw_messages = get_post_meta( $purchase_id, 'purchase_messages', true );
    if ( ! is_array( $raw_messages ) ) {
        $raw_messages = array();
    }

    usort(
        $raw_messages,
        function ( $a, $b ) {
            $time_a = isset( $a['created_at'] ) ? strtotime( $a['created_at'] ) : 0;
            $time_b = isset( $b['created_at'] ) ? strtotime( $b['created_at'] ) : 0;

            if ( $time_a === $time_b ) {
                return 0;
            }

            return ( $time_a < $time_b ) ? -1 : 1;
        }
    );

    $messages = array();
    foreach ( $raw_messages as $message ) {
        $user_id      = isset( $message['user_id'] ) ? (int) $message['user_id'] : 0;
        $display_name = isset( $message['display_name'] ) ? $message['display_name'] : '';

        if ( ! $display_name && $user_id ) {
            $user         = get_user_by( 'id', $user_id );
            $display_name = $user ? $user->display_name : '';
        }

        $messages[] = array(
            'id'              => isset( $message['id'] ) ? (string) $message['id'] : uniqid( 'msg_', true ),
            'message'         => isset( $message['message'] ) ? (string) $message['message'] : '',
            'created_at'      => isset( $message['created_at'] ) ? $message['created_at'] : gmdate( 'Y-m-d H:i:s' ),
            'sender_role'     => isset( $message['sender_role'] ) ? $message['sender_role'] : 'buyer',
            'user_id'         => $user_id,
            'display_name'    => $display_name,
            'avatar'          => isset( $message['avatar'] ) ? $message['avatar'] : ( $user_id ? get_avatar_url( $user_id ) : '' ),
            'is_current_user' => ( $user_id === $current_user_id ),
        );
    }

    $participants = motorlan_get_purchase_participants( $purchase_id );
    $viewer_role  = ( $participants['seller_id'] === $current_user_id ) ? 'seller' : 'buyer';

    return new WP_REST_Response( array(
        'data' => $messages,
        'meta' => array(
            'current_user_id' => $current_user_id,
            'viewer_role'     => $viewer_role,
            'purchase_uuid'   => $uuid,
        ),
    ), 200 );
}

/**
 * Append a new message to a purchase conversation.
 *
 * @param WP_REST_Request $request REST request.
 *
 * @return WP_REST_Response|WP_Error
 */
function motorlan_add_purchase_message_callback( WP_REST_Request $request ) {
    $uuid          = sanitize_text_field( $request['uuid'] );
    $purchase_post = motorlan_find_purchase_by_uuid( $uuid );

    if ( ! $purchase_post ) {
        return new WP_Error( 'not_found', 'Purchase not found', array( 'status' => 404 ) );
    }

    $purchase_id     = $purchase_post->ID;
    $current_user_id = get_current_user_id();

    if ( ! motorlan_user_can_access_purchase( $purchase_id, $current_user_id ) ) {
        return new WP_Error( 'forbidden', 'You are not allowed to send messages for this purchase.', array( 'status' => 403 ) );
    }

    $raw_message = $request->get_param( 'message' );
    $message     = sanitize_textarea_field( wp_unslash( $raw_message ) );

    if ( '' === trim( $message ) ) {
        return new WP_Error( 'empty_message', 'Message cannot be empty.', array( 'status' => 400 ) );
    }

    if ( mb_strlen( $message ) > 1000 ) {
        return new WP_Error( 'message_too_long', 'Message is too long.', array( 'status' => 400 ) );
    }

    $participants = motorlan_get_purchase_participants( $purchase_id );
    $sender_role  = ( $participants['seller_id'] === $current_user_id ) ? 'seller' : 'buyer';

    $user         = get_user_by( 'id', $current_user_id );
    $display_name = $user ? $user->display_name : '';
    $avatar       = get_avatar_url( $current_user_id );

    $new_message = array(
        'id'           => uniqid( 'msg_', true ),
        'user_id'      => $current_user_id,
        'sender_role'  => $sender_role,
        'message'      => $message,
        'created_at'   => gmdate( 'Y-m-d H:i:s' ),
        'display_name' => $display_name,
        'avatar'       => $avatar,
    );

    $existing_messages = get_post_meta( $purchase_id, 'purchase_messages', true );
    if ( ! is_array( $existing_messages ) ) {
        $existing_messages = array();
    }

    $existing_messages[] = $new_message;

    update_post_meta( $purchase_id, 'purchase_messages', $existing_messages );

    motorlan_trigger_purchase_event(
        $uuid,
        'purchase.message',
        array(
            'message'       => $new_message,
            'purchase_uuid' => $uuid,
            'sender_role'   => $sender_role,
        )
    );

    $response_message                 = $new_message;
    $response_message['is_current_user'] = true;

    return new WP_REST_Response( array(
        'data' => $response_message,
        'meta' => array(
            'current_user_id' => $current_user_id,
            'viewer_role'     => $sender_role,
            'purchase_uuid'   => $uuid,
        ),
    ), 201 );
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
