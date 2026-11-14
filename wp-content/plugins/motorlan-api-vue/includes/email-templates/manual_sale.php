<?php
/**
 * Email — el vendedor ha registrado una venta manual al comprador.
 *
 * Antes este aviso reutilizaba «offer_accepted» («tienes 24 horas para completar la
 * compra… la oferta expirará»), que no corresponde: la compra ya está registrada. El
 * listener la selecciona con data['email_template'] = 'manual_sale'.
 *
 * @var array $args title, message, data (product_*, url), user.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$data          = Motorlan_Email::data( $args );
$product_title = Motorlan_Email::text( $data, 'product_title' );
$price         = Motorlan_Email::price( isset( $data['product_price'] ) ? $data['product_price'] : '' );
$message       = trim( (string) ( isset( $args['message'] ) ? $args['message'] : '' ) );
$url           = Motorlan_Email::link( $data, $args, 'dashboard/purchases/purchases' );

echo Motorlan_Email::h1( __( '¡Compra registrada!', 'motorlan-api-vue' ) );
echo Motorlan_Email::greeting( isset( $args['user'] ) ? $args['user'] : null );
if ( '' !== $message ) {
    echo Motorlan_Email::p( esc_html( $message ) );
}

echo Motorlan_Email::product_card(
    $product_title,
    Motorlan_Email::text( $data, 'product_image' ),
    array( '' !== $price ? Motorlan_Email::amount( $price ) : '' ),
    Motorlan_Email::chip( __( 'Compra registrada', 'motorlan-api-vue' ), 'success' )
);

echo Motorlan_Email::p( esc_html__( 'Coordina el pago y la entrega directamente con el vendedor.', 'motorlan-api-vue' ) );
echo Motorlan_Email::button( __( 'Ver mi compra', 'motorlan-api-vue' ), $url );
