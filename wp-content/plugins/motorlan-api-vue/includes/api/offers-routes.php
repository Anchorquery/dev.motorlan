<?php

add_action('rest_api_init', 'motorlan_register_offers_routes');

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
                    return in_array($param, ['accepted', 'rejected']);
                }
            ),
        ),
    ));
}

function motorlan_handle_create_offer($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'motorlan_offers';
    $user_id = get_current_user_id();
    $post_id = $request['id'];
    $amount = $request['amount'];
    $justification = $request['justification'];

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
    $wpdb->insert(
        $table_name,
        array(
            'publication_id' => $post_id,
            'user_id' => $user_id,
            'offer_amount' => $amount,
            'justification' => $justification,
            'offer_date' => current_time('mysql'),
            'status' => 'pending'
        )
    );

    // Update user's offer count
    $offers_today['count']++;
    update_user_meta($user_id, 'offers_today', $offers_today);

    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'Offer sent successfully.',
        'remaining_offers' => 10 - $offers_today['count']
    ), 200);
}

function motorlan_handle_get_received_offers($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'motorlan_offers';
    $user_id = get_current_user_id();

    $page = isset($request['page']) ? absint($request['page']) : 1;
    $per_page = isset($request['per_page']) ? absint($request['per_page']) : 10;
    $offset = ($page - 1) * $per_page;

    $total_query = $wpdb->prepare(
        "SELECT COUNT(o.id)
         FROM $table_name o
         JOIN {$wpdb->posts} p ON o.publication_id = p.ID
         WHERE p.post_author = %d",
        $user_id
    );
    $total = $wpdb->get_var($total_query);

    $query = $wpdb->prepare(
        "SELECT o.*, p.post_title as publication_title, u.display_name as user_name
         FROM $table_name o
         JOIN {$wpdb->posts} p ON o.publication_id = p.ID
         JOIN {$wpdb->users} u ON o.user_id = u.ID
         WHERE p.post_author = %d
         LIMIT %d OFFSET %d",
        $user_id,
        $per_page,
        $offset
    );

    $offers = $wpdb->get_results($query);

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
    global $wpdb;
    $table_name = $wpdb->prefix . 'motorlan_offers';
    $offer_id = $request['id'];
    $status = $request['status'];
    $user_id = get_current_user_id();

    // Verify that the user is the owner of the publication
    $offer = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $offer_id));
    if (!$offer) {
        return new WP_Error('not_found', 'Offer not found.', array('status' => 404));
    }

    $publication = get_post($offer->publication_id);
    if (!$publication || $publication->post_author != $user_id) {
        return new WP_Error('unauthorized', 'You are not authorized to update this offer.', array('status' => 403));
    }

    $wpdb->update(
        $table_name,
        array('status' => $status),
        array('id' => $offer_id)
    );

    return new WP_REST_Response(array('success' => true, 'message' => 'Offer status updated successfully.'), 200);
}

function motorlan_handle_get_sent_offers($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'motorlan_offers';
    $user_id = get_current_user_id();

    $page = isset($request['page']) ? absint($request['page']) : 1;
    $per_page = isset($request['per_page']) ? absint($request['per_page']) : 10;
    $offset = ($page - 1) * $per_page;

    $total_query = $wpdb->prepare(
        "SELECT COUNT(id) FROM $table_name WHERE user_id = %d",
        $user_id
    );
    $total = $wpdb->get_var($total_query);

    $query = $wpdb->prepare(
        "SELECT o.*, p.post_title as publication_title
         FROM $table_name o
         JOIN {$wpdb->posts} p ON o.publication_id = p.ID
         WHERE o.user_id = %d
         LIMIT %d OFFSET %d",
        $user_id,
        $per_page,
        $offset
    );

    $offers = $wpdb->get_results($query);

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