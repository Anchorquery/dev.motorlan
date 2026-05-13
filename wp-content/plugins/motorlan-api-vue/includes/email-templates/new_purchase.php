<?php
/**
 * Email template for a new purchase notification (seller).
 *
 * @var array $args
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$purchase_url = ! empty( $args['data']['url'] ) ? home_url( $args['data']['url'] ) : home_url( '/dashboard/publications/sales' );
$message_text = $args['message'] ?? '';
?>
<h2 class="h1" style="margin:0 0 16px;font-size:24px;line-height:30px;font-weight:700;color:#111827;"><?php echo esc_html( $args['title'] ); ?></h2>
<p style="margin:0 0 16px;">Hola,</p>
<p style="margin:0 0 16px;"><?php echo esc_html( $message_text ); ?></p>
<p style="margin:0 0 16px;">Revisa los detalles y gestiona la compra desde tu panel:</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 0;">
    <tr>
        <td align="center" style="border-radius:8px;background:#0073aa;">
            <a class="btn" href="<?php echo esc_url( $purchase_url ); ?>" style="display:inline-block;padding:13px 28px;font-weight:600;font-size:15px;color:#ffffff;text-decoration:none;border-radius:8px;">Ver compra</a>
        </td>
    </tr>
</table>
