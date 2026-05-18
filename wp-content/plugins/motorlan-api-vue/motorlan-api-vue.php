<?php
/**
 * Plugin Name: motorlan-api-vue
 * Plugin URI:  https://motorlan.com
 * Description: API para conectar con VUE
 * Version:     1.2
 * Author:      Adaki - Daniel H
 * Author URI:  https://motorlan.com
 */

use Tmeister\Firebase\JWT\JWT;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}


// Define plugin constants
define( 'MOTORLAN_API_VUE_VERSION', '1.2' );
define( 'MOTORLAN_API_VUE_PATH', plugin_dir_path( __FILE__ ) );
define( 'MOTORLAN_API_VUE_URL', plugin_dir_url( __FILE__ ) );


// Include required files
require_once MOTORLAN_API_VUE_PATH . 'includes/post-type/cpt-setup.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/post-type/cpt-setup-garantia.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/post-type/cpt-setup-purchases.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/custom-field/acf-setup.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/custom-field/acf-setup-garantia.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/custom-field/acf-setup-purchases.php';

require_once MOTORLAN_API_VUE_PATH . 'includes/api/publicaciones-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/profile-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/garantia-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/motor-helpers.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/purchases-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/questions-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/offers-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/session-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/sales-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/notifications-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/my-publications-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/admin-approval-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/admin-publications-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/product-messages-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/reviews-routes.php';

require_once MOTORLAN_API_VUE_PATH . 'includes/classes/class-motorlan-rate-limiter.php'; // Security Rate Limiter
require_once MOTORLAN_API_VUE_PATH . 'includes/classes/class-motorlan-security-logger.php'; // Security Logger

require_once MOTORLAN_API_VUE_PATH . 'includes/classes/class-motorlan-notification-manager.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/classes/class-motorlan-notification-listener.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/i18n/motorlan-i18n.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/admin/admin-mods.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/admin/admin-migration.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/admin/admin-offers-setup.php'; // New Offers Admin Page
require_once MOTORLAN_API_VUE_PATH . 'includes/admin/admin-email-debug.php'; // Email Debug Tool
require_once MOTORLAN_API_VUE_PATH . 'includes/admin/admin-translations.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/admin/acf-purchases-sync.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/vue-app-setup.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/cron/cron-jobs.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/db/db-setup.php';

/**
 * Interceptar la carga de plantillas para usar nuestra plantilla personalizada del plugin
 * para el Custom Post Type 'publicacion'.
 */

function motorlan_init_notifications() {
    if ( class_exists( 'Motorlan_Notification_Listener' ) ) {
        $listener = new Motorlan_Notification_Listener();
        $listener->init();
    }
}
add_action( 'init', 'motorlan_init_notifications' );

/**
 * Procesa el envío de notificaciones por correo de forma asíncrona.
 */
function motorlan_execute_async_email( $user_id, $type, $title, $message, $data ) {
    if ( class_exists( 'Motorlan_Notification_Manager' ) ) {
        $manager = new Motorlan_Notification_Manager();
        // Llamamos al método público recién expuesto (necesitamos actualizar la clase también)
        if ( method_exists( $manager, 'send_email_notification_direct' ) ) {
            $manager->send_email_notification_direct( $user_id, $type, $title, $message, $data );
        }
    }
}
add_action( 'motorlan_async_email_notification', 'motorlan_execute_async_email', 10, 5 );

function motorlan_publicacion_template_include($template) {
    if (is_singular('publicacion')) {
        $plugin_template = MOTORLAN_API_VUE_PATH . 'includes/templates/single-publicacion.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
}
add_filter('template_include', 'motorlan_publicacion_template_include', 99);

/**
 * Configuración global del plugin y funciones utilitarias.
 */

// CORS headers
/**
 * CORS seguro con whitelist de dominios permitidos.
 * Previene ataques desde orígenes no autorizados.
 */
function motorlan_add_cors_headers() {
    // Definir dominios permitidos
    $allowed_origins = apply_filters('motorlan_allowed_origins', [
        'https://motorlan.es',
        'https://www.motorlan.es',
        'https://dev.motorlan.es'
        
    ]);
    
    // En desarrollo local, permitir localhost
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $allowed_origins[] = 'http://localhost:3000';
        $allowed_origins[] = 'http://localhost:5173'; // Vite dev server
        $allowed_origins[] = 'http://127.0.0.1:3000';
        $allowed_origins[] = 'http://127.0.0.1:5173';
    }
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    // Validar contra whitelist
    if (in_array($origin, $allowed_origins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    } else {
        // Si el origen no está permitido, usar el domain principal
        header('Access-Control-Allow-Origin: ' . home_url());
        header('Vary: Origin');
    }
    
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-WP-Nonce, Authorization');
    
    if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
        status_header(200);
        exit();
    }
}
add_action('rest_api_init', 'motorlan_add_cors_headers', 15);

/**
 * Agrega headers de seguridad HTTP para mejorar la protección.
 * Implementa HSTS, Anti-clickjacking, XSS protection, etc.
 */
function motorlan_add_security_headers($headers) {
    // HSTS (Strict-Transport-Security) - Solo si es HTTPS
    if (is_ssl()) {
        $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
    }

    // Headers de seguridad estándar
    $headers['X-Content-Type-Options'] = 'nosniff';
    $headers['X-Frame-Options'] = 'SAMEORIGIN';
    $headers['X-XSS-Protection'] = '1; mode=block';
    $headers['Referrer-Policy'] = 'strict-origin-when-cross-origin';

    // Content-Security-Policy (CSP)
    // Permite scripts propios, fonts de Google, y estilos inline
    $csp = "default-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com data: blob:; ";
    $csp .= "script-src 'self' 'unsafe-inline' 'unsafe-eval' blob:; ";
    $csp .= "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; ";
    $csp .= "worker-src 'self' blob:; ";
    $csp .= "img-src 'self' data: https: blob:; ";
    $csp .= "font-src 'self' https://fonts.gstatic.com data:; ";
    $csp .= "connect-src 'self' https://motorlan.com https://www.motorlan.es https://dev.motorlan.es http://localhost:* ws://localhost:* http://127.0.0.1:* ws://127.0.0.1:*; ";
    $csp .= "frame-src 'self' blob:; ";
    $csp .= "object-src 'none'; ";
    
    $headers['Content-Security-Policy'] = $csp;

    return $headers;
}
// Usamos wp_headers para mayor compatibilidad y permitir que WP lo maneje
add_filter('wp_headers', 'motorlan_add_security_headers', 999);

/**
 * Personaliza el título del menú que tenga la clase 'menu-user-name' para mostrar el nombre del usuario si está logueado.
 */
function motorlan_customize_nav_menu_items($items, $args) {
    foreach ($items as &$item) {
        // Buscamos el item por su clase CSS personalizada para evitar problemas con idiomas
        if (in_array('menu-user-name', (array) $item->classes)) {
            if (is_user_logged_in()) {
                $current_user = wp_get_current_user();
                $display_name = !empty($current_user->display_name) ? $current_user->display_name : $current_user->user_login;
                $item->title = 'Bienvenido, ' . $display_name;
            }
        }
    }
    return $items;
}
add_filter('wp_nav_menu_objects', 'motorlan_customize_nav_menu_items', 10, 2);

// Permisos básicos
function motorlan_is_user_authenticated() {
    return is_user_logged_in();
}

function motorlan_permission_callback_true() {
    return true;
}

/**
 * Determine whether the current REST request targets a public Motorlan endpoint.
 *
 * @param WP_REST_Request|null $request The request object.
 * @return bool
 */
function motorlan_is_public_rest_route( $request ) {
    if ( ! class_exists( 'WP_REST_Request' ) || ! $request instanceof WP_REST_Request ) {
        return false;
    }

    $method = strtoupper( $request->get_method() );
    $route  = untrailingslashit( $request->get_route() );

    $public_routes = [
        'GET'  => [
            '#^/motorlan/v1/publicaciones$#',
            '#^/motorlan/v1/publicaciones/uuid/[A-Za-z0-9-]+$#',
            '#^/motorlan/v1/publicaciones/[A-Za-z0-9-]+$#',
            '#^/motorlan/v1/store/publicaciones$#',
            '#^/motorlan/v1/publicacion-categories$#',
            '#^/motorlan/v1/tipos$#',
            '#^/motorlan/v1/marcas$#',
            '#^/motorlan/v1/garantias/publicacion/[A-Za-z0-9-]+$#',
            '#^/motorlan/v1/session$#',
        ],
        'POST' => [
            '#^/motorlan/v1/register$#',
            '#^/motorlan/v1/check-username$#',
        ],
    ];

    if ( empty( $public_routes[ $method ] ) ) {
        return false;
    }

    foreach ( $public_routes[ $method ] as $pattern ) {
        if ( preg_match( $pattern, $route ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Allow public Motorlan endpoints or expired JWTs to bypass REST authentication errors.
 *
 * @param WP_Error|null $result Current authentication result.
 * @return WP_Error|null
 */
function motorlan_allow_public_rest_routes( $result ) {
    if ( ! function_exists( 'rest_is_request' ) || ! function_exists( 'rest_get_server' ) || ! rest_is_request() ) {
        return $result;
    }

    $request = rest_get_server()->get_current_request();
    if ( ! $request instanceof WP_REST_Request ) {
        return $result;
    }

    if ( is_wp_error( $result ) ) {
        $error_code    = $result->get_error_code();
        $error_message = $result->get_error_message();

        if ( $error_code === 'jwt_auth_invalid_token' && false !== stripos( $error_message, 'Expired token' ) ) {
            if ( motorlan_refresh_jwt_for_request( $request ) ) {
                return null;
            }
        }
    }

    if ( motorlan_is_public_rest_route( $request ) ) {
        return null;
    }

    return $result;
}

add_filter( 'rest_authentication_errors', 'motorlan_allow_public_rest_routes', 100 );

/**
 * Intercept the REST request before dispatch to purge expired JWT errors.
 *
 * @param WP_Error|null  $result
 * @param WP_REST_Server $server
 * @param WP_REST_Request $request
 * @return WP_Error|null
 */
function motorlan_refresh_jwt_on_pre_dispatch( $result, $server, $request ) {
    if ( ! is_wp_error( $result ) ) {
        return $result;
    }

    $error_code    = $result->get_error_code();
    $error_message = $result->get_error_message();

    if ( $error_code === 'jwt_auth_invalid_token' && false !== stripos( $error_message, 'Expired token' ) ) {
        if ( motorlan_refresh_jwt_for_request( $request ) ) {
            return null;
        }
    }

    return $result;
}

add_filter( 'rest_pre_dispatch', 'motorlan_refresh_jwt_on_pre_dispatch', 20, 3 );

/**
 * Refreshes an expired JWT by validating its signature and issuing a new token.
 *
 * @param WP_REST_Request $request
 * @return string|null New token string on success.
 */
function motorlan_refresh_jwt_for_request( WP_REST_Request $request ) {
    if ( ! class_exists( 'Tmeister\\Firebase\\JWT\\JWT' ) ) {
        return null;
    }

    $header = motorlan_get_authorization_header_from_request( $request );
    if ( ! $header ) {
        return null;
    }

    $token = motorlan_extract_bearer_token( $header );
    if ( ! $token ) {
        return null;
    }

    $decoded = motorlan_decode_jwt_payload( $token );
    if ( ! $decoded || ! isset( $decoded['payload']['data']['user']['id'] ) ) {
        return null;
    }

    $user_id = absint( $decoded['payload']['data']['user']['id'] );
    if ( ! $user_id ) {
        return null;
    }

    $token_data = motorlan_generate_jwt_for_user( $user_id );
    if ( ! $token_data || empty( $token_data['token'] ) ) {
        return null;
    }

    $expiration = $token_data['__expiration'] ?? 0;
    motorlan_store_refreshed_jwt_token( $token_data['token'], $expiration );

    return $token_data['token'];
}

/**
 * Generate a new JWT for the provided user ID.
 *
 * @param int $user_id
 * @return array|null
 */
function motorlan_generate_jwt_for_user( $user_id ) {
    if ( ! class_exists( 'Tmeister\\Firebase\\JWT\\JWT' ) ) {
        return null;
    }

    $user = get_userdata( $user_id );
    if ( ! $user ) {
        return null;
    }

    $secret_key = defined( 'JWT_AUTH_SECRET_KEY' ) ? JWT_AUTH_SECRET_KEY : false;
    if ( ! $secret_key ) {
        return null;
    }

    $issued_at  = time();
    $not_before = apply_filters( 'jwt_auth_not_before', $issued_at, $issued_at );
    $expire     = apply_filters( 'jwt_auth_expire', $issued_at + ( DAY_IN_SECONDS * 7 ), $issued_at );

    $token = [
        'iss'  => get_bloginfo( 'url' ),
        'iat'  => $issued_at,
        'nbf'  => $not_before,
        'exp'  => $expire,
        'data' => [
            'user' => [
                'id' => $user->ID,
            ],
        ],
    ];

    $algorithm = apply_filters( 'jwt_auth_algorithm', 'HS256' );
    $token     = JWT::encode(
        apply_filters( 'jwt_auth_token_before_sign', $token, $user ),
        $secret_key,
        $algorithm
    );

    $response_data = [
        'token'             => $token,
        'user_email'        => $user->user_email,
        'user_nicename'     => $user->user_nicename,
        'user_display_name' => $user->display_name,
        '__expiration'      => $expire,
    ];

    return apply_filters( 'jwt_auth_token_before_dispatch', $response_data, $user );
}

/**
 * Record the refreshed token so other hooks can reuse it.
 *
 * @param string $token
 * @param int    $expiration
 */
function motorlan_store_refreshed_jwt_token( $token, $expiration = 0 ) {
    $expiration = $expiration ?: time() + ( DAY_IN_SECONDS * 7 );
    $GLOBALS['motorlan_refreshed_jwt'] = [
        'token'      => $token,
        'expiration' => $expiration,
    ];

    motorlan_set_access_token_cookie( $token, $expiration );
}

/**
 * Retrieve the refreshed token for the current request.
 *
 * @return array|null
 */
function motorlan_get_refreshed_jwt_token() {
    return $GLOBALS['motorlan_refreshed_jwt'] ?? null;
}

/**
 * Set the access token cookie so the browser picks up the new token.
 *
 * @param string $token
 * @param int    $expiration
 */
function motorlan_set_access_token_cookie( $token, $expiration = 0 ) {
    if ( headers_sent() ) {
        return;
    }

    $expiration = $expiration ?: time() + ( DAY_IN_SECONDS * 7 );
    $secure     = ! empty( $_SERVER['HTTPS'] ) && 'off' !== strtolower( $_SERVER['HTTPS'] ?? '' );
    $domain     = $_SERVER['HTTP_HOST'] ?? '';

    setcookie(
        'accessToken',
        $token,
        $expiration,
        '/',
        $domain,
        $secure,
        false
    );
}

/**
 * Add an extra header to the REST response so clients know a new token was issued.
 *
 * @param mixed            $result
 * @param WP_REST_Server   $server
 * @param WP_REST_Request  $request
 * @return mixed
 */
function motorlan_add_refreshed_jwt_header( $result, $server, $request ) {
    $refreshed = motorlan_get_refreshed_jwt_token();
    if ( $refreshed && ! empty( $refreshed['token'] ) ) {
        $server->send_header( 'X-Motorlan-New-Access-Token', $refreshed['token'] );
        if ( ! empty( $refreshed['expiration'] ) ) {
            $server->send_header( 'X-Motorlan-New-Access-Token-Expires', gmdate( DATE_RFC850, $refreshed['expiration'] ) );
        }
    }

    return $result;
}

/**
 * Extract the Authorization header from the request.
 *
 * @param WP_REST_Request $request
 * @return string|null
 */
function motorlan_get_authorization_header_from_request( WP_REST_Request $request ) {
    $header = $request->get_header( 'Authorization' );

    if ( empty( $header ) ) {
        $header = $request->get_header( 'authorization' );
    }

    if ( empty( $header ) && ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
        $header = sanitize_text_field( $_SERVER['HTTP_AUTHORIZATION'] );
    }

    if ( empty( $header ) && ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
        $header = sanitize_text_field( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
    }

    return $header ?: null;
}

/**
 * Extract a Bearer token from an Authorization header.
 *
 * @param string $header
 * @return string|null
 */
function motorlan_extract_bearer_token( $header ) {
    if ( ! $header ) {
        return null;
    }

    [ $token ] = sscanf( $header, 'Bearer %s' );

    return $token ?: null;
}

/**
 * Decode the JWT payload after verifying the signature.
 *
 * @param string $token
 * @return array|null
 */
function motorlan_decode_jwt_payload( $token ) {
    $parts = explode( '.', $token );
    if ( count( $parts ) !== 3 ) {
        return null;
    }

    [ $header_encoded, $payload_encoded, $signature_encoded ] = $parts;
    $header_json  = motorlan_base64url_decode( $header_encoded );
    $payload_json = motorlan_base64url_decode( $payload_encoded );

    if ( false === $header_json || false === $payload_json ) {
        return null;
    }

    $header  = json_decode( $header_json, true );
    $payload = json_decode( $payload_json, true );

    if ( ! is_array( $header ) || ! is_array( $payload ) ) {
        return null;
    }

    $secret_key = defined( 'JWT_AUTH_SECRET_KEY' ) ? JWT_AUTH_SECRET_KEY : false;
    if ( ! $secret_key ) {
        return null;
    }

    $signature = motorlan_base64url_decode( $signature_encoded );
    if ( false === $signature ) {
        return null;
    }

    if ( empty( $header['alg'] ) ) {
        return null;
    }

    if ( ! motorlan_verify_jwt_signature( $header['alg'], $header_encoded . '.' . $payload_encoded, $signature, $secret_key ) ) {
        return null;
    }

    return [
        'header'  => $header,
        'payload' => $payload,
    ];
}

/**
 * Verify a JWT signature.
 *
 * @param string $algorithm
 * @param string $data
 * @param string $signature
 * @param string $secret
 * @return bool
 */
function motorlan_verify_jwt_signature( $algorithm, $data, $signature, $secret ) {
    $algorithm = strtoupper( $algorithm );

    switch ( $algorithm ) {
        case 'HS256':
            $hash = 'sha256';
            break;
        case 'HS384':
            $hash = 'sha384';
            break;
        case 'HS512':
            $hash = 'sha512';
            break;
        default:
            return false;
    }

    $expected = hash_hmac( $hash, $data, $secret, true );

    return hash_equals( $expected, $signature );
}

/**
 * Decode a base64url string.
 *
 * @param string $value
 * @return string|false
 */
function motorlan_base64url_decode( $value ) {
    $value = str_replace( [ '-', '_' ], [ '+', '/' ], $value );
    $remainder = strlen( $value ) % 4;
    if ( $remainder ) {
        $value .= str_repeat( '=', 4 - $remainder );
    }

    return base64_decode( $value );
}

add_filter( 'rest_post_dispatch', 'motorlan_add_refreshed_jwt_header', 10, 3 );


require_once MOTORLAN_API_VUE_PATH . 'includes/db/db-create-purchase-messages.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/db/db-create-product-messages.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/db/db-create-product-room-reads.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/db/db-create-notifications.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/db/db-create-security-logs.php'; // Logs de seguridad

register_activation_hook( __FILE__, 'motorlan_create_notifications_table' );
register_activation_hook( __FILE__, 'motorlan_offers_create_table' );
register_activation_hook( __FILE__, 'motorlan_create_purchase_messages_table' );
register_activation_hook( __FILE__, 'motorlan_create_product_messages_table' );
register_activation_hook( __FILE__, 'motorlan_run_migrations' );
register_activation_hook( __FILE__, 'motorlan_create_product_room_reads_table' );
register_activation_hook( __FILE__, 'motorlan_create_security_logs_table' ); // Activar tabla de logs

require_once MOTORLAN_API_VUE_PATH . 'includes/db/db-add-chat-indexes.php';
register_activation_hook( __FILE__, 'motorlan_add_chat_indexes' );
/**
 * Filter the JWT payload to fix the "Not Before" (nbf) claim.
 * This prevents issues with server time synchronization.
 *
 * @param array $payload The JWT payload.
 * @param WP_User $user The user object.
 * @return array The modified JWT payload.
 */
function motorlan_jwt_payload_fix_nbf($payload, $user) {
    // Subtract 10 seconds from the 'nbf' claim to ensure the token is valid immediately.
    if (isset($payload['nbf'])) {
        $payload['nbf'] = $payload['nbf'] - 10;
    }
    
    // Also, ensure the 'iat' (Issued At) is not in the future.
    if (isset($payload['iat'])) {
        $payload['iat'] = min($payload['iat'], time());
    }

    return $payload;
}
add_filter('jwt_auth_payload', 'motorlan_jwt_payload_fix_nbf', 10, 2);

/**
 * Vue History Mode: Register rewrite rules for pages with Vue app shortcode.
 * This allows sub-routes like /marketplace-motorlan/product-slug to load the correct page.
 */
function motorlan_vue_history_rewrite_rules() {
    // Páginas conocidas con la app Vue (agregar más si es necesario)
    $vue_pages = ['marketplace-motorlan', 'mi-cuenta', 'store', 'login', 'register', 'forgot-password', 'reset-password', 'verify-email', 'register-success'];

    foreach ($vue_pages as $page_slug) {
        // Captura cualquier sub-ruta bajo esta página
        add_rewrite_rule(
            "^{$page_slug}(/.*)?/?$",
            "index.php?pagename={$page_slug}",
            'top'
        );
    }
}
add_action('init', 'motorlan_vue_history_rewrite_rules', 1);

/**
 * Flush rewrite rules on plugin activation.
 */
function motorlan_vue_activate() {
    motorlan_vue_history_rewrite_rules();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'motorlan_vue_activate');

/**
 * Prevent WordPress from redirecting to login when accessing Vue app pages.
 * This allows Vue Router to handle authentication internally.
 */
function motorlan_prevent_wp_login_redirect() {
    // Check if we're on a Vue app page
    $vue_pages = ['marketplace-motorlan', 'mi-cuenta', 'store', 'login', 'register', 'forgot-password', 'reset-password', 'verify-email', 'register-success'];
    $current_page = get_query_var('pagename');

    if (empty($current_page)) {
        // Try to get page from post name
        $current_page = get_query_var('name');
    }

    // Check if current page is a Vue app page
    $is_vue_page = false;
    foreach ($vue_pages as $vue_page) {
        if ($current_page === $vue_page || strpos($_SERVER['REQUEST_URI'], '/' . $vue_page . '/') === 0) {
            $is_vue_page = true;
            break;
        }
    }

    if ($is_vue_page) {
        // Remove any auth redirect hooks
        remove_action('template_redirect', 'auth_redirect');

        // Allow access without login
        add_filter('user_has_cap', function($allcaps) {
            $allcaps['read'] = true;
            return $allcaps;
        }, 999);
    }
}
add_action('template_redirect', 'motorlan_prevent_wp_login_redirect', 1);

/**
 * Force WordPress to recognize Vue app pages even with sub-routes.
 */
function motorlan_parse_vue_app_request($wp) {
    // Get the request URI
    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

    // List of Vue app base pages
    $vue_pages = ['marketplace-motorlan', 'mi-cuenta', 'store', 'login', 'register', 'forgot-password', 'reset-password', 'verify-email', 'register-success'];

    foreach ($vue_pages as $vue_page) {
        // Check if the URI starts with this Vue page
        $path = parse_url($request_uri, PHP_URL_PATH);
        if ($path && preg_match("#^/{$vue_page}(/|$)#", $path)) {
            
            // Try to find the specific page first
            $page = get_page_by_path($vue_page);
            $final_vue_page = $vue_page;

            // FALLBACK logic: If the specific page doesn't exist, use a "master" page that has the app
            if (!$page) {
                $fallbacks = ['mi-cuenta', 'marketplace-motorlan', 'store'];
                foreach ($fallbacks as $fallback) {
                    $page = get_page_by_path($fallback);
                    if ($page) {
                        $final_vue_page = $fallback;
                        break; 
                    }
                }
            }

            if ($page) {
                // Set query vars to the page we found
                $wp->query_vars['pagename'] = $final_vue_page;

                // Remove 404 status
                global $wp_query;
                if ($wp_query) {
                    $wp_query->is_404 = false;
                    $wp_query->is_page = true;
                    $wp_query->is_singular = true;
                    $wp_query->queried_object = $page;
                    $wp_query->queried_object_id = $page->ID;
                    $wp_query->post = $page;
                    $wp_query->posts = [$page];
                    $wp_query->post_count = 1;
                }
            }
            break;
        }
    }
}
add_action('parse_request', 'motorlan_parse_vue_app_request', 1);
