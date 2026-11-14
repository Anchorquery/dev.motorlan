<?php
/**
 * Email — el vendedor recibe una compra de su artículo.
 *
 * @var array $args title, message, data, user.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$data          = Motorlan_Email::data( $args );
$product_title = Motorlan_Email::text( $data, 'product_title' );
$buyer_name    = Motorlan_Email::text( $data, 'buyer_name', __( 'Un usuario', 'motorlan-api-vue' ) );
$price         = Motorlan_Email::price( isset( $data['product_price'] ) ? $data['product_price'] : '' );
$url           = Motorlan_Email::link( $data, $args, 'dashboard/purchases/purchases' );

echo Motorlan_Email::h1( __( '¡Felicidades, has realizado una venta!', 'motorlan-api-vue' ) );
echo Motorlan_Email::greeting( isset( $args['user'] ) ? $args['user'] : null );
echo Motorlan_Email::p( sprintf(
    wp_kses( __( 'El usuario <strong>%s</strong> ha completado el proceso de compra para tu artículo.', 'motorlan-api-vue' ), array( 'strong' => array() ) ),
    esc_html( $buyer_name )
) );

echo Motorlan_Email::product_card(
    $product_title,
    Motorlan_Email::text( $data, 'product_image' ),
    array( '' !== $price ? Motorlan_Email::amount( $price ) : '' ),
    Motorlan_Email::chip( __( 'Estado: Vendido', 'motorlan-api-vue' ), 'success' )
);

echo Motorlan_Email::p( esc_html__( 'Por favor, ponte en contacto con el comprador para gestionar el envío o la entrega.', 'motorlan-api-vue' ) );
echo Motorlan_Email::button( __( 'Ver Detalles de la Venta', 'motorlan-api-vue' ), $url );
