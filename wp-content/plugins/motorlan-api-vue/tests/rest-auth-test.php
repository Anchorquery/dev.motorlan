<?php
/**
 * Test aislado de includes/api/rest-auth.php (sin WordPress ni base de datos).
 *
 * Uso: php tests/rest-auth-test.php
 */

require __DIR__ . '/bootstrap.php';

require dirname( __DIR__ ) . '/includes/api/rest-auth.php';

// --- motorlan_is_anonymous_post_route ---
t_assert( motorlan_is_anonymous_post_route( 'POST', '/motorlan/v1/login' ), 'login POST es anónima' );
t_assert( motorlan_is_anonymous_post_route( 'post', '/motorlan/v1/resend-verification/' ), 'método en minúsculas y barra final' );
t_assert( motorlan_is_anonymous_post_route( 'POST', 'motorlan/v1/register' ), 'ruta sin barra inicial' );
t_assert( ! motorlan_is_anonymous_post_route( 'GET', '/motorlan/v1/login' ), 'GET no cuenta' );
t_assert( ! motorlan_is_anonymous_post_route( 'POST', '/motorlan/v1/logout' ), 'logout no es anónima' );
t_assert( ! motorlan_is_anonymous_post_route( 'POST', '/motorlan/v1/login-x' ), 'sin coincidencias parciales' );
t_assert( ! motorlan_is_anonymous_post_route( 'POST', '/motorlan/v1/profile' ), 'perfil no es anónima' );

// --- motorlan_allow_public_rest_routes ---
$nonce_error = new WP_Error( 'rest_cookie_invalid_nonce', 'x' );

function t_request( $method, $route ) {
    $GLOBALS['wp'] = (object) [ 'query_vars' => [ 'rest_route' => $route ] ];
    $_SERVER['REQUEST_METHOD'] = $method;
    unset( $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] );
    $_GET = [];
    $GLOBALS['__current_user'] = 7; // cookie válida
}

t_request( 'POST', '/motorlan/v1/resend-verification' );
t_assert( null === motorlan_allow_public_rest_routes( $nonce_error ), 'POST anónima con nonce inválido pasa' );
t_assert( 0 === $GLOBALS['__current_user'], '... y se atiende como anónima' );

t_request( 'GET', '/motorlan/v1/session' );
t_assert( $nonce_error === motorlan_allow_public_rest_routes( $nonce_error ), 'GET /session con nonce inválido sigue dando el error (la SPA renueva el nonce)' );
t_assert( 7 === $GLOBALS['__current_user'], '... sin tocar el usuario' );

t_request( 'POST', '/motorlan/v1/profile' );
t_assert( $nonce_error === motorlan_allow_public_rest_routes( $nonce_error ), 'POST autenticada con nonce inválido sigue dando error' );

t_request( 'POST', '/motorlan/v1/login' );
t_assert( true === motorlan_allow_public_rest_routes( true ), 'sin error no se toca el resultado' );
t_assert( 7 === $GLOBALS['__current_user'], '... ni el usuario' );

t_request( 'POST', '/motorlan/v1/login' );
$_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] = 'DELETE';
t_assert( $nonce_error === motorlan_allow_public_rest_routes( $nonce_error ), 'X-HTTP-Method-Override cambia el método' );

t_request( 'POST', '/motorlan/v1/login' );
$GLOBALS['__rest'] = false;
t_assert( $nonce_error === motorlan_allow_public_rest_routes( $nonce_error ), 'fuera de REST no actúa' );
$GLOBALS['__rest'] = true;

t_assert( has_filter_registered( 'rest_jsonp_enabled', '__return_false' ), 'JSONP desactivado' );

t_done();
