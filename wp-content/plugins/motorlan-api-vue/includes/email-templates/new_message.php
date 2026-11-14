<?php
/**
 * Email — mensaje de chat (consulta de producto o mensaje de compra) para el destinatario,
 * usuario registrado o invitado (data.direct_email, sin $args['user']).
 *
 * @var array $args title, message, data (sender_name, message_full, product_*, url), user.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$data          = Motorlan_Email::data( $args );
$user          = isset( $args['user'] ) ? $args['user'] : null;
$is_guest      = ! is_object( $user ) || empty( $user->ID );
$product_title = Motorlan_Email::text( $data, 'product_title' );
$sender_name   = Motorlan_Email::sender_label( Motorlan_Email::text( $data, 'sender_name' ) );
$message_full  = Motorlan_Email::text( $data, 'message_full' );
$url           = Motorlan_Email::link( $data, $args, 'dashboard/inquiries' );

echo Motorlan_Email::h1( __( 'Nuevo Mensaje', 'motorlan-api-vue' ) );
echo Motorlan_Email::greeting( $is_guest ? null : $user );

if ( $is_guest ) {
    echo Motorlan_Email::p( esc_html__( 'Has recibido una respuesta a tu consulta en Motorlan.', 'motorlan-api-vue' ) );
}

echo Motorlan_Email::p( sprintf(
    wp_kses( __( '<strong>%s</strong> te ha enviado un mensaje:', 'motorlan-api-vue' ), array( 'strong' => array() ) ),
    esc_html( $sender_name )
) );

if ( '' !== $message_full ) {
    echo Motorlan_Email::quote( $message_full );
}

if ( '' !== $product_title || '' !== Motorlan_Email::text( $data, 'product_image' ) ) {
    echo Motorlan_Email::product_card(
        $product_title,
        Motorlan_Email::text( $data, 'product_image' ),
        array( esc_html__( 'Sobre la publicación', 'motorlan-api-vue' ) )
    );
}

echo Motorlan_Email::button( __( 'Ver y Responder', 'motorlan-api-vue' ), $url );

$expires_at = isset( $data['chat_link_expires_at'] ) ? (int) $data['chat_link_expires_at'] : 0;
if ( $is_guest && $expires_at > 0 ) {
    $expires_display = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expires_at );
    echo Motorlan_Email::p( esc_html( sprintf( __( 'Este enlace caduca el %s.', 'motorlan-api-vue' ), $expires_display ) ), true );
}

if ( $is_guest ) {
    $register_path = function_exists( 'motorlan_localized_page_url' ) ? motorlan_localized_page_url( 'register' ) : '/register/';
    echo Motorlan_Email::panel(
        Motorlan_Email::h2( __( '¡Saca el máximo provecho a Motorlan!', 'motorlan-api-vue' ) )
        . Motorlan_Email::p( esc_html__( 'Crea una cuenta gratuita para guardar tus conversaciones, marcar favoritos y recibir avisos cuando alguien te responda.', 'motorlan-api-vue' ) )
        . Motorlan_Email::button( __( 'Crear mi cuenta gratis', 'motorlan-api-vue' ), Motorlan_Email::abs_url( $register_path, 'register' ) )
    );
}
