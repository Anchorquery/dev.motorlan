<?php
/**
 * Email template for a new question notification.
 *
 * @var array $args
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$publication_url = ! empty( $args['data']['url'] ) ? home_url( $args['data']['url'] ) : home_url( '/dashboard' );
$question_text   = $args['message'] ?? '';
?>
<h2 class="h1" style="margin:0 0 16px;font-size:24px;line-height:30px;font-weight:700;color:#111827;"><?php echo esc_html( $args['title'] ); ?></h2>
<p style="margin:0 0 16px;">Has recibido una nueva pregunta sobre una de tus publicaciones:</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">
    <tr>
        <td style="border-left:4px solid #0073aa;background:#f3f6f9;padding:14px 18px;border-radius:6px;font-style:italic;color:#374151;">
            <?php echo nl2br( esc_html( $question_text ) ); ?>
        </td>
    </tr>
</table>
<p style="margin:0 0 16px;">Responde desde tu panel para no perder la oportunidad:</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 0;">
    <tr>
        <td align="center" style="border-radius:8px;background:#0073aa;">
            <a class="btn" href="<?php echo esc_url( $publication_url ); ?>" style="display:inline-block;padding:13px 28px;font-weight:600;font-size:15px;color:#ffffff;text-decoration:none;border-radius:8px;">Responder pregunta</a>
        </td>
    </tr>
</table>
