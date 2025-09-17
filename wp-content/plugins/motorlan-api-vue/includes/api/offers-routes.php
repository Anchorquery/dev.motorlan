<?php

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
}

add_action('rest_api_init', 'motorlan_register_offers_routes');

function motorlan_handle_create_offer($request) {
    $user_id = get_current_user_id();
    $post_id = $request['id'];
    $amount = $request['amount'];

    // Lógica para el límite de ofertas diarias
    $today = date('Y-m-d');
    $offers_today = get_user_meta($user_id, 'offers_today', true);

    if (empty($offers_today) || $offers_today['date'] !== $today) {
        $offers_today = ['date' => $today, 'count' => 0];
    }

    if ($offers_today['count'] >= 10) {
        return new WP_Error('too_many_offers', 'Has alcanzado el límite de 10 ofertas diarias.', array('status' => 429));
    }

    // Guardar la oferta (aquí se podría crear un CPT para ofertas o guardarlo en post_meta)
    // Por simplicidad, lo guardaremos en los metas de la publicación
    $offer_data = array(
        'user_id' => $user_id,
        'amount' => $amount,
        'date' => current_time('mysql')
    );

    add_post_meta($post_id, 'publication_offer', $offer_data);

    // Actualizar el contador de ofertas del usuario
    $offers_today['count']++;
    update_user_meta($user_id, 'offers_today', $offers_today);

    // Aquí también se podría enviar una notificación al vendedor

    return new WP_REST_Response(array(
        'success' => true,
        'message' => 'Oferta enviada correctamente.',
        'remaining_offers' => 10 - $offers_today['count']
    ), 200);
}