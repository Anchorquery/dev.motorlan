<?php
// Arguments available: $args['title'], $args['message'], $args['data'], $args['user']
$data = $args['data'] ?? [];
$product_id = $data['post_id'] ?? 0;
$product_image = get_the_post_thumbnail_url( $product_id, 'medium' );
$product_title = motorlan_format_motor_name( $product_id );
$url = get_site_url() . $data['url'];
?>

<h1><?php esc_html_e( '¡Publicación Aprobada!', 'motorlan-api-vue' ); ?></h1>

<p><?php printf( esc_html__( 'Hola %s,', 'motorlan-api-vue' ), esc_html( $args['user']->display_name ) ); ?></p>

<p><?php echo wp_kses_post( $args['message'] ); ?></p>

<div class="product-card">
    <?php if ( ! empty( $product_image ) ) : ?>
        <img src="<?php echo esc_url($product_image); ?>" alt="<?php esc_attr_e( 'Producto', 'motorlan-api-vue' ); ?>" class="product-img">
    <?php endif; ?>
    <div class="product-info">
        <div class="product-title"><?php echo esc_html($product_title); ?></div>
        <div style="font-size: 14px; color: #28C76F; font-weight: bold;"><?php esc_html_e( 'Estado: Aprobado', 'motorlan-api-vue' ); ?></div>
    </div>
</div>

<div style="text-align: center;">
    <a href="<?php echo esc_url($url); ?>" class="button"><?php esc_html_e( 'Ver Publicación', 'motorlan-api-vue' ); ?></a>
</div>
