<?php
// Arguments available: $args['title'], $args['message'], $args['data'], $args['user']
$data = $args['data'] ?? [];
$product_image = $data['product_image'] ?? '';
$product_title = $data['product_title'] ?? '';
$sender_name = $data['sender_name'] ?? 'Un usuario';
$message_full = $data['message_full'] ?? '...';
$url = get_site_url() . $data['url'];
?>

<h1><?php esc_html_e( 'Nuevo Mensaje', 'motorlan-api-vue' ); ?></h1>

<?php 
$is_guest = empty($args['user']) || (isset($args['user']->ID) && $args['user']->ID === 0);
$display_name = !empty($args['user']) ? $args['user']->display_name : __('Invitado', 'motorlan-api-vue');
?>

<p><?php printf( esc_html__( 'Hola %s,', 'motorlan-api-vue' ), esc_html( $display_name ) ); ?></p>

<?php if ( $is_guest ) : ?>
    <p><?php esc_html_e( 'Has recibido una respuesta a tu consulta en Motorlan.', 'motorlan-api-vue' ); ?></p>
<?php endif; ?>

<p><?php printf( __( '<strong>%s</strong> te ha enviado un mensaje:', 'motorlan-api-vue' ), esc_html( $sender_name ) ); ?></p>

<div style="background-color: #f0f0f5; border-left: 4px solid #DA291C; padding: 15px; margin: 15px 0; font-style: italic;">
    "<?php echo nl2br(esc_html($message_full)); ?>"
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
        <div style="font-size: 14px; color: #888;"><?php esc_html_e( 'Sobre la publicación', 'motorlan-api-vue' ); ?></div>
    </div>
</div>
<?php endif; ?>

<div style="text-align: center; margin-bottom: 25px;">
    <a href="<?php echo esc_url($url); ?>" class="button"><?php esc_html_e( 'Ver y Responder', 'motorlan-api-vue' ); ?></a>
</div>

<?php if ( $is_guest ) : ?>
<div style="background-color: #F9FAFB; border: 1px solid #EAECF0; padding: 30px; border-radius: 12px; margin-top: 40px; text-align: center;">
    <h2 style="margin-top: 0; margin-bottom: 12px; color: #101828; font-size: 20px; font-weight: 700; letter-spacing: -0.02em;">
        <?php esc_html_e( '¡Saca el máximo provecho a Motorlan!', 'motorlan-api-vue' ); ?>
    </h2>
    <p style="font-size: 15px; color: #475467; line-height: 1.6; margin-bottom: 24px; max-width: 480px; margin-left: auto; margin-right: auto;">
        <?php esc_html_e( 'Crea una cuenta gratuita para guardar tus conversaciones, marcar favoritos y recibir avisos cuando alguien te responda.', 'motorlan-api-vue' ); ?>
    </p>
    <a href="<?php echo esc_url(trailingslashit(get_site_url()) . 'register'); ?>" 
       style="background-color: #DA291C; color: #ffffff !important; padding: 14px 28px; text-decoration: none; border-radius: 8px; font-weight: 600; display: inline-block; font-size: 15px; box-shadow: 0px 1px 2px rgba(16, 24, 40, 0.05);">
        <?php esc_html_e( 'Crear mi cuenta gratis', 'motorlan-api-vue' ); ?>
    </a>
</div>
<?php endif; ?>
