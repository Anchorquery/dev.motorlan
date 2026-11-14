<?php
/**
 * Genera el HTML de cada email con datos de ejemplo en docs/migracion/emails-previews/
 * (más un index.html que los lista). No envía nada: solo renderiza con los stubs.
 *
 * Ejecutar: php tests/email-previews.php [directorio_de_salida]
 */

require_once __DIR__ . '/email-stubs.php';

$out = isset( $argv[1] ) ? $argv[1] : dirname( __DIR__ ) . '/docs/migracion/emails-previews';
if ( ! is_dir( $out ) ) {
    mkdir( $out, 0777, true );
}

// Usuario cuyo display_name es un email: el caso que rompía el saludo.
$user  = t_user( 21, '', '', 'vendedor.prueba@motorlan.test', 'vendedor.prueba@motorlan.test', 'vendedor.prueba@motorlan.test' );
$named = t_user( 22, 'Lucía', 'Fernández', 'lucia@example.com', 'lucia', 'lucia@example.com' );

$rows = array();
foreach ( t_templates() as $tpl ) {
    list( $data, $title, $message ) = t_sample( $tpl );
    $variants = array( '' => array( $user, $data ) );
    if ( 'new_message' === $tpl ) {
        $variants['-invitado']      = array( null, array_merge( $data, array( 'chat_link_expires_at' => 1795600000 ) ) );
        $variants['-con-nombre']    = array( $named, $data );
    }
    if ( 'new_purchase' === $tpl ) {
        $variants['-con-nombre'] = array( $named, $data );
        // Campos mínimos: la plantilla sigue siendo coherente
        $variants['-datos-minimos'] = array( $user, array() );
    }
    foreach ( $variants as $suffix => $v ) {
        $html = t_render( $tpl, $v[1], $v[0], $title, $message );
        $file = $tpl . $suffix . '.html';
        file_put_contents( $out . '/' . $file, $html );
        $rows[] = array( $file, $tpl . $suffix, $title );
    }
}

$li = '';
foreach ( $rows as $r ) {
    $li .= '<li><a href="' . htmlspecialchars( $r[0] ) . '">' . htmlspecialchars( $r[1] ) . '</a> — ' . htmlspecialchars( $r[2] ) . "</li>\n";
}
file_put_contents( $out . '/index.html', "<!doctype html><meta charset=\"utf-8\"><title>Previews de emails</title><body style=\"font-family:system-ui;max-width:720px;margin:32px auto\"><h1>Previews de emails</h1><p>HTML generado por <code>tests/email-previews.php</code> con datos de ejemplo.</p><ul>\n" . $li . "</ul>\n" );

echo count( $rows ) . " previews en $out\n";
