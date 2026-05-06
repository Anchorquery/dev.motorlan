<?php
// Welcome Email
$url = get_site_url() . '/login';
?>

<h1><?php esc_html_e( '¡Bienvenido/a a Motorlan!', 'motorlan-api-vue' ); ?></h1>

<p><?php printf( esc_html__( 'Hola %s,', 'motorlan-api-vue' ), esc_html( $args['user']->display_name ) ); ?></p>

<p><?php esc_html_e( 'Gracias por unirte a la mayor comunidad de compra-venta de recambios industriales y robótica.', 'motorlan-api-vue' ); ?></p>

<p><?php esc_html_e( 'Tu cuenta ha sido creada correctamente. Ahora puedes:', 'motorlan-api-vue' ); ?></p>

<ul>
    <li><?php esc_html_e( 'Publicar tus productos y llegar a miles de compradores.', 'motorlan-api-vue' ); ?></li>
    <li><?php esc_html_e( 'Guardar tus búsquedas y productos favoritos.', 'motorlan-api-vue' ); ?></li>
    <li><?php esc_html_e( 'Contactar directamente con vendedores profesionales.', 'motorlan-api-vue' ); ?></li>
</ul>

<div style="text-align: center;">
    <a href="<?php echo esc_url($url); ?>" class="button"><?php esc_html_e( 'Acceder a mi Cuenta', 'motorlan-api-vue' ); ?></a>
</div>

<p><?php esc_html_e( 'Si tienes alguna pregunta, nuestro equipo de soporte está aquí para ayudarte.', 'motorlan-api-vue' ); ?></p>
