<?php
/**
 * Setup for My Account REST API Routes.
 *
 * @package motorlan-api-vue
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// Prefer publicacion helpers; keep motor helpers as fallback if not present.
if ( ! function_exists( 'motorlan_get_publicacion_data' ) ) {
    require_once MOTORLAN_API_VUE_PATH . 'includes/api/publicaciones/helpers.php';
}
if ( ! function_exists( 'motorlan_get_publicacion_data' ) && ! function_exists( 'motorlan_get_motor_data' ) ) {
    require_once MOTORLAN_API_VUE_PATH . 'includes/api/motor-helpers.php';
}

require_once MOTORLAN_API_VUE_PATH . 'includes/api/purchases/controllers/class-purchase-chat-controller.php';

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
    // Eliminado: ya no se envÃƒÆ’Ã‚Â­an eventos en tiempo real con Pusher.
    // Conservada por compatibilidad futura (por ejemplo, si se usa polling o WebSockets autogestionados).
    return;
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
    $chat_controller = new Motorlan_Purchase_Chat_Controller();
    $chat_controller->register_routes();

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

    // Route for removing a favorite publicacion
    register_rest_route( $namespace, '/purchases/favorites/(?P<publicacion_id>\\d+)', array(
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

            // Related publicacion (fallback to legacy 'motor' meta)
            $related = get_field('publicacion', $post_id);
            if ( ! $related ) {
                $related = get_post_meta($post_id, 'motor', true);
            }
            $publicacion_id = null;
            if ( $related instanceof WP_Post ) {
                $publicacion_id = (int) $related->ID;
            } elseif ( is_array( $related ) && isset( $related['ID'] ) ) {
                $publicacion_id = (int) $related['ID'];
            } elseif ( is_numeric( $related ) ) {
                $publicacion_id = (int) $related;
            }

            $publicacion_data = null;
            if ( $publicacion_id ) {
                if ( function_exists('motorlan_get_publicacion_data') ) {
                    $publicacion_data = motorlan_get_publicacion_data( $publicacion_id );
                } elseif ( function_exists('motorlan_get_motor_data') ) {
                    $publicacion_data = motorlan_get_motor_data( $publicacion_id );
                }
            }

            // Extra purchase meta used in list view
            $tipo_venta   = get_post_meta( $post_id, 'tipo_venta', true );
            $offer_id_raw = get_post_meta( $post_id, 'offer_id', true );
            $offer_id     = $offer_id_raw ? (int) $offer_id_raw : 0;

            $data[] = array(
                'uuid'         => get_field('uuid', $post_id),
                'title'        => get_the_title(),
                'fecha_compra' => get_field('fecha_compra', $post_id),
                'publicacion'  => $publicacion_data,
                'vendedor'     => get_field('vendedor', $post_id) ?: get_post_meta($post_id, 'vendedor_id', true),
                'comprador'    => get_field('comprador', $post_id) ?: get_post_meta($post_id, 'comprador_id', true),
                'estado'       => get_field('estado', $post_id) ?: get_post_meta($post_id, 'estado', true),
                'tipo_venta'   => $tipo_venta ?: '',
                'offer_id'     => $offer_id,
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

            $related = get_field('publicacion', $post_id);
            if ( ! $related ) {
                $related = get_field('motor', $post_id);
            }
            $publicacion_id = null;
            if ( $related instanceof WP_Post ) {
                $publicacion_id = (int) $related->ID;
            } elseif ( is_array( $related ) && isset( $related['ID'] ) ) {
                $publicacion_id = (int) $related['ID'];
            } elseif ( is_numeric( $related ) ) {
                $publicacion_id = (int) $related;
            }

            $publicacion_data = null;
            if ( $publicacion_id ) {
                if ( function_exists('motorlan_get_publicacion_data') ) {
                    $publicacion_data = motorlan_get_publicacion_data( $publicacion_id );
                } elseif ( function_exists('motorlan_get_motor_data') ) {
                    $publicacion_data = motorlan_get_motor_data( $publicacion_id );
                }
            }

            $data[] = array(
                'id'        => $post_id,
                'title'     => get_the_title(),
                'pregunta'  => get_field('pregunta', $post_id),
                'respuesta' => get_field('respuesta', $post_id),
                'publicacion' => $publicacion_data,
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

            $related = get_field('publicacion', $post_id);
            if ( ! $related ) {
                $related = get_field('motor', $post_id);
            }
            $publicacion_id = null;
            if ( $related instanceof WP_Post ) {
                $publicacion_id = (int) $related->ID;
            } elseif ( is_array( $related ) && isset( $related['ID'] ) ) {
                $publicacion_id = (int) $related['ID'];
            } elseif ( is_numeric( $related ) ) {
                $publicacion_id = (int) $related;
            }

            $publicacion_data = null;
            if ( $publicacion_id ) {
                if ( function_exists('motorlan_get_publicacion_data') ) {
                    $publicacion_data = motorlan_get_publicacion_data( $publicacion_id );
                } elseif ( function_exists('motorlan_get_motor_data') ) {
                    $publicacion_data = motorlan_get_motor_data( $publicacion_id );
                }
            }

            $data[] = array(
                'id'         => $post_id,
                'title'      => get_the_title(),
                'valoracion' => get_field('valoracion', $post_id),
                'comentario' => get_field('comentario', $post_id),
                'publicacion' => $publicacion_data,
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

    // Prefer new favorites key; fallback to legacy if empty
    $favorite_ids = get_user_meta( $user_id, 'motorlan_favorites', true );
    if ( empty( $favorite_ids ) ) {
        $favorite_ids = get_user_meta( $user_id, 'favorite_motors', true );
    }

    if ( ! is_array( $favorite_ids ) || empty( $favorite_ids ) ) {
        return new WP_REST_Response( array( 'data' => [], 'pagination' => [ 'total' => 0, 'totalPages' => 0, 'currentPage' => (int) $page, 'perPage' => (int) $per_page ] ), 200 );
    }

    $args = array(
        'post_type'      => 'publicacion',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'post__in'       => array_map( 'intval', $favorite_ids ),
    );

    $query = new WP_Query( $args );
    $data = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();
            if ( function_exists( 'motorlan_get_publicacion_data' ) ) {
                $data[] = motorlan_get_publicacion_data( $post_id );
            } elseif ( function_exists( 'motorlan_get_motor_data' ) ) {
                $data[] = motorlan_get_motor_data( $post_id );
            }
        }
        wp_reset_postdata();
    }

    $total_favorites = count( $favorite_ids );
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
    // Ahora solo se acepta publicacion_id como parámetro oficial
    $publicacion_id = absint( $request->get_param( 'publicacion_id' ) );

    if ( ! $publicacion_id ) {
        return new WP_Error( 'no_publicacion', 'Publicación ID es requerido', array( 'status' => 400 ) );
    }
    $uuid = wp_generate_uuid4();

    $publicacion_title = get_the_title( $publicacion_id );
    $buyer       = get_userdata( $user_id );
    $buyer_name  = $buyer ? $buyer->display_name : '';

    $purchase_id = wp_insert_post( array(
        'post_type'   => 'compra',
        'post_status' => 'publish',
        'post_name'  => 'Compra ' . $publicacion_title,
        'post_title'  => $publicacion_title . ' - ' . $buyer_name,
    ) );

    if ( is_wp_error( $purchase_id ) ) {
        return $purchase_id;
    }

    $seller_id = get_post_field( 'post_author', $publicacion_id );

    update_field( 'uuid', $uuid, $purchase_id );
    // Guardar en el campo nuevo y mantener compatibilidad con el legado
    if ( function_exists( 'update_field' ) ) {
        update_field( 'publicacion', $publicacion_id, $purchase_id );
        update_field( 'motor', $publicacion_id, $purchase_id );
    } else {
        update_post_meta( $purchase_id, 'publicacion', $publicacion_id );
        update_post_meta( $purchase_id, 'motor', $publicacion_id );
    }
    update_field( 'vendedor', $seller_id, $purchase_id );
    // Save both buyer fields for compatibility and admin visibility
    update_field( 'comprador', $user_id, $purchase_id );
    update_field( 'usuario', $user_id, $purchase_id );

    // Save purchase price: default to the publication sale price
    $purchase_price = function_exists( 'get_field' ) ? get_field( 'precio_de_venta', $publicacion_id ) : get_post_meta( $publicacion_id, 'precio_de_venta', true );
    if ( $purchase_price !== '' && $purchase_price !== null ) {
        $purchase_price = (float) $purchase_price;
        update_field( 'precio_compra', $purchase_price, $purchase_id );
        // keep meta in sync for consumers that read it directly
        update_post_meta( $purchase_id, 'precio_compra', $purchase_price );
    }

    // Use the ACF key for pending state
    update_field( 'estado', 'pending', $purchase_id );
    update_field( 'fecha_compra', current_time( 'd/m/Y' ), $purchase_id );
    // mark as direct sale by default
    update_post_meta( $purchase_id, 'tipo_venta', 'direct' );

    // Notify seller
    $notification_manager = new Motorlan_Notification_Manager();
    $notification_manager->create_notification(
        $seller_id,
        'new_purchase',
        "Nueva compra de {$buyer_name} en \"{$publicacion_title}\"",
        "El usuario {$buyer_name} ha iniciado una compra para tu publicación.",
        array(
            'purchase_uuid' => $uuid,
            'purchase_id'   => $purchase_id,
            'url'           => '/purchases/' . $uuid,
        ),
        array( 'web', 'email' )
    );

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


    // Obtener publicacion asociada (o legado 'motor')
    $related = get_field( 'publicacion', $purchase_id );
    if ( ! $related ) {
        $related = get_field( 'motor', $purchase_id );
    }
    $publicacion_data = null;
    if ( $related ) {
        $publicacion_id = ( $related instanceof WP_Post ) ? $related->ID : ( is_array( $related ) && isset( $related['ID'] ) ? $related['ID'] : (int) $related );
        if ( function_exists( 'motorlan_get_publicacion_data' ) ) {
            $publicacion_data = motorlan_get_publicacion_data( $publicacion_id );
        } elseif ( function_exists( 'motorlan_get_motor_data' ) ) {
            $publicacion_data = motorlan_get_motor_data( $publicacion_id );
        }
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
    if ( is_array( $publicacion_data ) && isset( $publicacion_data['acf']['precio_de_venta'] ) && '' !== $publicacion_data['acf']['precio_de_venta'] ) {
        $published_price = (float) $publicacion_data['acf']['precio_de_venta'];
    }

    $data = array(
        'uuid'         => $uuid,
        'title'        => get_the_title( $purchase_id ),
        'fecha_compra' => get_field( 'fecha_compra', $purchase_id ),
        'publicacion'  => $publicacion_data,
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
    _doing_it_wrong(
        __FUNCTION__,
        'motorlan_get_purchase_messages_callback() is deprecated. Use Motorlan_Purchase_Chat_Controller instead.',
        '2.0.0'
    );

    $controller = new Motorlan_Purchase_Chat_Controller();
    return $controller->get_messages( $request );
}

/**
 * Append a new message to a purchase conversation.
 *
 * @param WP_REST_Request $request REST request.
 *
 * @return WP_REST_Response|WP_Error
 */
function motorlan_add_purchase_message_callback( WP_REST_Request $request ) {
    _doing_it_wrong(
        __FUNCTION__,
        'motorlan_add_purchase_message_callback() is deprecated. Use Motorlan_Purchase_Chat_Controller instead.',
        '2.0.0'
    );

    $controller = new Motorlan_Purchase_Chat_Controller();
    return $controller->create_message( $request );
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
    $related  = get_field( 'publicacion', $purchase_id );
    if ( ! $related ) {
        $related = get_field( 'motor', $purchase_id );
    }
    if ( ! $related ) {
        return new WP_Error( 'no_publicacion', 'Purchase without publicacion', array( 'status' => 400 ) );
    }

    $opinion_id = wp_insert_post( array(
        'post_type'   => 'opinion',
        'post_status' => 'publish',
        'post_title'  => 'Opinion compra ' . $purchase_id,
    ) );

    if ( is_wp_error( $opinion_id ) ) {
        return $opinion_id;
    }

    $publicacion_id = ( $related instanceof WP_Post ) ? $related->ID : ( is_array( $related ) && isset( $related['ID'] ) ? $related['ID'] : (int) $related );
    update_field( 'usuario', get_current_user_id(), $opinion_id );
    // Save to new ACF field and keep legacy meta updated
    update_field( 'publicacion', $publicacion_id, $opinion_id );
    update_field( 'motor', $publicacion_id, $opinion_id );
    update_field( 'valoracion', $valoracion, $opinion_id );
    update_field( 'comentario', $comentario, $opinion_id );

    return new WP_REST_Response( array( 'id' => $opinion_id ), 201 );
}

/**
 * Callback function to remove a publicacion from the current user's favorites.
 */
function motorlan_remove_my_favorite_callback( WP_REST_Request $request ) {
    $user_id = get_current_user_id();
    $publicacion_id = absint( $request->get_param( 'publicacion_id' ) );

    if ( ! $publicacion_id ) {
        return new WP_Error( 'no_publicacion_id', 'Publicacion ID is required.', array( 'status' => 400 ) );
    }

    // Primary: new favorites meta
    $favorite_ids = get_user_meta( $user_id, 'motorlan_favorites', true );
    if ( ! is_array( $favorite_ids ) ) {
        $favorite_ids = array();
    }
    $index = array_search( $publicacion_id, $favorite_ids, true );
    if ( $index !== false ) {
        unset( $favorite_ids[ $index ] );
        $favorite_ids = array_values( $favorite_ids );
        update_user_meta( $user_id, 'motorlan_favorites', $favorite_ids );
    }

    // Legacy sync: remove from old favorites if present
    $legacy_favs = get_user_meta( $user_id, 'favorite_motors', true );
    if ( is_array( $legacy_favs ) ) {
        $legacy_idx = array_search( $publicacion_id, $legacy_favs, true );
        if ( $legacy_idx !== false ) {
            unset( $legacy_favs[ $legacy_idx ] );
            $legacy_favs = array_values( $legacy_favs );
            update_user_meta( $user_id, 'favorite_motors', $legacy_favs );
        }
    }

    return new WP_REST_Response( array( 'success' => true ), 200 );
}
