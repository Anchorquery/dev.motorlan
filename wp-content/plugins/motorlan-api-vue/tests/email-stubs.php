<?php
/**
 * Stubs de WordPress para renderizar los emails sin cargar WordPress ni la base de datos.
 * Los usan email-templates-test.php y email-previews.php. Las funciones de escapado
 * replican el comportamiento de las reales (esc_url descarta esquemas peligrosos y codifica
 * «&»; wp_kses solo deja las etiquetas permitidas, sin atributos).
 */

require_once __DIR__ . '/bootstrap.php';

if ( ! defined( 'MOTORLAN_API_VUE_PATH' ) ) {
    define( 'MOTORLAN_API_VUE_PATH', dirname( __DIR__ ) . '/' );
}

$GLOBALS['__home']    = 'https://dev.motorlan.test/';
$GLOBALS['__rtl']     = false;
$GLOBALS['__locale']  = 'es_ES';
$GLOBALS['__mails']   = array();
$GLOBALS['__users']   = array();
$GLOBALS['__errlog']  = array();

if ( ! function_exists( 'esc_html__' ) ) {
    function esc_html__( $t, $d = '' ) { return esc_html( $t ); }
    function esc_html_e( $t, $d = '' ) { echo esc_html( $t ); }
    function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
    function esc_attr__( $t, $d = '' ) { return esc_attr( $t ); }
    function esc_url( $url ) {
        $url = trim( (string) $url );
        if ( '' === $url ) { return ''; }
        $url = preg_replace( '|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\\x80-\\xff]|i', '', $url );
        if ( preg_match( '#^([a-z][a-z0-9+.-]*):#i', $url, $m ) && ! in_array( strtolower( $m[1] ), array( 'http', 'https', 'mailto', 'tel' ), true ) ) {
            return '';
        }
        return str_replace( array( '&', "'" ), array( '&#038;', '&#039;' ), $url );
    }
    function wp_strip_all_tags( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ); }
    function wp_kses( $html, $allowed ) {
        return preg_replace_callback( '#<(/?)([a-z0-9]+)[^>]*>#i', function ( $m ) use ( $allowed ) {
            return isset( $allowed[ strtolower( $m[2] ) ] ) ? '<' . $m[1] . strtolower( $m[2] ) . '>' : '';
        }, (string) $html );
    }
    function apply_filters( $hook, $value ) { return $value; }
    function home_url( $path = '' ) { return rtrim( $GLOBALS['__home'], '/' ) . '/' . ltrim( (string) $path, '/' ); }
    function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
    function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, $d, ',', '.' ); }
    function wp_date( $fmt, $ts = null ) { return gmdate( $fmt, null === $ts ? 1795000000 : $ts ); }
    function get_option( $k, $d = false ) { return array( 'date_format' => 'd/m/Y', 'time_format' => 'H:i' )[ $k ] ?? $d; }
    function determine_locale() { return $GLOBALS['__locale']; }
    function is_rtl() { return $GLOBALS['__rtl']; }
    function get_privacy_policy_url() { return ''; }
    function get_user_meta( $id, $key = '', $single = false ) { return $GLOBALS['__users'][ $id ]['meta'][ $key ] ?? ''; }
    function get_userdata( $id ) { return $GLOBALS['__users'][ $id ]['obj'] ?? false; }
    function sanitize_file_name( $n ) { return preg_replace( '/[^a-zA-Z0-9._-]/', '', (string) $n ); }
    function sanitize_email( $e ) { return preg_replace( '/[^a-zA-Z0-9@._+-]/', '', (string) $e ); }
    function is_email( $e ) { return (bool) preg_match( '/^[^@\s]+@[^@\s]+\.[^@\s]+$/', (string) $e ); }
    function remove_action( $hook, $cb, $prio = 10 ) { return true; }
    function wp_mail( $to, $subject, $body, $headers = '' ) {
        $GLOBALS['__mails'][] = compact( 'to', 'subject', 'body', 'headers' );
        return true;
    }
    function motorlan_account_url( $sub = '', $user_id = null ) { return '/mi-cuenta/' . ltrim( $sub, '/' ); }
    function motorlan_localized_page_url( $slug, $sub = '', $user_id = null ) { return '/' . $slug . '/' . ltrim( $sub, '/' ); }
}

// error_log() va a un buffer: los tests miran qué se registra sin ensuciar la salida.
ini_set( 'error_log', sys_get_temp_dir() . '/motorlan-email-tests.log' );

require_once MOTORLAN_API_VUE_PATH . 'includes/classes/class-motorlan-email.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/classes/class-motorlan-notification-manager.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/admin/admin-email-debug.php';

/** Usuario de prueba (como WP_User). */
function t_user( $id, $first = '', $last = '', $display = '', $login = '', $email = '', $company = '' ) {
    $u = (object) array(
        'ID'           => $id,
        'first_name'   => $first,
        'last_name'    => $last,
        'display_name' => $display,
        'user_login'   => $login,
        'user_email'   => $email,
    );
    $GLOBALS['__users'][ $id ] = array( 'obj' => $u, 'meta' => array( 'company_nombre' => $company ) );
    return $u;
}

/** Renderiza una plantilla con el manager real. */
function t_render( $template, $data = array(), $user = null, $title = 'Asunto de prueba', $message = '' ) {
    $manager = new Motorlan_Notification_Manager();
    return $manager->get_email_template( $template, array(
        'title'   => $title,
        'message' => $message,
        'data'    => $data,
        'user'    => $user,
    ) );
}

/** Plantillas con su tipo de uso (todas las de includes/email-templates salvo base). */
function t_templates() {
    $out = array();
    foreach ( glob( MOTORLAN_API_VUE_PATH . 'includes/email-templates/*.php' ) as $f ) {
        $n = basename( $f, '.php' );
        if ( 'base' !== $n ) {
            $out[] = $n;
        }
    }
    sort( $out );
    return $out;
}

/**
 * Datos de ejemplo por plantilla (los mismos de la página de depuración del admin).
 *
 * @return array{0:array,1:string,2:string} data, title, message
 */
function t_sample( $template ) {
    return motorlan_email_debug_sample( $template );
}
