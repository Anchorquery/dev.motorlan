<?php
// Arguments available: $args['title'], $args['message'], $args['data'], $args['user']
$data = $args['data'] ?? [];
$product_image = $data['product_image'] ?? '';
$product_title = $data['product_title'] ?? '';
$asker_name = $data['asker_name'] ?? 'Usuario';
$question_text = $data['question_text'] ?? '';
$url = get_site_url() . $data['url'];
?>

<h1><?php esc_html_e( 'Nueva Pregunta', 'motorlan-api-vue' ); ?></h1>

<p><?php printf( esc_html__( 'Hola %s,', 'motorlan-api-vue' ), esc_html( $args['user']->display_name ) ); ?></p>

<p><?php printf( __( 'El usuario <strong>%s</strong> tiene una pregunta sobre tu publicación:', 'motorlan-api-vue' ), esc_html( $asker_name ) ); ?></p>

<div style="background-color: #f0f0f5; border-left: 4px solid #DA291C; padding: 15px; margin: 15px 0; font-style: italic;">
    "<?php echo nl2br(esc_html($question_text)); ?>"
</div>

<?php if ( ! empty( $product_image ) || ! empty( $product_title ) ) : ?>
<div class="product-card">
    <?php if ( ! empty( $product_image ) ) : ?>
        <img src="<?php echo esc_url($product_image); ?>" alt="<?php esc_attr_e( 'Producto', 'motorlan-api-vue' ); ?>" class="product-img">
    <?php endif; ?>
    <div class="product-info">
        <?php if ( ! empty( $product_title ) ) : ?>
            <div class="product-title"><?php echo esc_html($product_title); ?></div>
        <?php endif; ?>
        <div style="font-size: 14px; color: #888;"><?php esc_html_e( 'Tienes una nueva pregunta', 'motorlan-api-vue' ); ?></div>
    </div>
</div>
<?php endif; ?>

<div style="text-align: center;">
    <a href="<?php echo esc_url($url); ?>" class="button"><?php esc_html_e( 'Responder Pregunta', 'motorlan-api-vue' ); ?></a>
</div>