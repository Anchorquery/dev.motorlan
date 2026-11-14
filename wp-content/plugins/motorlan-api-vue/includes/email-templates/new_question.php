<?php
/**
 * Email — nueva pregunta sobre una publicación (para el vendedor).
 *
 * Nota: ningún evento dispara hoy esta plantilla (el módulo de preguntas se sustituyó por
 * el chat). Se mantiene y arregla por si se reutiliza.
 *
 * @var array $args title, message, data (asker_name, question_text, product_*, url), user.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$data          = Motorlan_Email::data( $args );
$product_title = Motorlan_Email::text( $data, 'product_title' );
$asker_name    = Motorlan_Email::text( $data, 'asker_name', __( 'Usuario', 'motorlan-api-vue' ) );
$question_text = Motorlan_Email::text( $data, 'question_text' );
$url           = Motorlan_Email::link( $data, $args, 'dashboard/inquiries' );

echo Motorlan_Email::h1( __( 'Nueva Pregunta', 'motorlan-api-vue' ) );
echo Motorlan_Email::greeting( isset( $args['user'] ) ? $args['user'] : null );
echo Motorlan_Email::p( sprintf(
    wp_kses( __( 'El usuario <strong>%s</strong> tiene una pregunta sobre tu publicación:', 'motorlan-api-vue' ), array( 'strong' => array() ) ),
    esc_html( $asker_name )
) );
if ( '' !== $question_text ) {
    echo Motorlan_Email::quote( $question_text );
}
if ( '' !== $product_title || '' !== Motorlan_Email::text( $data, 'product_image' ) ) {
    echo Motorlan_Email::product_card(
        $product_title,
        Motorlan_Email::text( $data, 'product_image' ),
        array( esc_html__( 'Tienes una nueva pregunta', 'motorlan-api-vue' ) )
    );
}
echo Motorlan_Email::button( __( 'Responder Pregunta', 'motorlan-api-vue' ), $url );
