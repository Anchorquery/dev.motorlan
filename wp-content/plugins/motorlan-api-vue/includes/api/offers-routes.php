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
}

function motorlan_handle_create_offer($request) {
    $user_id = get_current_user_id();
    $post_id = $request['id'];
    $amount = $request['amount'];

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
    $offer_data = array(
        'user_id' => $user_id,
        'amount' => $amount,
        'date' => current_time('mysql')
    );

    add_post_meta($post_id, 'publication_offer', $offer_data);

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
    $user_id = get_current_user_id();

    $args = array(
        'post_type' => 'publicacion',
        'author' => $user_id,
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'publication_offer',
                'compare' => 'EXISTS'
            )
        )
    );

    $publications = get_posts($args);
    $offers = array();

    foreach ($publications as $publication) {
        $publication_offers = get_post_meta($publication->ID, 'publication_offer');
        if (!empty($publication_offers)) {
            foreach($publication_offers as $offer_data) {
                $user_info = get_userdata($offer_data['user_id']);
                $offers[] = array(
                    'publication_id' => $publication->ID,
                    'publication_title' => $publication->post_title,
                    'offer_amount' => $offer_data['amount'],
                    'offer_date' => $offer_data['date'],
                    'user_name' => $user_info ? $user_info->display_name : 'Unknown User'
                );
            }
        }
    }

    return new WP_REST_Response($offers, 200);
}

function motorlan_handle_get_sent_offers($request) {
    $user_id = get_current_user_id();
    global $wpdb;

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'publication_offer'",
    ));

    $sent_offers = array();
    foreach ($results as $result) {
        $offer_data = maybe_unserialize($result->meta_value);
        if (isset($offer_data['user_id']) && $offer_data['user_id'] == $user_id) {
            $publication = get_post($result->post_id);
            if ($publication) {
                $sent_offers[] = array(
                    'publication_id' => $publication->ID,
                    'publication_title' => $publication->post_title,
                    'offer_amount' => $offer_data['amount'],
                    'offer_date' => $offer_data['date'],
                );
            }
        }
    }

    return new WP_REST_Response($sent_offers, 200);
}