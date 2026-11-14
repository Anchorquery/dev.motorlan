<?php
/**
 * Email — publicación aprobada (para su autor).
 *
 * @var array $args title, message, data (post_id, product_title, url), user.
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
$url     = Motorlan_Email::link( $data, $args, 'dashboard/publications/publication/list' );

echo Motorlan_Email::h1( __( '¡Publicación Aprobada!', 'motorlan-api-vue' ) );
echo Motorlan_Email::greeting( isset( $args['user'] ) ? $args['user'] : null );
if ( '' !== $message ) {
    echo Motorlan_Email::p( esc_html( $message ) );
}
echo Motorlan_Email::product_card(
    $product_title,
    $product_image,
    array(),
    Motorlan_Email::chip( __( 'Estado: Aprobado', 'motorlan-api-vue' ), 'success' )
);
echo Motorlan_Email::button( __( 'Ver Publicación', 'motorlan-api-vue' ), $url );
