<?php
/**
 * Ajustes de autenticación de la API REST de Motorlan.
 *
 * @package motorlan-api-vue
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Rutas POST que no dependen de la sesión del usuario (alta, acceso, recuperación,
 * verificación). /check-username se eliminó.
 */
function motorlan_anonymous_post_routes() {
    return [
        '#^/motorlan/v1/register$#',
        '#^/motorlan/v1/login$#',
        '#^/motorlan/v1/forgot-password$#',
        '#^/motorlan/v1/reset-password$#',
        '#^/motorlan/v1/verify-email$#',
        '#^/motorlan/v1/resend-verification$#',
        '#^/motorlan/v1/profile/confirm-email-change$#',
    ];
}

/**
 * ¿Es (método, ruta) uno de los flujos anónimos POST?
 *
 * @param string $method Método HTTP.
 * @param string $route  Ruta REST (p. ej. /motorlan/v1/login).
 * @return bool
 */
function motorlan_is_anonymous_post_route( $method, $route ) {
    if ( 'POST' !== strtoupper( (string) $method ) ) {
        return false;
    }
    $route = '/' . trim( (string) $route, '/' );
    foreach ( motorlan_anonymous_post_routes() as $pattern ) {
        if ( preg_match( $pattern, $route ) ) {
            return true;
        }
    }
    return false;
}

/**
 * Deja pasar los flujos anónimos POST aunque falle la comprobación de cookie/nonce.
 *
 * Caso típico: pestaña de "registro completado" abierta desde ayer → el nonce anónimo
 * caducó → "Reenviar enlace" daba 403 rest_cookie_invalid_nonce para siempre.
 *
 * SEGURIDAD: si la comprobación falló, la petición se atiende como ANÓNIMA
 * (wp_set_current_user(0)): un nonce inválido nunca autentica a nadie.
 *
 * Antes este filtro no hacía nada: dependía de rest_is_request() y
 * WP_REST_Server::get_current_request(), que no existen en WordPress (6.9.4), y salía en
 * la primera línea. Ya no se aplica a las GET "públicas": con cookie válida y nonce
 * inválido seguían autenticadas y, con ?_jsonp=, otra web podía leer /session o
 * /publicaciones/uuid/*. Las GET con nonce caducado devuelven 403 y la SPA renueva el
 * nonce y repite (app/src/composables/useApi.ts, fetchWithNonceRetry).
 *
 * Eliminado el código heredado que "refrescaba" JWT caducados (aceptaba cualquier
 * token con firma válida aunque hubiera expirado y emitía otro nuevo). La SPA no usa
 * JWT; el soporte JWT del plugin jwt-authentication-for-wp-rest-api sigue intacto.
 *
 * @param WP_Error|null|true $result Current authentication result.
 * @return WP_Error|null|true
 */
function motorlan_allow_public_rest_routes( $result ) {
    if ( ! is_wp_error( $result ) ) {
        return $result;
    }
    $is_rest = function_exists( 'wp_is_serving_rest_request' ) ? wp_is_serving_rest_request() : ( defined( 'REST_REQUEST' ) && REST_REQUEST );
    if ( ! $is_rest ) {
        return $result;
    }

    $route  = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
    $method = isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : '';
    if ( ! empty( $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ) ) {
        $method = (string) $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'];
    } elseif ( ! empty( $_GET['_method'] ) && is_string( $_GET['_method'] ) ) {
        $method = $_GET['_method'];
    }

    if ( ! motorlan_is_anonymous_post_route( $method, $route ) ) {
        return $result;
    }

    wp_set_current_user( 0 );
    return null;
}

add_filter( 'rest_authentication_errors', 'motorlan_allow_public_rest_routes', 100 );

// Nada usa JSONP (?_jsonp=): sin él, una web ajena no puede leer respuestas autenticadas
// con la cookie mediante <script src>.
add_filter( 'rest_jsonp_enabled', '__return_false' );
