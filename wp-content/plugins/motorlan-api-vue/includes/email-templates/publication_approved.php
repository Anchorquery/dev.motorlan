<?php
/**
 * Email template for a publication approved notification.
 *
 * @var array $args
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$post_id           = $args['data']['post_id'] ?? 0;
$publication_title = get_the_title( $post_id );
$list_url          = home_url( '/dashboard/publications/list' );
?>
<h2 class="h1" style="margin:0 0 16px;font-size:24px;line-height:30px;font-weight:700;color:#111827;"><?php echo esc_html( $args['title'] ); ?></h2>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px;">
    <tr>
        <td style="background:#ecfdf5;border-left:4px solid #16a34a;padding:14px 18px;border-radius:6px;color:#065f46;">
            ✅ Tu publicación <strong>"<?php echo esc_html( $publication_title ); ?>"</strong> ha sido aprobada y ya es pública en la tienda.
        </td>
    </tr>
</table>
<p style="margin:0 0 16px;">Puedes ver y gestionar tus publicaciones desde el panel:</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 0;">
    <tr>
        <td align="center" style="border-radius:8px;background:#0073aa;">
            <a class="btn" href="<?php echo esc_url( $list_url ); ?>" style="display:inline-block;padding:13px 28px;font-weight:600;font-size:15px;color:#ffffff;text-decoration:none;border-radius:8px;">Ver mis publicaciones</a>
        </td>
    </tr>
</table>
