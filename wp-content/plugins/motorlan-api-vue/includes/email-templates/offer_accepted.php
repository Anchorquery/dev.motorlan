<?php
/**
 * Email template for an accepted offer notification.
 *
 * @var array $args {
 *     @type string $title
 *     @type string $message
 *     @type array  $data
 *     @type WP_User $user
 * }
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$publication_id = $args['data']['publication_id'] ?? 0;
$publication_title = get_the_title( $publication_id );
$confirm_url = home_url( '/offers/sent' ); // URL de la app Vue

?>
<h2><?php echo esc_html( $args['title'] ); ?></h2>
<p>¡Buenas noticias! El vendedor ha aceptado tu oferta por <strong>"<?php echo esc_html( $publication_title ); ?>"</strong>.</p>
<p><strong>IMPORTANTE:</strong> Para completar la compra, debes confirmar el pedido en las próximas <strong>24 horas</strong>.</p>
<p>Si no confirmas dentro del plazo, la oferta expirará y el motor volverá a estar disponible para otros interesados.</p>
<p><a href="<?php echo esc_url( $confirm_url ); ?>" style="display: inline-block; padding: 10px 20px; background-color: #28a745; color: #ffffff; text-decoration: none;">Confirmar Compra Ahora</a></p>
