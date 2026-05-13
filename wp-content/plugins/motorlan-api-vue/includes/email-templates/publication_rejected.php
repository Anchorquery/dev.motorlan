<?php
/**
 * Email template for a publication rejected notification.
 *
 * @var array $args
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$post_id           = $args['data']['post_id'] ?? 0;
$publication_title = get_the_title( $post_id );
$reason            = $args['data']['reason'] ?? 'No cumple con las normas de la comunidad.';
$list_url          = home_url( '/dashboard/publications/list' );
?>
<h2 class="h1" style="margin:0 0 16px;font-size:24px;line-height:30px;font-weight:700;color:#111827;"><?php echo esc_html( $args['title'] ); ?></h2>
<p style="margin:0 0 16px;">Tu publicación <strong>"<?php echo esc_html( $publication_title ); ?>"</strong> requiere cambios para ser aprobada.</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">
    <tr>
        <td style="background:#fef2f2;border-left:4px solid #dc2626;padding:14px 18px;border-radius:6px;color:#7f1d1d;">
            <strong>Motivo del rechazo:</strong><br>
            <?php echo nl2br( esc_html( $reason ) ); ?>
        </td>
    </tr>
</table>
<p style="margin:0 0 16px;">Edita tu publicación y vuelve a enviarla a revisión:</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 0;">
    <tr>
        <td align="center" style="border-radius:8px;background:#0073aa;">
            <a class="btn" href="<?php echo esc_url( $list_url ); ?>" style="display:inline-block;padding:13px 28px;font-weight:600;font-size:15px;color:#ffffff;text-decoration:none;border-radius:8px;">Gestionar publicaciones</a>
        </td>
    </tr>
</table>
