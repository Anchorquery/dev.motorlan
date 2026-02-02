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

require_once MOTORLAN_API_VUE_PATH . 'includes/classes/class-motorlan-notification-manager.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/admin/admin-mods.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/admin/admin-migration.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/admin/acf-purchases-sync.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/vue-app-setup.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/cron/cron-jobs.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/db/db-setup.php';

/**
 * Configuración global del plugin y funciones utilitarias.
 */

// CORS headers
function motorlan_add_cors_headers() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allow_origin = $origin ?: home_url();
    header('Access-Control-Allow-Origin: ' . $allow_origin);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-WP-Nonce, Authorization');
    header('Access-Control-Allow-Credentials: true');

    if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
        status_header(200);
        exit();
    }
}
add_action('rest_api_init', 'motorlan_add_cors_headers', 15);

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

register_activation_hook( __FILE__, 'motorlan_create_notifications_table' );
register_activation_hook( __FILE__, 'motorlan_offers_create_table' );
register_activation_hook( __FILE__, 'motorlan_create_purchase_messages_table' );
register_activation_hook( __FILE__, 'motorlan_create_product_messages_table' );
register_activation_hook( __FILE__, 'motorlan_create_product_room_reads_table' );
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
