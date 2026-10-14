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

    // Cambio de email: se confirma con el enlace enviado al email NUEVO. Pública porque
    // el enlace puede abrirse en otro dispositivo sin sesión; el token (32 caracteres,
    // guardado como hash, 24 h) es la prueba.
    register_rest_route($namespace, '/profile/confirm-email-change', [
        'methods' => 'POST',
        'callback' => 'motorlan_handle_confirm_email_change',
        'permission_callback' => '__return_true'
    ]);

    register_rest_route($namespace, '/profile/cancel-email-change', [
        'methods' => 'POST',
        'callback' => 'motorlan_handle_cancel_email_change',
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
        // Email nuevo pendiente de confirmar (el email de la cuenta no cambia hasta entonces)
        'pending_email' => motorlan_get_pending_email($user_id),
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

        // Solo imágenes (antes aceptaba pdf, zip, doc...)
        $attachment_id = media_handle_upload('avatar', 0, [], [
            'test_form' => false,
            'mimes'     => [
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png'          => 'image/png',
                'webp'         => 'image/webp',
                'gif'          => 'image/gif',
            ],
        ]);

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
        $current_user_obj = get_userdata($user_id);
        $first_name = sanitize_text_field($personal_data['nombre'] ?? '');
        $last_name  = sanitize_text_field($personal_data['apellidos'] ?? '');

        $user_update = [
            'ID'         => $user_id,
            'first_name' => $first_name,
            'last_name'  => $last_name,
        ];

        // Nombre público (vendedor, "Bienvenido, X") coherente con nombre y apellidos, pero
        // solo si el actual era el derivado (vacío, login o nombre+apellidos anteriores): no
        // pisar un nombre público personalizado (p. ej. nombre comercial).
        $full_name = trim($first_name . ' ' . $last_name);
        $previous_full = trim($current_user_obj->first_name . ' ' . $current_user_obj->last_name);
        $current_display = (string) $current_user_obj->display_name;
        $display_is_derived = $current_display === '' || $current_display === $current_user_obj->user_login
            || $current_display === $previous_full || $current_display === $current_user_obj->user_email;
        if ($full_name !== '' && $display_is_derived) {
            $user_update['display_name'] = $full_name;
        }

        // Email: solo si es válido y distinto. SEGURIDAD: ya no se cambia directamente.
        // Antes bastaba con escribir el email de otra persona para que, al volver a
        // entrar, se transfirieran sus chats de invitado (y con una sesión robada se
        // podía cambiar el email y pedir "olvidé mi contraseña"). Ahora se exige la
        // contraseña actual, se guarda como pendiente y solo se aplica cuando se
        // confirma con el enlace enviado al email NUEVO.
        $pending_new_email = null;
        if (array_key_exists('email', $personal_data)) {
            $new_email = sanitize_email((string) $personal_data['email']);
            if (!is_email($new_email)) {
                return new WP_Error('invalid_email', __('El email no es válido.', 'motorlan-api-vue'), ['status' => 400]);
            }
            if (strtolower($new_email) !== strtolower($current_user_obj->user_email)) {
                if (class_exists('Motorlan_Rate_Limiter')
                    && is_wp_error(Motorlan_Rate_Limiter::check('email_change_attempt', 'u' . $user_id, 5, 15 * 60))) {
                    return new WP_Error('too_many_attempts', __('Demasiados intentos. Inténtalo más tarde.', 'motorlan-api-vue'), ['status' => 429]);
                }

                $current_password = (string) ($params['current_password'] ?? ($personal_data['current_password'] ?? ''));
                if ($current_password === '') {
                    return new WP_Error('password_required', __('Introduce tu contraseña actual para cambiar el email.', 'motorlan-api-vue'), ['status' => 400]);
                }
                if (!wp_check_password($current_password, $current_user_obj->user_pass, $user_id)) {
                    return new WP_Error('incorrect_password', __('La contraseña actual no es correcta.', 'motorlan-api-vue'), ['status' => 400]);
                }

                $owner = email_exists($new_email);
                if ($owner && (int) $owner !== (int) $user_id) {
                    return new WP_Error('email_exists', __('Ese email ya está en uso por otra cuenta.', 'motorlan-api-vue'), ['status' => 400]);
                }

                if (class_exists('Motorlan_Rate_Limiter')
                    && is_wp_error(Motorlan_Rate_Limiter::check('email_change_request', 'u' . $user_id, 5, HOUR_IN_SECONDS))) {
                    return new WP_Error('too_many_requests', __('Has solicitado demasiados cambios de email. Inténtalo más tarde.', 'motorlan-api-vue'), ['status' => 429]);
                }

                $pending_new_email = $new_email;
            }
        }

        $update_result = wp_update_user($user_update);
        if (is_wp_error($update_result)) {
            return new WP_Error($update_result->get_error_code(), $update_result->get_error_message(), ['status' => 400]);
        }

        if ($pending_new_email !== null) {
            motorlan_start_email_change($user_id, $pending_new_email);
        }

        if ( array_key_exists( 'telefono', $personal_data ) ) {
            update_user_meta($user_id, 'telefono', sanitize_text_field($personal_data['telefono']));
        }
    }

    // Update company data
    $company_keys = ['nombre', 'direccion', 'cp', 'persona_contacto', 'email_contacto', 'tel_contacto', 'cif_nif'];
    if (is_array($company_data)) {
        if (empty($company_data)) {
            // Desmarcar "empresa" envía {}: borrar los datos (antes no se borraba nada)
            foreach ($company_keys as $key) {
                delete_user_meta($user_id, 'company_' . $key);
            }
        } else {
            foreach ($company_data as $key => $value) {
                // Lista blanca: antes aceptaba cualquier company_<x>
                if (in_array($key, $company_keys, true) && is_scalar($value)) {
                    update_user_meta($user_id, 'company_' . $key, sanitize_text_field((string) $value));
                }
            }
        }
    }
    
    $response_data = [
        'message' => __('Datos guardados correctamente.', 'motorlan-api-vue'),
        'avatar' => motorlan_get_user_avatar_url($user_id),
        'pending_email' => motorlan_get_pending_email($user_id),
    ];

    if (!empty($pending_new_email)) {
        $response_data['code'] = 'email_change_pending';
        $response_data['email_change_pending'] = true;
        $response_data['message'] = sprintf(
            __('Te hemos enviado un enlace a %s. Tu email no cambiará hasta que lo confirmes.', 'motorlan-api-vue'),
            $pending_new_email
        );
    }

    return new WP_REST_Response($response_data, 200);
}
}

/**
 * Email nuevo pendiente de confirmar ('' si no hay o caducó).
 */
function motorlan_get_pending_email($user_id) {
    $email = (string) get_user_meta($user_id, '_motorlan_pending_email', true);
    $expires = (int) get_user_meta($user_id, '_motorlan_pending_email_expires', true);
    if ($email === '' || ($expires && time() > $expires)) {
        return '';
    }
    return $email;
}

function motorlan_clear_pending_email($user_id) {
    delete_user_meta($user_id, '_motorlan_pending_email');
    delete_user_meta($user_id, '_motorlan_pending_email_token');
    delete_user_meta($user_id, '_motorlan_pending_email_expires');
}

function motorlan_email_change_token_hash($token) {
    return wp_hash('motorlan_email_change|' . $token);
}

/**
 * Guarda el email pendiente y envía el enlace de confirmación al email NUEVO.
 * El token se guarda solo como hash; caduca en 24 h; una nueva solicitud sustituye
 * a la anterior.
 */
function motorlan_start_email_change($user_id, $new_email) {
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }

    $token = wp_generate_password(32, false);
    update_user_meta($user_id, '_motorlan_pending_email', $new_email);
    update_user_meta($user_id, '_motorlan_pending_email_token', motorlan_email_change_token_hash($token));
    update_user_meta($user_id, '_motorlan_pending_email_expires', time() + DAY_IN_SECONDS);

    $path = function_exists('motorlan_account_url')
        ? motorlan_account_url('verify-email?email_change=' . $token, $user_id)
        : '/mi-cuenta/verify-email?email_change=' . $token;
    $url = (strpos($path, 'http') === 0) ? $path : home_url($path);

    $sent = motorlan_send_email_change_confirmation($user, $new_email, $url);

    if (class_exists('Motorlan_Security_Logger')) {
        Motorlan_Security_Logger::log('email_change_requested', [
            'user_id' => $user_id,
            'sent'    => (bool) $sent,
        ]);
    }

    if (defined('MOTORLAN_EXPOSE_DEV_TOKENS') && MOTORLAN_EXPOSE_DEV_TOKENS) {
        error_log(sprintf('[Motorlan][DEV] Email change URL for user_id=%d: %s', $user_id, $url));
    }

    return $sent;
}

/**
 * Email de confirmación del cambio de email (al email NUEVO), con la plantilla base
 * de los correos de Motorlan y en el idioma del usuario.
 */
function motorlan_send_email_change_confirmation(WP_User $user, $new_email, $url) {
    $switched = switch_to_locale(get_user_locale($user->ID));
    try {
        $subject = __('Confirma tu nuevo email', 'motorlan-api-vue');

        $content  = '<h1>' . esc_html__('Confirma tu nuevo email', 'motorlan-api-vue') . '</h1>';
        $content .= '<p>' . sprintf(esc_html__('Hola %s,', 'motorlan-api-vue'), esc_html($user->display_name)) . '</p>';
        $content .= '<p>' . sprintf(
            esc_html__('Has solicitado usar %s como email de tu cuenta de Motorlan. Para confirmarlo, haz clic en el siguiente botón:', 'motorlan-api-vue'),
            '<strong>' . esc_html($new_email) . '</strong>'
        ) . '</p>';
        $content .= '<div style="text-align: center;"><a href="' . esc_url($url) . '" class="button">' . esc_html__('Confirmar mi nuevo email', 'motorlan-api-vue') . '</a></div>';
        $content .= '<p>' . esc_html__('Este enlace expirará en 24 horas. Hasta que lo confirmes, tu cuenta seguirá usando el email anterior.', 'motorlan-api-vue') . '</p>';
        $content .= '<p>' . esc_html__('Si el botón no funciona, copia y pega el siguiente enlace en tu navegador:', 'motorlan-api-vue') . '</p>';
        $content .= '<p style="word-break: break-all;"><a href="' . esc_url($url) . '">' . esc_html($url) . '</a></p>';
        $content .= '<div class="info-box">' . esc_html__('Si no has solicitado este cambio, ignora este mensaje: tu cuenta no se modificará.', 'motorlan-api-vue') . '</div>';

        $body = $content;
        $base_template = defined('MOTORLAN_API_VUE_PATH') ? MOTORLAN_API_VUE_PATH . 'includes/email-templates/base.php' : '';
        if ($base_template && file_exists($base_template)) {
            $args = ['user' => $user, 'title' => $subject];
            ob_start();
            include $base_template;
            $base_html = ob_get_clean();
            $body = str_replace(['{{subject}}', '{{content}}'], [esc_html($subject), $content], $base_html);
        }

        return wp_mail($new_email, '[Motorlan] ' . $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
    } finally {
        if ($switched) {
            restore_previous_locale();
        }
    }
}

/**
 * Confirma el cambio de email con el token del enlace.
 */
function motorlan_handle_confirm_email_change(WP_REST_Request $request) {
    $params = $request->get_json_params();
    $token = trim(sanitize_text_field($params['token'] ?? ''));

    if ($token === '') {
        return new WP_REST_Response(['success' => false, 'code' => 'token_required', 'message' => __('Falta el token de confirmación.', 'motorlan-api-vue')], 400);
    }

    if (class_exists('Motorlan_Rate_Limiter') && !Motorlan_Rate_Limiter::check_limit('email_change_confirm', 20, 15 * 60)) {
        return new WP_REST_Response(['success' => false, 'code' => 'too_many_attempts', 'message' => __('Demasiados intentos. Inténtalo más tarde.', 'motorlan-api-vue')], 429);
    }

    global $wpdb;
    $user_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
        '_motorlan_pending_email_token',
        motorlan_email_change_token_hash($token)
    ));
    $user = $user_id ? get_userdata($user_id) : null;

    if (!$user) {
        return new WP_REST_Response(['success' => false, 'code' => 'invalid_token', 'message' => __('El enlace para confirmar el email no es válido o ya se ha usado.', 'motorlan-api-vue')], 400);
    }

    $expires = (int) get_user_meta($user_id, '_motorlan_pending_email_expires', true);
    $new_email = (string) get_user_meta($user_id, '_motorlan_pending_email', true);

    if ($expires && time() > $expires) {
        motorlan_clear_pending_email($user_id);
        return new WP_REST_Response(['success' => false, 'code' => 'token_expired', 'message' => __('El enlace para confirmar el email ha caducado. Vuelve a cambiarlo desde tu perfil.', 'motorlan-api-vue')], 400);
    }

    if (!is_email($new_email)) {
        motorlan_clear_pending_email($user_id);
        return new WP_REST_Response(['success' => false, 'code' => 'invalid_token', 'message' => __('El enlace para confirmar el email no es válido o ya se ha usado.', 'motorlan-api-vue')], 400);
    }

    // Puede haberse registrado otra cuenta con ese email mientras tanto
    $owner = email_exists($new_email);
    if ($owner && (int) $owner !== $user_id) {
        motorlan_clear_pending_email($user_id);
        return new WP_REST_Response(['success' => false, 'code' => 'email_exists', 'message' => __('Ese email ya está en uso por otra cuenta.', 'motorlan-api-vue')], 400);
    }

    // wp_update_user() avisa al email ANTERIOR del cambio (aviso nativo de WordPress)
    $result = wp_update_user(['ID' => $user_id, 'user_email' => $new_email]);
    if (is_wp_error($result)) {
        return new WP_REST_Response(['success' => false, 'code' => 'update_failed', 'message' => __('No se pudo actualizar el email. Inténtalo de nuevo.', 'motorlan-api-vue')], 400);
    }

    motorlan_clear_pending_email($user_id);

    // El email nuevo queda verificado: ahora sí es legítimo reclamar los chats de
    // invitado escritos con él.
    update_user_meta($user_id, '_motorlan_email_verified', '1');
    if (function_exists('motorlan_claim_guest_chats_on_login')) {
        motorlan_claim_guest_chats_on_login($user->user_login, get_userdata($user_id));
    }

    if (class_exists('Motorlan_Security_Logger')) {
        Motorlan_Security_Logger::log('email_change_confirmed', ['user_id' => $user_id]);
    }

    return new WP_REST_Response([
        'success' => true,
        'code'    => 'email_changed',
        'message' => __('Tu email se ha actualizado correctamente.', 'motorlan-api-vue'),
        'email'   => $new_email,
    ], 200);
}

/**
 * Anula el cambio de email pendiente.
 */
function motorlan_handle_cancel_email_change(WP_REST_Request $request) {
    motorlan_clear_pending_email(get_current_user_id());
    return new WP_REST_Response(['success' => true, 'code' => 'email_change_cancelled', 'message' => __('Cambio de email cancelado.', 'motorlan-api-vue')], 200);
}

/**
 * Handle password change request by sending a verification code.
 */
function motorlan_handle_change_password_request(WP_REST_Request $request) {
    // Validate Content-Type
    if ( function_exists( 'motorlan_validate_json_content_type' ) ) {
        $valid_type = motorlan_validate_json_content_type( $request );
        if ( is_wp_error( $valid_type ) ) {
            return $valid_type;
        }
    }

    $user_id = get_current_user_id();
    $user = get_userdata($user_id);

    if (!$user) {
        return new WP_Error('user_not_found', __('Usuario no encontrado.', 'motorlan-api-vue'), ['status' => 404]);
    }

    // Si el front envía ya la contraseña nueva, validarla ANTES de mandar el código
    // (antes el usuario recibía el código y al confirmarlo se enteraba de que la
    // contraseña no valía).
    $params = $request->get_json_params();
    if (!empty($params['password']) && function_exists('motorlan_validate_password_strength')) {
        $strength = motorlan_validate_password_strength((string) $params['password'], motorlan_password_personal_data($user));
        if (is_wp_error($strength)) {
            return motorlan_weak_password_response($strength);
        }
    }

    // Máximo 3 códigos por hora y cuenta: antes era ilimitado y cada petición ponía los
    // intentos a 0 (5 intentos por código x códigos infinitos, y emails sin fin).
    if (class_exists('Motorlan_Rate_Limiter')
        && is_wp_error(Motorlan_Rate_Limiter::check('change_password_request', 'u' . $user_id, 3, HOUR_IN_SECONDS))) {
        return new WP_REST_Response([
            'success' => false,
            'code'    => 'too_many_requests',
            'message' => __('Has pedido demasiados códigos. Inténtalo dentro de una hora.', 'motorlan-api-vue'),
        ], 429);
    }

    // Código de 6 dígitos criptográficamente seguro; se guarda solo su hash
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiry = time() + (15 * 60); // 15 minutes validity

    update_user_meta($user_id, '_password_reset_code', wp_hash($code . '|' . $user_id));
    update_user_meta($user_id, '_password_reset_expiry', $expiry);
    update_user_meta($user_id, '_password_reset_attempts', 0);

    // El email lo envía el listener (solo por correo, nunca a la web)
    do_action( 'motorlan_password_reset_code', $user_id, $code, $expiry );

    return new WP_REST_Response(['code' => 'code_sent', 'message' => __('Código de verificación enviado al correo.', 'motorlan-api-vue')], 200);
}

/**
 * Datos personales que la contraseña no debe contener.
 */
function motorlan_password_personal_data(WP_User $user) {
    return [
        'username'   => $user->user_login,
        'email'      => $user->user_email,
        'first_name' => $user->first_name,
    ];
}

/**
 * 400 con la lista de requisitos que faltan (data.errors), igual que el registro.
 */
function motorlan_weak_password_response(WP_Error $strength) {
    $data = $strength->get_error_data();
    return new WP_REST_Response([
        'success' => false,
        'code'    => 'weak_password',
        'field'   => 'password',
        'message' => $strength->get_error_message(),
        'errors'  => isset($data['errors']) && is_array($data['errors']) ? $data['errors'] : [],
    ], 400);
}

/**
 * Handle password change confirmation.
 */
function motorlan_handle_change_password_confirm(WP_REST_Request $request) {
    // Validate Content-Type
    if ( function_exists( 'motorlan_validate_json_content_type' ) ) {
        $valid_type = motorlan_validate_json_content_type( $request );
        if ( is_wp_error( $valid_type ) ) {
            return $valid_type;
        }
    }

    $user_id = get_current_user_id();
    $params = $request->get_json_params();
    $code = sanitize_text_field($params['code'] ?? '');
    $new_password = $params['password'] ?? '';

    if (empty($code) || empty($new_password)) {
        return new WP_Error('missing_params', __('Faltan parámetros requeridos.', 'motorlan-api-vue'), ['status' => 400]);
    }

    $saved_code = get_user_meta($user_id, '_password_reset_code', true);
    $expiry = (int) get_user_meta($user_id, '_password_reset_expiry', true);
    $attempts = (int) get_user_meta($user_id, '_password_reset_attempts', true);

    $clear_code = function () use ($user_id) {
        delete_user_meta($user_id, '_password_reset_code');
        delete_user_meta($user_id, '_password_reset_expiry');
        delete_user_meta($user_id, '_password_reset_attempts');
    };

    // Máximo 5 intentos por código (antes ilimitados)
    if ($attempts >= 5) {
        $clear_code();
        return new WP_Error('too_many_attempts', __('Demasiados intentos. Solicita un código nuevo.', 'motorlan-api-vue'), ['status' => 429]);
    }

    if (!$saved_code || time() > $expiry || !hash_equals((string) $saved_code, wp_hash($code . '|' . $user_id))) {
        update_user_meta($user_id, '_password_reset_attempts', $attempts + 1);
        return new WP_Error('invalid_code', __('El código de verificación es incorrecto o ha expirado.', 'motorlan-api-vue'), ['status' => 400]);
    }

    if (function_exists('motorlan_validate_password_strength')) {
        // Antes se pasaba ['user_id' => …], que el validador ignora: no se comprobaba
        // que la contraseña no contuviera el nombre o el email.
        $user_obj = get_userdata($user_id);
        $strength = motorlan_validate_password_strength($new_password, $user_obj ? motorlan_password_personal_data($user_obj) : []);
        if (is_wp_error($strength)) {
            return motorlan_weak_password_response($strength);
        }
    }

    // Update password
    wp_set_password($new_password, $user_id);
    $clear_code();

    // wp_set_password invalida la cookie actual: volver a emitirla para no expulsar al usuario.
    // OJO: el nonce de la página queda ligado al token de sesión anterior; el front lo
    // renueva (refreshRestNonce) justo después de esta respuesta.
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id, true, is_ssl());

    if (class_exists('Motorlan_Security_Logger')) {
        Motorlan_Security_Logger::log('password_changed', ['user_id' => $user_id]);
    }

    return new WP_REST_Response(['code' => 'password_changed', 'message' => __('Contraseña actualizada correctamente.', 'motorlan-api-vue')], 200);
}
