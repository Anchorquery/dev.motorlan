<?php
/**
 * Email — el vendedor ha respondido la pregunta.
 *
 * Nota: ningún evento dispara hoy esta plantilla (ver new_question.php).
 *
 * @var array $args title, message, data (publication_title, answer, url), user.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$data              = Motorlan_Email::data( $args );
$publication_title = Motorlan_Email::text( $data, 'publication_title', __( 'una publicación', 'motorlan-api-vue' ) );
$answer_text       = Motorlan_Email::text( $data, 'answer', trim( (string) ( isset( $args['message'] ) ? $args['message'] : '' ) ) );
$track_url         = Motorlan_Email::link( $data, $args, 'dashboard/purchases/inquiries' );

echo Motorlan_Email::h1( __( 'Tienes una respuesta', 'motorlan-api-vue' ) );
echo Motorlan_Email::p( sprintf(
    wp_kses( __( 'El vendedor ha respondido tu pregunta sobre <strong>"%s"</strong>.', 'motorlan-api-vue' ), array( 'strong' => array() ) ),
    esc_html( $publication_title )
) );
if ( '' !== $answer_text ) {
    echo Motorlan_Email::quote( $answer_text, 'success' );
}
echo Motorlan_Email::button( __( 'Ver respuesta', 'motorlan-api-vue' ), $track_url );
