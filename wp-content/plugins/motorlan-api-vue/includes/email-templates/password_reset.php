<?php
/**
 * Email template for password reset link.
 *
 * @var array $args
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$reset_url = $args['data']['reset_url'] ?? '';
$name      = $args['data']['name'] ?? '';
?>
<h2 class="h1" style="margin:0 0 16px;font-size:24px;line-height:30px;font-weight:700;color:#111827;"><?php echo esc_html( $args['title'] ); ?></h2>
<p style="margin:0 0 16px;">Hola <?php echo esc_html( $name ); ?>,</p>
<p style="margin:0 0 16px;">Has solicitado restablecer tu contraseña. Pulsa el botón para crear una nueva:</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px;">
    <tr>
        <td align="center" style="border-radius:8px;background:#0073aa;">
            <a class="btn" href="<?php echo esc_url( $reset_url ); ?>" style="display:inline-block;padding:13px 28px;font-weight:600;font-size:15px;color:#ffffff;text-decoration:none;border-radius:8px;">Restablecer contraseña</a>
        </td>
    </tr>
</table>
<p style="margin:0 0 8px;font-size:13px;color:#6b7280;">Este enlace expirará en 24 horas.</p>
<p style="margin:0 0 16px;font-size:13px;color:#6b7280;">Si no funciona el botón, copia y pega esta URL en tu navegador:</p>
<p style="margin:0 0 16px;font-size:12px;color:#374151;word-break:break-all;background:#f9fafb;padding:10px 12px;border-radius:6px;border:1px solid #e5e7eb;">
    <?php echo esc_html( $reset_url ); ?>
</p>
<p style="margin:16px 0 0;font-size:13px;color:#6b7280;">Si no solicitaste este cambio, ignora este mensaje.</p>
