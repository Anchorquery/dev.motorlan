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

if ( ! function_exists( 'motorlan_get_publicacion_data' ) ) {
    require_once MOTORLAN_API_VUE_PATH . 'includes/api/publicaciones/helpers.php';
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

    // POST /purchases/{uuid}/opinion DESACTIVADO: sustituido por POST /reviews
    // (RatingModal), que valida el rango 1-5 y no permite duplicados. Este creaba un
    // post 'opinion' por llamada sin comprobar si ya existía y aceptaba valoracion=999.
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
                $publicacion_data = motorlan_get_publicacion_data( $publicacion_id );
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
    if ( $purchase_ids ) {
        update_meta_cache( 'post', $purchase_ids ); // N+1: fields=ids no precarga el meta
    }

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
            $data[] = motorlan_get_publicacion_data( $post_id );
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
