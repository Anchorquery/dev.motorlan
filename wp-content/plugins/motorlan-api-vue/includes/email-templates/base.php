<?php
/**
 * Base email layout. Receives variables from notification manager:
 *
 * @var string $subject
 * @var string $content   HTML del cuerpo del correo (ya renderizado).
 * @var string $logo_url  URL del logo del sitio (puede estar vacío).
 * @var string $site_name Nombre del sitio.
 * @var string $site_url  URL pública.
 * @var string $year
 */
if ( ! defined( 'WPINC' ) ) {
    die;
}
?><!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title><?php echo esc_html( $subject ); ?></title>
    <!--[if mso]>
    <style type="text/css">
        body, table, td, a { font-family: Arial, Helvetica, sans-serif !important; }
    </style>
    <![endif]-->
    <style>
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; background-color: #f4f6f8; }
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; display: block; }
        a { color: #005b96; text-decoration: none; }
        .btn:hover { opacity: 0.92; }
        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .px-32 { padding-left: 20px !important; padding-right: 20px !important; }
            .py-32 { padding-top: 24px !important; padding-bottom: 24px !important; }
            .h1 { font-size: 22px !important; line-height: 28px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f4f6f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
    <span style="display:none;visibility:hidden;opacity:0;height:0;width:0;font-size:1px;line-height:1px;color:#f4f6f8;overflow:hidden;mso-hide:all;">
        <?php echo esc_html( $subject ); ?>
    </span>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f6f8;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.06);">
                    <tr>
                        <td align="center" style="background:linear-gradient(135deg,#005b96 0%,#0073aa 100%);padding:28px 32px;">
                            <?php if ( ! empty( $logo_url ) ) : ?>
                                <a href="<?php echo esc_url( $site_url ); ?>" style="text-decoration:none;display:inline-block;">
                                    <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" width="160" style="max-width:160px;height:auto;display:block;margin:0 auto;">
                                </a>
                            <?php else : ?>
                                <a href="<?php echo esc_url( $site_url ); ?>" style="color:#ffffff;text-decoration:none;font-size:24px;font-weight:700;letter-spacing:0.5px;">
                                    <?php echo esc_html( $site_name ); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-32 py-32" style="padding:36px 40px;font-size:15px;line-height:1.6;color:#1f2937;">
                            <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:24px 32px;text-align:center;font-size:12px;line-height:1.5;color:#6b7280;">
                            <p style="margin:0 0 6px;">Este es un correo automático, por favor no respondas a este mensaje.</p>
                            <p style="margin:0;">&copy; <?php echo esc_html( $year ); ?> <a href="<?php echo esc_url( $site_url ); ?>" style="color:#6b7280;text-decoration:underline;"><?php echo esc_html( $site_name ); ?></a>. Todos los derechos reservados.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
