<?php
/**
 * Email template for password reset verification code.
 *
 * @var array $args
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$code       = $args['data']['code'] ?? '';
$user       = $args['user'] ?? null;
$first_name = $user && ! empty( $user->first_name ) ? $user->first_name : ( $user->display_name ?? '' );
?>
<h2 class="h1" style="margin:0 0 16px;font-size:24px;line-height:30px;font-weight:700;color:#111827;">Código de verificación</h2>
<p style="margin:0 0 16px;">Hola <?php echo esc_html( $first_name ); ?>,</p>
<p style="margin:0 0 16px;">Tu código para confirmar el cambio de contraseña es:</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">
    <tr>
        <td align="center" style="background:#f3f6f9;border:1px solid #e5e7eb;padding:24px;border-radius:10px;">
            <div style="font-size:32px;font-weight:700;letter-spacing:8px;color:#005b96;font-family:'Courier New',Courier,monospace;">
                <?php echo esc_html( $code ); ?>
            </div>
        </td>
    </tr>
</table>
<p style="margin:0 0 8px;font-size:13px;color:#6b7280;">Este código expirará en <strong>15 minutos</strong>.</p>
<p style="margin:16px 0 0;font-size:13px;color:#6b7280;">Si no solicitaste este cambio, ignora este mensaje.</p>
