<?php
/**
 * Email template for the welcome message sent after user registration.
 *
 * @var array $args {
 *     @type string  $title
 *     @type string  $message
 *     @type array   $data
 *     @type WP_User $user
 * }
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$user = $args['user'] ?? null;
$data = $args['data'] ?? [];
$name = $data['name'] ?? ( $user ? $user->display_name : '' );
$username = $data['username'] ?? ( $user ? $user->user_login : '' );
$login_url = $data['login_url'] ?? home_url( '/login' );
$message = $args['message'] ?? '';

?>
<h2><?php echo esc_html( $args['title'] ); ?></h2>
<p>Hola <?php echo esc_html( $name ); ?>,</p>
<p><?php echo esc_html( $message ); ?></p>

<?php if ( ! empty( $username ) ) : ?>
    <p>Tu nombre de usuario es: <strong><?php echo esc_html( $username ); ?></strong></p>
<?php endif; ?>

<p>Puedes iniciar sesion usando el siguiente enlace:</p>
<p>
    <a
        href="<?php echo esc_url( $login_url ); ?>"
        style="display: inline-block; padding: 10px 20px; background-color: #005b96; color: #ffffff; text-decoration: none; border-radius: 4px;"
    >
        Iniciar sesion
    </a>
</p>
<p>Si no has solicitado esta cuenta, por favor ignora este mensaje.</p>
