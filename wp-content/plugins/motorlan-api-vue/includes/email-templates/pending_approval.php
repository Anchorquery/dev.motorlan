<?php
// Arguments available: $args['title'], $args['message'], $args['data'], $args['user']
$data = $args['data'] ?? [];
$product_id = $data['post_id'] ?? 0;
$product_image = get_the_post_thumbnail_url( $product_id, 'medium' );
$product_title = motorlan_format_motor_name( $product_id );
$url = get_site_url() . $data['url'];
?>

<h1><?php esc_html_e( 'Solicitud de Publicación', 'motorlan-api-vue' ); ?></h1>

<p><?php esc_html_e( 'Hola Administrador,', 'motorlan-api-vue' ); ?></p>

<p><?php echo wp_kses_post( $args['message'] ); ?></p>

<div class="product-card">
    <?php if ( ! empty( $product_image ) ) : ?>
        <img src="<?php echo esc_url($product_image); ?>" alt="<?php esc_attr_e( 'Producto', 'motorlan-api-vue' ); ?>" class="product-img">
    <?php endif; ?>
    <div class="product-info">
        <div class="product-title"><?php echo esc_html($product_title); ?></div>
        
        <div style="margin-top: 10px; padding: 10px; background-color: #1A1A1A; border-radius: 4px;">
            <p style="margin: 0; font-size: 13px; color: #BBB;"><?php esc_html_e( 'Detalles Técnicos:', 'motorlan-api-vue' ); ?></p>
            <table width="100%" style="margin-top: 5px; border-collapse: collapse;">
                <?php if (!empty($data['brand'])): ?>
                <tr>
                    <td style="padding: 2px 0; font-size: 13px; color: #888;"><?php esc_html_e( 'Marca:', 'motorlan-api-vue' ); ?></td>
                    <td style="padding: 2px 0; font-size: 13px; color: #FFF; text-align: right;"><?php echo esc_html($data['brand']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($data['reference'])): ?>
                <tr>
                    <td style="padding: 2px 0; font-size: 13px; color: #888;"><?php esc_html_e( 'Referencia:', 'motorlan-api-vue' ); ?></td>
                    <td style="padding: 2px 0; font-size: 13px; color: #FFF; text-align: right;"><?php echo esc_html($data['reference']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($data['power'])): ?>
                <tr>
                    <td style="padding: 2px 0; font-size: 13px; color: #888;"><?php esc_html_e( 'Potencia:', 'motorlan-api-vue' ); ?></td>
                    <td style="padding: 2px 0; font-size: 13px; color: #FFF; text-align: right;"><?php echo esc_html($data['power']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($data['speed'])): ?>
                <tr>
                    <td style="padding: 2px 0; font-size: 13px; color: #888;"><?php esc_html_e( 'Velocidad:', 'motorlan-api-vue' ); ?></td>
                    <td style="padding: 2px 0; font-size: 13px; color: #FFF; text-align: right;"><?php echo esc_html($data['speed']); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>

        <div style="margin-top: 15px; font-size: 14px; color: #E68F3C; font-weight: bold;"><?php esc_html_e( 'Estado: Pendiente de Aprobación', 'motorlan-api-vue' ); ?></div>
    </div>
</div>

<div style="text-align: center; margin-top: 30px;">
    <a href="<?php echo esc_url($url); ?>" class="button"><?php esc_html_e( 'Acceder al Panel de Aprobación', 'motorlan-api-vue' ); ?></a>
</div>
