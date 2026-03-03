<?php

add_action('rest_api_init', 'motorlan_register_offers_routes');

if (!defined('MOTORLAN_OFFER_STATUS_PENDING')) {
    define('MOTORLAN_OFFER_STATUS_PENDING', 'pending');
    define('MOTORLAN_OFFER_STATUS_REJECTED', 'rejected');
    define('MOTORLAN_OFFER_STATUS_ACCEPTED_LEGACY', 'accepted');
    define('MOTORLAN_OFFER_STATUS_ACCEPTED_PENDING', 'accepted_pending_confirmation');
    define('MOTORLAN_OFFER_STATUS_CONFIRMED', 'confirmed');
    define('MOTORLAN_OFFER_STATUS_EXPIRED', 'expired');
}

function motorlan_offers_get_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'motorlan_offers';
}

function motorlan_offers_get_stock($publication_id) {
    static $cache = array();

    $publication_id = (int) $publication_id;
    if ($publication_id <= 0) {
        return 0;
    }

    if (array_key_exists($publication_id, $cache)) {
        return $cache[$publication_id];
    }

    $stock = function_exists('get_field') ? get_field('stock', $publication_id) : 0;

    if ($stock === null || $stock === '') {
        $cache[$publication_id] = 0;
        return 0;
    }

    $cache[$publication_id] = (int) $stock;

    return $cache[$publication_id];
}

function motorlan_offers_get_reserved_units($publication_id, $exclude_offer_id = null) {
    global $wpdb;

    $table_name = motorlan_offers_get_table_name();
    $query = "SELECT COUNT(*) FROM $table_name WHERE publication_id = %d AND status = %s";
    $params = array($publication_id, MOTORLAN_OFFER_STATUS_ACCEPTED_PENDING);

    if (!empty($exclude_offer_id)) {
        $query .= " AND id != %d";
        $params[] = (int) $exclude_offer_id;
    }

    return (int) $wpdb->get_var($wpdb->prepare($query, $params));
}

function motorlan_offers_has_available_stock_for_acceptance($publication_id, $exclude_offer_id = null) {
    $stock = motorlan_offers_get_stock($publication_id);
    if ($stock <= 0) {
        return false;
    }

    $reserved = motorlan_offers_get_reserved_units($publication_id, $exclude_offer_id);

    return ($stock - $reserved) > 0;
}

function motorlan_offers_can_accept_offer($offer) {
    if (!$offer) {
        return false;
    }

    $allowed_statuses = array(MOTORLAN_OFFER_STATUS_PENDING, MOTORLAN_OFFER_STATUS_EXPIRED);
    if (!in_array($offer->status, $allowed_statuses, true)) {
        return false;
    }

    return motorlan_offers_has_available_stock_for_acceptance($offer->publication_id, $offer->id);
}

function motorlan_offers_normalize_status($status) {
    if ($status === MOTORLAN_OFFER_STATUS_ACCEPTED_LEGACY) {
        return MOTORLAN_OFFER_STATUS_CONFIRMED;
    }

    return $status;
}

function motorlan_offers_refresh_offer($offer) {
    if (!$offer) {
        return $offer;
    }

    global $wpdb;

    $table_name = motorlan_offers_get_table_name();
    $normalized_status = motorlan_offers_normalize_status($offer->status);

    if ($normalized_status !== $offer->status) {
        $updates = array('status' => $normalized_status);
        if (empty($offer->confirmed_at) && !empty($offer->accepted_at)) {
            $updates['confirmed_at'] = $offer->accepted_at;
            $offer->confirmed_at = $offer->accepted_at;
        }

        $wpdb->update(
            $table_name,
            $updates,
            array('id' => (int) $offer->id)
        );

        $offer->status = $normalized_status;
    }

    if ($offer->status === MOTORLAN_OFFER_STATUS_ACCEPTED_PENDING && !empty($offer->expires_at)) {
        $expires_ts = strtotime($offer->expires_at);
        if ($expires_ts && $expires_ts <= current_time('timestamp')) {
            $wpdb->update(
                $table_name,
                array(
                    'status' => MOTORLAN_OFFER_STATUS_EXPIRED,
                    'accepted_at' => null,
                    'expires_at' => null,
                ),
                array('id' => (int) $offer->id)
            );

            $offer->status = MOTORLAN_OFFER_STATUS_EXPIRED;
            $offer->accepted_at = null;
            $offer->expires_at = null;
        }
    }

    return $offer;
}

function motorlan_offers_seconds_to_expire($offer) {
    if (!$offer || $offer->status !== MOTORLAN_OFFER_STATUS_ACCEPTED_PENDING || empty($offer->expires_at)) {
        return null;
    }

    $expires_ts = strtotime($offer->expires_at);
    if (!$expires_ts) {
        return null;
    }

    $diff = $expires_ts - current_time('timestamp');

    return $diff > 0 ? $diff : 0;
}

function motorlan_offers_prepare_offer_item($offer) {
    if (!$offer) {
        return null;
    }

    $offer = motorlan_offers_refresh_offer($offer);
    $offer_vars = get_object_vars($offer);
    $base_keys = array(
        'id',
        'publication_id',
        'user_id',
        'offer_amount',
        'offer_date',
        'status',
        'justification',
        'accepted_at',
        'expires_at',
        'confirmed_at',
    );

    $base = array(
        'id' => (int) $offer->id,
        'publication_id' => (int) $offer->publication_id,
        'user_id' => (int) $offer->user_id,
        'offer_amount' => (float) $offer->offer_amount,
        'offer_date' => $offer->offer_date,
        'status' => $offer->status,
        'justification' => $offer->justification,
        'accepted_at' => $offer->accepted_at,
        'expires_at' => $offer->expires_at,
        'confirmed_at' => $offer->confirmed_at,
        'time_to_expire' => motorlan_offers_seconds_to_expire($offer),
        'can_accept' => motorlan_offers_can_accept_offer($offer),
        'accept_disabled_reason' => null,
    );

    $accept_candidate_statuses = array(MOTORLAN_OFFER_STATUS_PENDING, MOTORLAN_OFFER_STATUS_EXPIRED);
    if (in_array($offer->status, $accept_candidate_statuses, true) && !$base['can_accept']) {
        $current_stock = motorlan_offers_get_stock($offer->publication_id);
        $reserved_units = motorlan_offers_get_reserved_units($offer->publication_id, $offer->id);

        if ($current_stock <= 0) {
            $base['accept_disabled_reason'] = 'Sin stock disponible para este motor.';
        } elseif ($reserved_units >= $current_stock) {
            $base['accept_disabled_reason'] = 'Ya existe una oferta aceptada esperando confirmación.';
        }
    }

    $extra = array_diff_key($offer_vars, array_flip($base_keys));

    return array_merge($base, $extra);
}

function motorlan_offers_create_purchase_from_offer($offer) {
    if (!function_exists('motorlan_create_purchase')) {
        require_once MOTORLAN_API_VUE_PATH . 'includes/api/motor-helpers.php';
    }

    $motor_id = (int) $offer->publication_id;
    $buyer_id = (int) $offer->user_id;
    $amount = (float) $offer->offer_amount;
    $seller_id = (int) get_post_field('post_author', $motor_id);

    return motorlan_create_purchase( $motor_id, $buyer_id, $amount, $seller_id, $offer->id );
}

function motorlan_register_offers_routes() {
    register_rest_route('motorlan/v1', '/publicaciones/(?P<id>\d+)/offers', array(
        'methods' => 'POST',
        'callback' => 'motorlan_handle_create_offer',
        'permission_callback' => function () {
            return is_user_logged_in();
        },
        'args' => array(
            'amount' => array(
                'required' => true,
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param) && $param > 0;
                }
            ),
            'justification' => array(
                'required' => false,
                'validate_callback' => function($param, $request, $key) {
                    return is_string($param);
                }
            ),
        ),
    ));

    register_rest_route('motorlan/v1', '/offers/received', array(
        'methods' => 'GET',
        'callback' => 'motorlan_handle_get_received_offers',
        'permission_callback' => 'motorlan_is_user_authenticated'
    ));

    register_rest_route('motorlan/v1', '/offers/sent', array(
        'methods' => 'GET',
        'callback' => 'motorlan_handle_get_sent_offers',
        'permission_callback' => 'motorlan_is_user_authenticated'
    ));

    register_rest_route('motorlan/v1', '/offers/(?P<id>\d+)/status', array(
        'methods' => 'POST',
        'callback' => 'motorlan_handle_update_offer_status',
        'permission_callback' => 'motorlan_is_user_authenticated',
        'args' => array(
            'status' => array(
                'required' => true,
                'validate_callback' => function($param, $request, $key) {
                    return in_array($param, array('accepted', 'rejected'), true);
                }
            ),
        ),
    ));

    register_rest_route('motorlan/v1', '/offers/(?P<id>\d+)/confirm', array(
        'methods' => 'POST',
        'callback' => 'motorlan_handle_confirm_offer',
        'permission_callback' => 'motorlan_is_user_authenticated',
    ));

    register_rest_route('motorlan/v1', '/offers/(?P<id>\d+)', array(
        'methods' => 'DELETE',
        'callback' => 'motorlan_handle_withdraw_offer',
        'permission_callback' => 'motorlan_is_user_authenticated',
    ));
}

function motorlan_handle_create_offer($request) {
    // Validate Content-Type
    if ( function_exists( 'motorlan_validate_json_content_type' ) ) {
        $valid_type = motorlan_validate_json_content_type( $request );
        if ( is_wp_error( $valid_type ) ) {
            return $valid_type;
        }
    }

    global $wpdb;
    $table_name = motorlan_offers_get_table_name();
    $user_id = get_current_user_id();
    $post_id = (int) $request['id'];
    $amount = $request['amount'];
    $justification = isset($request['justification']) ? sanitize_textarea_field($request['justification']) : null;

    $publication = get_post($post_id);
    if (!$publication || $publication->post_type !=='publicacion') {
        return new WP_Error('invalid_publication', 'La publicación no está disponible.', array('status' => 404));
    }

    if ((int) $publication->post_author === (int) $user_id) {
        return new WP_Error('own_publication', 'No puedes ofertar sobre tu propia publicación.', array('status' => 400));
    }

    $publication_status = function_exists('get_field') ? get_field('publicar_acf', $post_id) : '';
    if (in_array($publication_status, array('paused', 'sold'), true)) {
        return new WP_Error('publication_unavailable', 'La publicación no admite ofertas en este momento.', array('status' => 400));
    }

    $stock = motorlan_offers_get_stock($post_id);
    if ($stock <= 0) {
        return new WP_Error('no_stock', 'La publicación no tiene stock disponible.', array('status' => 400));
    }

    // Daily offers limit logic
    $today = date('Y-m-d');
    $offers_today = get_user_meta($user_id, 'offers_today', true);

    if (empty($offers_today) || $offers_today['date'] !== $today) {
        $offers_today = ['date' => $today, 'count' => 0];
    }

    if ($offers_today['count'] >= 10) {
        return new WP_Error('too_many_offers', 'You have reached the limit of 10 daily offers.', array('status' => 429));
    }

    // Save the offer
    $inserted = $wpdb->insert(
        $table_name,
        array(
            'publication_id' => $post_id,
            'user_id' => $user_id,
            'offer_amount' => $amount,
            'justification' => $justification,
            'offer_date' => current_time('mysql'),
            'status' => MOTORLAN_OFFER_STATUS_PENDING,
            'accepted_at' => null,
            'expires_at' => null,
            'confirmed_at' => null,
        )
    );

    if ($inserted === false) {
        return new WP_Error('db_error', 'No se pudo registrar la oferta en este momento.', array('status' => 500));
    }

    // Update user's offer count
    $offers_today['count']++;
    update_user_meta($user_id, 'offers_today', $offers_today);

    $offer_id = (int) $wpdb->insert_id;
    $offer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $offer_id));
    $offer_payload = motorlan_offers_prepare_offer_item($offer);

    // Notify seller
    do_action( 'motorlan_offer_created', $offer_id );

    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'Oferta enviada correctamente. El vendedor será notificado para revisarla.',
        'remaining_offers' => 10 - $offers_today['count'],
        'data' => $offer_payload,
    ), 200);
}

function motorlan_handle_get_received_offers($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'motorlan_offers';
    $user_id = get_current_user_id();

    $page = isset($request['page']) ? absint($request['page']) : 1;
    $per_page = isset($request['per_page']) ? absint($request['per_page']) : 10;
    $offset = ($page - 1) * $per_page;

    $search = isset($request['search']) ? sanitize_text_field(wp_unslash($request['search'])) : '';
    $status = isset($request['status']) ? sanitize_text_field(wp_unslash($request['status'])) : '';
    $date_from = isset($request['date_from']) ? sanitize_text_field(wp_unslash($request['date_from'])) : '';
    $date_to = isset($request['date_to']) ? sanitize_text_field(wp_unslash($request['date_to'])) : '';

    $where_clauses = array('p.post_author = %d');
    $query_params = array($user_id);

    if (!empty($search)) {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where_clauses[] = '(p.post_title LIKE %s OR u.display_name LIKE %s)';
        $query_params[] = $like;
        $query_params[] = $like;
    }

    $allowed_status = array(
        MOTORLAN_OFFER_STATUS_PENDING,
        MOTORLAN_OFFER_STATUS_ACCEPTED_PENDING,
        MOTORLAN_OFFER_STATUS_CONFIRMED,
        MOTORLAN_OFFER_STATUS_REJECTED,
        MOTORLAN_OFFER_STATUS_EXPIRED,
    );

    if (!empty($status)) {
        $status = motorlan_offers_normalize_status($status);
    }

    if (!empty($status) && in_array($status, $allowed_status, true)) {
        $where_clauses[] = 'o.status = %s';
        $query_params[] = $status === MOTORLAN_OFFER_STATUS_ACCEPTED_LEGACY ? MOTORLAN_OFFER_STATUS_CONFIRMED : $status;
    }

    if (!empty($date_from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
        $where_clauses[] = 'DATE(o.offer_date) >= %s';
        $query_params[] = $date_from;
    }

    if (!empty($date_to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
        $where_clauses[] = 'DATE(o.offer_date) <= %s';
        $query_params[] = $date_to;
    }

    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

    $sortable_columns = array(
        'offer_date' => 'o.offer_date',
        'offer_amount' => 'o.offer_amount',
        'publication_title' => 'p.post_title',
        'user_name' => 'u.display_name',
        'status' => 'o.status',
    );

    $orderby = isset($request['orderby']) ? sanitize_key($request['orderby']) : 'offer_date';
    $order = isset($request['order']) && strtolower($request['order']) === 'asc' ? 'ASC' : 'DESC';

    $orderby_sql = isset($sortable_columns[$orderby]) ? $sortable_columns[$orderby] : $sortable_columns['offer_date'];

    $total_query = $wpdb->prepare(
        "SELECT COUNT(o.id)
         FROM $table_name o
         JOIN {$wpdb->posts} p ON o.publication_id = p.ID
         JOIN {$wpdb->users} u ON o.user_id = u.ID
         $where_sql",
        $query_params
    );
    $total = $wpdb->get_var($total_query);

    $offers_query_params = array_merge($query_params, array($per_page, $offset));

    $query = $wpdb->prepare(
        "SELECT o.*, p.post_title as publication_title, u.display_name as user_name
         FROM $table_name o
         JOIN {$wpdb->posts} p ON o.publication_id = p.ID
         JOIN {$wpdb->users} u ON o.user_id = u.ID
         $where_sql
         ORDER BY $orderby_sql $order
         LIMIT %d OFFSET %d",
        $offers_query_params
    );

    $offers = $wpdb->get_results($query);
    $offers = array_map('motorlan_offers_prepare_offer_item', $offers);

    $response = array(
        'data' => $offers,
        'pagination' => array(
            'total' => (int) $total,
            'totalPages' => ceil($total / $per_page),
            'currentPage' => $page,
            'perPage' => $per_page
        )
    );

    return new WP_REST_Response($response, 200);
}

function motorlan_handle_update_offer_status($request) {
    // Validate Content-Type
    if ( function_exists( 'motorlan_validate_json_content_type' ) ) {
        $valid_type = motorlan_validate_json_content_type( $request );
        if ( is_wp_error( $valid_type ) ) {
            return $valid_type;
        }
    }

    global $wpdb;
    $table_name = motorlan_offers_get_table_name();
    $offer_id = (int) $request['id'];
    $status = $request['status'];
    $user_id = get_current_user_id();

    // Verify that the user is the owner of the publication
    $offer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $offer_id));
    if (!$offer) {
        return new WP_Error('not_found', 'Offer not found.', array('status' => 404));
    }

    $offer = motorlan_offers_refresh_offer($offer);

    $publication = get_post($offer->publication_id);
    if (!$publication || (int) $publication->post_author !== (int) $user_id) {
        return new WP_Error('unauthorized', 'You are not authorized to update this offer.', array('status' => 403));
    }

    if ($status === 'accepted') {
        if (!in_array($offer->status, array(MOTORLAN_OFFER_STATUS_PENDING, MOTORLAN_OFFER_STATUS_EXPIRED), true)) {
            return new WP_Error('invalid_status', 'Solo se pueden aceptar ofertas con estado pendiente o expirado.', array('status' => 400));
        }

        $stock = motorlan_offers_get_stock($offer->publication_id);
        if ($stock <= 0) {
            return new WP_Error('no_stock', 'No hay stock disponible para aceptar la oferta.', array('status' => 400));
        }

        if (!motorlan_offers_has_available_stock_for_acceptance($offer->publication_id, $offer_id)) {
            return new WP_Error(
                'reserved_stock',
                'Ya existe otra oferta aceptada esperando confirmación para este motor.',
                array('status' => 400)
            );
        }

        $accepted_at = current_time('mysql');
        $expires_at = date_i18n('Y-m-d H:i:s', current_time('timestamp') + DAY_IN_SECONDS);

        $wpdb->update(
            $table_name,
            array(
                'status' => MOTORLAN_OFFER_STATUS_ACCEPTED_PENDING,
                'accepted_at' => $accepted_at,
                'expires_at' => $expires_at,
                'confirmed_at' => null,
            ),
            array('id' => $offer_id)
        );

        $updated_offer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $offer_id));

        // Notify Buyer
        do_action( 'motorlan_offer_status_updated', $offer_id, 'accepted' );

        return new WP_REST_Response(array(

            'success' => true,
            'message' => 'Oferta aceptada. El comprador debe confirmar en las próximas 24 horas.',
            'data' => motorlan_offers_prepare_offer_item($updated_offer),
        ), 200);
    }

    if (in_array($offer->status, array(MOTORLAN_OFFER_STATUS_CONFIRMED), true)) {
        return new WP_Error('invalid_status', 'No es posible rechazar una oferta que ya fue confirmada.', array('status' => 400));
    }

    $wpdb->update(
        $table_name,
        array(
            'status' => MOTORLAN_OFFER_STATUS_REJECTED,
            'accepted_at' => null,
            'expires_at' => null,
            'confirmed_at' => null,
        ),
        array('id' => $offer_id)
    );

    $updated_offer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $offer_id));

    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'Oferta rechazada correctamente.',
        'data' => motorlan_offers_prepare_offer_item($updated_offer),
    ), 200);
}

function motorlan_handle_confirm_offer($request) {
    // Validate Content-Type
    if ( function_exists( 'motorlan_validate_json_content_type' ) ) {
        $valid_type = motorlan_validate_json_content_type( $request );
        if ( is_wp_error( $valid_type ) ) {
            return $valid_type;
        }
    }

    global $wpdb;

    $table_name = motorlan_offers_get_table_name();
    $offer_id = (int) $request['id'];
    $user_id = get_current_user_id();

    $offer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $offer_id));
    if (!$offer) {
        return new WP_Error('not_found', 'Offer not found.', array('status' => 404));
    }

    if ((int) $offer->user_id !== (int) $user_id) {
        return new WP_Error('unauthorized', 'No estás autorizado para confirmar esta oferta.', array('status' => 403));
    }

    $offer = motorlan_offers_refresh_offer($offer);

    if ($offer->status === MOTORLAN_OFFER_STATUS_CONFIRMED) {
        return new WP_Error('already_confirmed', 'Esta oferta ya fue confirmada.', array('status' => 400));
    }

    if ($offer->status === MOTORLAN_OFFER_STATUS_REJECTED) {
        return new WP_Error('rejected_offer', 'Esta oferta fue rechazada por el vendedor.', array('status' => 400));
    }

    if ($offer->status === MOTORLAN_OFFER_STATUS_PENDING) {
        return new WP_Error('pending_offer', 'La oferta aún no ha sido aceptada por el vendedor.', array('status' => 400));
    }

    if ($offer->status === MOTORLAN_OFFER_STATUS_EXPIRED) {
        return new WP_Error('expired_offer', 'El plazo para confirmar esta oferta ha expirado.', array('status' => 400));
    }

    if ($offer->status !== MOTORLAN_OFFER_STATUS_ACCEPTED_PENDING) {
        return new WP_Error('invalid_status', 'La oferta no puede confirmarse en su estado actual.', array('status' => 400));
    }

    // Start Transaction to prevent race conditions
    $wpdb->query('START TRANSACTION');

    // Lock the stock row (pessimistic locking)
    // We select meta_id to lock the specific row in postmeta
    $wpdb->get_results( $wpdb->prepare("SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'stock' FOR UPDATE", $offer->publication_id) );

    // Re-fetch stock directly from DB to ensure we have the locked value (bypass WP object cache)
    $current_stock = $wpdb->get_var( $wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'stock'", $offer->publication_id) );
    
    // Cast to int, treating empty as 0
    $current_stock = (int) $current_stock;

    if ($current_stock <= 0) {
        $wpdb->update(
            $table_name,
            array(
                'status' => MOTORLAN_OFFER_STATUS_EXPIRED,
                'accepted_at' => null,
                'expires_at' => null,
                'confirmed_at' => null,
            ),
            array('id' => $offer_id)
        );
        
        $wpdb->query('COMMIT'); // Commit the status change

        return new WP_Error('no_stock', 'La publicación se quedó sin stock al intentar confirmar la oferta.', array('status' => 400));
    }

    $purchase_data = motorlan_offers_create_purchase_from_offer($offer);
    
    if (is_wp_error($purchase_data)) {
        $wpdb->query('ROLLBACK');
        return $purchase_data;
    }

    $new_stock = max(0, $current_stock - 1);

    if (function_exists('update_field')) {
        update_field('stock', $new_stock, $offer->publication_id);
        // Solo pausar cuando el stock llegue a 0
        if ($new_stock === 0) {
            update_field('publicar_acf', 'paused', $offer->publication_id);
        }
    }
    update_post_meta($offer->publication_id, 'stock', $new_stock);
    if ($new_stock === 0) {
        update_post_meta($offer->publication_id, 'publicar_acf', 'paused');
    }

    $confirmed_at = current_time('mysql');

    $updated = $wpdb->update(
        $table_name,
        array(
            'status' => MOTORLAN_OFFER_STATUS_CONFIRMED,
            'confirmed_at' => $confirmed_at,
            'expires_at' => null,
        ),
        array('id' => $offer_id)
    );

    if ( $updated === false ) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('db_error', 'Error al actualizar el estado de la oferta.', array('status' => 500));
    }

    $wpdb->query('COMMIT');

    $updated_offer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $offer_id));

    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'Oferta confirmada correctamente. Se generó la compra asociada.',
        'data' => motorlan_offers_prepare_offer_item($updated_offer),
        'purchase_uuid' => is_array($purchase_data) ? $purchase_data['uuid'] : $purchase_data,
        'purchase_id' => is_array($purchase_data) ? (int) $purchase_data['id'] : null,
        'stock_remaining' => $new_stock,
    ), 200);
}

function motorlan_handle_get_sent_offers($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'motorlan_offers';
    $user_id = get_current_user_id();

    $page = isset($request['page']) ? absint($request['page']) : 1;
    $per_page = isset($request['per_page']) ? absint($request['per_page']) : 10;
    $offset = ($page - 1) * $per_page;

    $search = isset($request['search']) ? sanitize_text_field(wp_unslash($request['search'])) : '';
    $status = isset($request['status']) ? sanitize_text_field(wp_unslash($request['status'])) : '';
    $date_from = isset($request['date_from']) ? sanitize_text_field(wp_unslash($request['date_from'])) : '';
    $date_to = isset($request['date_to']) ? sanitize_text_field(wp_unslash($request['date_to'])) : '';

    $where_clauses = array('o.user_id = %d');
    $query_params = array($user_id);

    if (!empty($search)) {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where_clauses[] = '(p.post_title LIKE %s OR o.justification LIKE %s)';
        $query_params[] = $like;
        $query_params[] = $like;
    }

    $allowed_status = array(
        MOTORLAN_OFFER_STATUS_PENDING,
        MOTORLAN_OFFER_STATUS_ACCEPTED_PENDING,
        MOTORLAN_OFFER_STATUS_CONFIRMED,
        MOTORLAN_OFFER_STATUS_REJECTED,
        MOTORLAN_OFFER_STATUS_EXPIRED,
    );

    if (!empty($status)) {
        $status = motorlan_offers_normalize_status($status);
    }

    if (!empty($status) && in_array($status, $allowed_status, true)) {
        $where_clauses[] = 'o.status = %s';
        $query_params[] = $status;
    }

    if (!empty($date_from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
        $where_clauses[] = 'DATE(o.offer_date) >= %s';
        $query_params[] = $date_from;
    }

    if (!empty($date_to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
        $where_clauses[] = 'DATE(o.offer_date) <= %s';
        $query_params[] = $date_to;
    }

    $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

    $sortable_columns = array(
        'offer_date' => 'o.offer_date',
        'offer_amount' => 'o.offer_amount',
        'publication_title' => 'p.post_title',
        'status' => 'o.status',
    );

    $orderby = isset($request['orderby']) ? sanitize_key($request['orderby']) : 'offer_date';
    $order = isset($request['order']) && strtolower($request['order']) === 'asc' ? 'ASC' : 'DESC';
    $orderby_sql = isset($sortable_columns[$orderby]) ? $sortable_columns[$orderby] : $sortable_columns['offer_date'];

    $total_query = $wpdb->prepare(
        "SELECT COUNT(o.id)
         FROM $table_name o
         JOIN {$wpdb->posts} p ON o.publication_id = p.ID
         $where_sql",
        $query_params
    );
    $total = $wpdb->get_var($total_query);

    $offers_query_params = array_merge($query_params, array($per_page, $offset));

    $query = $wpdb->prepare(
        "SELECT o.*, p.post_title as publication_title
         FROM $table_name o
         JOIN {$wpdb->posts} p ON o.publication_id = p.ID
         $where_sql
         ORDER BY $orderby_sql $order
         LIMIT %d OFFSET %d",
        $offers_query_params
    );

    $offers = $wpdb->get_results($query);
    $offers = array_map('motorlan_offers_prepare_offer_item', $offers);

    $response = array(
        'data' => $offers,
        'pagination' => array(
            'total' => (int) $total,
            'totalPages' => ceil($total / $per_page),
            'currentPage' => $page,
            'perPage' => $per_page
        )
    );

    return new WP_REST_Response($response, 200);
}

function motorlan_handle_withdraw_offer($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'motorlan_offers';
    $offer_id = absint($request['id']);
    $user_id = get_current_user_id();

    $offer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d AND user_id = %d", $offer_id, $user_id));

    if (!$offer) {
        return new WP_Error('not_found', 'Offer not found or you are not authorized to withdraw it.', array('status' => 404));
    }

    $offer = motorlan_offers_refresh_offer($offer);

    if (!in_array($offer->status, array(MOTORLAN_OFFER_STATUS_PENDING, MOTORLAN_OFFER_STATUS_EXPIRED), true)) {
        return new WP_Error('invalid_status', 'Only pending or expired offers can be withdrawn.', array('status' => 400));
    }

    $deleted = $wpdb->delete($table_name, array('id' => $offer_id), array('%d'));

    if ($deleted === false) {
        return new WP_Error('db_error', 'Unable to withdraw the offer at this time.', array('status' => 500));
    }

    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'Offer withdrawn successfully.',
    ), 200);
}
