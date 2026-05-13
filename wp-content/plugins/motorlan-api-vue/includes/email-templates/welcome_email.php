<?php
/**
 * Email template for the welcome message sent after user registration.
 *
 * @var array $args
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$user      = $args['user'] ?? null;
$data      = $args['data'] ?? [];
$name      = $data['name'] ?? ( $user ? $user->display_name : '' );
$username  = $data['username'] ?? ( $user ? $user->user_login : '' );
$login_url = $data['login_url'] ?? home_url( '/login' );
$message   = $args['message'] ?? '';
?>
<h2 class="h1" style="margin:0 0 16px;font-size:24px;line-height:30px;font-weight:700;color:#111827;"><?php echo esc_html( $args['title'] ); ?></h2>
<p style="margin:0 0 16px;">Hola <?php echo esc_html( $name ); ?>,</p>
<p style="margin:0 0 16px;"><?php echo esc_html( $message ); ?></p>
<?php if ( ! empty( $username ) ) : ?>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">
    <tr>
        <td style="background:#f3f6f9;padding:14px 18px;border-radius:8px;">
            Tu nombre de usuario: <strong><?php echo esc_html( $username ); ?></strong>
        </td>
    </tr>
</table>
<?php endif; ?>
<p style="margin:0 0 16px;">Ya puedes iniciar sesión y empezar a publicar o explorar:</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 0;">
    <tr>
        <td align="center" style="border-radius:8px;background:#005b96;">
            <a class="btn" href="<?php echo esc_url( $login_url ); ?>" style="display:inline-block;padding:13px 28px;font-weight:600;font-size:15px;color:#ffffff;text-decoration:none;border-radius:8px;">Iniciar sesión</a>
        </td>
    </tr>
</table>
<p style="margin:24px 0 0;font-size:13px;color:#6b7280;">Si no creaste esta cuenta, ignora este mensaje.</p>
