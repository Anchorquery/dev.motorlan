<?php
/**
 * Setup for Session REST API Routes.
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

    register_rest_route($namespace, '/session', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_session_data_callback',
        'permission_callback' => '__return_true',
    ]);


    register_rest_route($namespace, '/register', [
        'methods' => 'POST',
        'callback' => 'motorlan_register_user_callback',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($namespace, '/check-username', [
        'methods' => 'POST',
        'callback' => 'motorlan_check_username_callback',
        'permission_callback' => '__return_true',
    ]);
}
add_action('rest_api_init', 'motorlan_register_session_rest_routes');

/**
 * Callback function to check username availability.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response The response object.
 */
function motorlan_check_username_callback(WP_REST_Request $request) {
    $params = $request->get_json_params();
    $username = sanitize_text_field($params['username']);

    if (empty($username)) {
        return new WP_REST_Response(['available' => false, 'message' => 'Username is required.'], 400);
    }

    if (username_exists($username)) {
        return new WP_REST_Response(['available' => false, 'message' => 'Username already exists.'], 200);
    }

    return new WP_REST_Response(['available' => true], 200);
}

/**
 * Callback function to register a new user.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response The response object.
 */
function motorlan_register_user_callback(WP_REST_Request $request) {
    $params = $request->get_json_params();
    $username = sanitize_text_field($params['username']);
    $email = sanitize_email($params['email']);
    $password = $params['password'];
    $first_name = isset($params['first_name']) ? sanitize_text_field($params['first_name']) : '';
    $last_name = isset($params['last_name']) ? sanitize_text_field($params['last_name']) : '';
    $name = isset($params['name']) ? sanitize_text_field($params['name']) : '';

    if (empty($username) || empty($email) || empty($password)) {
        return new WP_REST_Response(['message' => 'Username, email, and password are required.'], 400);
    }

    if (username_exists($username)) {
        return new WP_REST_Response(['message' => 'Username already exists.'], 400);
    }

    if (email_exists($email)) {
        return new WP_REST_Response(['message' => 'Email already exists.'], 400);
    }

    $user_id = wp_create_user($username, $password, $email);

    if (is_wp_error($user_id)) {
        return new WP_REST_Response(['message' => $user_id->get_error_message()], 500);
    }

    // Determine name information prioritizing explicit first/last names and falling back to the full name string.
    if (empty($first_name) && !empty($name)) {
        $name_parts = preg_split('/\s+/', trim($name));
        if (!empty($name_parts)) {
            $first_name = array_shift($name_parts);
            $last_name = trim(implode(' ', $name_parts));
        }
    }

    if (empty($first_name) && !empty($name)) {
        $first_name = $name;
    }

    $display_name = trim(implode(' ', array_filter([$first_name, $last_name])));
    if (empty($display_name) && !empty($name)) {
        $display_name = $name;
    }

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

        $update_result = wp_update_user($update_args);
        if (is_wp_error($update_result)) {
            return new WP_REST_Response(['message' => 'User registered but failed to save profile data: ' . $update_result->get_error_message()], 500);
        }
    }

    // Send welcome email through the notification manager (also creates a notification entry).
    if (class_exists('Motorlan_Notification_Manager')) {
        $notification_manager = new Motorlan_Notification_Manager();
        $welcome_title = 'Bienvenido/a a Motorlan';
        $welcome_message = 'Gracias por registrarte en Motorlan. Tu cuenta ya esta activa y lista para usarse.';
        $notification_manager->create_notification(
            $user_id,
            'welcome_email',
            $welcome_title,
            $welcome_message,
            [
                'name' => !empty($display_name) ? $display_name : $username,
                'username' => $username,
                'login_url' => home_url('/login'),
            ],
            ['email']
        );
    }

    if (function_exists('wp_send_new_user_notifications')) {
        wp_send_new_user_notifications($user_id, 'user');
    }

    return new WP_REST_Response(['message' => 'User registered successfully.'], 200);
}

/**
 * Callback function to get session data.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response The response object.
 */
function motorlan_get_session_data_callback(WP_REST_Request $request)
{
    $user_data = [
        'is_logged_in' => is_user_logged_in(),
        'user' => null,
    ];

    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $user_data['user'] = [
            'id' => $current_user->ID,
            'email' => $current_user->user_email,
            'display_name' => $current_user->display_name,
            'is_admin' => in_array('administrator', (array) $current_user->roles),
        ];
    }

    return new WP_REST_Response($user_data, 200);
}

