<?php
/**
 * Email template for a publication rejected notification.
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

$post_id = $args['data']['post_id'] ?? 0;
$publication_title = get_the_title( $post_id );
$reason = $args['data']['reason'] ?? 'No cumple con las normas de la comunidad.';
$list_url = home_url( '/dashboard/publications/list' );

?>
<h2><?php echo esc_html( $args['title'] ); ?></h2>
<p>Tu publicación <strong>"<?php echo esc_html( $publication_title ); ?>"</strong> requiere cambios para ser aprobada.</p>
<p><strong>Motivo del rechazo:</strong> <?php echo esc_html( $reason ); ?></p>
<p>Puedes editar tu publicación para corregirla desde tu panel:</p>
<p><a href="<?php echo esc_url( $list_url ); ?>" style="display: inline-block; padding: 10px 20px; background-color: #0073aa; color: #ffffff; text-decoration: none;">Gestionar Mis Publicaciones</a></p>
