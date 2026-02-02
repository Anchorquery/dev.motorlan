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
    $params = $request->get_json_params();
    $username = sanitize_text_field($params['username'] ?? '');
    $password = $params['password'] ?? '';
    $remember = !empty($params['remember']);

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
    $params = $request->get_json_params();
    $username = sanitize_text_field($params['username'] ?? '');
    $email = sanitize_email($params['email'] ?? '');
    $password = $params['password'] ?? '';
    $first_name = sanitize_text_field($params['first_name'] ?? '');
    $last_name = sanitize_text_field($params['last_name'] ?? '');
    $name = sanitize_text_field($params['name'] ?? '');

    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Username, email, and password are required.'
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

    // Send welcome notification
    if (class_exists('Motorlan_Notification_Manager')) {
        $notification_manager = new Motorlan_Notification_Manager();
        $notification_manager->create_notification(
            $user_id,
            'welcome_email',
            'Bienvenido/a a Motorlan',
            'Gracias por registrarte en Motorlan. Tu cuenta ya está activa y lista para usarse.',
            [
                'name' => !empty($display_name) ? $display_name : $username,
                'username' => $username,
                'login_url' => home_url('/login'),
            ],
            ['email']
        );
    }

    // Send WordPress notifications
    if (function_exists('wp_send_new_user_notifications')) {
        wp_send_new_user_notifications($user_id, 'user');
    }

    return new WP_REST_Response([
        'success' => true,
        'message' => 'User registered successfully.'
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
    $params = $request->get_json_params();
    $email = sanitize_email($params['email'] ?? '');

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
    $reset_url = home_url("/reset-password?key={$reset_key}&login=" . rawurlencode($user->user_login));

    // Send email
    $subject = 'Restablecer contraseña - Motorlan';
    $message = sprintf(
        "Hola %s,\n\nHas solicitado restablecer tu contraseña.\n\nHaz clic en el siguiente enlace para crear una nueva contraseña:\n\n%s\n\nEste enlace expirará en 24 horas.\n\nSi no solicitaste este cambio, ignora este email.\n\nSaludos,\nEl equipo de Motorlan",
        $user->display_name,
        $reset_url
    );

    $sent = wp_mail($user->user_email, $subject, $message);

    // Also send via notification manager if available
    if (class_exists('Motorlan_Notification_Manager')) {
        $notification_manager = new Motorlan_Notification_Manager();
        $notification_manager->create_notification(
            $user->ID,
            'password_reset',
            'Solicitud de restablecimiento de contraseña',
            'Has solicitado restablecer tu contraseña.',
            [
                'name' => $user->display_name,
                'reset_url' => $reset_url,
            ],
            ['email']
        );
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
    if (strlen($password) < 8) {
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

    return new WP_REST_Response([
        'success' => true,
        'message' => 'Password has been reset successfully. You can now login.'
    ], 200);
}
