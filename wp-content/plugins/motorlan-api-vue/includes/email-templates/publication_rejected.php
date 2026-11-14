<?php
/**
 * Email — publicación rechazada (para su autor).
 *
 * @var array $args title, message, data (post_id, product_title, reason, url), user.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$data          = Motorlan_Email::data( $args );
$product_id    = isset( $data['post_id'] ) ? (int) $data['post_id'] : 0;
$product_title = Motorlan_Email::text( $data, 'product_title' );
if ( '' === $product_title && $product_id && function_exists( 'motorlan_format_motor_name' ) ) {
    $product_title = (string) motorlan_format_motor_name( $product_id );
}
$product_image = Motorlan_Email::text( $data, 'product_image' );
if ( '' === $product_image && $product_id && function_exists( 'get_the_post_thumbnail_url' ) ) {
    $product_image = (string) get_the_post_thumbnail_url( $product_id, 'medium' );
}
$reason  = Motorlan_Email::text( $data, 'reason' );
$message = trim( (string) ( isset( $args['message'] ) ? $args['message'] : '' ) );
// El listener añade «Motivo: …» al mensaje: se muestra una sola vez, en su propio aviso.
if ( '' !== $reason && '' !== $message ) {
    $message = trim( str_replace( sprintf( __( 'Motivo: %s', 'motorlan-api-vue' ), $reason ), '', $message ) );
}
$url = Motorlan_Email::link( $data, $args, 'dashboard/publications/publication/list' );

echo Motorlan_Email::h1( __( 'Publicación Rechazada', 'motorlan-api-vue' ) );
echo Motorlan_Email::greeting( isset( $args['user'] ) ? $args['user'] : null );
if ( '' !== $message ) {
    echo Motorlan_Email::p( esc_html( $message ) );
}
if ( '' !== $reason ) {
    echo Motorlan_Email::notice( '<strong>' . esc_html__( 'Motivo:', 'motorlan-api-vue' ) . '</strong> ' . nl2br( esc_html( $reason ) ), 'error' );
}
echo Motorlan_Email::product_card(
    $product_title,
    $product_image,
    array( esc_html__( 'Puedes editarla y volver a enviarla.', 'motorlan-api-vue' ) ),
    Motorlan_Email::chip( __( 'Estado: Rechazada', 'motorlan-api-vue' ), 'error' )
);
echo Motorlan_Email::button( __( 'Editar Publicación', 'motorlan-api-vue' ), $url );
