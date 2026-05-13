<?php
/**
 * Email template — copia al remitente tras enviar un mensaje en chat.
 *
 * @var array $args
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$message_text  = $args['message'] ?? '';
$recipient     = $args['data']['recipient_name'] ?? 'el destinatario';
$product_title = $args['data']['product_title'] ?? 'una publicación';
$thread_url    = ! empty( $args['data']['url'] ) ? home_url( $args['data']['url'] ) : home_url( '/dashboard' );
?>
<h2 class="h1" style="margin:0 0 16px;font-size:24px;line-height:30px;font-weight:700;color:#111827;">Mensaje enviado</h2>
<p style="margin:0 0 16px;">Tu mensaje a <strong><?php echo esc_html( $recipient ); ?></strong> sobre <strong><?php echo esc_html( $product_title ); ?></strong> se ha enviado correctamente.</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">
    <tr>
        <td style="border-left:4px solid #0073aa;background:#f3f6f9;padding:14px 18px;border-radius:6px;font-style:italic;color:#374151;">
            <?php echo nl2br( esc_html( $message_text ) ); ?>
        </td>
    </tr>
</table>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 0;">
    <tr>
        <td align="center" style="border-radius:8px;background:#0073aa;">
            <a class="btn" href="<?php echo esc_url( $thread_url ); ?>" style="display:inline-block;padding:13px 28px;font-weight:600;font-size:15px;color:#ffffff;text-decoration:none;border-radius:8px;">Ver conversación</a>
        </td>
    </tr>
</table>
