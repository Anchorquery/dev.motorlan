<?php
/**
 * Email — código de verificación para cambiar la contraseña.
 *
 * @var array $args title, message, data (code, expires_in_minutes), user.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$data    = Motorlan_Email::data( $args );
$code    = Motorlan_Email::text( $data, 'code' );
$minutes = isset( $data['expires_in_minutes'] ) ? max( 1, (int) $data['expires_in_minutes'] ) : 15;

echo Motorlan_Email::h1( __( 'Código de verificación', 'motorlan-api-vue' ) );
echo Motorlan_Email::greeting( isset( $args['user'] ) ? $args['user'] : null );
echo Motorlan_Email::p( esc_html__( 'Tu código para confirmar el cambio de contraseña es:', 'motorlan-api-vue' ) );
if ( '' !== $code ) {
    echo Motorlan_Email::code_box( $code );
}
echo Motorlan_Email::p( esc_html( sprintf( __( 'Este código expirará en %d minutos.', 'motorlan-api-vue' ), $minutes ) ), true );
echo Motorlan_Email::p( esc_html__( 'Si no solicitaste este cambio, ignora este mensaje.', 'motorlan-api-vue' ), true );
