<?php
/**
 * Setup for Session REST API Routes.
 *
 * Authentication system based on WordPress native cookies (no JWT).
 *
 * Todas las respuestas de error llevan un `code` estable: el front lo traduce con
 * t('auth_errors.<code>') y usa `message` (envuelto en __()) solo como respaldo.
 *
 * @package motorlan-api-vue
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Register custom REST API routes for session.
 */
function motorlan_register_session_rest_routes()
{
    $namespace = 'motorlan/v1';

    // Session info
    register_rest_route($namespace, '/session', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_session_data_callback',
        'permission_callback' => '__return_true',
    ]);

    // Login - creates WordPress session
    register_rest_route($namespace, '/login', [
        'methods' => 'POST',
        'callback' => 'motorlan_login_callback',
        'permission_callback' => '__return_true',
    ]);

    // Logout - destroys WordPress session
    register_rest_route($namespace, '/logout', [
        'methods' => 'POST',
        'callback' => 'motorlan_logout_callback',
        'permission_callback' => '__return_true',
    ]);

    // Register new user
    register_rest_route($namespace, '/register', [
        'methods' => 'POST',
        'callback' => 'motorlan_register_user_callback',
        'permission_callback' => '__return_true',
    ]);

    // Eliminada /check-username: servía para saber qué emails tienen cuenta (el registro
    // usa username = email), sin límite de intentos, y ninguna pantalla la usaba.

    // Password reset request
    register_rest_route($namespace, '/forgot-password', [
        'methods' => 'POST',
        'callback' => 'motorlan_forgot_password_callback',
        'permission_callback' => '__return_true',
    ]);

    // Password reset confirm
    register_rest_route($namespace, '/reset-password', [
        'methods' => 'POST',
        'callback' => 'motorlan_reset_password_callback',
        'permission_callback' => '__return_true',
    ]);

    // Verify email token
    register_rest_route($namespace, '/verify-email', [
        'methods' => 'POST',
        'callback' => 'motorlan_verify_email_callback',
        'permission_callback' => '__return_true',
    ]);

    // Resend verification email
    register_rest_route($namespace, '/resend-verification', [
        'methods' => 'POST',
        'callback' => 'motorlan_resend_verification_callback',
        'permission_callback' => '__return_true',
    ]);
}
add_action('rest_api_init', 'motorlan_register_session_rest_routes');

/**
 * Respuesta de error homogénea para los endpoints de acceso.
 */
function motorlan_auth_error_response($code, $message, $status = 400, $extra = [])
{
    return new WP_REST_Response(array_merge([
        'success' => false,
        'code'    => $code,
        'message' => $message,
    ], $extra), $status);
}

/**
 * Hash con el que se guarda un token ya usado (verificación idempotente).
 */
function motorlan_used_token_hash($token)
{
    return wp_hash('motorlan_verify|' . $token);
}

/**
 * Busca el usuario dueño de un token de verificación vigente o caducado.
 */
function motorlan_find_user_by_verification_token($token)
{
    global $wpdb;
    $user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
        '_motorlan_verification_token',
        $token
    ));
    return $user_id ? get_userdata((int) $user_id) : null;
}

/**
 * Busca el usuario que ya usó este token en las últimas 24 h (segundo clic, recarga,
 * escáner de correo...). Devuelve null si no existe o si ya pasó el plazo.
 */
function motorlan_find_user_by_used_verification_token($token)
{
    global $wpdb;
    $user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
        '_motorlan_verification_token_used',
        motorlan_used_token_hash($token)
    ));
    if (!$user_id) {
        return null;
    }
    $until = (int) get_user_meta((int) $user_id, '_motorlan_verification_token_used_until', true);
    if ($until && time() > $until) {
        return null;
    }
    return get_userdata((int) $user_id);
}

/**
 * Marca la cuenta como verificada y dispara la bienvenida (que también reclama los
 * chats de invitado con ese email). Guarda el token usado 24 h para que un segundo
 * clic responda "ya estaba verificada" en lugar de "token inválido".
 */
function motorlan_mark_email_verified($user_id, $used_token = '')
{
    $was = get_user_meta($user_id, '_motorlan_email_verified', true);

    update_user_meta($user_id, '_motorlan_email_verified', '1');
    delete_user_meta($user_id, '_motorlan_verification_token');
    delete_user_meta($user_id, '_motorlan_verification_token_expires');

    if ($used_token !== '') {
        update_user_meta($user_id, '_motorlan_verification_token_used', motorlan_used_token_hash($used_token));
        update_user_meta($user_id, '_motorlan_verification_token_used_until', time() + DAY_IN_SECONDS);
    }

    if ('1' !== $was) {
        do_action('motorlan_user_welcome', $user_id);
    }
}

/**
 * Genera (o reutiliza si sigue vigente) el token de verificación y envía el email.
 */
function motorlan_send_verification_email($user_id, $force_new = false)
{
    $token = get_user_meta($user_id, '_motorlan_verification_token', true);
    $expires = (int) get_user_meta($user_id, '_motorlan_verification_token_expires', true);
    if ($force_new || empty($token) || ($expires && time() > $expires)) {
        $token = wp_generate_password(32, false);
        update_user_meta($user_id, '_motorlan_verification_token', $token);
        update_user_meta($user_id, '_motorlan_verification_token_expires', time() + DAY_IN_SECONDS);
    }
    do_action('motorlan_user_verify_email', $user_id, $token);
    return $token;
}

/**
 * Respuesta 403 para login de cuenta sin verificar (reenvía el enlace, máx. 3/hora).
 */
function motorlan_unverified_login_response($user) {
    $resend_message = ' ' . __('Por favor, revise su email para encontrar el enlace de activación enviado anteriormente.', 'motorlan-api-vue');
    $resent = false;

    if ($user instanceof WP_User && class_exists('Motorlan_Rate_Limiter') && Motorlan_Rate_Limiter::check_limit('resend_verification', 3, 3600)) {
        motorlan_send_verification_email($user->ID);
        $resend_message = ' ' . __('Hemos reenviado un nuevo enlace de activación a su email.', 'motorlan-api-vue');
        $resent = true;
    }

    return motorlan_auth_error_response(
        'email_not_verified',
        __('Su cuenta aún no ha sido verificada.', 'motorlan-api-vue') . $resend_message,
        403,
        ['resent' => $resent]
    );
}

/**
 * Login callback - authenticates user and creates WordPress session.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response The response object.
 */
function motorlan_login_callback(WP_REST_Request $request)
{
    // Validate Content-Type
    if ( function_exists( 'motorlan_validate_json_content_type' ) ) {
        $valid_type = motorlan_validate_json_content_type( $request );
        if ( is_wp_error( $valid_type ) ) {
            return $valid_type;
        }
    }

    $params = $request->get_json_params();
    $username = sanitize_text_field($params['username'] ?? '');
    $password = $params['password'] ?? '';
    $remember = !empty($params['remember']);

    // Rate limiting: 5 attempts per 15 minutes
    if (class_exists('Motorlan_Rate_Limiter') && !Motorlan_Rate_Limiter::check_limit('login', 5, 15 * 60)) {
        $remaining_time = Motorlan_Rate_Limiter::get_retry_after('login', 15 * 60);
        $minutes = max(1, (int) ceil($remaining_time / 60));
        return motorlan_auth_error_response(
            'too_many_attempts',
            sprintf(__('Demasiados intentos de login. Intente nuevamente en %d minutos.', 'motorlan-api-vue'), $minutes),
            429,
            ['retry_minutes' => $minutes]
        );
    }

    if (empty($username) || empty($password)) {
        return motorlan_auth_error_response('missing_credentials', __('Introduce tu email y tu contraseña.', 'motorlan-api-vue'), 400);
    }

    // Check if username is an email
    if (is_email($username)) {
        $user = get_user_by('email', $username);
        if ($user) {
            $username = $user->user_login;
        }
    }

    // Authenticate using WordPress native function (emite la cookie de sesión)
    $credentials = [
        'user_login'    => $username,
        'user_password' => $password,
        'remember'      => $remember,
    ];

    $user = wp_signon($credentials, is_ssl());

    // Cuenta sin verificar (bloqueada por motorlan_block_unverified_login antes de emitir cookie)
    if (is_wp_error($user) && $user->get_error_code() === 'email_not_verified') {
        $unverified = get_user_by('login', $username);
        if (!$unverified && is_email($username)) {
            $unverified = get_user_by('email', $username);
        }
        return motorlan_unverified_login_response($unverified);
    }

    if (is_wp_error($user)) {
        // Log failed attempt
        if (class_exists('Motorlan_Rate_Limiter')) {
            Motorlan_Rate_Limiter::log_failed_attempt('login', [
                'username' => $username,
                'error_code' => $user->get_error_code(),
            ]);
        }

        // Mismo mensaje para "usuario inexistente" y "contraseña incorrecta": antes se
        // distinguían y servía para saber qué emails tienen cuenta.
        return motorlan_auth_error_response('invalid_credentials', __('Email o contraseña incorrectos.', 'motorlan-api-vue'), 401);
    }

    // Defensa adicional: mismo criterio que motorlan_block_unverified_login (solo '0').
    // Antes se exigía '1' y los usuarios antiguos o creados desde wp-admin (sin meta)
    // entraban por wp-login.php pero no por la SPA.
    $is_verified = get_user_meta($user->ID, '_motorlan_email_verified', true);
    if ('0' === $is_verified && !in_array('administrator', (array) $user->roles, true)) {
        wp_logout();
        return motorlan_unverified_login_response($user);
    }

    // wp_signon() ya emitió la cookie; antes se volvía a llamar a wp_set_auth_cookie()
    // y cada login creaba dos tokens de sesión.
    wp_set_current_user($user->ID);

    // Get user profile data
    $profile_data = [];
    if (function_exists('get_user_profile_data')) {
        $profile_request = new WP_REST_Request('GET', '/motorlan/v1/profile');
        $profile_response = get_user_profile_data($profile_request);
        if (!is_wp_error($profile_response)) {
            $profile_data = $profile_response->get_data();
        }
    }

    // If login successful, reset rate limit and log event
    if (class_exists('Motorlan_Rate_Limiter')) {
        Motorlan_Rate_Limiter::reset_limit('login');
    }

    if (class_exists('Motorlan_Security_Logger')) {
        Motorlan_Security_Logger::log('login_success', [
            'user_id' => $user->ID,
            'role' => reset($user->roles)
        ]);
    }

    return new WP_REST_Response([
        'success' => true,
        'message' => __('Sesión iniciada.', 'motorlan-api-vue'),
        'user' => [
            'id' => $user->ID,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
            'nicename' => $user->user_nicename,
            'is_admin' => in_array('administrator', (array) $user->roles),
        ],
        'profile' => $profile_data,
    ], 200);
}

/**
 * Logout callback - destroys WordPress session.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response The response object.
 */
function motorlan_logout_callback(WP_REST_Request $request)
{
    // Destroy WordPress session
    wp_logout();

    // Clear WordPress auth cookies
    wp_clear_auth_cookie();

    // Clear any legacy app cookies
    $cookie_domain = defined('COOKIE_DOMAIN') && COOKIE_DOMAIN ? COOKIE_DOMAIN : '';
    $legacy_cookies = ['accessToken', 'userData', 'userAbilityRules'];

    foreach ($legacy_cookies as $cookie_name) {
        setcookie($cookie_name, '', time() - 3600, '/', $cookie_domain, false, false);
        setcookie($cookie_name, '', time() - 3600, '/', '', false, false);
        unset($_COOKIE[$cookie_name]);
    }

    return new WP_REST_Response([
        'success' => true,
        'message' => __('Sesión cerrada.', 'motorlan-api-vue')
    ], 200);
}

/**
 * Get session data callback.
 * Uses only WordPress native session (no JWT).
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response The response object.
 */
function motorlan_get_session_data_callback(WP_REST_Request $request)
{
    $user_data = [
        'is_logged_in' => false,
        'user' => null,
    ];

    // Check WordPress session only
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();

        if ($current_user && $current_user->ID > 0) {
            $user_data = [
                'is_logged_in' => true,
                'user' => [
                    'id' => $current_user->ID,
                    'email' => $current_user->user_email,
                    'display_name' => $current_user->display_name,
                    'nicename' => $current_user->user_nicename,
                    'is_admin' => in_array('administrator', (array) $current_user->roles),
                ],
            ];
        }
    }

    return new WP_REST_Response($user_data, 200);
}

/**
 * Login interno a partir del email. El front usa username = email, pero
 * wp_insert_user() aplica sanitize_user(…, true) (quita "+", tildes…) y limita a 60
 * caracteres; además el login de una cuenta que cambió de email sigue ocupado. En
 * esos casos se genera uno único en lugar de devolver un error (el login se hace
 * siempre por email).
 */
function motorlan_build_unique_user_login($requested, $email)
{
    $requested = (string) $requested;
    $clean = sanitize_user($requested, true);
    if ($clean !== '' && $clean === $requested && strlen($clean) <= 60 && !username_exists($clean)) {
        return $clean;
    }

    $base = sanitize_user((string) strstr($email, '@', true), true);
    $base = $base !== '' ? substr($base, 0, 50) : 'usuario';
    for ($i = 0; $i < 10; $i++) {
        $candidate = $base . '_' . strtolower(wp_generate_password(6, false));
        if (!username_exists($candidate)) {
            return $candidate;
        }
    }
    return 'usuario_' . strtolower(wp_generate_password(12, false));
}

/**
 * Locale de WP para el idioma de la SPA (es, en, eu…), para que los emails y los
 * enlaces se envíen en el idioma en el que se registró el usuario.
 */
function motorlan_locale_from_lang_code($lang)
{
    $lang = sanitize_key((string) $lang);
    if ($lang === '') {
        return '';
    }
    $active = apply_filters('wpml_active_languages', null, []);
    if (is_array($active) && !empty($active[$lang]['default_locale'])) {
        return (string) $active[$lang]['default_locale'];
    }
    $fallback = ['es' => 'es_ES', 'en' => 'en_US', 'eu' => 'eu'];
    return $fallback[$lang] ?? '';
}

/**
 * Register new user callback.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response The response object.
 */
function motorlan_register_user_callback(WP_REST_Request $request)
{
    // Validate Content-Type
    if ( function_exists( 'motorlan_validate_json_content_type' ) ) {
        $valid_type = motorlan_validate_json_content_type( $request );
        if ( is_wp_error( $valid_type ) ) {
            return $valid_type;
        }
    }

    $params = $request->get_json_params();
    $username = sanitize_text_field($params['username'] ?? '');
    $email = sanitize_email($params['email'] ?? '');
    $password = $params['password'] ?? '';
    $first_name = sanitize_text_field($params['first_name'] ?? '');
    $last_name = sanitize_text_field($params['last_name'] ?? '');
    $name = sanitize_text_field($params['name'] ?? '');

    if (class_exists('Motorlan_Rate_Limiter')) {
        // Solo cuentan las altas creadas (3/h por IP): antes contaban también los
        // intentos con errores de validación y una oficina entera se quedaba sin poder
        // registrarse durante una hora. Límite aparte, más amplio, para los intentos.
        if (Motorlan_Rate_Limiter::get_remaining_attempts('register', 3) <= 0
            || !Motorlan_Rate_Limiter::check_limit('register_attempt', 20, 3600)) {
            return motorlan_auth_error_response('too_many_registrations', __('Demasiados registros desde esta IP. Intente más tarde.', 'motorlan-api-vue'), 429);
        }
    }

    // Validation
    if (empty($email) || empty($password)) {
        return motorlan_auth_error_response('missing_fields', __('El email y la contraseña son obligatorios.', 'motorlan-api-vue'), 400);
    }

    if (!is_email($email)) {
        return motorlan_auth_error_response('invalid_email', __('El email no es válido.', 'motorlan-api-vue'), 400, ['field' => 'email']);
    }

    // Validar nombre y apellido
    if (empty($first_name)) {
        return motorlan_auth_error_response('first_name_required', __('El nombre es obligatorio.', 'motorlan-api-vue'), 400, ['field' => 'first_name']);
    }

    if (empty($last_name)) {
        return motorlan_auth_error_response('last_name_required', __('El apellido es obligatorio.', 'motorlan-api-vue'), 400, ['field' => 'last_name']);
    }

    $existing = get_user_by('email', $email);
    if ($existing) {
        // Mismo mensaje tanto si la cuenta está activa como si no. Si no lo está
        // (alguien se registró con este email y no lo verificó), se reenvía el enlace
        // al dueño del correo para que pueda recuperarla.
        if ('0' === get_user_meta($existing->ID, '_motorlan_email_verified', true)
            && class_exists('Motorlan_Rate_Limiter')
            && Motorlan_Rate_Limiter::check_limit('resend_verification', 3, 3600)
            && !is_wp_error(Motorlan_Rate_Limiter::check('resend_verification_user', 'u' . $existing->ID, 5, 3600))) {
            motorlan_send_verification_email($existing->ID);
        }
        return motorlan_auth_error_response(
            'email_exists',
            __('Este correo ya está registrado. Inicia sesión o, si no recuerdas la contraseña, restablécela. Si la cuenta no estaba activada, recibirás de nuevo el enlace de activación.', 'motorlan-api-vue'),
            400,
            ['field' => 'email']
        );
    }

    $user_login = motorlan_build_unique_user_login($username !== '' ? $username : $email, $email);

    // Validate password strength
    if ( function_exists( 'motorlan_validate_password_strength' ) ) {
        $valid_pass = motorlan_validate_password_strength( $password, [
            'username'   => $user_login,
            'email'      => $email,
            'first_name' => $first_name,
            'name'       => $name,
        ] );
        if ( is_wp_error( $valid_pass ) ) {
            $error_data = $valid_pass->get_error_data();
            $errors_list = isset( $error_data['errors'] ) && is_array( $error_data['errors'] ) ? $error_data['errors'] : [];
            return motorlan_auth_error_response('weak_password', $valid_pass->get_error_message(), 400, [
                'field'  => 'password',
                'errors' => $errors_list,
            ]);
        }
    }

    // Create user
    $user_id = wp_create_user($user_login, $password, $email);

    if (is_wp_error($user_id)) {
        error_log('[Motorlan] wp_create_user failed: ' . $user_id->get_error_code() . ' ' . $user_id->get_error_message());
        return motorlan_auth_error_response('registration_failed', __('No se pudo crear la cuenta. Inténtalo de nuevo.', 'motorlan-api-vue'), 400);
    }

    // Cuenta creada: ahora sí consume uno de los 3 registros por hora
    if (class_exists('Motorlan_Rate_Limiter')) {
        Motorlan_Rate_Limiter::check('register', null, 3, 3600);
    }

    // Process name fields
    if (empty($first_name) && !empty($name)) {
        $name_parts = preg_split('/\s+/', trim($name));
        if (!empty($name_parts)) {
            $first_name = array_shift($name_parts);
            $last_name = trim(implode(' ', $name_parts));
        }
    }

    $display_name = trim(implode(' ', array_filter([$first_name, $last_name])));
    if (empty($display_name) && !empty($name)) {
        $display_name = $name;
    }

    // Update user profile
    if (!empty($first_name) || !empty($last_name) || !empty($display_name)) {
        $update_args = ['ID' => $user_id];

        if (!empty($first_name)) {
            $update_args['first_name'] = $first_name;
        }
        if (!empty($last_name)) {
            $update_args['last_name'] = $last_name;
        }
        if (!empty($display_name)) {
            $update_args['display_name'] = $display_name;
            $update_args['nickname'] = $display_name;
        }

        wp_update_user($update_args);
    }

    // Idioma del registro: get_user_locale() lo usan el email de verificación y
    // motorlan_account_url(). Antes todos recibían el email en español.
    $locale = motorlan_locale_from_lang_code($params['lang'] ?? '');
    if ($locale !== '') {
        update_user_meta($user_id, 'locale', $locale);
    }

    // Email verification setup
    update_user_meta($user_id, '_motorlan_email_verified', '0');
    $verification_token = motorlan_send_verification_email($user_id, true);

    // Log registration
    if (class_exists('Motorlan_Security_Logger')) {
        Motorlan_Security_Logger::log('user_registered', [
            'user_id' => $user_id,
            'email' => $email
        ]);
    }

    // Log recovery info without leaking the full token. Only emit the URL when
    // explicitly enabled for local debugging.
    if ( function_exists( 'motorlan_account_url' ) ) {
        $verify_relative = motorlan_account_url( 'verify-email?token=' . $verification_token, $user_id );
        $verify_absolute = ( strpos( $verify_relative, 'http' ) === 0 ) ? $verify_relative : home_url( $verify_relative );
        $token_hint      = substr( $verification_token, 0, 6 ) . '…';
        error_log( sprintf( '[Motorlan] Registered user_id=%d email=%s token_prefix=%s', $user_id, $email, $token_hint ) );
        if ( defined( 'MOTORLAN_EXPOSE_DEV_TOKENS' ) && MOTORLAN_EXPOSE_DEV_TOKENS ) {
            error_log( sprintf( '[Motorlan][DEV] Verify URL for user_id=%d: %s', $user_id, $verify_absolute ) );
        }
    }

    $response_payload = [
        'success' => true,
        'code'    => 'registered',
        'message' => __('Usuario registrado con éxito. Por favor, revise su email para activar su cuenta.', 'motorlan-api-vue'),
    ];

    // Dev URL only when an explicit constant is set, never solely WP_DEBUG.
    if ( defined( 'MOTORLAN_EXPOSE_DEV_TOKENS' ) && MOTORLAN_EXPOSE_DEV_TOKENS && isset( $verify_absolute ) ) {
        $response_payload['dev_verify_url'] = $verify_absolute;
    }

    return new WP_REST_Response( $response_payload, 200 );
}

/**
 * Verify email callback.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response The response object.
 */
function motorlan_verify_email_callback(WP_REST_Request $request)
{
    $params = $request->get_json_params();
    $token = trim(sanitize_text_field($params['token'] ?? ''));

    if (empty($token)) {
        return motorlan_auth_error_response('token_required', __('Falta el token de verificación.', 'motorlan-api-vue'), 400);
    }

    if (class_exists('Motorlan_Rate_Limiter') && !Motorlan_Rate_Limiter::check_limit('verify_email', 20, 15 * 60)) {
        return motorlan_auth_error_response('too_many_attempts', __('Demasiados intentos. Inténtalo más tarde.', 'motorlan-api-vue'), 429);
    }

    $user = motorlan_find_user_by_verification_token($token);

    if (!$user) {
        // Segundo clic / recarga / escáner de correo: la cuenta ya quedó verificada
        $used_by = motorlan_find_user_by_used_verification_token($token);
        if ($used_by) {
            return new WP_REST_Response([
                'success' => true,
                'code'    => 'already_verified',
                'message' => __('Tu cuenta ya estaba verificada. Ya puedes iniciar sesión.', 'motorlan-api-vue'),
            ], 200);
        }

        // Don't log raw token to avoid credential leakage in shared log files.
        error_log( '[Motorlan] Verification token not found (prefix=' . substr( $token, 0, 6 ) . '…)' );

        return motorlan_auth_error_response('invalid_token', __('Token de verificación inválido o expirado.', 'motorlan-api-vue'), 400);
    }

    // Enforce token expiration (24h after issuance). Tokens without an expiry
    // are treated as legacy and accepted.
    $expires_at = (int) get_user_meta( $user->ID, '_motorlan_verification_token_expires', true );
    if ( $expires_at && time() > $expires_at ) {
        // Se conserva el token caducado: es lo único que tiene la página para pedir un
        // reenvío. No sirve para verificar (se comprueba la caducidad) y el reenvío lo
        // sustituye. Antes se borraba y "Solicitar nuevo enlace" no enviaba nada.
        return motorlan_auth_error_response('token_expired', __('El enlace de verificación ha expirado. Solicite uno nuevo.', 'motorlan-api-vue'), 400);
    }

    motorlan_mark_email_verified($user->ID, $token);

    return new WP_REST_Response([
        'success' => true,
        'code'    => 'verified',
        'message' => __('Email verificado con éxito. Ya puede iniciar sesión.', 'motorlan-api-vue'),
    ], 200);
}

/**
 * Resend verification email callback.
 *
 * Por email/usuario la respuesta es siempre la misma (no revela si la cuenta existe
 * ni si está activa). Por token sí se puede decir "ya está activa": el token demuestra
 * que quien pregunta es el dueño del correo.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response The response object.
 */
function motorlan_resend_verification_callback(WP_REST_Request $request)
{
    $params = $request->get_json_params();
    $email_or_login = sanitize_text_field($params['email'] ?? ($params['username'] ?? ''));
    $tokenParam = trim(sanitize_text_field($params['token'] ?? ''));
    $neutral = __('Si la cuenta existe y no está activada, se ha enviado un nuevo enlace.', 'motorlan-api-vue');

    if (empty($email_or_login) && empty($tokenParam)) {
        return motorlan_auth_error_response('missing_fields', __('Indica tu email.', 'motorlan-api-vue'), 400);
    }

    // Límite global por IP para todas las variantes (antes solo se limitaba cuando la
    // cuenta existía y no estaba verificada, así que las demás respuestas eran gratis)
    if (class_exists('Motorlan_Rate_Limiter') && !Motorlan_Rate_Limiter::check_limit('resend_verification_any', 10, 3600)) {
        return motorlan_auth_error_response('too_many_requests', __('Demasiadas solicitudes de reenvío. Intente más tarde.', 'motorlan-api-vue'), 429);
    }

    $by_token = false;
    $user = null;
    if (!empty($tokenParam)) {
        $user = motorlan_find_user_by_verification_token($tokenParam);
        if (!$user) {
            $used_by = motorlan_find_user_by_used_verification_token($tokenParam);
            if ($used_by) {
                return new WP_REST_Response([
                    'success' => true,
                    'code'    => 'already_verified',
                    'message' => __('Esta cuenta ya está activa. Por favor, inicie sesión.', 'motorlan-api-vue'),
                ], 200);
            }
        }
        $by_token = (bool) $user;
    }

    if (!$user && !empty($email_or_login)) {
        $user = is_email($email_or_login) ? get_user_by('email', $email_or_login) : get_user_by('login', $email_or_login);
    }

    if (!$user) {
        if (!empty($tokenParam) && empty($email_or_login)) {
            return motorlan_auth_error_response('invalid_token', __('El enlace no es válido. Inicia sesión para recibir uno nuevo.', 'motorlan-api-vue'), 400);
        }
        return new WP_REST_Response(['success' => true, 'code' => 'resend_neutral', 'message' => $neutral], 200);
    }

    if ('1' === get_user_meta($user->ID, '_motorlan_email_verified', true)) {
        if ($by_token) {
            return new WP_REST_Response([
                'success' => true,
                'code'    => 'already_verified',
                'message' => __('Esta cuenta ya está activa. Por favor, inicie sesión.', 'motorlan-api-vue'),
            ], 200);
        }
        return new WP_REST_Response(['success' => true, 'code' => 'resend_neutral', 'message' => $neutral], 200);
    }

    // Límite de envíos: 3/h por IP y 5/h por cuenta (evita inundar el buzón del dueño)
    $ip_ok = !class_exists('Motorlan_Rate_Limiter') || Motorlan_Rate_Limiter::check_limit('resend_verification', 3, 3600);
    $user_ok = !class_exists('Motorlan_Rate_Limiter') || !is_wp_error(Motorlan_Rate_Limiter::check('resend_verification_user', 'u' . $user->ID, 5, 3600));
    if (!$ip_ok || !$user_ok) {
        if ($by_token) {
            return motorlan_auth_error_response('too_many_requests', __('Demasiadas solicitudes de reenvío. Intente más tarde.', 'motorlan-api-vue'), 429);
        }
        return new WP_REST_Response(['success' => true, 'code' => 'resend_neutral', 'message' => $neutral], 200);
    }

    motorlan_send_verification_email($user->ID, true);

    return new WP_REST_Response([
        'success' => true,
        'code'    => $by_token ? 'resent' : 'resend_neutral',
        'message' => $by_token ? __('Se ha enviado un nuevo enlace de activación a su email.', 'motorlan-api-vue') : $neutral,
    ], 200);
}

/**
 * Forgot password callback - sends reset link.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response The response object.
 */
function motorlan_forgot_password_callback(WP_REST_Request $request)
{
    // Validate Content-Type
    if ( function_exists( 'motorlan_validate_json_content_type' ) ) {
        $valid_type = motorlan_validate_json_content_type( $request );
        if ( is_wp_error( $valid_type ) ) {
            return $valid_type;
        }
    }

    $params = $request->get_json_params();
    $email = sanitize_email($params['email'] ?? '');
    $neutral = __('Si el email existe, se ha enviado un enlace para restablecer la contraseña.', 'motorlan-api-vue');

    // Rate limiting: 3 attempts per 30 minutes (cada intento puede enviar un email)
    if (class_exists('Motorlan_Rate_Limiter') && !Motorlan_Rate_Limiter::check_limit('forgot_password', 3, 30 * 60)) {
        return motorlan_auth_error_response('too_many_requests', __('Demasiadas solicitudes. Intente más tarde.', 'motorlan-api-vue'), 429);
    }

    if (empty($email)) {
        return motorlan_auth_error_response('email_required', __('Indica tu email.', 'motorlan-api-vue'), 400);
    }

    $user = get_user_by('email', $email);

    // Always return success to prevent email enumeration
    if (!$user) {
        return new WP_REST_Response(['success' => true, 'code' => 'reset_sent', 'message' => $neutral], 200);
    }

    // Máximo 5 emails por cuenta y hora, aunque cambie la IP (respuesta neutra)
    if (class_exists('Motorlan_Rate_Limiter') && is_wp_error(Motorlan_Rate_Limiter::check('forgot_password_user', 'u' . $user->ID, 5, 3600))) {
        return new WP_REST_Response(['success' => true, 'code' => 'reset_sent', 'message' => $neutral], 200);
    }

    // Generate reset key
    $reset_key = get_password_reset_key($user);

    if (is_wp_error($reset_key)) {
        return motorlan_auth_error_response('reset_key_failed', __('No se pudo generar el enlace. Inténtalo de nuevo.', 'motorlan-api-vue'), 500);
    }

    // Build reset URL (WPML-aware, usa idioma del usuario)
    // Template prepend home_url si relativo, fallback wp_mail necesita absoluto.
    $reset_path = motorlan_account_url(
        "reset-password?key={$reset_key}&login=" . rawurlencode($user->user_login),
        $user->ID
    );
    $reset_url = (strpos($reset_path, 'http') === 0) ? $reset_path : home_url($reset_path);

    // Asunto y plantilla en el idioma del usuario (antes siempre en español)
    $switched = switch_to_locale(get_user_locale($user->ID));
    try {
        if (class_exists('Motorlan_Notification_Manager')) {
            $notification_manager = new Motorlan_Notification_Manager();

            // Use direct email sending
            $notification_manager->send_email_notification_direct(
                $user->ID,
                'password_reset',
                __('Restablecer contraseña - Motorlan', 'motorlan-api-vue'),
                __('Has solicitado restablecer tu contraseña.', 'motorlan-api-vue'),
                [
                    'url' => $reset_url,
                    'key' => $reset_key,
                    'login' => $user->user_login
                ]
            );

            // Also create notification in DB for history
            $notification_manager->create_notification(
                $user->ID,
                'password_reset',
                __('Solicitud de restablecimiento de contraseña', 'motorlan-api-vue'),
                __('Has solicitado un cambio de contraseña. Revisa tu email.', 'motorlan-api-vue'),
                [],
                ['web']
            );
        } else {
            // Fallback
            $message = sprintf(__("Hola %s,\n\nVisita: %s", 'motorlan-api-vue'), $user->display_name, $reset_url);
            wp_mail($user->user_email, __('Restablecer contraseña - Motorlan', 'motorlan-api-vue'), $message);
        }
    } finally {
        if ($switched) {
            restore_previous_locale();
        }
    }

    return new WP_REST_Response(['success' => true, 'code' => 'reset_sent', 'message' => $neutral], 200);
}

/**
 * Reset password callback - validates key and sets new password.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response The response object.
 */
function motorlan_reset_password_callback(WP_REST_Request $request)
{
    // Validate Content-Type
    if ( function_exists( 'motorlan_validate_json_content_type' ) ) {
        $valid_type = motorlan_validate_json_content_type( $request );
        if ( is_wp_error( $valid_type ) ) {
            return $valid_type;
        }
    }

    $params = $request->get_json_params();
    $key = sanitize_text_field($params['key'] ?? '');
    $login = sanitize_text_field($params['login'] ?? '');
    $password = $params['password'] ?? '';

    if (empty($key) || empty($login) || empty($password)) {
        return motorlan_auth_error_response('missing_fields', __('Faltan datos para restablecer la contraseña.', 'motorlan-api-vue'), 400);
    }

    if (class_exists('Motorlan_Rate_Limiter') && !Motorlan_Rate_Limiter::check_limit('reset_password', 10, 15 * 60)) {
        return motorlan_auth_error_response('too_many_attempts', __('Demasiados intentos. Inténtalo más tarde.', 'motorlan-api-vue'), 429);
    }

    // Primero la clave: antes se validaba antes la contraseña y con un enlace caducado
    // el usuario la corregía para después enterarse de que el enlace no valía.
    $user = check_password_reset_key($key, $login);

    if (is_wp_error($user)) {
        if ($user->get_error_code() === 'expired_key') {
            return motorlan_auth_error_response('expired_key', __('El enlace para restablecer la contraseña ha caducado. Solicita uno nuevo.', 'motorlan-api-vue'), 400);
        }
        return motorlan_auth_error_response('invalid_key', __('El enlace para restablecer la contraseña no es válido o ya se ha usado. Solicita uno nuevo.', 'motorlan-api-vue'), 400);
    }

    // Validate password strength (también contra los datos personales del usuario)
    if ( function_exists( 'motorlan_validate_password_strength' ) ) {
        $valid_pass = motorlan_validate_password_strength( $password, [
            'username'   => $user->user_login,
            'email'      => $user->user_email,
            'first_name' => $user->first_name,
        ] );
        if ( is_wp_error( $valid_pass ) ) {
            $error_data = $valid_pass->get_error_data();
            $errors_list = isset( $error_data['errors'] ) && is_array( $error_data['errors'] ) ? $error_data['errors'] : [];
            return motorlan_auth_error_response('weak_password', $valid_pass->get_error_message(), 400, [
                'field'  => 'password',
                'errors' => $errors_list,
            ]);
        }
    } elseif (strlen($password) < 8) {
        return motorlan_auth_error_response('weak_password', __('La contraseña debe tener al menos 8 caracteres.', 'motorlan-api-vue'), 400, ['field' => 'password']);
    }

    // Reset the password
    reset_password($user, $password);

    // Quien restablece la contraseña desde el enlace del email ha demostrado que el
    // correo es suyo: si la cuenta estaba pendiente de verificar, se activa.
    if ('0' === get_user_meta($user->ID, '_motorlan_email_verified', true)) {
        motorlan_mark_email_verified($user->ID);
    }

    if (class_exists('Motorlan_Rate_Limiter')) {
        Motorlan_Rate_Limiter::reset_limit('reset_password');
    }

    // Log password reset
    if (class_exists('Motorlan_Security_Logger')) {
        Motorlan_Security_Logger::log('password_reset_success', [
            'user_id' => $user->ID
        ]);
    }

    return new WP_REST_Response([
        'success' => true,
        'code'    => 'password_reset',
        'message' => __('La contraseña se ha restablecido. Ya puedes iniciar sesión.', 'motorlan-api-vue'),
    ], 200);
}
