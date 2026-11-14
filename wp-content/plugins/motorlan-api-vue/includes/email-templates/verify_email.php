<?php
/**
 * Email — verificación de la cuenta.
 *
 * @var array $args title, message, data (url), user.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$data = Motorlan_Email::data( $args );
$url  = Motorlan_Email::link( $data, $args, 'login' );

echo Motorlan_Email::h1( __( 'Verifica tu cuenta', 'motorlan-api-vue' ) );
echo Motorlan_Email::greeting( isset( $args['user'] ) ? $args['user'] : null );
echo Motorlan_Email::p( esc_html__( 'Gracias por registrarte en Motorlan. Para completar tu registro y activar tu cuenta, por favor haz clic en el siguiente botón:', 'motorlan-api-vue' ) );
echo Motorlan_Email::button( __( 'Verificar mi Email', 'motorlan-api-vue' ), $url );
echo Motorlan_Email::link_fallback( $url, __( 'Si el botón no funciona, puedes copiar y pegar el siguiente enlace en tu navegador:', 'motorlan-api-vue' ) );
echo Motorlan_Email::notice( esc_html__( 'Si no has creado una cuenta en Motorlan, puedes ignorar este mensaje.', 'motorlan-api-vue' ), 'info' );
