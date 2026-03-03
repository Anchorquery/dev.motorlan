<?php
// Arguments available: $args['title'], $args['message'], $args['data'], $args['user']
$data = $args['data'] ?? [];
$product_image = $data['product_image'] ?? '';
$product_title = $data['product_title'] ?? 'Producto'; // Fallback if data missing (usually listener provides it, but sometimes it might be just title)
// Listener for offer_created puts title in message, but let's see. 
// Ah, listener passes 'publication_title' in data? No, in listener I passed 'product_image' but title is not explicitly in 'on_offer_created' data array, I just used it in subject string.
// Let me double check on_offer_created in listener.
// I see I didn't add 'product_title' to data in on_offer_created, only used it for subject. I should fix that if I want to use it here independently. 
// For now, I will extract it from the message if needed or just rely on 'Has recibido una oferta...'

// Actually, I can update the listener again to be sure, or just proceed. 
// Let's assume for now I'll use what's available. Listener has 'offer_amount'.
$offer_amount = $data['offer_amount'] ?? '---';
$url = get_site_url() . $data['url'];
?>

<h1><?php esc_html_e( '¡Nueva Oferta Recibida!', 'motorlan-api-vue' ); ?></h1>

<p><?php printf( esc_html__( 'Hola %s,', 'motorlan-api-vue' ), esc_html( $args['user']->display_name ) ); ?></p>

<p><?php echo wp_kses_post( $args['message'] ); ?></p>

<?php if ( ! empty( $product_image ) ) : ?>
<div class="product-card">
    <img src="<?php echo esc_url($product_image); ?>" alt="<?php esc_attr_e( 'Producto', 'motorlan-api-vue' ); ?>" class="product-img">
    <div class="product-info">
        <div class="product-title"><?php esc_html_e( 'Oferta de:', 'motorlan-api-vue' ); ?> <?php echo esc_html($offer_amount); ?>€</div>
        <?php if (!empty($data['product_price'])): ?>
            <div style="font-size: 14px; color: #888;"><?php printf( esc_html__( 'Precio original: %s€', 'motorlan-api-vue' ), esc_html( $data['product_price'] ) ); ?></div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<p><?php esc_html_e( 'Comprueba los detalles de la oferta y decide si aceptarla o rechazarla.', 'motorlan-api-vue' ); ?></p>

<div style="text-align: center;">
    <a href="<?php echo esc_url($url); ?>" class="button"><?php esc_html_e( 'Ver Oferta', 'motorlan-api-vue' ); ?></a>
</div>

<p style="margin-top: 30px; font-size: 14px; color: #666;">
    <?php esc_html_e( 'Si no puedes hacer clic en el botón, copia y pega este enlace:', 'motorlan-api-vue' ); ?><br>
    <?php echo esc_url($url); ?>
</p>
