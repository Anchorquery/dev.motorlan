<?php
/**
 * Email — el vendedor ha aceptado la oferta del comprador.
 *
 * @var array $args title, message, data (offer_amount, product_*, url), user.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$data          = Motorlan_Email::data( $args );
$product_title = Motorlan_Email::text( $data, 'product_title' );
$amount        = Motorlan_Email::price( isset( $data['offer_amount'] ) ? $data['offer_amount'] : '' );
$url           = Motorlan_Email::link( $data, $args, 'dashboard/purchases/offers-sent' );

echo Motorlan_Email::h1( __( '¡Oferta Aceptada!', 'motorlan-api-vue' ) );
echo Motorlan_Email::greeting( isset( $args['user'] ) ? $args['user'] : null );

if ( '' !== $product_title ) {
    echo Motorlan_Email::p( sprintf(
        wp_kses( __( '¡Buenas noticias! El vendedor ha aceptado tu oferta por <strong>"%s"</strong>.', 'motorlan-api-vue' ), array( 'strong' => array() ) ),
        esc_html( $product_title )
    ) );
} else {
    echo Motorlan_Email::p( esc_html__( 'El vendedor ha aceptado tu oferta. Tienes 24 horas para confirmar la compra.', 'motorlan-api-vue' ) );
}

$lines = array();
if ( '' !== $amount ) {
    $lines[] = esc_html__( 'Oferta aceptada:', 'motorlan-api-vue' ) . ' ' . Motorlan_Email::amount( $amount );
}
$lines[] = esc_html__( 'Tienes 24 horas para completar la compra.', 'motorlan-api-vue' );
echo Motorlan_Email::product_card( $product_title, Motorlan_Email::text( $data, 'product_image' ), $lines, Motorlan_Email::chip( __( 'Oferta aceptada', 'motorlan-api-vue' ), 'success' ) );

echo Motorlan_Email::notice(
    '<strong>' . esc_html__( 'IMPORTANTE:', 'motorlan-api-vue' ) . '</strong> ' . esc_html__( 'Si no confirmas el pedido dentro del plazo, la oferta expirará y el producto volverá a estar disponible para otros usuarios.', 'motorlan-api-vue' ),
    'warning'
);
echo Motorlan_Email::button( __( 'Confirmar Compra Ahora', 'motorlan-api-vue' ), $url );
