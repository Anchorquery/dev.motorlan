<?php
/**
 * Email — el vendedor recibe una oferta.
 *
 * @var array $args title, message, data (offer_amount, product_*, url), user.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$data          = Motorlan_Email::data( $args );
$product_title = Motorlan_Email::text( $data, 'product_title', __( 'Producto', 'motorlan-api-vue' ) );
$offer_amount  = Motorlan_Email::price( isset( $data['offer_amount'] ) ? $data['offer_amount'] : '' );
$orig_price    = Motorlan_Email::price( isset( $data['product_price'] ) ? $data['product_price'] : '', false );
$message       = trim( (string) ( isset( $args['message'] ) ? $args['message'] : '' ) );
$url           = Motorlan_Email::link( $data, $args, 'dashboard/publications/offers-received' );

echo Motorlan_Email::h1( __( '¡Nueva Oferta Recibida!', 'motorlan-api-vue' ) );
echo Motorlan_Email::greeting( isset( $args['user'] ) ? $args['user'] : null );
if ( '' !== $message ) {
    echo Motorlan_Email::p( esc_html( $message ) );
}

// La tarjeta sale siempre (antes sin imagen se perdía también el importe ofertado).
$lines = array();
if ( '' !== $offer_amount ) {
    $lines[] = esc_html__( 'Oferta de:', 'motorlan-api-vue' ) . ' ' . Motorlan_Email::amount( $offer_amount );
}
if ( '' !== $orig_price ) {
    /* translators: %s: precio original (número) */
    $lines[] = esc_html( sprintf( __( 'Precio original: %s€', 'motorlan-api-vue' ), $orig_price ) );
}
echo Motorlan_Email::product_card( $product_title, Motorlan_Email::text( $data, 'product_image' ), $lines );

echo Motorlan_Email::p( esc_html__( 'Comprueba los detalles de la oferta y decide si aceptarla o rechazarla.', 'motorlan-api-vue' ) );
echo Motorlan_Email::button( __( 'Ver Oferta', 'motorlan-api-vue' ), $url );
echo Motorlan_Email::link_fallback( $url, __( 'Si no puedes hacer clic en el botón, copia y pega este enlace:', 'motorlan-api-vue' ) );
