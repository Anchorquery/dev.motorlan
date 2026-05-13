<?php
/**
 * Email template for a new offer notification.
 *
 * @var array $args
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$publication_id    = $args['data']['publication_id'] ?? 0;
$publication_title = get_the_title( $publication_id );
$offer_amount      = $args['data']['amount'] ?? '';
$offers_url        = ! empty( $args['data']['url'] ) ? home_url( $args['data']['url'] ) : home_url( '/dashboard/publications/offers-received' );
?>
<h2 class="h1" style="margin:0 0 16px;font-size:24px;line-height:30px;font-weight:700;color:#111827;"><?php echo esc_html( $args['title'] ); ?></h2>
<p style="margin:0 0 16px;">Has recibido una nueva oferta para tu publicación <strong>"<?php echo esc_html( $publication_title ); ?>"</strong>.</p>
<?php if ( ! empty( $offer_amount ) ) : ?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">
    <tr>
        <td style="background:#f3f6f9;padding:18px 20px;border-radius:8px;text-align:center;">
            <div style="font-size:13px;color:#6b7280;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">Importe ofertado</div>
            <div style="font-size:28px;font-weight:700;color:#005b96;"><?php echo esc_html( $offer_amount ); ?>€</div>
        </td>
    </tr>
</table>
<?php else : ?>
<p style="margin:0 0 16px;"><?php echo esc_html( $args['message'] ?? '' ); ?></p>
<?php endif; ?>
<p style="margin:0 0 16px;">Revisa los detalles y decide si la aceptas o la rechazas:</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 0;">
    <tr>
        <td align="center" style="border-radius:8px;background:#0073aa;">
            <a class="btn" href="<?php echo esc_url( $offers_url ); ?>" style="display:inline-block;padding:13px 28px;font-weight:600;font-size:15px;color:#ffffff;text-decoration:none;border-radius:8px;">Gestionar ofertas</a>
        </td>
    </tr>
</table>
