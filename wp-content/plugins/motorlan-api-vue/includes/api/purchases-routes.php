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

    // POST /purchases DESACTIVADO (SEGURIDAD): ninguna pantalla lo usa y permitía a
    // cualquier usuario registrado "comprar" publicaciones ajenas sin el vendedor:
    // descontaba stock, despublicaba al llegar a 0, enviaba el email de nueva compra y
    // exponía el precio oculto en GET /purchases/{uuid}. Las compras se registran con
    // la venta manual del vendedor (/user/sales/manual) o confirmando una oferta.

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

    // POST /purchases/{uuid}/opinion DESACTIVADO: sustituido por POST /reviews
    // (RatingModal), que valida el rango 1-5 y no permite duplicados. Este creaba un
    // post 'opinion' por llamada sin comprobar si ya existía y aceptaba valoracion=999.

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
    $per_page = max( 1, min( 100, $per_page ) );
    $search = sanitize_text_field( (string) $request->get_param( 'search' ) );

    $args = array(
        'post_type'      => 'compra',
        's'              => $search,
        'posts_per_page' => $per_page,
        'paged'          => max( 1, $page ),
        'meta_query' => array(
            array(
                'key'     => 'comprador',
                'value'   => $user_id,
                'compare' => '=',
            ),
        ),
    );

    // Ordenación de la tabla "Mis compras" (antes se ignoraba orderby/order)
    $order = strtoupper( sanitize_text_field( (string) $request->get_param( 'order' ) ) );
    $args['order'] = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';
    switch ( sanitize_key( (string) $request->get_param( 'orderby' ) ) ) {
        case 'publicacion':
        case 'title':
            $args['orderby'] = 'title';
            break;
        case 'estado':
            $args['meta_key'] = 'estado';
            $args['orderby']  = 'meta_value';
            break;
        case 'fecha_compra':
        case 'date':
        default:
            $args['orderby'] = 'date';
            break;
    }

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

            // Obtener fecha de compra: primero intentar meta directo para evitar
            // comportamiento de ACF date_picker que retorna fecha actual si vacío
            $fecha_raw = get_post_meta( $post_id, 'fecha_compra', true );
            if ( empty( $fecha_raw ) ) {
                // Fallback a la fecha de creación del post
                $fecha_raw = get_the_date( 'd/m/Y', $post_id );
            } else {
                // Si el valor está en formato ACF (Ymd), convertir a d/m/Y
                if ( preg_match( '/^\d{8}$/', $fecha_raw ) ) {
                    $fecha_raw = date_i18n( 'd/m/Y', strtotime( $fecha_raw ) );
                }
            }

            $data[] = array(
                'uuid'         => get_field('uuid', $post_id),
                'title'        => get_the_title(),
                'fecha_compra' => $fecha_raw,
                'publicacion'  => $publicacion_data,
                'vendedor'     => get_field('vendedor', $post_id) ?: get_post_meta($post_id, 'vendedor_id', true),
                'comprador'    => get_field('comprador', $post_id) ?: get_post_meta($post_id, 'comprador_id', true),
                'estado'       => get_field('estado', $post_id) ?: get_post_meta($post_id, 'estado', true),
                'tipo_venta'   => $tipo_venta ?: '',
                'offer_id'     => $offer_id,
                'review_done'  => (bool) get_post_meta($post_id, '_review_by_buyer_done', true),
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
 *
 * Valoraciones hechas por el usuario. Las nuevas (POST /reviews, RatingModal) se guardan
 * como metas de la compra (_review_seller_* al valorar al vendedor, _review_buyer_* al
 * valorar al comprador); antes este listado solo leía el CPT 'opinion' del endpoint
 * antiguo y "Mis opiniones" salía siempre vacía. Se mantienen las opiniones antiguas.
 */
function motorlan_get_my_opinions_callback( $request ) {
    $user_id  = get_current_user_id();
    $page     = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
    $per_page = max( 1, min( 100, absint( $request->get_param( 'per_page' ) ?: 10 ) ) );
    $search   = sanitize_text_field( (string) $request->get_param( 'search' ) );
    $order    = strtoupper( sanitize_text_field( (string) $request->get_param( 'order' ) ) ) === 'ASC' ? 'ASC' : 'DESC';
    $orderby  = sanitize_key( (string) $request->get_param( 'orderby' ) );

    $items = array();

    // 1. Valoraciones nuevas (metas en la compra)
    $purchase_ids = get_posts( array(
        'post_type'      => 'compra',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => array(
            'relation' => 'OR',
            array( 'key' => '_review_seller_by', 'value' => $user_id ),
            array( 'key' => '_review_buyer_by', 'value' => $user_id ),
        ),
    ) );

    foreach ( $purchase_ids as $purchase_id ) {
        foreach ( array( 'seller', 'buyer' ) as $target ) {
            if ( (int) get_post_meta( $purchase_id, '_review_' . $target . '_by', true ) !== (int) $user_id ) {
                continue;
            }
            $related = get_post_meta( $purchase_id, 'publicacion', true ) ?: get_post_meta( $purchase_id, 'motor', true );
            $items[] = array(
                'id'             => (int) $purchase_id,
                'purchase_uuid'  => (string) get_post_meta( $purchase_id, 'uuid', true ),
                'title'          => get_the_title( $purchase_id ),
                'valoracion'     => (int) get_post_meta( $purchase_id, '_review_' . $target . '_rating', true ),
                'comentario'     => (string) get_post_meta( $purchase_id, '_review_' . $target . '_comment', true ),
                'target_role'    => $target,
                'fecha'          => (string) ( get_post_meta( $purchase_id, '_review_' . $target . '_at', true ) ?: get_post_field( 'post_date', $purchase_id ) ),
                'publicacion_id' => is_numeric( $related ) ? (int) $related : 0,
            );
        }
    }

    // 2. Opiniones antiguas (CPT 'opinion', endpoint ya desactivado)
    $legacy_ids = get_posts( array(
        'post_type'      => 'opinion',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => array(
            array( 'key' => 'usuario', 'value' => $user_id, 'compare' => '=' ),
        ),
    ) );

    foreach ( $legacy_ids as $opinion_id ) {
        $related = get_post_meta( $opinion_id, 'publicacion', true ) ?: get_post_meta( $opinion_id, 'motor', true );
        $items[] = array(
            'id'             => (int) $opinion_id,
            'purchase_uuid'  => '',
            'title'          => get_the_title( $opinion_id ),
            'valoracion'     => max( 0, min( 5, (int) get_post_meta( $opinion_id, 'valoracion', true ) ) ),
            'comentario'     => (string) get_post_meta( $opinion_id, 'comentario', true ),
            'target_role'    => 'seller',
            'fecha'          => (string) get_post_field( 'post_date', $opinion_id ),
            'publicacion_id' => is_numeric( $related ) ? (int) $related : 0,
        );
    }

    // Búsqueda por título de la publicación o comentario
    if ( '' !== $search ) {
        $items = array_values( array_filter( $items, static function ( $item ) use ( $search ) {
            $haystack = $item['title'] . ' ' . $item['comentario'] . ' ' . ( $item['publicacion_id'] ? get_the_title( $item['publicacion_id'] ) : '' );
            return false !== stripos( $haystack, $search );
        } ) );
    }

    usort( $items, static function ( $a, $b ) use ( $orderby, $order ) {
        $cmp = ( 'valoracion' === $orderby ) ? ( $a['valoracion'] <=> $b['valoracion'] ) : strcmp( $a['fecha'], $b['fecha'] );
        return 'ASC' === $order ? $cmp : -$cmp;
    } );

    $total = count( $items );
    $data  = array();
    foreach ( array_slice( $items, ( $page - 1 ) * $per_page, $per_page ) as $item ) {
        $item['publicacion'] = ( $item['publicacion_id'] && function_exists( 'motorlan_get_publicacion_data' ) )
            ? motorlan_get_publicacion_data( $item['publicacion_id'], false, false, true )
            : null;
        $data[] = $item;
    }

    return new WP_REST_Response( array(
        'data'       => $data,
        'pagination' => array(
            'total'       => $total,
            'totalPages'  => (int) ceil( $total / $per_page ),
            'currentPage' => $page,
            'perPage'     => $per_page,
        ),
    ), 200 );
}

/**
 * Callback function to get a list of current user's favorites.
 */
function motorlan_get_my_favorites_callback( $request ) {
    $user_id = get_current_user_id();
    $page = $request->get_param( 'page' ) ? absint( $request->get_param( 'page' ) ) : 1;
    $page = max( 1, $page );
    $per_page = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : 10;
    $per_page = max( 1, min( 100, $per_page ) );
    $empty_response = new WP_REST_Response( array( 'data' => [], 'pagination' => [ 'total' => 0, 'totalPages' => 0, 'currentPage' => (int) $page, 'perPage' => (int) $per_page ] ), 200 );

    // Favoritos actuales + legacy (favorite_motors), sin duplicados
    $favorite_ids = get_user_meta( $user_id, 'motorlan_favorites', true );
    $legacy_ids   = get_user_meta( $user_id, 'favorite_motors', true );
    $favorite_ids = array_merge( is_array( $favorite_ids ) ? $favorite_ids : array(), is_array( $legacy_ids ) ? $legacy_ids : array() );
    $favorite_ids = array_values( array_unique( array_filter( array_map( 'intval', $favorite_ids ) ) ) );

    if ( empty( $favorite_ids ) ) {
        return $empty_response;
    }

    // Visibles: publicadas, pausadas o vendidas, o propias (como GET /favorites). Antes el
    // total era count($favorite_ids) e incluía IDs borrados o en borrador: páginas vacías.
    _prime_post_caches( $favorite_ids, false, false );
    $visible_ids = array();
    foreach ( $favorite_ids as $favorite_id ) {
        $favorite_post = get_post( $favorite_id );
        if ( $favorite_post && 'publicacion' === $favorite_post->post_type
            && ( in_array( $favorite_post->post_status, array( 'publish', 'paused', 'sold' ), true ) || (int) $favorite_post->post_author === (int) $user_id ) ) {
            $visible_ids[] = $favorite_id;
        }
    }

    if ( empty( $visible_ids ) ) {
        return $empty_response;
    }

    $args = array(
        'post_type'      => 'publicacion',
        'post_status'    => array( 'publish', 'paused', 'sold', 'draft', 'pending', 'private', 'future' ),
        'posts_per_page' => $per_page,
        'paged'          => $page,
        // Los últimos añadidos primero
        'post__in'       => array_reverse( $visible_ids ),
        'orderby'        => 'post__in',
    );

    $search = sanitize_text_field( (string) $request->get_param( 'search' ) );
    if ( '' !== $search ) {
        $args['s'] = $search;
    }

    $status = sanitize_key( (string) $request->get_param( 'status' ) );
    if ( in_array( $status, array( 'publish', 'paused', 'sold', 'draft', 'pending' ), true ) ) {
        $args['meta_query'] = array(
            array( 'key' => 'publicar_acf', 'value' => $status, 'compare' => '=' ),
        );
    }

    $orderby = sanitize_key( (string) $request->get_param( 'orderby' ) );
    if ( in_array( $orderby, array( 'title', 'publicacion' ), true ) ) {
        $args['orderby'] = 'title';
        $args['order']   = strtoupper( (string) $request->get_param( 'order' ) ) === 'DESC' ? 'DESC' : 'ASC';
    }

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

    $total_favorites = (int) $query->found_posts;
    $max_num_pages   = (int) $query->max_num_pages;

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
    // Ruta desregistrada (ver motorlan_register_purchases_rest_routes). Si se vuelve a
    // enganchar por error, no registra nada: la compra directa debe pasar por el vendedor.
    return new WP_Error( 'purchase_disabled', 'La compra directa no está disponible. Contacta con el vendedor.', array( 'status' => 410 ) );

    $user_id  = get_current_user_id();
    // Ahora solo se acepta publicacion_id como parámetro oficial
    $publicacion_id = absint( $request->get_param( 'publicacion_id' ) );

    if ( ! $publicacion_id ) {
        return new WP_Error( 'no_publicacion', 'Publicación ID es requerido', array( 'status' => 400 ) );
    }

    // Solo publicaciones publicadas, ajenas y con stock. Antes cualquier usuario logueado
    // podía pasar el ID de cualquier post (página, publicación ajena...) y dejarlo en borrador.
    $publicacion = get_post( $publicacion_id );
    if ( ! $publicacion || 'publicacion' !== $publicacion->post_type ) {
        return new WP_Error( 'not_found', 'Publicación no encontrada', array( 'status' => 404 ) );
    }
    if ( 'publish' !== $publicacion->post_status ) {
        return new WP_Error( 'not_available', 'La publicación no está disponible.', array( 'status' => 400 ) );
    }
    if ( (int) $publicacion->post_author === (int) $user_id ) {
        return new WP_Error( 'own_publication', 'No puedes comprar tu propia publicación.', array( 'status' => 400 ) );
    }
    $current_stock = get_field( 'stock', $publicacion_id );
    if ( null !== $current_stock && '' !== $current_stock && (int) $current_stock <= 0 ) {
        return new WP_Error( 'no_stock', 'No hay stock disponible.', array( 'status' => 400 ) );
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

    // Stock management
    $stock = get_field( 'stock', $publicacion_id );
    if ( null === $stock || '' === $stock ) {
        $stock = 1;
    } else {
        $stock = (int) $stock;
    }

    if ( $stock > 0 ) {
        $new_stock = $stock - 1;
        update_field( 'stock', $new_stock, $publicacion_id );

        if ( 0 === $new_stock ) {
            wp_update_post( array(
                'ID' => $publicacion_id,
                'post_status' => 'draft'
            ) );
        }
    }

    // Notify seller
    do_action( 'motorlan_new_purchase', $purchase_id );

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

    // Security check: IDOR protection
    if ( ! motorlan_user_can_access_purchase( $purchase_id, get_current_user_id() ) ) {
        return new WP_Error( 'forbidden', 'No tienes permisos para ver esta compra.', array( 'status' => 403 ) );
    }


    // Obtener publicacion asociada (o legado 'motor')
    $related = get_field( 'publicacion', $purchase_id );
    if ( ! $related ) {
        $related = get_field( 'motor', $purchase_id );
    }
    $publicacion_data = null;
    if ( $related ) {
        $publicacion_id = ( $related instanceof WP_Post ) ? $related->ID : ( is_array( $related ) && isset( $related['ID'] ) ? $related['ID'] : (int) $related );
        if ( function_exists( 'motorlan_get_publicacion_data' ) ) {
            // Detalle de compra (ya autorizado a participantes): el comprador necesita el email del vendedor.
            $publicacion_data = motorlan_get_publicacion_data( $publicacion_id, false, true );
            // Reputación del vendedor con número de ventas (tarjeta "Datos del vendedor")
            if ( ! empty( $publicacion_data['author'] ) && function_exists( 'motorlan_get_seller_reputation' ) ) {
                $publicacion_data['author']['acf'] = motorlan_get_seller_reputation( (int) $publicacion_data['author']['id'], true );
            }
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
    // Venta manual de un artículo con "Consultar precio" sin importe acordado: el importe
    // guardado es el precio oculto de referencia del vendedor. El comprador no lo ve.
    if ( 'seller' !== $viewer_role && ! user_can( $current_user_id, 'manage_options' ) && get_post_meta( $purchase_id, '_precio_referencia', true ) ) {
        $purchase_price = null;
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

    // Obtener fecha de compra evitando comportamiento de ACF date_picker
    $fecha_compra_raw = get_post_meta( $purchase_id, 'fecha_compra', true );
    if ( empty( $fecha_compra_raw ) ) {
        $purchase_post = get_post( $purchase_id );
        $fecha_compra_raw = $purchase_post ? date_i18n( 'd/m/Y', strtotime( $purchase_post->post_date ) ) : '';
    } else {
        if ( preg_match( '/^\d{8}$/', $fecha_compra_raw ) ) {
            $fecha_compra_raw = date_i18n( 'd/m/Y', strtotime( $fecha_compra_raw ) );
        }
    }

    $data = array(
        'uuid'         => $uuid,
        'title'        => get_the_title( $purchase_id ),
        'fecha_compra' => $fecha_compra_raw,
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
        // Valoración pendiente según quién mira (comprador → vendedor, vendedor → comprador)
        'review_done'  => (bool) get_post_meta( $purchase_id, 'seller' === $viewer_role ? '_review_by_seller_done' : '_review_by_buyer_done', true ),
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
    // Ruta desregistrada: usar POST /reviews.
    return new WP_Error( 'opinion_disabled', 'Usa la valoración de la compra.', array( 'status' => 410 ) );

    // Validate Content-Type
    if ( function_exists( 'motorlan_validate_json_content_type' ) ) {
        $valid_type = motorlan_validate_json_content_type( $request );
        if ( is_wp_error( $valid_type ) ) {
            return $valid_type;
        }
    }

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

    // Validate if current user is the buyer of this purchase
    $buyer_id = (int) ( get_field( 'comprador', $purchase_id ) ?: get_post_meta( $purchase_id, 'comprador_id', true ) );
    if ( get_current_user_id() !== $buyer_id ) {
        return new WP_Error( 'forbidden', 'Solo el comprador puede dejar una opinión.', array( 'status' => 403 ) );
    }

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
    // intval: los favoritos guardados como string no se encontraban con la búsqueda estricta
    $index = array_search( $publicacion_id, array_map( 'intval', $favorite_ids ), true );
    if ( $index !== false ) {
        unset( $favorite_ids[ $index ] );
        $favorite_ids = array_values( $favorite_ids );
        update_user_meta( $user_id, 'motorlan_favorites', $favorite_ids );
    }

    // Legacy sync: remove from old favorites if present
    $legacy_favs = get_user_meta( $user_id, 'favorite_motors', true );
    if ( is_array( $legacy_favs ) ) {
        $legacy_idx = array_search( $publicacion_id, array_map( 'intval', $legacy_favs ), true );
        if ( $legacy_idx !== false ) {
            unset( $legacy_favs[ $legacy_idx ] );
            $legacy_favs = array_values( $legacy_favs );
            update_user_meta( $user_id, 'favorite_motors', $legacy_favs );
        }
    }

    return new WP_REST_Response( array( 'success' => true ), 200 );
}
