<?php
/**
 * Email template for a new offer notification.
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
$offer_amount = $args['data']['amount'] ?? ( $args['message'] ?? '0' );
$offers_url = home_url( '/offers/received' ); // URL de la app Vue

?>
<h2><?php echo esc_html( $args['title'] ); ?></h2>
<p>Has recibido una nueva oferta para tu motor <strong>"<?php echo esc_html( $publication_title ); ?>"</strong>.</p>
<p><strong>Importe ofertado:</strong> <?php echo esc_html( $offer_amount ); ?>€</p>
<p>Puedes revisar los detalles de la oferta y decidir si la aceptas o la rechazas desde tu panel de gestión:</p>
<p><a href="<?php echo esc_url( $offers_url ); ?>" style="display: inline-block; padding: 10px 20px; background-color: #0073aa; color: #ffffff; text-decoration: none;">Gestionar Ofertas</a></p>
