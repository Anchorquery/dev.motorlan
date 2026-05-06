<?php
/**
 * Email template for a publication approved notification.
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
$list_url = home_url( '/dashboard/publications/list' );

?>
<h2><?php echo esc_html( $args['title'] ); ?></h2>
<p>Tu publicación <strong>"<?php echo esc_html( $publication_title ); ?>"</strong> ha sido aprobada por el administrador y ya es pública en la tienda.</p>
<p>Puedes ver tus publicaciones desde tu panel:</p>
<p><a href="<?php echo esc_url( $list_url ); ?>" style="display: inline-block; padding: 10px 20px; background-color: #0073aa; color: #ffffff; text-decoration: none;">Ver Mis Publicaciones</a></p>
