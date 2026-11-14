<?php
/**
 * Email — bienvenida tras verificar la cuenta.
 *
 * @var array $args title, message, data, user.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$user    = isset( $args['user'] ) ? $args['user'] : null;
$user_id = ( is_object( $user ) && ! empty( $user->ID ) ) ? (int) $user->ID : null;
$login   = function_exists( 'motorlan_localized_page_url' ) ? motorlan_localized_page_url( 'login', '', $user_id ) : '/login/';
$url     = Motorlan_Email::abs_url( $login, 'login' );

echo Motorlan_Email::h1( __( '¡Bienvenido/a a Motorlan!', 'motorlan-api-vue' ) );
echo Motorlan_Email::greeting( $user );
echo Motorlan_Email::p( esc_html__( 'Gracias por unirte a la mayor comunidad de compra-venta de recambios industriales y robótica.', 'motorlan-api-vue' ) );
echo Motorlan_Email::p( esc_html__( 'Tu cuenta ha sido creada correctamente. Ahora puedes:', 'motorlan-api-vue' ) );
echo Motorlan_Email::bullets( array(
    __( 'Publicar tus productos y llegar a miles de compradores.', 'motorlan-api-vue' ),
    __( 'Guardar tus búsquedas y productos favoritos.', 'motorlan-api-vue' ),
    __( 'Contactar directamente con vendedores profesionales.', 'motorlan-api-vue' ),
) );
echo Motorlan_Email::button( __( 'Acceder a mi Cuenta', 'motorlan-api-vue' ), $url );
echo Motorlan_Email::p( esc_html__( 'Si tienes alguna pregunta, nuestro equipo de soporte está aquí para ayudarte.', 'motorlan-api-vue' ), true );
