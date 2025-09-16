<?php
/**
 * Email template for a new question notification.
 *
 * @var array $args {
 *     @type string $title
 *     @type string $message
 *     @type array  $data
 *     @type WP_User $user
 * }
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$publication_url = $args['data']['url'] ?? '';
$question_text = $args['message'] ?? '';

?>
<h2><?php echo esc_html( $args['title'] ); ?></h2>
<p>Has recibido una nueva pregunta en una de tus publicaciones.</p>
<blockquote style="border-left: 4px solid #ddd; padding-left: 15px; margin-left: 0;">
    <p><?php echo nl2br( esc_html( $question_text ) ); ?></p>
</blockquote>
<p>Puedes ver la publicación y responder la pregunta aquí:</p>
<p><a href="<?php echo esc_url( $publication_url ); ?>" style="display: inline-block; padding: 10px 20px; background-color: #0073aa; color: #ffffff; text-decoration: none;">Ver Publicación</a></p>