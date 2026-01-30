<?php

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Register custom REST API routes for profile.
 */
function motorlan_register_profile_rest_routes()
{
    $namespace = 'motorlan/v1';

    register_rest_route($namespace, '/profile', [
        'methods' => 'GET',
        'callback' => 'get_user_profile_data',
        'permission_callback' => 'motorlan_is_user_authenticated'
    ]);

    register_rest_route($namespace, '/profile', [
        'methods' => 'POST',
        'callback' => 'update_user_profile_data',
        'permission_callback' => 'motorlan_is_user_authenticated'
    ]);

    register_rest_route($namespace, '/profile/change-password-request', [
        'methods' => 'POST',
        'callback' => 'motorlan_handle_change_password_request',
        'permission_callback' => 'motorlan_is_user_authenticated'
    ]);

    register_rest_route($namespace, '/profile/change-password-confirm', [
        'methods' => 'POST',
        'callback' => 'motorlan_handle_change_password_confirm',
        'permission_callback' => 'motorlan_is_user_authenticated'
    ]);
}
add_action('rest_api_init', 'motorlan_register_profile_rest_routes');

function motorlan_get_user_avatar_url($user_id) {
    $attachment_id = (int) get_user_meta($user_id, 'avatar_attachment_id', true);
    if ($attachment_id) {
        $avatar_url = wp_get_attachment_url($attachment_id);
        if ($avatar_url) {
            return $avatar_url;
        }
    }

    return get_avatar_url($user_id);
}

if ( ! function_exists( 'get_user_profile_data' ) ) {
function get_user_profile_data() {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return new WP_Error( 'rest_not_logged_in', 'Sorry, you are not allowed to do that.', array( 'status' => 401 ) );
    }
    $user_data = get_userdata($user_id);
    if ( ! $user_data ) {
        return new WP_Error( 'user_not_found', 'User not found.', array( 'status' => 404 ) );
    }
    $user_meta = get_user_meta($user_id);

    $profile_data = [
        'id' => $user_id,
        'personal_data' => [
            'nombre' => $user_data->first_name,
            'apellidos' => $user_data->last_name,
            'email' => $user_data->user_email,
            'telefono' => $user_meta['telefono'][0] ?? '',
            'avatar' => motorlan_get_user_avatar_url($user_id),
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
}

if ( ! function_exists( 'update_user_profile_data' ) ) {
function update_user_profile_data(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return new WP_Error( 'rest_not_logged_in', 'Sorry, you are not allowed to do that.', array( 'status' => 401 ) );
    }
    if ( ! get_userdata( $user_id ) ) {
        return new WP_Error( 'user_not_found', 'User not found.', array( 'status' => 404 ) );
    }

    // WordPress handles multipart/form-data by default with REST API.
    // We can get text fields from get_param and files from get_file_params.
    $params = $request->get_params();
    $files = $request->get_file_params();

    // Handle avatar upload
    if (!empty($files['avatar'])) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        $attachment_id = media_handle_upload('avatar', 0);

        if (is_wp_error($attachment_id)) {
            return new WP_REST_Response(['message' => $attachment_id->get_error_message()], 400);
        }
        
        // Save avatar URL in user meta (or use a dedicated avatar plugin's function)
        update_user_meta($user_id, 'avatar_attachment_id', $attachment_id);
    }

    // The parameters might be sent as JSON strings inside the form data or as arrays in JSON requests.
    $personal_data_param = $params['personal_data'] ?? null;
    if (is_string($personal_data_param)) {
        $personal_data = json_decode($personal_data_param, true);
    } elseif (is_array($personal_data_param)) {
        $personal_data = $personal_data_param;
    } else {
        $personal_data = null;
    }

    $company_data_param = $params['company_data'] ?? null;
    if (is_string($company_data_param)) {
        $company_data = json_decode($company_data_param, true);
    } elseif (is_array($company_data_param)) {
        $company_data = $company_data_param;
    } else {
        $company_data = null;
    }

    // Update personal data
    if ($personal_data) {
        wp_update_user([
            'ID' => $user_id,
            'first_name' => sanitize_text_field($personal_data['nombre'] ?? ''),
            'last_name' => sanitize_text_field($personal_data['apellidos'] ?? ''),
            'user_email' => sanitize_email($personal_data['email'] ?? ''),
        ]);
        if ( array_key_exists( 'telefono', $personal_data ) ) {
            update_user_meta($user_id, 'telefono', sanitize_text_field($personal_data['telefono']));
        }
    }

    // Update company data
    if ($company_data) {
        foreach ($company_data as $key => $value) {
            update_user_meta($user_id, 'company_' . $key, sanitize_text_field($value));
        }
    }
    
    $response_data = [
        'message' => 'Profile updated successfully',
        'avatar' => motorlan_get_user_avatar_url($user_id)
    ];

    return new WP_REST_Response($response_data, 200);
}
}

/**
 * Handle password change request by sending a verification code.
 */
function motorlan_handle_change_password_request(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    $user = get_userdata($user_id);

    if (!$user) {
        return new WP_Error('user_not_found', 'Usuario no encontrado.', ['status' => 404]);
    }

    // Generate a 6-digit code
    $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiry = time() + (15 * 60); // 15 minutes validity

    // Save in user meta
    update_user_meta($user_id, '_password_reset_code', $code);
    update_user_meta($user_id, '_password_reset_expiry', $expiry);

    // Send email (assuming Motorlan_Notification_Manager or wp_mail)
    $to = $user->user_email;
    $subject = 'Código de verificación para cambio de contraseña';
    $message = "Hola {$user->first_name},\n\nTu código de verificación para cambiar la contraseña es: **{$code}**\n\nEste código expirará en 15 minutos.";

    if (class_exists('Motorlan_Notification_Manager')) {
        $notification_manager = new Motorlan_Notification_Manager();
        $notification_manager->create_notification(
            $user_id,
            'password_reset_code',
            $subject,
            $message,
            ['code' => $code],
            ['email']
        );
    } else {
        wp_mail($to, $subject, $message);
    }

    return new WP_REST_Response(['message' => 'Código de verificación enviado al correo.'], 200);
}

/**
 * Handle password change confirmation.
 */
function motorlan_handle_change_password_confirm(WP_REST_Request $request) {
    $user_id = get_current_user_id();
    $params = $request->get_json_params();
    $code = sanitize_text_field($params['code'] ?? '');
    $new_password = $params['password'] ?? '';

    if (empty($code) || empty($new_password)) {
        return new WP_Error('missing_params', 'Faltan parámetros requeridos.', ['status' => 400]);
    }

    $saved_code = get_user_meta($user_id, '_password_reset_code', true);
    $expiry = get_user_meta($user_id, '_password_reset_expiry', true);

    if (!$saved_code || $saved_code !== $code || time() > $expiry) {
        return new WP_Error('invalid_code', 'El código de verificación es incorrecto o ha expirado.', ['status' => 400]);
    }

    // Update password
    wp_set_password($new_password, $user_id);

    // Clear code
    delete_user_meta($user_id, '_password_reset_code');
    delete_user_meta($user_id, '_password_reset_expiry');

    return new WP_REST_Response(['message' => 'Contraseña actualizada correctamente.'], 200);
}
