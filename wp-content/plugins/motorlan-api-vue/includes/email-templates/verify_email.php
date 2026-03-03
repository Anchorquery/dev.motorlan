<?php
// Verify Email Template
$url = isset($args['data']['url']) ? $args['data']['url'] : '';
// Ensure absolute URL if not provided
if (strpos($url, 'http') !== 0) {
    $url = home_url($url);
}
?>

<h1><?php esc_html_e( 'Verifica tu cuenta', 'motorlan-api-vue' ); ?></h1>

<p><?php printf( esc_html__( 'Hola %s,', 'motorlan-api-vue' ), esc_html( $args['user']->display_name ) ); ?></p>

<p><?php esc_html_e( 'Gracias por registrarte en Motorlan. Para completar tu registro y activar tu cuenta, por favor haz clic en el siguiente botón:', 'motorlan-api-vue' ); ?></p>

<div style="text-align: center;">
    <a href="<?php echo esc_url($url); ?>" class="button">
        <?php esc_html_e( 'Verificar mi Email', 'motorlan-api-vue' ); ?>
    </a>
</div>

<p><?php esc_html_e( 'Si el botón no funciona, puedes copiar y pegar el siguiente enlace en tu navegador:', 'motorlan-api-vue' ); ?></p>

<p style="word-break: break-all;">
    <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($url); ?></a>
</p>

<div class="info-box">
    <?php esc_html_e( 'Si no has creado una cuenta en Motorlan, puedes ignorar este mensaje.', 'motorlan-api-vue' ); ?>
</div>
