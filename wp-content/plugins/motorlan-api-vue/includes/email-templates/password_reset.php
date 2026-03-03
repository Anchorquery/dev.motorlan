<?php
// Password Reset Email Template
$url = isset($args['data']['url']) ? $args['data']['url'] : '';
// Ensure absolute URL if not provided
if (strpos($url, 'http') !== 0) {
    $url = home_url($url);
}
?>

<h1><?php esc_html_e( 'Restablecer contraseña', 'motorlan-api-vue' ); ?></h1>

<p><?php printf( esc_html__( 'Hola %s,', 'motorlan-api-vue' ), esc_html( $args['user']->display_name ) ); ?></p>

<p><?php esc_html_e( 'Has solicitado restablecer tu contraseña para tu cuenta en Motorlan.', 'motorlan-api-vue' ); ?></p>

<p><?php esc_html_e( 'Para crear una nueva contraseña, haz clic en el siguiente botón:', 'motorlan-api-vue' ); ?></p>

<div style="text-align: center;">
    <a href="<?php echo esc_url($url); ?>" class="button">
        <?php esc_html_e( 'Restablecer contraseña', 'motorlan-api-vue' ); ?>
    </a>
</div>

<p><?php esc_html_e( 'Este enlace expirará en 24 horas.', 'motorlan-api-vue' ); ?></p>

<p><?php esc_html_e( 'Si el botón no funciona, copia y pega el siguiente enlace en tu navegador:', 'motorlan-api-vue' ); ?></p>

<p style="word-break: break-all;">
    <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($url); ?></a>
</p>

<div class="info-box">
    <?php esc_html_e( 'Si no solicitaste este cambio, puedes ignorar este correo de forma segura. Tu contraseña no cambiará.', 'motorlan-api-vue' ); ?>
</div>
