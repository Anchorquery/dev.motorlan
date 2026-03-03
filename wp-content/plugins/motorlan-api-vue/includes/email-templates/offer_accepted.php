<?php
// Arguments available: $args['title'], $args['message'], $args['data'], $args['user']
$data = $args['data'] ?? [];
$product_image = $data['product_image'] ?? '';
$product_title = $data['product_title'] ?? '';
$offer_amount = $data['offer_amount'] ?? '';
$url = get_site_url() . $data['url'];
?>

<h1><?php esc_html_e( '¡Oferta Aceptada!', 'motorlan-api-vue' ); ?></h1>

<p><?php printf( esc_html__( 'Hola %s,', 'motorlan-api-vue' ), esc_html( $args['user']->display_name ) ); ?></p>

<p><?php printf( __( '¡Buenas noticias! El vendedor ha aceptado tu oferta por <strong>"%s"</strong>.', 'motorlan-api-vue' ), esc_html( $product_title ) ); ?></p>

<div class="product-card">
    <?php if ( ! empty( $product_image ) ) : ?>
        <img src="<?php echo esc_url($product_image); ?>" alt="<?php esc_attr_e( 'Producto', 'motorlan-api-vue' ); ?>" class="product-img">
    <?php endif; ?>
    <div class="product-info">
        <div class="product-title"><?php echo esc_html($product_title); ?></div>
        <div style="font-size: 14px; color: #DA291C; font-weight: bold; margin-bottom: 5px;">
            <?php esc_html_e( 'Oferta aceptada:', 'motorlan-api-vue' ); ?> <?php echo esc_html($offer_amount); ?>€
        </div>
        <div style="font-size: 13px; color: #888;">
            <?php esc_html_e( 'Tienes 24 horas para completar la compra.', 'motorlan-api-vue' ); ?>
        </div>
    </div>
</div>

<div style="background-color: #FFF3CD; border-left: 4px solid #FFC107; padding: 15px; margin: 15px 0;">
    <strong><?php esc_html_e( 'IMPORTANTE:', 'motorlan-api-vue' ); ?></strong> <?php esc_html_e( 'Si no confirmas el pedido dentro del plazo, la oferta expirará y el producto volverá a estar disponible para otros usuarios.', 'motorlan-api-vue' ); ?>
</div>

<div style="text-align: center;">
    <a href="<?php echo esc_url($url); ?>" class="button"><?php esc_html_e( 'Confirmar Compra Ahora', 'motorlan-api-vue' ); ?></a>
</div>
