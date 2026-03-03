<?php
// Arguments available: $args['title'], $args['message'], $args['data'], $args['user']
$data = $args['data'] ?? [];
$product_image = $data['product_image'] ?? '';
$product_title = $data['product_title'] ?? '';
$buyer_name = $data['buyer_name'] ?? 'Un usuario';
$product_price = $data['product_price'] ?? '';
$url = get_site_url() . $data['url'];
?>

<h1><?php esc_html_e( '¡Felicidades, has realizado una venta!', 'motorlan-api-vue' ); ?></h1>

<p><?php printf( esc_html__( 'Hola %s,', 'motorlan-api-vue' ), esc_html( $args['user']->display_name ) ); ?></p>

<p><?php printf( __( 'El usuario <strong>%s</strong> ha completado el proceso de compra para tu artículo.', 'motorlan-api-vue' ), esc_html( $buyer_name ) ); ?></p>

<div class="product-card">
    <?php if ( ! empty( $product_image ) ) : ?>
        <img src="<?php echo esc_url($product_image); ?>" alt="<?php esc_attr_e( 'Producto', 'motorlan-api-vue' ); ?>" class="product-img">
    <?php endif; ?>
    <div class="product-info">
        <div class="product-title"><?php echo esc_html($product_title); ?></div>
        <?php if ( ! empty( $product_price ) ) : ?>
            <div class="product-price"><?php echo esc_html($product_price); ?>€</div>
        <?php endif; ?>
        <div style="font-size: 14px; color: #DA291C; margin-top: 5px;"><?php esc_html_e( 'Estado: Vendido', 'motorlan-api-vue' ); ?></div>
    </div>
</div>

<p><?php esc_html_e( 'Por favor, ponte en contacto con el comprador para gestionar el envío o la entrega.', 'motorlan-api-vue' ); ?></p>

<div style="text-align: center;">
    <a href="<?php echo esc_url($url); ?>" class="button"><?php esc_html_e( 'Ver Detalles de la Venta', 'motorlan-api-vue' ); ?></a>
</div>
