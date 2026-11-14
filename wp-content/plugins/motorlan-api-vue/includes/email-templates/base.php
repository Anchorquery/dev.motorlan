<?php
/**
 * Plantilla base de los emails de Motorlan.
 *
 * Se incluye desde Motorlan_Email::render_layout() con estas variables:
 *   $content   HTML ya seguro del cuerpo (lo genera cada plantilla).
 *   $subject   Asunto en texto plano (va escapado en <title>).
 *   $preheader Resumen oculto que ve la bandeja de entrada.
 *   $args      Datos de la notificación (user, data…); solo se lee 'user'.
 *
 * Maquetación en tablas con estilos inline en cada elemento importante; el <style> solo
 * añade el modo oscuro y el ajuste a móvil (los clientes que lo descartan siguen viendo el
 * correo correcto en modo claro). Identidad: primary #DA291C, fondo #F8F7FA, texto #2F2B3D.
 *
 * @var string $content
 * @var string $subject
 * @var string $preheader
 * @var array  $args
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

$ml_pal   = Motorlan_Email::palette();
$ml_l     = $ml_pal['light'];
$ml_d     = $ml_pal['dark'];
$ml_font  = Motorlan_Email::FONT;
$ml_start = Motorlan_Email::start();
$ml_lang  = Motorlan_Email::lang();
$ml_dir   = Motorlan_Email::is_rtl() ? 'rtl' : 'ltr';

$ml_user = ( is_array( $args ) && isset( $args['user'] ) && is_object( $args['user'] ) && ! empty( $args['user']->ID ) ) ? $args['user'] : null;

// Enlaces del pie. «Preferencias» lleva a la cuenta (dashboard/user/account): no existe
// ninguna pantalla de preferencias de notificaciones. Los invitados no tienen cuenta.
$ml_prefs_url = $ml_user ? Motorlan_Email::account_url( 'dashboard/user/account', $ml_user ) : '';
$ml_privacy   = function_exists( 'get_privacy_policy_url' ) ? (string) get_privacy_policy_url() : '';
$ml_privacy   = '' !== $ml_privacy ? $ml_privacy : Motorlan_Email::abs_url( '/politica-de-privacidad/', 'privacy' );
$ml_home      = Motorlan_Email::abs_url( home_url( '/' ), 'home' );
$ml_logo      = Motorlan_Email::logo_url();
$ml_year      = function_exists( 'wp_date' ) ? wp_date( 'Y' ) : gmdate( 'Y' );

/**
 * Reglas de modo oscuro. Se emiten dos veces: con @media (Apple Mail, Outlook para
 * Mac/iOS/Android, Thunderbird…) y con [data-ogsc]/[data-ogsb] (Outlook.com / web).
 * Gmail y Outlook de escritorio invierten por su cuenta: por eso cada bloque lleva su
 * propio par fondo/texto y el botón, el logo y los estados no dependen del cuerpo.
 */
$ml_dark_rules = array(
    '.ml-page'      => 'background-color:' . $ml_d['page'] . ' !important;',
    '.ml-card'      => 'background-color:' . $ml_d['card'] . ' !important;border-color:' . $ml_d['border'] . ' !important;',
    '.ml-head'      => 'background-color:' . $ml_d['card'] . ' !important;border-color:' . $ml_d['border'] . ' !important;',
    '.ml-soft'      => 'background-color:' . $ml_d['soft'] . ' !important;',
    '.ml-text'      => 'color:' . $ml_d['text'] . ' !important;',
    '.ml-muted'     => 'color:' . $ml_d['muted'] . ' !important;',
    '.ml-link'      => 'color:' . $ml_d['link'] . ' !important;',
    '.ml-border'    => 'border-color:' . $ml_d['border'] . ' !important;',
    '.ml-st-red'    => 'background-color:' . $ml_d['red_bg'] . ' !important;color:' . $ml_d['red'] . ' !important;',
    '.ml-st-green'  => 'background-color:' . $ml_d['green_bg'] . ' !important;color:' . $ml_d['green'] . ' !important;',
    '.ml-st-amber'  => 'background-color:' . $ml_d['amber_bg'] . ' !important;color:' . $ml_d['amber'] . ' !important;',
    '.ml-st-info'   => 'background-color:' . $ml_d['info_bg'] . ' !important;color:' . $ml_d['info'] . ' !important;',
    '.ml-bar-red'   => 'border-color:' . $ml_d['red'] . ' !important;',
    '.ml-bar-green' => 'border-color:' . $ml_d['green'] . ' !important;',
    '.ml-bar-amber' => 'border-color:' . $ml_d['amber'] . ' !important;',
);
// El botón mantiene el rojo de marca con texto blanco (4,9:1) en ambos modos.
$ml_btn_keep = array(
    '.ml-btn-td' => 'background-color:' . Motorlan_Email::PRIMARY . ' !important;',
    '.ml-btn-a'  => 'background-color:' . Motorlan_Email::PRIMARY . ' !important;color:#ffffff !important;',
    '.ml-btn-a span' => 'color:#ffffff !important;',
);
$ml_dark_css = '';
foreach ( array_merge( $ml_dark_rules, $ml_btn_keep ) as $sel => $decl ) {
    $ml_dark_css .= $sel . '{' . $decl . '}';
}
$ml_ogs_css = '';
foreach ( array_merge( $ml_dark_rules, $ml_btn_keep ) as $sel => $decl ) {
    $bg_only = ( false !== strpos( $decl, 'background-color' ) );
    $ml_ogs_css .= '[data-ogsc] ' . $sel . '{' . $decl . '}';
    if ( $bg_only ) {
        $ml_ogs_css .= '[data-ogsb] ' . $sel . '{' . $decl . '}';
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( $ml_lang ); ?>" dir="<?php echo esc_attr( $ml_dir ); ?>" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
<meta charset="utf-8">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="x-apple-disable-message-reformatting">
<meta name="format-detection" content="telephone=no, date=no, address=no, email=no, url=no">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title><?php echo esc_html( $subject ); ?></title>
<!--[if mso]>
<noscript><xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
<style type="text/css">
  body, table, td, a, p, h1 { font-family: Arial, Helvetica, sans-serif !important; }
  table { border-collapse: collapse; }
</style>
<![endif]-->
<style type="text/css">
  :root { color-scheme: light dark; supported-color-schemes: light dark; }
  body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
  table, td { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
  img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
  a { text-decoration: underline; }
  a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; font-size: inherit !important; font-family: inherit !important; font-weight: inherit !important; line-height: inherit !important; }
  u + #body a { color: inherit; text-decoration: none; font-size: inherit; font-family: inherit; font-weight: inherit; line-height: inherit; }
  @media only screen and (max-width: 620px) {
    .ml-pad { padding: 24px 20px !important; }
    .ml-outer { padding: 12px 8px !important; }
    .ml-container { width: 100% !important; }
    .ml-btn-table, .ml-btn-inner { width: 100% !important; }
    .ml-btn-td { display: block !important; width: 100% !important; }
    .ml-btn-a { display: block !important; box-sizing: border-box; }
    .ml-card-img { display: none !important; }
  }
  @media (prefers-color-scheme: dark) {
    <?php echo $ml_dark_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- colores fijos de la paleta ?>
  }
  <?php echo $ml_ogs_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</style>
</head>
<body id="body" class="ml-page" bgcolor="<?php echo esc_attr( $ml_l['page'] ); ?>" style="margin:0;padding:0;background-color:<?php echo esc_attr( $ml_l['page'] ); ?>;font-family:<?php echo $ml_font; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;">
<?php if ( '' !== $preheader ) : ?>
<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;visibility:hidden;"><?php echo esc_html( $preheader ); ?><?php echo str_repeat( '&#8199;&#847; ', 30 ); // relleno: evita que el cliente muestre texto del cuerpo tras el resumen ?></div>
<?php endif; ?>
<table role="presentation" class="ml-page" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="<?php echo esc_attr( $ml_l['page'] ); ?>" style="width:100%;background-color:<?php echo esc_attr( $ml_l['page'] ); ?>;">
<tr>
<td class="ml-outer" align="center" style="padding:28px 12px;">
  <!--[if mso]><table role="presentation" width="600" align="center" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
  <table role="presentation" class="ml-container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;margin:0 auto;">

    <!-- Cabecera: franja de marca + logo sobre un chip blanco fijo (el PNG del logo es oscuro y transparente) -->
    <tr>
      <td height="4" bgcolor="<?php echo esc_attr( Motorlan_Email::PRIMARY ); ?>" style="height:4px;font-size:0;line-height:0;background-color:<?php echo esc_attr( Motorlan_Email::PRIMARY ); ?>;border-radius:10px 10px 0 0;">&nbsp;</td>
    </tr>
    <tr>
      <td class="ml-head ml-border" align="center" bgcolor="<?php echo esc_attr( $ml_l['card'] ); ?>" style="padding:22px 24px 18px;background-color:<?php echo esc_attr( $ml_l['card'] ); ?>;border-left:1px solid <?php echo esc_attr( $ml_l['border'] ); ?>;border-right:1px solid <?php echo esc_attr( $ml_l['border'] ); ?>;">
        <a href="<?php echo esc_url( $ml_home ); ?>" target="_blank" style="text-decoration:none;">
          <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center"><tr>
            <td bgcolor="#FFFFFF" style="background-color:#FFFFFF;border-radius:10px;padding:8px 14px;">
              <img src="<?php echo esc_url( $ml_logo ); ?>" width="180" alt="Motorlan" style="display:block;width:180px;max-width:100%;height:auto;border:0;font-family:<?php echo $ml_font; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;font-size:22px;font-weight:700;color:<?php echo esc_attr( Motorlan_Email::PRIMARY ); ?>;">
            </td>
          </tr></table>
        </a>
      </td>
    </tr>

    <!-- Contenido -->
    <tr>
      <td class="ml-card ml-border ml-pad" bgcolor="<?php echo esc_attr( $ml_l['card'] ); ?>" style="padding:12px 36px 32px;background-color:<?php echo esc_attr( $ml_l['card'] ); ?>;border:1px solid <?php echo esc_attr( $ml_l['border'] ); ?>;border-top:0;border-radius:0 0 10px 10px;font-family:<?php echo $ml_font; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;font-size:16px;line-height:24px;color:<?php echo esc_attr( $ml_l['text'] ); ?>;text-align:<?php echo esc_attr( $ml_start ); ?>;">
        <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ya escapado por los componentes de Motorlan_Email ?>
      </td>
    </tr>

    <!-- Pie -->
    <tr>
      <td align="center" style="padding:22px 12px 4px;">
        <p class="ml-muted" style="margin:0 0 8px;padding:0;font-family:<?php echo $ml_font; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;font-size:13px;line-height:20px;color:<?php echo esc_attr( $ml_l['muted'] ); ?>;text-align:center;">
          &copy; <?php echo esc_html( $ml_year ); ?> <strong>Motorlan</strong>
        </p>
        <p class="ml-muted" style="margin:0 0 8px;padding:0;font-family:<?php echo $ml_font; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;font-size:13px;line-height:20px;color:<?php echo esc_attr( $ml_l['muted'] ); ?>;text-align:center;">
          <?php if ( '' !== $ml_prefs_url ) : ?>
            <a class="ml-muted" href="<?php echo esc_url( $ml_prefs_url ); ?>" target="_blank" style="color:<?php echo esc_attr( $ml_l['muted'] ); ?>;text-decoration:underline;"><?php esc_html_e( 'Preferencias', 'motorlan-api-vue' ); ?></a>
            &nbsp;&bull;&nbsp;
          <?php endif; ?>
          <a class="ml-muted" href="<?php echo esc_url( $ml_privacy ); ?>" target="_blank" style="color:<?php echo esc_attr( $ml_l['muted'] ); ?>;text-decoration:underline;"><?php esc_html_e( 'Privacidad', 'motorlan-api-vue' ); ?></a>
        </p>
        <p class="ml-muted" style="margin:0;padding:0;font-family:<?php echo $ml_font; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;font-size:12px;line-height:18px;color:<?php echo esc_attr( $ml_l['muted'] ); ?>;text-align:center;">
          <?php esc_html_e( 'Este es un mensaje automático, por favor no respondas a este correo.', 'motorlan-api-vue' ); ?>
        </p>
      </td>
    </tr>

  </table>
  <!--[if mso]></td></tr></table><![endif]-->
</td>
</tr>
</table>
</body>
</html>
