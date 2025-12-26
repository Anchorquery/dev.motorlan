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

    register_rest_route($namespace, '/profile', [
        'methods' => 'GET',
        'callback' => 'get_user_profile_data',
        'permission_callback' => 'motorlan_permission_callback_true'
    ]);

    register_rest_route($namespace, '/profile', [
        'methods' => 'POST',
        'callback' => 'update_user_profile_data',
        'permission_callback' => 'motorlan_permission_callback_true'
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
        ];
    }

    return new WP_REST_Response($user_data, 200);
}

function get_user_profile_data() {
    $user_id = get_current_user_id();
    $user_data = get_userdata($user_id);
    $user_meta = get_user_meta($user_id);

    $profile_data = [
        'personal_data' => [
            'nombre' => $user_data->first_name ?: $user_data->display_name,
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
