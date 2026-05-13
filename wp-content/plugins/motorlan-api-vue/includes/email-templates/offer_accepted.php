<?php
/**
 * Email template for an accepted offer notification.
 *
 * @var array $args
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$publication_id    = $args['data']['publication_id'] ?? 0;
$publication_title = get_the_title( $publication_id );
$confirm_url       = ! empty( $args['data']['url'] ) ? home_url( $args['data']['url'] ) : home_url( '/dashboard/purchases/offers-sent' );
?>
<h2 class="h1" style="margin:0 0 16px;font-size:24px;line-height:30px;font-weight:700;color:#111827;"><?php echo esc_html( $args['title'] ); ?></h2>
<p style="margin:0 0 16px;">¡Buenas noticias! El vendedor ha aceptado tu oferta por <strong>"<?php echo esc_html( $publication_title ); ?>"</strong>.</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">
    <tr>
        <td style="background:#fff7ed;border-left:4px solid #f59e0b;padding:14px 18px;border-radius:6px;color:#7c2d12;">
            <strong>IMPORTANTE:</strong> Tienes <strong>24 horas</strong> para confirmar el pedido. Si no lo haces, la oferta expirará y la publicación volverá a estar disponible.
        </td>
    </tr>
</table>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 0;">
    <tr>
        <td align="center" style="border-radius:8px;background:#16a34a;">
            <a class="btn" href="<?php echo esc_url( $confirm_url ); ?>" style="display:inline-block;padding:13px 28px;font-weight:600;font-size:15px;color:#ffffff;text-decoration:none;border-radius:8px;">Confirmar compra ahora</a>
        </td>
    </tr>
</table>
