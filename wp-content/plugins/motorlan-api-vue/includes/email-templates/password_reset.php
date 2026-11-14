<?php
/**
 * Email — enlace para restablecer la contraseña.
 *
 * @var array $args title, message, data (url), user.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$data = Motorlan_Email::data( $args );
$url  = Motorlan_Email::link( $data, $args, 'forgot-password' );

echo Motorlan_Email::h1( __( 'Restablecer contraseña', 'motorlan-api-vue' ) );
echo Motorlan_Email::greeting( isset( $args['user'] ) ? $args['user'] : null );
echo Motorlan_Email::p( esc_html__( 'Has solicitado restablecer tu contraseña para tu cuenta en Motorlan.', 'motorlan-api-vue' ) );
echo Motorlan_Email::p( esc_html__( 'Para crear una nueva contraseña, haz clic en el siguiente botón:', 'motorlan-api-vue' ) );
echo Motorlan_Email::button( __( 'Restablecer contraseña', 'motorlan-api-vue' ), $url );
echo Motorlan_Email::p( esc_html__( 'Este enlace expirará en 24 horas.', 'motorlan-api-vue' ) );
echo Motorlan_Email::link_fallback( $url, __( 'Si el botón no funciona, copia y pega el siguiente enlace en tu navegador:', 'motorlan-api-vue' ) );
echo Motorlan_Email::notice( esc_html__( 'Si no solicitaste este cambio, puedes ignorar este correo de forma segura. Tu contraseña no cambiará.', 'motorlan-api-vue' ), 'info' );
