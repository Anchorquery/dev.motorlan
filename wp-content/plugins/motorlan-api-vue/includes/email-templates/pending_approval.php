<?php
/**
 * Email template for a pending approval notification (admin).
 *
 * @var array $args
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$post_id           = $args['data']['post_id'] ?? 0;
$publication_title = get_the_title( $post_id );
$author_id         = $args['data']['author_id'] ?? 0;
$author            = get_userdata( $author_id );
$author_name       = $author ? $author->display_name : 'Un usuario';
$approvals_url     = home_url( '/dashboard/admin/approvals' );
?>
<h2 class="h1" style="margin:0 0 16px;font-size:24px;line-height:30px;font-weight:700;color:#111827;"><?php echo esc_html( $args['title'] ); ?></h2>
<p style="margin:0 0 16px;"><strong><?php echo esc_html( $author_name ); ?></strong> ha creado una publicación titulada <strong>"<?php echo esc_html( $publication_title ); ?>"</strong> que requiere revisión.</p>
<p style="margin:0 0 16px;">Revísala y apruébala desde el panel de administración:</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 0;">
    <tr>
        <td align="center" style="border-radius:8px;background:#0073aa;">
            <a class="btn" href="<?php echo esc_url( $approvals_url ); ?>" style="display:inline-block;padding:13px 28px;font-weight:600;font-size:15px;color:#ffffff;text-decoration:none;border-radius:8px;">Revisar publicaciones</a>
        </td>
    </tr>
</table>
