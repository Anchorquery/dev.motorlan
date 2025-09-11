<?php

add_action('rest_api_init', function () {
    register_rest_route('motorlan/v1', '/profile', [
        'methods' => 'GET',
        'callback' => 'get_user_profile_data',
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ]);

    register_rest_route('motorlan/v1', '/profile', [
        'methods' => 'POST',
        'callback' => 'update_user_profile_data',
        'permission_callback' => function () {
            return is_user_logged_in();
        }
    ]);
});

function get_user_profile_data() {
    $user_id = get_current_user_id();
    $user_data = get_userdata($user_id);
    $user_meta = get_user_meta($user_id);

    $profile_data = [
        'personal_data' => [
            'nombre' => $user_data->first_name,
            'apellidos' => $user_data->last_name,
            'email' => $user_data->user_email,
            'telefono' => $user_meta['telefono'][0] ?? '',
            'avatar' => get_avatar_url($user_id),
        ],
        'company_data' => [
            'nombre' => $user_meta['company_nombre'][0] ?? '',
            'direccion' => $user_meta['company_direccion'][0] ?? '',
            'cp' => $user_meta['company_cp'][0] ?? '',
            'persona_contacto' => $user_meta['company_persona_contacto'][0] ?? '',
            'email_contacto' => $user_meta['company_email_contacto'][0] ?? '',
            'tel_contacto' => $user_meta['company_tel_contacto'][0] ?? '',
            'cif_nif' => $user_meta['company_cif_nif'][0] ?? '',
        ],
    ];

    return new WP_REST_Response($profile_data, 200);
}

function update_user_profile_data(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    $params = $request->get_json_params();

    // Update personal data
    if (isset($params['personal_data'])) {
        $personal_data = $params['personal_data'];
        wp_update_user([
            'ID' => $user_id,
            'first_name' => sanitize_text_field($personal_data['nombre']),
            'last_name' => sanitize_text_field($personal_data['apellidos']),
            'user_email' => sanitize_email($personal_data['email']),
        ]);
        update_user_meta($user_id, 'telefono', sanitize_text_field($personal_data['telefono']));
    }

    // Update company data
    if (isset($params['company_data'])) {
        $company_data = $params['company_data'];
        foreach ($company_data as $key => $value) {
            update_user_meta($user_id, 'company_' . $key, sanitize_text_field($value));
        }
    }

    return new WP_REST_Response(['message' => 'Profile updated successfully'], 200);
}