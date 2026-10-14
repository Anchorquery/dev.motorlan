<?php
/**
 * Email template — copia al remitente tras enviar un mensaje en chat.
 *
 * @var array $args
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$message_text  = $args['message'] ?? '';
// Textos traducibles: la plantilla estaba entera en español fijo
$recipient     = ! empty( $args['data']['recipient_name'] ) ? $args['data']['recipient_name'] : __( 'el vendedor', 'motorlan-api-vue' );
$product_title = ! empty( $args['data']['product_title'] ) ? $args['data']['product_title'] : __( 'una publicación', 'motorlan-api-vue' );
$recipient_user_id = isset( $args['user'] ) && is_object( $args['user'] ) ? (int) $args['user']->ID : null;
$thread_url        = ! empty( $args['data']['url'] )
    ? ( strpos( $args['data']['url'], 'http' ) === 0 ? $args['data']['url'] : home_url( $args['data']['url'] ) )
    : ( function_exists( 'motorlan_localized_page_url_absolute' )
        ? motorlan_localized_page_url_absolute( 'mi-cuenta', 'dashboard', $recipient_user_id )
        : home_url( '/mi-cuenta/dashboard' ) );
?>
<h2 class="h1" style="margin:0 0 16px;font-size:24px;line-height:30px;font-weight:700;color:#111827;"><?php esc_html_e( 'Mensaje enviado', 'motorlan-api-vue' ); ?></h2>
<p style="margin:0 0 16px;"><?php
    printf(
        /* translators: 1: destinatario, 2: publicación */
        wp_kses( __( 'Tu mensaje a %1$s sobre %2$s se ha enviado correctamente.', 'motorlan-api-vue' ), array() ),
        '<strong>' . esc_html( $recipient ) . '</strong>',
        '<strong>' . esc_html( $product_title ) . '</strong>'
    );
?></p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">
    <tr>
        <td style="border-left:4px solid #0073aa;background:#f3f6f9;padding:14px 18px;border-radius:6px;font-style:italic;color:#374151;">
            <?php echo nl2br( esc_html( $message_text ) ); ?>
        </td>
    </tr>
</table>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 0;">
    <tr>
        <td align="center" style="border-radius:8px;background:#0073aa;">
            <a class="btn" href="<?php echo esc_url( $thread_url ); ?>" style="display:inline-block;padding:13px 28px;font-weight:600;font-size:15px;color:#ffffff;text-decoration:none;border-radius:8px;"><?php esc_html_e( 'Ver conversación', 'motorlan-api-vue' ); ?></a>
        </td>
    </tr>
</table>
