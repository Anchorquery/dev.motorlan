<?php
/**
 * Mínimo entorno para tests aislados: stubs de las funciones de WordPress que usan
 * los archivos probados. No carga WordPress ni toca la base de datos.
 *
 * Ejecuta todos: for f in tests/*-test.php; do php "$f" || exit 1; done
 */

// Solo por línea de comandos: la carpeta se despliega con el plugin.
if ( PHP_SAPI !== 'cli' ) {
    http_response_code( 404 );
    exit;
}

error_reporting( E_ALL );
set_error_handler( function ( $severity, $message, $file, $line ) {
    // Cualquier aviso de PHP (p. ej. índice inexistente) hace fallar el test.
    throw new ErrorException( $message, 0, $severity, $file, $line );
} );

define( 'ABSPATH', __DIR__ . '/' );
define( 'WPINC', 'wp-includes' );

$GLOBALS['__filters']      = [];
$GLOBALS['__current_user'] = 0;
$GLOBALS['__rest']         = true;
$GLOBALS['__failures']     = 0;
$GLOBALS['__passes']       = 0;

class WP_Error {
    public $code;
    public $message;
    public $data;
    public function __construct( $code = '', $message = '', $data = '' ) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}

function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['__filters'][ $hook ][] = $cb; return true; }
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { return add_filter( $hook, $cb, $prio, $args ); }
function has_filter_registered( $hook, $cb ) { return in_array( $cb, $GLOBALS['__filters'][ $hook ] ?? [], true ); }
function wp_is_serving_rest_request() { return $GLOBALS['__rest']; }
function wp_set_current_user( $id ) { $GLOBALS['__current_user'] = (int) $id; }
function get_current_user_id() { return $GLOBALS['__current_user']; }
function __( $text, $domain = '' ) { return $text; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_text_field( $v ) { return is_string( $v ) ? trim( strip_tags( $v ) ) : ''; }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }

function t_assert( $cond, $label ) {
    if ( $cond ) {
        $GLOBALS['__passes']++;
        echo "  ok   $label\n";
    } else {
        $GLOBALS['__failures']++;
        echo "  FAIL $label\n";
    }
}

function t_done() {
    $f = $GLOBALS['__failures'];
    echo sprintf( "%s: %d ok, %d fallos\n", basename( $_SERVER['argv'][0] ?? 'test' ), $GLOBALS['__passes'], $f );
    exit( $f ? 1 : 0 );
}
