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
