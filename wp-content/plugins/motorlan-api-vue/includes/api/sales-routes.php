<?php
/**
 * REST API routes for sales.
 *
 * @package motorlan-api-vue
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

// Ensure publicacion helpers are available
if ( ! function_exists( 'motorlan_get_publicacion_data' ) ) {
    require_once MOTORLAN_API_VUE_PATH . 'includes/api/publicaciones/helpers.php';
}

function motorlan_register_sales_rest_routes() {
    $namespace = 'motorlan/v1';

    register_rest_route( $namespace, '/user/sales', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_user_sales_callback',
        'permission_callback' => 'motorlan_is_user_authenticated',
    ) );

    register_rest_route( $namespace, '/user/sale-details/(?P<uuid>[a-zA-Z0-9-]+)', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_user_sale_by_uuid_callback',
        'permission_callback' => 'motorlan_is_user_authenticated',
    ) );

    register_rest_route( $namespace, '/user/sales/manual', array(
        'methods'  => WP_REST_Server::CREATABLE,
        'callback' => 'motorlan_handle_create_manual_sale',
        'permission_callback' => 'motorlan_is_user_authenticated',
    ) );
}
add_action( 'rest_api_init', 'motorlan_register_sales_rest_routes' );

/**
 * Normalize a sale date value into ISO 8601 if possible.
 *
 * @param string $date_string Raw date string.
 * @return string
 */
function motorlan_normalize_sale_date( $date_string ) {
    if ( empty( $date_string ) ) {
        return '';
    }

    $normalized = str_replace( '/', '-', $date_string );
    $timestamp  = strtotime( $normalized );

    return $timestamp ? gmdate( DATE_ATOM, $timestamp ) : $date_string;
}

/**
 * Build the response payload for a sale.
 *
 * @param int $purchase_id Purchase post ID.
 * @return array
 */
function motorlan_prepare_sale_item( $purchase_id ) {
    $purchase_id    = absint( $purchase_id );
    $publication_id = null;

    // Resolve related publication robustly: try ACF 'publicacion', then legacy 'motor',
    // and fall back to raw post meta if needed.
    $related = function_exists( 'get_field' ) ? get_field( 'publicacion', $purchase_id ) : null;
    if ( ! $related ) {
        $related = function_exists( 'get_field' ) ? get_field( 'motor', $purchase_id ) : null;
    }
    if ( ! $related ) {
        $related = get_post_meta( $purchase_id, 'publicacion', true );
    }
    if ( ! $related ) {
        $related = get_post_meta( $purchase_id, 'motor', true );
    }

    if ( $related instanceof WP_Post && isset( $related->ID ) ) {
        $publication_id = (int) $related->ID;
    } elseif ( is_array( $related ) && isset( $related['ID'] ) ) {
        $publication_id = (int) $related['ID'];
    } elseif ( is_numeric( $related ) ) {
        $publication_id = (int) $related;
    }

    $price = function_exists( 'get_field' ) ? get_field( 'precio_compra', $purchase_id ) : get_post_meta( $purchase_id, 'precio_compra', true );
    if ( '' === $price ) {
        $price = get_post_meta( $purchase_id, 'precio_compra', true );
    }
    if ( '' === $price && $publication_id ) {
        $price = function_exists( 'get_field' ) ? get_field( 'precio_de_venta', $publication_id ) : get_post_meta( $publication_id, 'precio_de_venta', true );
    }

    $buyer_id  = function_exists( 'get_field' ) ? get_field( 'comprador', $purchase_id ) : get_post_meta( $purchase_id, 'comprador_id', true );
    $buyer_id  = $buyer_id ? absint( $buyer_id ) : 0;
    $buyer     = null;
    $buyer_name  = '';
    $buyer_email = '';

    if ( $buyer_id ) {
        $buyer_user = get_userdata( $buyer_id );
        if ( $buyer_user ) {
            $buyer_name  = $buyer_user->display_name ?: trim( $buyer_user->first_name . ' ' . $buyer_user->last_name );
            $buyer_email = $buyer_user->user_email;
            $buyer = array(
                'id'       => $buyer_id,
                'name'     => $buyer_name,
                'email'    => $buyer_email,
                'username' => $buyer_user->user_login,
            );
        }
    }

    $status = function_exists( 'get_field' ) ? get_field( 'estado', $purchase_id ) : get_post_meta( $purchase_id, 'estado', true );
    $type   = function_exists( 'get_field' ) ? get_field( 'tipo_venta', $purchase_id ) : get_post_meta( $purchase_id, 'tipo_venta', true );
    $uuid   = function_exists( 'get_field' ) ? get_field( 'uuid', $purchase_id ) : get_post_meta( $purchase_id, 'uuid', true );

    // Usar get_post_meta para evitar que ACF date_picker retorne fecha actual si vacío
    $raw_date   = get_post_meta( $purchase_id, 'fecha_compra', true );
    // Convertir formato ACF (Ymd) a d/m/Y si es necesario
    if ( ! empty( $raw_date ) && preg_match( '/^\d{8}$/', $raw_date ) ) {
        $raw_date = date_i18n( 'd/m/Y', strtotime( $raw_date ) );
    }
    $iso_date   = ! empty( $raw_date ) ? motorlan_normalize_sale_date( $raw_date ) : get_post_time( DATE_ATOM, true, $purchase_id );
    $date_label = ! empty( $raw_date ) ? $raw_date : get_the_date( 'd/m/Y', $purchase_id );

    $publication_slug  = '';
    // Default to purchase title, but we will override with the publication title when available
    $publication_title = get_the_title( $purchase_id );
    $publication_uuid  = '';

    if ( $publication_id ) {
        $publication = get_post( $publication_id );
        if ( $publication ) {
            $publication_title = motorlan_format_motor_name( $publication_id );
            $publication_slug  = $publication->post_name;
            $publication_uuid  = function_exists( 'get_field' ) ? get_field( 'uuid', $publication_id ) : get_post_meta( $publication_id, 'uuid', true );
        }
    }

    $price_value = is_numeric( $price ) ? (float) $price : null;

    // Obtener y normalizar info del vendedor (campo ACF “vendedor”)
    $seller_field = function_exists( 'get_field' ) ? get_field( 'vendedor', $purchase_id ) : get_post_meta( $purchase_id, 'vendedor', true );
    $seller_id = null;

    if ( is_numeric( $seller_field ) ) {
        $seller_id = absint( $seller_field );
    } elseif ( is_object( $seller_field ) && isset( $seller_field->ID ) ) {
        $seller_id = absint( $seller_field->ID );
    } elseif ( is_array( $seller_field ) ) {
        if ( isset( $seller_field['ID'] ) ) {
            $seller_id = absint( $seller_field['ID'] );
        } elseif ( isset( $seller_field[0]['ID'] ) ) {
            $seller_id = absint( $seller_field[0]['ID'] );
        } elseif ( isset( $seller_field[0] ) && is_numeric( $seller_field[0] ) ) {
            $seller_id = absint( $seller_field[0] );
        }
    }

    $seller = null;
    $seller_name = '';
    $seller_email = '';

    if ( $seller_id ) {
        $seller_user = get_userdata( $seller_id );
        if ( $seller_user ) {
            $seller_name  = $seller_user->display_name ?: trim( $seller_user->first_name . ' ' . $seller_user->last_name );
            $seller_email = $seller_user->user_email;

            $seller = array(
                'id'       => $seller_id,
                'name'     => $seller_name,
                'email'    => $seller_email,
                'username' => $seller_user->user_login,
            );
        }
    }

    return array(
        'id'                   => $purchase_id,
        'uuid'                 => $uuid ?: '',
        'motor_id'             => $publication_id,
        'motor_uuid'           => $publication_uuid ?: '',
        'motor_title'          => $publication_title,
        'motor_slug'           => $publication_slug,
        // Provide publication_* keys for frontend compatibility
        'publication_title'    => $publication_title,
        'publication_slug'     => $publication_slug,
        'price'                => $price,
        'price_value'          => $price_value,
        'currency'             => '',
        'date'                 => $iso_date,
        'date_label'           => $date_label,
        'status'               => $status ?: 'pending',
        'type'                 => $type ?: 'sale',
        'buyer_id'             => $buyer_id ?: null,
        'buyer_name'           => $buyer_name,
        'buyer_email'          => $buyer_email,
        'buyer'                => $buyer,
        'seller_id'            => $seller_id ?: null,
        'seller_name'          => $seller_name,
        'seller_email'         => $seller_email,
        'seller'               => $seller,
        'detail_url'           => get_permalink( $purchase_id ),
        // Ficha real de la SPA (/marketplace-motorlan/{slug}/); el permalink del CPT daba 404
        'motor_permalink'      => $publication_id ? ( function_exists( 'motorlan_get_store_url' ) ? motorlan_get_store_url( $publication_id ) : get_permalink( $publication_id ) ) : '',
    );
}

/**
 * Append motor details to a sale item if available.
 *
 * @param array $sale_item Sale payload.
 * @param int   $purchase_id Purchase post ID.
 * @return array
 */
function motorlan_enrich_sale_with_publicacion( $sale_item, $purchase_id ) {
    if ( ! function_exists( 'motorlan_get_publicacion_data' ) || ! function_exists( 'get_field' ) ) {
        return $sale_item;
    }

    // Try new field 'publicacion' first, then legacy 'motor'
    $related = get_field( 'publicacion', $purchase_id );
    if ( ! $related ) {
        $related = get_field( 'motor', $purchase_id );
        if ( ! $related ) {
            $related = get_post_meta( $purchase_id, 'motor', true );
        }
    }

    if ( $related instanceof WP_Post ) {
        $sale_item['publicacion'] = motorlan_get_publicacion_data( $related->ID );
    } elseif ( is_array( $related ) && isset( $related['ID'] ) ) {
        $sale_item['publicacion'] = motorlan_get_publicacion_data( (int) $related['ID'] );
    } elseif ( is_numeric( $related ) && $related ) {
        $sale_item['publicacion'] = motorlan_get_publicacion_data( (int) $related );
    }

    return $sale_item;
}

function motorlan_get_user_sales_callback( WP_REST_Request $request ) {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return new WP_Error( 'rest_not_logged_in', 'Sorry, you are not allowed to do that.', array( 'status' => 401 ) );
    }

    $params = $request->get_params();
    $per_page = isset( $params['per_page'] ) ? (int) $params['per_page'] : 10;
    $per_page = max( 1, min( 100, $per_page ) );
    $page = isset( $params['page'] ) ? max( 1, (int) $params['page'] ) : 1;
    $search = isset( $params['search'] ) ? sanitize_text_field( $params['search'] ) : '';
    $status = isset( $params['status'] ) ? sanitize_text_field( $params['status'] ) : '';
    $type   = isset( $params['type'] ) ? sanitize_text_field( $params['type'] ) : '';
    $order  = isset( $params['order'] ) ? strtoupper( sanitize_text_field( $params['order'] ) ) : 'DESC';
    $orderby = isset( $params['orderby'] ) ? sanitize_text_field( $params['orderby'] ) : 'date';
    $date_from = isset( $params['date_from'] ) ? sanitize_text_field( $params['date_from'] ) : '';
    $date_to   = isset( $params['date_to'] ) ? sanitize_text_field( $params['date_to'] ) : '';

    if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
        $order = 'DESC';
    }

    $meta_query = array(
        'relation' => 'AND',
        array(
            'key'   => 'vendedor',
            'value' => $user_id,
            'compare' => '=',
        ),
    );

    $status_meta_map = array(
        // El valor guardado en ACF es 'pending' (opciones pending/completed/cancelled)
        'pending'   => 'pending',
        'pendiente' => 'pending',
        'completed' => 'completed',
        'complete'  => 'completed',
        'processing'=> 'processing',
        'cancelled' => 'cancelled',
        'canceled'  => 'cancelled',
        'refunded'  => 'refunded',
        'rejected'  => 'rejected',
        'expired'   => 'expired',
    );

    if ( '' === $status ) {
        $meta_query[] = array(
            'key'   => 'estado',
            'value' => 'completed',
            'compare' => '=',
        );
    } elseif ( 'all' !== $status ) {
        $status_value = isset( $status_meta_map[ $status ] ) ? $status_meta_map[ $status ] : $status;
        $meta_query[] = array(
            'key'   => 'estado',
            'value' => $status_value,
            'compare' => '=',
        );
    }

    if ( ! empty( $type ) ) {
        $meta_query[] = array(
            'key'   => 'tipo_venta',
            'value' => $type,
            'compare' => '=',
        );
    }

    $args = array(
        'post_type'      => 'compra',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'meta_query'     => $meta_query,
        'order'          => $order,
        'post_status'    => 'any',
    );

    if ( ! empty( $search ) ) {
        $args['s'] = $search;
    }

    if ( ! empty( $date_from ) || ! empty( $date_to ) ) {
        $date_filter = array( 'inclusive' => true );
        if ( ! empty( $date_from ) ) {
            $timestamp = strtotime( $date_from );
            if ( $timestamp ) {
                $date_filter['after'] = gmdate( 'Y-m-d', $timestamp );
            }
        }
        if ( ! empty( $date_to ) ) {
            $timestamp = strtotime( $date_to );
            if ( $timestamp ) {
                $date_filter['before'] = gmdate( 'Y-m-d', $timestamp ) . ' 23:59:59';
            }
        }
        if ( count( $date_filter ) > 1 ) {
            $args['date_query'] = array( $date_filter );
        }
    }

    switch ( $orderby ) {
        case 'price':
        case 'price_value':
            $args['meta_key'] = 'precio_compra';
            $args['orderby']  = 'meta_value_num';
            break;
        case 'publication_title':
            $args['orderby'] = 'title';
            break;
        case 'date':
        default:
            $args['orderby'] = 'date';
            break;
    }

    $query = new WP_Query( $args );
    $sales = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $purchase_id = get_the_ID();

            $sale_item = motorlan_prepare_sale_item( $purchase_id );
            $sales[]   = motorlan_enrich_sale_with_publicacion( $sale_item, $purchase_id );
        }
        wp_reset_postdata();
    }

    $total_sales = $query->found_posts;

    $response = array(
        'data' => $sales,
        'pagination' => array(
            'total' => (int) $total_sales,
            'per_page' => $per_page,
            'current_page' => $page,
            'total_pages' => (int) $query->max_num_pages,
        ),
    );

    return new WP_REST_Response( $response, 200 );
}

/**
 * Retrieve the details for a single sale.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function motorlan_get_user_sale_by_uuid_callback( WP_REST_Request $request ) {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return new WP_Error( 'rest_not_logged_in', 'Sorry, you are not allowed to do that.', array( 'status' => 401 ) );
    }

    $uuid = sanitize_text_field( $request['uuid'] );
    if ( empty( $uuid ) ) {
        return new WP_Error( 'invalid_sale_uuid', 'Invalid sale identifier.', array( 'status' => 400 ) );
    }

    $args = array(
        'post_type'  => 'compra',
        'meta_key'   => 'uuid',
        'meta_value' => $uuid,
        'posts_per_page' => 1,
        'post_status' => 'any',
    );
    $posts = get_posts( $args );

    if ( empty( $posts ) ) {
        return new WP_Error( 'sale_not_found', 'Sale not found.', array( 'status' => 404 ) );
    }

    $sale_post = is_array($posts) ? reset($posts) : $posts;
    $sale_id   = is_object($sale_post) ? $sale_post->ID : 0;

    $seller_id = 0;

    if ( function_exists( 'get_field' ) ) {
        $seller_field = get_field( 'vendedor', $sale_id );

        // ACF puede retornar distintos tipos (ID, objeto, array o lista)
        if ( is_array( $seller_field ) && isset( $seller_field['ID'] ) ) {
            $seller_id = absint( $seller_field['ID'] );
        } elseif ( is_array( $seller_field ) && isset( $seller_field[0]['ID'] ) ) {
            $seller_id = absint( $seller_field[0]['ID'] );
        } elseif ( is_array( $seller_field ) && isset( $seller_field[0] ) && is_numeric( $seller_field[0] ) ) {
            $seller_id = absint( $seller_field[0] );
        } elseif ( is_object( $seller_field ) && isset( $seller_field->ID ) ) {
            $seller_id = absint( $seller_field->ID );
        } elseif ( is_numeric( $seller_field ) ) {
            $seller_id = absint( $seller_field );
        }
    }

    // Fallback: si no se encuentra vendedor, usar autor de la publicación asociada
    if ( ! $seller_id && function_exists( 'get_field' ) ) {
        $publicacion_post = get_field( 'publicacion', $sale_id );
        if ( $publicacion_post instanceof WP_Post ) {
            $seller_id = (int) $publicacion_post->post_author;
        } elseif ( is_array( $publicacion_post ) && isset( $publicacion_post['ID'] ) ) {
            $seller_id = (int) get_post_field( 'post_author', $publicacion_post['ID'] );
        } elseif ( is_numeric( $publicacion_post ) ) {
            $seller_id = (int) get_post_field( 'post_author', (int) $publicacion_post );
        }
    }

    // Sin IDs en la respuesta ni log por petición (antes se escribía en el error_log en
    // cada consulta y el 403 devolvía seller_id/user_id)
    if ( ! $seller_id || intval($seller_id) !== intval($user_id) ) {
        return new WP_Error(
            'forbidden_sale_access',
            'You are not allowed to view this sale.',
            array( 'status' => 403 )
        );
    }

    $sale_item = motorlan_prepare_sale_item( $sale_id );
    $sale_item = motorlan_enrich_sale_with_publicacion( $sale_item, $sale_id );
    // ¿El vendedor ya valoró al comprador? (para ofrecer RatingModal en el detalle de venta)
    $sale_item['review_done'] = (bool) get_post_meta( $sale_id, '_review_by_seller_done', true );
    
    if ( function_exists( 'get_field' ) ) {
        $sale_item['notes']        = get_field( 'notas', $sale_id );
        $sale_item['payment_type'] = get_field( 'tipo_de_pago', $sale_id );
        $sale_item['payment_meta'] = get_fields( $sale_id ) ?: array();
        // La oferta se guarda en el meta offer_id (tabla motorlan_offers), igual que
        // en el detalle de compra. Antes se leía un campo ACF 'offer' inexistente y se
        // llamaba a motorlan_get_offer_data(), que no existe.
        $offer_id = (int) get_post_meta( $sale_id, 'offer_id', true );
        if ( $offer_id && function_exists( 'motorlan_offers_prepare_offer_item' ) ) {
            global $wpdb;
            $offer_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}motorlan_offers WHERE id = %d", $offer_id ) );
            if ( $offer_row ) {
                $sale_item['offer'] = motorlan_offers_prepare_offer_item( $offer_row );
            }
        }
    }

    return new WP_REST_Response( array( 'data' => $sale_item ), 200 );
}

/**
 * Handle manual sale creation from seller (e.g. via chat).
 */
function motorlan_handle_create_manual_sale( $request ) {
    // Validate Content-Type
    if ( function_exists( 'motorlan_validate_json_content_type' ) ) {
        $valid_type = motorlan_validate_json_content_type( $request );
        if ( is_wp_error( $valid_type ) ) {
            return $valid_type;
        }
    }

    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return new WP_Error( 'rest_not_logged_in', 'Debes iniciar sesión.', array( 'status' => 401 ) );
    }

    $product_id = (int) $request['product_id'];
    $room_key   = sanitize_text_field( $request['room_key'] );

    $post = get_post( $product_id );
    if ( ! $post || 'publicacion' !== $post->post_type ) {
        return new WP_Error( 'not_found', 'Publicación no encontrada.', array( 'status' => 404 ) );
    }

    // 1. Validate Ownership
    if ( (int) $post->post_author !== (int) $user_id ) {
        return new WP_Error( 'forbidden', 'No tienes permisos para vender este artículo.', array( 'status' => 403 ) );
    }

    // 2. Extract Buyer from Room Key
    // Format: pub-{productId}-viewer-{viewerId}
    if ( ! preg_match( '/^pub-(\d+)-viewer-(.+)$/', $room_key, $m ) ) {
        return new WP_Error( 'invalid_room', 'Identificador de chat inválido.', array( 'status' => 400 ) );
    }

    $key_pid   = (int) $m[1];
    $viewer_id = $m[2];

    if ( $key_pid !== $product_id ) {
        return new WP_Error( 'invalid_room', 'El chat no corresponde a esta publicación.', array( 'status' => 400 ) );
    }

    if ( is_numeric( $viewer_id ) ) {
        $buyer_id = (int) $viewer_id;
    } else {
        // Sala de invitado reclamada por un usuario con email verificado: el comprador es
        // el user_id que tienen ahora sus mensajes
        global $wpdb;
        $buyer_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}motorlan_product_messages WHERE room_key = %s AND sender_role = 'viewer' AND user_id > 0 ORDER BY id DESC LIMIT 1",
            $room_key
        ) );
        if ( ! $buyer_id ) {
            return new WP_Error( 'invalid_buyer', 'No se puede vender a un usuario invitado o desconocido.', array( 'status' => 400 ) );
        }
    }
    $buyer    = get_userdata( $buyer_id );
    if ( ! $buyer ) {
        return new WP_Error( 'invalid_buyer', 'El usuario comprador no existe.', array( 'status' => 404 ) );
    }

    if ( $buyer_id === (int) $user_id ) {
        return new WP_Error( 'invalid_buyer', 'No puedes venderte a ti mismo.', array( 'status' => 400 ) );
    }

    global $wpdb;

    // El room_key lo envía el cliente: exigir que ese comprador haya escrito de verdad
    // en esa conversación. Antes se podía registrar una "venta" (y luego valorar) a
    // cualquier usuario inventando el room_key.
    $messages_table = $wpdb->prefix . 'motorlan_product_messages';
    $buyer_messages = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$messages_table} WHERE product_id = %d AND room_key = %s AND user_id = %d",
        $product_id,
        $room_key,
        $buyer_id
    ) );
    if ( $buyer_messages < 1 ) {
        return new WP_Error( 'invalid_buyer', 'Ese usuario no tiene una conversación sobre esta publicación.', array( 'status' => 400 ) );
    }

    // 3. Transaction & Stock Validation
    $wpdb->query('START TRANSACTION');

    // Lock Stock Row
    $wpdb->get_results( $wpdb->prepare("SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'stock' FOR UPDATE", $product_id) );
    $raw_stock     = $wpdb->get_var( $wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'stock'", $product_id) );
    // Sin meta de stock = 1 unidad, igual que el resto del código (antes daba "No hay stock")
    $current_stock = ( null === $raw_stock || '' === $raw_stock ) ? 1 : (int) $raw_stock;

    if ( $current_stock <= 0 ) {
        $wpdb->query('COMMIT'); // Close transaction neatly
        return new WP_Error( 'no_stock', 'No hay stock disponible.', array( 'status' => 400 ) );
    }

    // Respetar las unidades reservadas por ofertas aceptadas pendientes de confirmar
    // (antes la venta manual se quedaba la última unidad y el comprador de la oferta
    // se encontraba "sin stock" al confirmar).
    if ( function_exists( 'motorlan_offers_get_reserved_units' ) ) {
        $reserved_units = (int) motorlan_offers_get_reserved_units( $product_id );
        if ( $current_stock - $reserved_units <= 0 ) {
            $wpdb->query('COMMIT');
            return new WP_Error( 'reserved_stock', 'Las unidades disponibles están reservadas por ofertas aceptadas pendientes de confirmar.', array( 'status' => 400 ) );
        }
    }

    // 4. Create Purchase using Helper
    if ( ! function_exists( 'motorlan_create_purchase' ) ) {
        require_once MOTORLAN_API_VUE_PATH . 'includes/api/motor-helpers.php';
    }

    // Importe: el acordado en el chat (opcional, `amount`) o, si no, el precio publicado.
    // Con "Consultar precio" y sin importe acordado se guarda el precio de referencia del
    // vendedor marcado con _precio_referencia: el comprador no lo ve en el detalle.
    $agreed_amount = $request->get_param( 'amount' );
    $has_agreed    = is_numeric( $agreed_amount ) && (float) $agreed_amount > 0 && (float) $agreed_amount < 100000000;
    if ( $has_agreed ) {
        $amount = round( (float) $agreed_amount, 2 );
    } else {
        $price  = function_exists( 'get_field' ) ? get_field( 'precio_de_venta', $product_id ) : get_post_meta( $product_id, 'precio_de_venta', true );
        $amount = (float) $price;
    }
    $precio_negociable = get_post_meta( $product_id, 'precio_negociable', true );
    if ( is_array( $precio_negociable ) ) {
        $precio_negociable = reset( $precio_negociable );
    }
    $consult_price = in_array( (string) $precio_negociable, array( 'yes', 'Sí', 'si', 'true', '1' ), true );

    $purchase_data = motorlan_create_purchase( $product_id, $buyer_id, $amount, $user_id, 0 );
    if ( is_wp_error( $purchase_data ) ) {
        $wpdb->query('ROLLBACK');
        return $purchase_data;
    }

    if ( is_array( $purchase_data ) && ! empty( $purchase_data['id'] ) ) {
        if ( $has_agreed ) {
            update_post_meta( (int) $purchase_data['id'], '_precio_acordado', 1 );
        } elseif ( $consult_price ) {
            update_post_meta( (int) $purchase_data['id'], '_precio_referencia', 1 );
        }
    }

    // 5. Update Stock: dentro de la transacción (fila bloqueada) y una sola vez.
    // Al llegar a 0 queda como 'sold' (Vendido), igual que al confirmar una oferta;
    // antes quedaba 'paused' y el vendedor lo veía como "Pausado".
    $new_stock = max( 0, $current_stock - 1 );
    update_post_meta( $product_id, 'stock', $new_stock );
    if ( 0 === $new_stock ) {
        update_post_meta( $product_id, 'publicar_acf', 'sold' );
    }

    $wpdb->query('COMMIT');

    // ACF (referencias de campo) fuera de la transacción
    if ( function_exists( 'update_field' ) ) {
        update_field( 'stock', $new_stock, $product_id );
        if ( 0 === $new_stock ) {
            update_field( 'publicar_acf', 'sold', $product_id );
        }
    }

    // 6. Notify Buyer
    $notification_payload = array(
        'buyer_id'      => $buyer_id,
        'product_title' => $post->post_title,
        'uuid'          => is_array( $purchase_data ) ? $purchase_data['uuid'] : '',
        'product_id'    => $product_id,
    );
    do_action( 'motorlan_manual_sale_created', $notification_payload );

    return new WP_REST_Response( array(
        'success' => true,
        'message' => 'Venta registrada correctamente.',
        'data'    => $purchase_data,
        'stock'   => $new_stock,
    ), 200 );
}
