<?php
/**
 * Setup for Session REST API Routes.
 *
 * Authentication system based on WordPress native cookies (no JWT).
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

    // Check username availability
    register_rest_route($namespace, '/check-username', [
        'methods' => 'POST',
        'callback' => 'motorlan_check_username_callback',
        'permission_callback' => '__return_true',
    ]);

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
        $remaining_time = ceil(get_option('_transient_timeout_motorlan_rate_limit_' . md5('login_' . $_SERVER['REMOTE_ADDR'])) - time());
        return new WP_REST_Response([
            'success' => false,
            'message' => sprintf(
                'Demasiados intentos de login. Intente nuevamente en %d minutos.',
                ceil($remaining_time / 60)
            )
        ], 429);
    }

    if (empty($username) || empty($password)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Username and password are required.'
        ], 400);
    }

    // Check if username is an email
    if (is_email($username)) {
        $user = get_user_by('email', $username);
        if ($user) {
            $username = $user->user_login;
        }
    }

    // Authenticate using WordPress native function
    $credentials = [
        'user_login'    => $username,
        'user_password' => $password,
        'remember'      => $remember,
    ];

    $user = wp_signon($credentials, is_ssl());

    if (is_wp_error($user)) {
        // Log failed attempt
        if (class_exists('Motorlan_Rate_Limiter')) {
            Motorlan_Rate_Limiter::log_failed_attempt('login', [
                'username' => $username,
                'error_code' => $user->get_error_code(),
            ]);
        }
        
        $error_code = $user->get_error_code();
        $message = 'Invalid username or password.';

        if ($error_code === 'invalid_username' || $error_code === 'invalid_email') {
            $message = 'Invalid username or email.';
        } elseif ($error_code === 'incorrect_password') {
            $message = 'Incorrect password.';
        }

        return new WP_REST_Response([
            'success' => false,
            'message' => $message,
            'code' => $error_code
        ], 401);
    }

    // Check if email is verified
    $is_verified = get_user_meta($user->ID, '_motorlan_email_verified', true);
    if ('1' !== $is_verified && !in_array('administrator', (array) $user->roles)) {
        
        // Auto-resend on login attempt - limited to 3 per hour to prevent spam
        if (class_exists('Motorlan_Rate_Limiter') && Motorlan_Rate_Limiter::check_limit('resend_verification', 3, 3600)) {
            $token = get_user_meta($user->ID, '_motorlan_verification_token', true);
            if (empty($token)) {
                $token = wp_generate_password(32, false);
                update_user_meta($user->ID, '_motorlan_verification_token', $token);
            }
            do_action('motorlan_user_verify_email', $user->ID, $token);
            
            $resend_message = ' Hemos reenviado un nuevo enlace de activación a su email.';
        } else {
            $resend_message = ' Por favor, revise su email para encontrar el enlace de activación enviado anteriormente.';
        }

        wp_logout();
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Su cuenta aún no ha sido verificada.' . $resend_message,
            'code' => 'email_not_verified'
        ], 403);
    }

    // Set auth cookie for the logged in user
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, $remember, is_ssl());

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
        'message' => 'Login successful.',
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
        'message' => 'Logged out successfully.'
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
 * Check username availability callback.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response The response object.
 */
function motorlan_check_username_callback(WP_REST_Request $request)
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

    if (empty($username)) {
        return new WP_REST_Response([
            'available' => false,
            'message' => 'Username is required.'
        ], 400);
    }

    if (username_exists($username)) {
        return new WP_REST_Response([
            'available' => false,
            'message' => 'Username already exists.'
        ], 200);
    }

    return new WP_REST_Response(['available' => true], 200);
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

    // Rate limiting: 3 registrations per hour
    if (class_exists('Motorlan_Rate_Limiter') && !Motorlan_Rate_Limiter::check_limit('register', 3, 3600)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Demasiados registros desde esta IP. Intente más tarde.'
        ], 429);
    }

    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Username, email, and password are required.'
        ], 400);
    }

    // Validar nombre y apellido
    if (empty($first_name)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'El nombre es obligatorio.'
        ], 400);
    }

    if (empty($last_name)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'El apellido es obligatorio.'
        ], 400);
    }

    if (username_exists($username)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Username already exists.'
        ], 400);
    }

    if (email_exists($email)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Email already exists.'
        ], 400);
    }

    // Validate password strength
    if ( function_exists( 'motorlan_validate_password_strength' ) ) {
        $valid_pass = motorlan_validate_password_strength( $password );
        if ( is_wp_error( $valid_pass ) ) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $valid_pass->get_error_message()
            ], 400);
        }
    }

    // Create user
    $user_id = wp_create_user($username, $password, $email);

    if (is_wp_error($user_id)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => $user_id->get_error_message()
        ], 500);
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

    // Email verification setup
    $verification_token = wp_generate_password(32, false);
    update_user_meta($user_id, '_motorlan_email_verified', '0');
    update_user_meta($user_id, '_motorlan_verification_token', $verification_token);

    // Send verification notification instead of welcome
    do_action('motorlan_user_verify_email', $user_id, $verification_token);

    // Log registration
    if (class_exists('Motorlan_Security_Logger')) {
        Motorlan_Security_Logger::log('user_registered', [
            'user_id' => $user_id,
            'email' => $email
        ]);
    }

    // Send WordPress notifications (maybe disable or keep)
    // Comentado para evitar el envío del correo de accesos por defecto de WP
    /*
    if (function_exists('wp_send_new_user_notifications')) {
        wp_send_new_user_notifications($user_id, 'user');
    }
    */

    return new WP_REST_Response([
        'success' => true,
        'message' => 'Usuario registrado con éxito. Por favor, revise su email para activar su cuenta.'
    ], 200);
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
    $token = sanitize_text_field($params['token'] ?? '');

    if (empty($token)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Token is required.'
        ], 400);
    }

    // Find user by verification token using direct SQL for maximum reliability
    global $wpdb;
    $token = trim($token);
    
    $user_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
        '_motorlan_verification_token',
        $token
    ) );

    if (!$user_id) {
        // Log error for debugging if needed
        error_log("Motorlan Verification Error: Token $token not found in database.");
        
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Token de verificación inválido o expirado.'
        ], 400);
    }

    $user = get_userdata($user_id);
    if (!$user) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Usuario no encontrado.'
        ], 404);
    }

    // Mark as verified
    update_user_meta($user->ID, '_motorlan_email_verified', '1');
    delete_user_meta($user->ID, '_motorlan_verification_token');

    // Trigger welcome now that they are verified
    do_action('motorlan_user_welcome', $user->ID);

    return new WP_REST_Response([
        'success' => true,
        'message' => 'Email verificado con éxito. Ya puede iniciar sesión.'
    ], 200);
}

/**
 * Resend verification email callback.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response The response object.
 */
function motorlan_resend_verification_callback(WP_REST_Request $request)
{
    $params = $request->get_json_params();
    $email_or_login = sanitize_text_field($params['email'] ?? ($params['username'] ?? ''));
    $tokenParam = sanitize_text_field($params['token'] ?? '');

    if (empty($email_or_login) && empty($tokenParam)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Email, username or token is required.'
        ], 400);
    }

    $user = null;
    if (!empty($tokenParam)) {
        global $wpdb;
        $tokenParam = trim($tokenParam);
        $user_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
            '_motorlan_verification_token',
            $tokenParam
        ) );
        
        if ($user_id) {
            $user = get_userdata($user_id);
        }
    }

    if (!$user && !empty($email_or_login)) {
        if (is_email($email_or_login)) {
            $user = get_user_by('email', $email_or_login);
        } else {
            $user = get_user_by('login', $email_or_login);
        }
    }

    // Always return a neutral success message to prevent user enumeration
    if (!$user) {
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Si la cuenta existe y no está activada, se ha enviado un nuevo enlace.'
        ], 200);
    }

    // Check if already verified
    $is_verified = get_user_meta($user->ID, '_motorlan_email_verified', true);
    if ('1' === $is_verified) {
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Esta cuenta ya está activa. Por favor, inicie sesión.'
        ], 200);
    }

    // Rate limiting for resend: 3 per hour
    if (class_exists('Motorlan_Rate_Limiter') && !Motorlan_Rate_Limiter::check_limit('resend_verification', 3, 3600)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Demasiadas solicitudes de reenvío. Intente más tarde.'
        ], 429);
    }

    // Generate new token
    $token = wp_generate_password(32, false);
    update_user_meta($user->ID, '_motorlan_verification_token', $token);

    // Trigger email send
    do_action('motorlan_user_verify_email', $user->ID, $token);
    
    // Also send via manager direct (sync/backup) if action fails? No, action handles it.
    // However, the action uses wp_schedule_single_event which might be delayed.
    // If we want instant email, we should call manager direct.
    // But `Motorlan_Notification_Listener::on_user_verify_email` creates a DB notification AND schedules email.
    // Let's trust the action. The previous implementation was:
    // do_action('motorlan_user_verify_email', $user->ID, $token);
    // So this part is fine, as long as listener uses correct template.

    return new WP_REST_Response([
        'success' => true,
        'message' => 'Se ha enviado un nuevo enlace de activación a su email.'
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

    // Rate limiting: 3 attempts per 30 minutes
    if (class_exists('Motorlan_Rate_Limiter') && !Motorlan_Rate_Limiter::check_limit('forgot_password', 3, 30 * 60)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Demasiadas solicitudes. Intente más tarde.'
        ], 429);
    }

    if (empty($email)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Email is required.'
        ], 400);
    }

    $user = get_user_by('email', $email);

    // Always return success to prevent email enumeration
    if (!$user) {
        return new WP_REST_Response([
            'success' => true,
            'message' => 'If the email exists, a reset link has been sent.'
        ], 200);
    }

    // Generate reset key
    $reset_key = get_password_reset_key($user);

    if (is_wp_error($reset_key)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Could not generate reset key.'
        ], 500);
    }

    // Build reset URL
    $reset_url = home_url("/mi-cuenta/reset-password?key={$reset_key}&login=" . rawurlencode($user->user_login));

    // Send email via Notification Manager to ensure HTML template usage
    if (class_exists('Motorlan_Notification_Manager')) {
        $notification_manager = new Motorlan_Notification_Manager();
        
        // Use direct email sending
        $notification_manager->send_email_notification_direct(
            $user->ID,
            'password_reset',
            'Restablecer contraseña - Motorlan',
            'Has solicitado restablecer tu contraseña.',
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
            'Solicitud de restablecimiento de contraseña',
            'Has solicitado un cambio de contraseña. Revisa tu email.',
            [],
            ['web'] 
        );
    } else {
        // Fallback
        $message = "Hola {$user->display_name},\n\nVisita: {$reset_url}";
        wp_mail($user->user_email, 'Reset Password', $message);
    }

    return new WP_REST_Response([
        'success' => true,
        'message' => 'If the email exists, a reset link has been sent.'
    ], 200);
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
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Key, login and password are required.'
        ], 400);
    }

    // Validate password strength
    if ( function_exists( 'motorlan_validate_password_strength' ) ) {
        $valid_pass = motorlan_validate_password_strength( $password );
        if ( is_wp_error( $valid_pass ) ) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $valid_pass->get_error_message()
            ], 400);
        }
    } elseif (strlen($password) < 8) {
        // Fallback if function doesn't exist (shouldn't happen)
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Password must be at least 8 characters long.'
        ], 400);
    }

    // Verify the reset key
    $user = check_password_reset_key($key, $login);

    if (is_wp_error($user)) {
        $error_code = $user->get_error_code();
        $message = 'Invalid or expired reset key.';

        if ($error_code === 'expired_key') {
            $message = 'Reset key has expired. Please request a new one.';
        }

        return new WP_REST_Response([
            'success' => false,
            'message' => $message
        ], 400);
    }

    // Reset the password
    reset_password($user, $password);

    // Log password reset
    if (class_exists('Motorlan_Security_Logger')) {
        Motorlan_Security_Logger::log('password_reset_success', [
            'user_id' => $user->ID
        ]);
    }

    return new WP_REST_Response([
        'success' => true,
        'message' => 'Password has been reset successfully. You can now login.'
    ], 200);
}
