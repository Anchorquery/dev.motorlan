<?php
/**
 * Email template — el vendedor ha respondido la pregunta.
 *
 * @var array $args
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$publication_title = $args['data']['publication_title'] ?? 'una publicación';
$answer_text       = $args['data']['answer'] ?? ( $args['message'] ?? '' );
$recipient_user_id = isset( $args['user'] ) && is_object( $args['user'] ) ? (int) $args['user']->ID : null;
$track_url         = ! empty( $args['data']['url'] )
    ? ( strpos( $args['data']['url'], 'http' ) === 0 ? $args['data']['url'] : home_url( $args['data']['url'] ) )
    : ( function_exists( 'motorlan_localized_page_url_absolute' )
        ? motorlan_localized_page_url_absolute( 'mi-cuenta', 'dashboard/purchases/questions', $recipient_user_id )
        : home_url( '/mi-cuenta/dashboard/purchases/questions' ) );
?>
<h2 class="h1" style="margin:0 0 16px;font-size:24px;line-height:30px;font-weight:700;color:#111827;">Tienes una respuesta</h2>
<p style="margin:0 0 16px;">El vendedor ha respondido tu pregunta sobre <strong>"<?php echo esc_html( $publication_title ); ?>"</strong>.</p>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">
    <tr>
        <td style="border-left:4px solid #16a34a;background:#ecfdf5;padding:14px 18px;border-radius:6px;color:#065f46;">
            <?php echo nl2br( esc_html( $answer_text ) ); ?>
        </td>
    </tr>
</table>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 0;">
    <tr>
        <td align="center" style="border-radius:8px;background:#0073aa;">
            <a class="btn" href="<?php echo esc_url( $track_url ); ?>" style="display:inline-block;padding:13px 28px;font-weight:600;font-size:15px;color:#ffffff;text-decoration:none;border-radius:8px;">Ver respuesta</a>
        </td>
    </tr>
</table>
