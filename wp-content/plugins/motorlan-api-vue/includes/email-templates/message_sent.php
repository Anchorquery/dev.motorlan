<?php
/**
 * Email — copia al remitente (invitado) tras enviar un mensaje en el chat de un producto.
 *
 * El texto del mensaje no se reenvía a propósito: el email del invitado no está verificado
 * y la copia no debe servir para hacer llegar texto propio a terceros con la marca de Motorlan.
 *
 * @var array $args title, message, data (recipient_name, product_title, url), user.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$data          = Motorlan_Email::data( $args );
$message_text  = trim( (string) ( isset( $args['message'] ) ? $args['message'] : '' ) );
$recipient     = Motorlan_Email::text( $data, 'recipient_name', __( 'el vendedor', 'motorlan-api-vue' ) );
$product_title = Motorlan_Email::text( $data, 'product_title', __( 'una publicación', 'motorlan-api-vue' ) );
$thread_url    = Motorlan_Email::link( $data, $args, 'dashboard' );

echo Motorlan_Email::h1( __( 'Mensaje enviado', 'motorlan-api-vue' ) );
echo Motorlan_Email::p( sprintf(
    /* translators: 1: destinatario, 2: publicación */
    wp_kses( __( 'Tu mensaje a %1$s sobre %2$s se ha enviado correctamente.', 'motorlan-api-vue' ), array() ),
    '<strong>' . esc_html( $recipient ) . '</strong>',
    '<strong>' . esc_html( $product_title ) . '</strong>'
) );
if ( '' !== $message_text ) {
    echo Motorlan_Email::quote( $message_text );
}
echo Motorlan_Email::button( __( 'Ver conversación', 'motorlan-api-vue' ), $thread_url );
