<?php
/**
 * Email — copia al remitente tras enviar una pregunta.
 *
 * Nota: ningún evento dispara hoy esta plantilla (ver new_question.php).
 *
 * @var array $args title, message, data (publication_title, url), user.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$data              = Motorlan_Email::data( $args );
$publication_title = Motorlan_Email::text( $data, 'publication_title', __( 'una publicación', 'motorlan-api-vue' ) );
$question_text     = trim( (string) ( isset( $args['message'] ) ? $args['message'] : '' ) );
$track_url         = Motorlan_Email::link( $data, $args, 'dashboard/purchases/inquiries' );

echo Motorlan_Email::h1( __( 'Pregunta enviada', 'motorlan-api-vue' ) );
echo Motorlan_Email::p( sprintf(
    wp_kses( __( 'Tu pregunta sobre <strong>"%s"</strong> se ha enviado correctamente. Recibirás un aviso cuando el vendedor responda.', 'motorlan-api-vue' ), array( 'strong' => array() ) ),
    esc_html( $publication_title )
) );
if ( '' !== $question_text ) {
    echo Motorlan_Email::quote( $question_text );
}
echo Motorlan_Email::button( __( 'Ver mis preguntas', 'motorlan-api-vue' ), $track_url );
