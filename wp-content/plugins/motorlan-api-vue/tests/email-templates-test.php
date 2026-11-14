<?php
/**
 * Tests de las plantillas de email: renderiza cada una con datos mínimos, completos y
 * hostiles, y comprueba que no hay HTML inyectado, avisos de PHP (el bootstrap convierte
 * cualquier aviso en excepción), nombres que sean un email ni enlaces rotos, además de la
 * estructura pensada para clientes de correo y el contraste (WCAG AA) de la paleta.
 *
 * Ejecutar: php tests/email-templates-test.php
 */

require_once __DIR__ . '/email-stubs.php';

$EMAIL = 'vendedor.prueba@motorlan.test';

// ---------------------------------------------------------------- utilidades
function t_luminance( $hex ) {
    $hex = ltrim( $hex, '#' );
    $c   = array( hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
    foreach ( $c as &$v ) {
        $v /= 255;
        $v  = $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
    }
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}
function t_contrast( $a, $b ) {
    $la = t_luminance( $a );
    $lb = t_luminance( $b );
    return ( max( $la, $lb ) + 0.05 ) / ( min( $la, $lb ) + 0.05 );
}
/** Renderiza capturando excepciones (cualquier aviso de PHP). */
function t_try_render( $tpl, $data, $user, $title = 'Asunto', $message = '' ) {
    try {
        return array( t_render( $tpl, $data, $user, $title, $message ), null );
    } catch ( Throwable $e ) {
        return array( '', $e->getMessage() . ' @' . basename( $e->getFile() ) . ':' . $e->getLine() );
    }
}
function t_button_hrefs( $html ) {
    preg_match_all( '#class="ml-btn-a" href="([^"]*)"#', $html, $m );
    return $m[1];
}

$templates = t_templates();
echo "Plantillas: " . implode( ', ', $templates ) . "\n";
t_assert( count( $templates ) === 16, 'hay 16 plantillas (15 originales + manual_sale)' );

// ---------------------------------------------------------------- paleta / contraste
echo "\n== Contraste WCAG AA (4,5:1) ==\n";
$pal   = Motorlan_Email::palette();
$pairs = array();
foreach ( array( 'light', 'dark' ) as $mode ) {
    $p = $pal[ $mode ];
    foreach ( array( 'page', 'card', 'soft' ) as $bg ) {
        foreach ( array( 'text', 'muted', 'link' ) as $fg ) {
            $pairs[] = array( "$mode $fg/$bg", $p[ $fg ], $p[ $bg ] );
        }
    }
    foreach ( array( 'red', 'green', 'amber', 'info' ) as $tone ) {
        $pairs[] = array( "$mode estado $tone", $p[ $tone ], $p[ $tone . '_bg' ] );
        // la barra/borde de acento (UI) sobre la tarjeta: 3:1 es suficiente para componentes
    }
}
$pairs[] = array( 'botón blanco/primary', '#FFFFFF', Motorlan_Email::PRIMARY );
foreach ( $pairs as $pair ) {
    t_assert( t_contrast( $pair[1], $pair[2] ) >= 4.5, sprintf( '%s %.2f:1', $pair[0], t_contrast( $pair[1], $pair[2] ) ) );
}
t_assert( t_contrast( Motorlan_Email::PRIMARY, $pal['dark']['card'] ) >= 3, 'botón primary sobre la tarjeta oscura >= 3:1 (componente UI)' );

// ---------------------------------------------------------------- nombres
echo "\n== Nombres ==\n";
$u_email = t_user( 11, '', '', $EMAIL, $EMAIL, $EMAIL );
$u_full  = t_user( 12, 'Ana', 'García', 'ana.garcia@x.es', 'ana', 'ana.garcia@x.es' );
$u_first = t_user( 13, 'Luis', '', 'luis@x.es', 'luis', 'luis@x.es' );
$u_co    = t_user( 14, '', '', 'co@x.es', 'co', 'co@x.es', 'Talleres Norte S.L.' );
$u_disp  = t_user( 15, '', '', 'Prueba Portal', 'pportal', 'pp@x.es' );
$u_login = t_user( 16, '', '', 'usuario1', 'usuario1', 'u1@x.es' );
$u_html  = t_user( 17, '<b>Eva</b>', '<script>alert(1)</script>', 'e@x.es', 'eva', 'e@x.es' );
t_assert( Motorlan_Email::user_name( $u_email ) === '', 'display_name = email → sin nombre' );
t_assert( Motorlan_Email::user_name( $u_full ) === 'Ana García', 'nombre y apellidos' );
t_assert( Motorlan_Email::user_name( $u_first ) === 'Luis', 'solo nombre' );
t_assert( Motorlan_Email::user_name( $u_co ) === 'Talleres Norte S.L.', 'empresa si no hay nombre' );
t_assert( Motorlan_Email::user_name( $u_disp ) === 'Prueba Portal', 'display_name que no es email ni login' );
t_assert( Motorlan_Email::user_name( $u_login ) === '', 'display_name = login → sin nombre' );
t_assert( false === strpos( Motorlan_Email::user_name( $u_html ), '<' ), 'nombre sin etiquetas' );
t_assert( Motorlan_Email::user_name( null ) === '', 'null → sin nombre' );
t_assert( Motorlan_Email::party_name( $u_email, 'Un usuario' ) === 'Un usuario', 'tercero sin nombre → etiqueta genérica' );
t_assert( strpos( Motorlan_Email::greeting( $u_email ), 'Hola,' ) !== false && strpos( Motorlan_Email::greeting( $u_email ), '@' ) === false, 'saludo neutro sin email' );
t_assert( strpos( Motorlan_Email::greeting( $u_full ), 'Hola Ana García,' ) !== false, 'saludo con nombre' );

t_assert( Motorlan_Email::sender_label( $EMAIL ) === 'Un usuario', 'remitente con email como nombre → etiqueta genérica' );
$mail_sender = t_render( 'new_message', array_merge( t_sample( 'new_message' )[0], array( 'sender_name' => $EMAIL ) ), $u_full, 'x', '' );
t_assert( false === strpos( $mail_sender, $EMAIL ) && strpos( $mail_sender, 'Un usuario' ) !== false, 'new_message: el email del remitente no se muestra a la otra parte' );

// ---------------------------------------------------------------- URLs
echo "\n== URLs ==\n";
$GLOBALS['__home'] = 'https://dev.motorlan.test/en/'; // WPML con prefijo de idioma en home_url()
t_assert( Motorlan_Email::abs_url( '/en/mi-cuenta/dashboard/' ) === 'https://dev.motorlan.test/en/mi-cuenta/dashboard/', 'ruta con prefijo de idioma no se duplica' );
t_assert( Motorlan_Email::abs_url( 'https://otro.test/x?a=1&b=2' ) === 'https://otro.test/x?a=1&b=2', 'URL absoluta intacta' );
t_assert( strpos( Motorlan_Email::abs_url( '' ), 'https://' ) === 0, 'URL vacía → portada, nunca vacío' );
$GLOBALS['__home'] = 'https://dev.motorlan.test/';
t_assert( Motorlan_Email::abs_url( '/mi-cuenta/x' ) === 'https://dev.motorlan.test/mi-cuenta/x', 'ruta relativa → absoluta' );
t_assert( Motorlan_Email::button( 'Ir', 'javascript:alert(1)' ) === '', 'botón con esquema peligroso no se genera' );

// ---------------------------------------------------------------- asunto / texto plano / precio
echo "\n== Asunto, texto plano, importes ==\n";
$subj = Motorlan_Email::subject( "Hola\r\nBcc: victima@x.com <b>negrita</b>" );
t_assert( false === strpos( $subj, "\n" ) && false === strpos( $subj, "\r" ) && false === strpos( $subj, '<' ), 'asunto sin saltos de línea ni etiquetas' );
t_assert( strpos( Motorlan_Email::subject( '[Motorlan] Algo' ), '[Motorlan] [Motorlan]' ) === false, 'prefijo [Motorlan] una sola vez' );
t_assert( Motorlan_Email::price( '3900.50' ) === "3.900,50\xC2\xA0€", 'importe con decimales' );
t_assert( Motorlan_Email::price( '4250' ) === "4.250\xC2\xA0€", 'importe entero' );
t_assert( Motorlan_Email::price( '' ) === '', 'importe vacío' );

// ---------------------------------------------------------------- render: mínimos, completos, hostiles
$hostile_str = '<script>alert(1)</script>"><img src=x onerror=alert(1)>\'" & <a href="https://evil.test">x</a>';
$users       = array(
    'sin usuario'      => null,
    'nombre vacío'     => $u_email,
    'con nombre'       => $u_full,
    'nombre hostil'    => $u_html,
);

foreach ( $templates as $tpl ) {
    echo "\n== $tpl ==\n";
    list( $sample_data, $sample_title, $sample_msg ) = t_sample( $tpl );
    $has_button = ! in_array( $tpl, array( 'password_reset_code' ), true );

    // Hostil: todos los campos con HTML y la URL con esquema peligroso
    $hostile_data = array();
    foreach ( array_keys( $sample_data ) as $k ) {
        $hostile_data[ $k ] = ( 'url' === $k ) ? 'javascript:alert(1)' : ( ( 'product_image' === $k ) ? 'javascript:alert(2)' : $hostile_str );
    }
    foreach ( array( 'product_title', 'sender_name', 'buyer_name', 'message_full', 'asker_name', 'question_text', 'answer', 'recipient_name', 'publication_title', 'reason', 'brand', 'reference', 'power', 'speed', 'code', 'offer_amount', 'product_price' ) as $k ) {
        $hostile_data[ $k ] = $hostile_str;
    }
    $hostile_data['url'] = 'javascript:alert(1)';

    $cases = array(
        'mínimo (data vacío, usuario sin nombre)' => array( array(), $u_email, 'T', '' ),
        'mínimo sin usuario'                      => array( array(), null, 'T', '' ),
        'completo'                                => array( $sample_data, $u_full, $sample_title, $sample_msg ),
        'completo, usuario con email de nombre'   => array( $sample_data, $u_email, $sample_title, $sample_msg ),
        'hostil'                                  => array( $hostile_data, $u_html, $hostile_str, $hostile_str ),
        'hostil sin usuario'                      => array( $hostile_data, null, $hostile_str, $hostile_str ),
    );

    foreach ( $cases as $label => $c ) {
        list( $html, $err ) = t_try_render( $tpl, $c[0], $c[1], $c[2], $c[3] );
        $name = "$tpl · $label";
        t_assert( null === $err, "$name: sin avisos de PHP" . ( $err ? " [$err]" : '' ) );
        if ( null !== $err ) {
            continue;
        }
        t_assert( '' !== $html && false !== strpos( $html, '<!DOCTYPE html>' ), "$name: HTML completo" );
        t_assert( ! preg_match( '/<script/i', $html ), "$name: sin <script>" );
        t_assert( ! preg_match( '/<img\b[^>]*(onerror|src=["\']?x)/i', $html ), "$name: sin <img> inyectado" );
        t_assert( ! preg_match( '/<a\b[^>]*href=["\']?https?:\/\/evil\.test/i', $html ), "$name: sin enlace inyectado" );
        t_assert( ! preg_match( '/href=["\']?\s*javascript:/i', $html ) && ! preg_match( '/src=["\']?\s*javascript:/i', $html ), "$name: sin javascript: en href/src" );
        t_assert( false === strpos( $html, $GLOBALS['EMAIL'] ?? $EMAIL ) || 'hostil' === $label, "$name: el email del usuario no aparece" );
        t_assert( false === strpos( $html, '{{' ), "$name: sin marcadores sin sustituir" );
        if ( $has_button ) {
            $hrefs = t_button_hrefs( $html );
            t_assert( count( $hrefs ) >= 1 && preg_match( '#^https?://#', html_entity_decode( $hrefs[0] ) ), "$name: botón con enlace absoluto" );
        }
    }

    // Saludo neutro cuando el usuario no tiene nombre real
    list( $html ) = t_try_render( $tpl, $sample_data, $u_email, $sample_title, $sample_msg );
    $greeting_tpls = array( 'new_purchase', 'new_offer', 'offer_accepted', 'manual_sale', 'new_message', 'new_question', 'password_reset', 'password_reset_code', 'publication_approved', 'publication_rejected', 'verify_email', 'welcome_email' );
    if ( in_array( $tpl, $greeting_tpls, true ) ) {
        t_assert( strpos( $html, '>Hola,</p>' ) !== false, "$tpl: saludo «Hola,» con usuario sin nombre" );
    }
    list( $html_named ) = t_try_render( $tpl, $sample_data, $u_full, $sample_title, $sample_msg );
    if ( in_array( $tpl, $greeting_tpls, true ) && 'new_message' !== $tpl ) {
        t_assert( strpos( $html_named, 'Hola Ana García,' ) !== false, "$tpl: saludo con nombre real" );
    }
    if ( in_array( $tpl, $greeting_tpls, true ) && 'new_message' === $tpl ) {
        t_assert( strpos( $html_named, 'Hola Ana García,' ) !== false, "$tpl: saludo con nombre real (usuario registrado)" );
    }
}

// ---------------------------------------------------------------- estructura para clientes de correo
echo "\n== Estructura (base.php) ==\n";
list( $d, $t, $m ) = t_sample( 'new_purchase' );
$html = t_render( 'new_purchase', $d, $u_full, $t, $m );
t_assert( strpos( $html, '<meta name="color-scheme" content="light dark">' ) !== false, 'meta color-scheme' );
t_assert( strpos( $html, '<meta name="supported-color-schemes" content="light dark">' ) !== false, 'meta supported-color-schemes' );
t_assert( strpos( $html, 'prefers-color-scheme: dark' ) !== false, 'media query de modo oscuro' );
t_assert( strpos( $html, '[data-ogsc]' ) !== false, 'reglas Outlook.com ([data-ogsc])' );
t_assert( strpos( $html, 'max-width:600px' ) !== false, 'ancho máximo 600 px' );
t_assert( strpos( $html, 'name="viewport"' ) !== false, 'viewport' );
t_assert( preg_match( '/<html lang="es-ES" dir="ltr"/', $html ) === 1, 'lang/dir del documento' );
t_assert( strpos( $html, 'display:none' ) !== false && strpos( $html, 'mso-hide:all' ) !== false, 'preheader oculto' );
t_assert( strpos( $html, 'alt="Motorlan"' ) !== false, 'logo con texto alternativo' );
t_assert( ! preg_match( '/p,\s*span,\s*div\s*\{/', $html ), 'sin la regla p, span, div { color !important }' );
t_assert( strpos( $html, '#e42313' ) === false && stripos( $html, '#DA291C' ) !== false, 'primary unificado en #DA291C' );
t_assert( strpos( $html, 'dashboard/notifications' ) === false, 'sin enlace a la pantalla inexistente dashboard/notifications' );
t_assert( strpos( $html, 'dashboard/user/account' ) !== false, 'Preferencias → pantalla de cuenta existente' );
t_assert( strpos( $html, 'calc(' ) === false, 'sin calc() (Outlook)' );

// El botón: celda con bgcolor + estilo inline y texto con color inline (no depende del <style>)
t_assert( preg_match( '#<td class="ml-btn-td" align="center" bgcolor="\#DA291C" style="[^"]*background-color:\#DA291C#', $html ) === 1, 'botón: td con bgcolor y background-color inline' );
t_assert( preg_match( '#class="ml-btn-a" href="[^"]+" target="_blank" style="[^"]*padding:14px 28px[^"]*color:\#ffffff[^"]*background-color:\#DA291C#', $html ) === 1, 'botón: enlace con padding, color blanco y fondo inline' );
t_assert( strpos( $html, 'v:roundrect' ) !== false, 'botón: VML para Outlook de escritorio' );
// Estado: chip con su propio fondo y color (no rojo sobre oscuro)
t_assert( preg_match( '#class="ml-st-green" style="[^"]*background-color:\#E6F5EC;color:\#166534#', $html ) === 1, 'estado «Vendido»: chip verde con fondo propio' );
t_assert( strpos( $html, 'color:#DA291C' ) === false || strpos( $html, 'Estado: Vendido' ) !== false, 'sin texto rojo suelto' );

// Invitado: sin enlace a la cuenta en el pie
$html_guest = t_render( 'new_message', t_sample( 'new_message' )[0], null, 'x', '' );
t_assert( strpos( $html_guest, 'dashboard/user/account' ) === false, 'invitado: pie sin «Preferencias»' );
t_assert( strpos( $html_guest, 'Hola Invitado' ) === false, 'invitado: sin «Hola Invitado»' );

// RTL
$GLOBALS['__rtl']    = true;
$GLOBALS['__locale'] = 'ar';
$html_ar = t_render( 'new_purchase', $d, $u_full, $t, $m );
t_assert( strpos( $html_ar, 'dir="rtl"' ) !== false && strpos( $html_ar, 'text-align:right' ) !== false, 'árabe: dir="rtl" y alineación a la derecha' );
$GLOBALS['__rtl']    = false;
$GLOBALS['__locale'] = 'es_ES';

// Texto plano
$alt = Motorlan_Email::html_to_text( $html );
t_assert( false === strpos( $alt, '<' ) && strpos( $alt, 'Ver Detalles de la Venta' ) !== false && strpos( $alt, 'https://' ) !== false, 'texto plano sin etiquetas y con el enlace' );

// ---------------------------------------------------------------- manager: envío
echo "\n== Manager: send_email_notification_direct ==\n";
$GLOBALS['__mails'] = array();
$manager = new Motorlan_Notification_Manager();
$manager->send_email_notification_direct( 0, 'new_message', "Asunto\r\nBcc: x@y.com", '', array_merge( t_sample( 'new_message' )[0], array( 'direct_email' => 'invitado@example.com' ) ) );
t_assert( count( $GLOBALS['__mails'] ) === 1, 'invitado (direct_email válido): se envía' );
$mail = $GLOBALS['__mails'][0];
t_assert( false === strpos( $mail['subject'], "\n" ) && strpos( $mail['subject'], '[Motorlan] ' ) === 0, 'asunto saneado y con prefijo' );
t_assert( in_array( 'Content-Type: text/html; charset=UTF-8', $mail['headers'], true ), 'cabecera HTML UTF-8' );
t_assert( in_array( 'Auto-Submitted: auto-generated', $mail['headers'], true ), 'cabecera Auto-Submitted' );

$GLOBALS['__mails'] = array();
$manager->send_email_notification_direct( 0, 'new_message', 'T', '', array( 'direct_email' => 'no-es-un-email' ) );
// Un valor con saltos de línea se sanea a una sola dirección sin cabeceras añadidas
$manager->send_email_notification_direct( 0, 'new_message', 'T', '', array( 'direct_email' => "a@example.com\r\nBcc:x@y.com" ) );
t_assert( 0 === count( array_filter( $GLOBALS['__mails'], function ( $m ) { return false !== strpos( $m['to'], "\n" ) || false !== strpos( $m['to'], ',' ); } ) ), 'direct_email con saltos de línea: nunca llega a wp_mail con cabeceras añadidas' );
$GLOBALS['__mails'] = array();
$manager->send_email_notification_direct( 0, 'new_message', 'T', '', array() );
t_assert( count( $GLOBALS['__mails'] ) === 0, 'direct_email inválido o ausente: no se envía' );

$GLOBALS['__mails'] = array();
$manager->send_email_notification_direct( 12, 'offer_accepted', '¡Compra registrada!', 'x', array_merge( t_sample( 'manual_sale' )[0], array( 'email_template' => 'manual_sale' ) ) );
t_assert( count( $GLOBALS['__mails'] ) === 1 && strpos( $GLOBALS['__mails'][0]['body'], 'Compra registrada' ) !== false && strpos( $GLOBALS['__mails'][0]['body'], '24 horas' ) === false, 'venta manual: plantilla manual_sale, no «oferta aceptada»' );
t_assert( strpos( $GLOBALS['__mails'][0]['to'], 'ana.garcia@x.es' ) === 0, 'destinatario = usuario' );

$GLOBALS['__mails'] = array();
$ok = Motorlan_Notification_Manager::send_custom_email( 'nuevo@example.com', 'Confirma tu nuevo email', Motorlan_Email::h1( 'X' ) . Motorlan_Email::button( 'Ir', 'https://dev.motorlan.test/a' ), array( 'user' => $u_full ) );
t_assert( true === $ok && count( $GLOBALS['__mails'] ) === 1 && strpos( $GLOBALS['__mails'][0]['body'], 'ml-btn-a' ) !== false, 'send_custom_email: misma plantilla base' );
t_assert( false === Motorlan_Notification_Manager::send_custom_email( 'no-valido', 'X', 'y' ), 'send_custom_email: destinatario inválido → false' );

t_done();
