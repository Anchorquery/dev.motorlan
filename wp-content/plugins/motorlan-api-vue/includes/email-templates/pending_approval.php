<?php
/**
 * Email — publicación pendiente de aprobación (para los administradores).
 *
 * @var array $args title, message, data (post_id, product_title, brand, reference, power, speed, url), user.
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
$message = trim( (string) ( isset( $args['message'] ) ? $args['message'] : '' ) );
$url     = Motorlan_Email::link( $data, $args, 'dashboard/admin/approvals' );

echo Motorlan_Email::h1( __( 'Solicitud de Publicación', 'motorlan-api-vue' ) );
echo Motorlan_Email::p( esc_html__( 'Hola Administrador,', 'motorlan-api-vue' ) );
if ( '' !== $message ) {
    echo Motorlan_Email::p( esc_html( $message ) );
}

echo Motorlan_Email::product_card(
    $product_title,
    $product_image,
    array(),
    Motorlan_Email::chip( __( 'Estado: Pendiente de Aprobación', 'motorlan-api-vue' ), 'warning' )
);

echo Motorlan_Email::key_values( array(
    __( 'Marca:', 'motorlan-api-vue' )      => Motorlan_Email::text( $data, 'brand' ),
    __( 'Referencia:', 'motorlan-api-vue' ) => Motorlan_Email::text( $data, 'reference' ),
    __( 'Potencia:', 'motorlan-api-vue' )   => Motorlan_Email::text( $data, 'power' ),
    __( 'Velocidad:', 'motorlan-api-vue' )  => Motorlan_Email::text( $data, 'speed' ),
) );

echo Motorlan_Email::button( __( 'Acceder al Panel de Aprobación', 'motorlan-api-vue' ), $url );
