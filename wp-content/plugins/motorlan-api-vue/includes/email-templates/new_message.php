<?php
/**
 * Email template for a new message notification.
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

$inquiry_url = $args['data']['url'] ?? '';
$message_text = $args['message'] ?? '';
$sender_name = $args['data']['sender_name'] ?? 'Un usuario';
$product_title = $args['data']['product_title'] ?? 'una publicación';

?>
<h2><?php echo esc_html( $args['title'] ); ?></h2>
<p>Hola,</p>
<p>Has recibido un nuevo mensaje de <strong><?php echo esc_html( $sender_name ); ?></strong> sobre tu publicación <strong><?php echo esc_html( $product_title ); ?></strong>.</p>
<blockquote style="border-left: 4px solid #ddd; padding-left: 15px; margin-left: 0; background: #f9f9f9; padding: 10px;">
    <p style="margin: 0; font-style: italic;"><?php echo nl2br( esc_html( $message_text ) ); ?></p>
</blockquote>
<p>Puedes ver la conversación y responder desde tu panel de control:</p>
<p>
    <a href="<?php echo esc_url( $inquiry_url ); ?>" style="display: inline-block; padding: 10px 20px; background-color: #0073aa; color: #ffffff; text-decoration: none; border-radius: 4px;">
        Ver Mensaje
    </a>
</p>
