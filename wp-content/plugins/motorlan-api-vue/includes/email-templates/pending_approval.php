<?php
/**
 * Email template for a pending approval notification.
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
$author_id = $args['data']['author_id'] ?? 0;
$author = get_userdata( $author_id );
$author_name = $author ? $author->display_name : 'Un usuario';

$approvals_url = home_url( '/dashboard/admin/approvals' );

?>
<h2><?php echo esc_html( $args['title'] ); ?></h2>
<p><strong><?php echo esc_html( $author_name ); ?></strong> ha creado una nueva publicación titulada <strong>"<?php echo esc_html( $publication_title ); ?>"</strong> que requiere revisión.</p>
<p>Puedes revisar y aprobar la publicación desde el panel de administración:</p>
<p><a href="<?php echo esc_url( $approvals_url ); ?>" style="display: inline-block; padding: 10px 20px; background-color: #0073aa; color: #ffffff; text-decoration: none;">Revisar Publicaciones</a></p>
