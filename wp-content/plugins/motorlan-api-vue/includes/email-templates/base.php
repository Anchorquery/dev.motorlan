<?php
// Base Email Template
// Brand Colors: Fagor Red #e42313, Dark #25293C, Gray #F8F7FA
$primary_color = '#e42313';
$bg_color = '#F4F5F7';
$card_bg = '#FFFFFF';
$text_color = '#2F2B3D';
$text_muted = '#808390';
?>
<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{subject}}</title>
    <!--[if mso]>
    <style type="text/css">
        body, table, td, a { font-family: Arial, sans-serif !important; }
    </style>
    <![endif]-->
    <style>
        /* General Resets */
        body { margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; width: 100% !important; background-color: <?php echo $bg_color; ?>; }
        img { border: 0; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        
        /* Typography */
        body, table, td, p, a { font-family: 'Segoe UI', 'Helvetica Neue', Helvetica, Arial, sans-serif; color: <?php echo $text_color; ?>; }
        
        /* Layout */
        .wrapper { width: 100%; table-layout: fixed; background-color: <?php echo $bg_color; ?>; padding: 40px 0; }
        .container { width: 100%; max-width: 600px; margin: 0 auto; background-color: <?php echo $card_bg; ?>; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        
        .header { background-color: #ffffff; padding: 30px 20px; text-align: center; border-bottom: 3px solid <?php echo $primary_color; ?>; }
        .content { padding: 40px 30px; font-size: 16px; line-height: 1.6; color: <?php echo $text_color; ?>; }
        
        .footer { padding: 30px 20px; text-align: center; font-size: 13px; color: <?php echo $text_muted; ?>; background-color: <?php echo $bg_color; ?>; }
        .footer a { color: <?php echo $text_muted; ?>; text-decoration: underline; }
        
        /* Components */
        .button { 
            background-color: <?php echo $primary_color; ?>; 
            color: #ffffff !important; 
            padding: 14px 30px; 
            text-decoration: none; 
            border-radius: 6px; 
            font-weight: 600; 
            display: inline-block; 
            margin: 20px 0; 
            text-align: center;
            font-size: 16px;
            mso-padding-alt: 0;
            cursor: pointer;
        }
        /* Button hover fallback */
        .button:hover { background-color: #c91f11; }

        h1 { font-size: 24px; font-weight: 700; margin-top: 0; margin-bottom: 20px; color: #1a1a1a; }
        p { margin-bottom: 15px; }
        a { color: <?php echo $primary_color; ?>; text-decoration: none; font-weight: 500; }
        
        .product-card { background-color: #f9f9f9; border: 1px solid #eeeeee; border-radius: 8px; padding: 15px; margin: 20px 0; display: block; }
        .product-img { width: 80px; height: 80px; object-fit: cover; border-radius: 4px; display: inline-block; vertical-align: middle; background-color: #ddd; }
        .product-info { display: inline-block; vertical-align: middle; margin-left: 15px; width: calc(100% - 110px); }
        .product-title { font-weight: 600; font-size: 15px; margin: 0 0 5px 0; color: #333; }
        .product-price { color: <?php echo $primary_color; ?>; font-weight: 700; margin: 0; }

        .info-box { background-color: #eef2f5; border-left: 4px solid #6c757d; padding: 15px; margin: 20px 0; font-size: 14px; color: #555; }

        /* Dark Mode Support */
        @media (prefers-color-scheme: dark) {
            body, .wrapper { background-color: #1A1A1A !important; }
            .container { background-color: #2D2D2D !important; box-shadow: none !important; }
            .header { background-color: #252525 !important; border-bottom-color: <?php echo $primary_color; ?> !important; }
            .content { color: #E0E0E0 !important; }
            h1 { color: #FFFFFF !important; }
            p, span, div { color: #E0E0E0 !important; }
            .product-card { background-color: #333333 !important; border-color: #444444 !important; }
            .product-title { color: #FFFFFF !important; }
            .info-box { background-color: #333333 !important; border-left-color: #999999 !important; color: #CCCCCC !important; }
            .footer { background-color: #1A1A1A !important; color: #888888 !important; }
            .button { background-color: <?php echo $primary_color; ?> !important; color: #FFFFFF !important; }
        }
        
        /* Mobile */
        @media only screen and (max-width: 600px) {
            .container { width: 100% !important; border-radius: 0 !important; }
            .content { padding: 25px 20px !important; }
            .wrapper { padding: 0 !important; }
            .product-img { width: 60px; height: 60px; }
            .product-info { width: calc(100% - 80px); }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <center>
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td align="center">
                        <table class="container" border="0" cellpadding="0" cellspacing="0">
                            <!-- Header -->
                            <tr>
                                <td class="header">
                                    <a href="https://www.motorlan.es" target="_blank">
                                        <img src="https://www.motorlan.es/wp-content/uploads/2025/11/logo-motorlan-trans-1.png" alt="Motorlan" width="180" style="display: block; border: 0; margin: 0 auto; max-width: 100%;">
                                    </a>
                                </td>
                            </tr>
                            
                            <!-- Main Content -->
                            <tr>
                                <td class="content">
                                    {{content}}
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td class="footer">
                                    <p>&copy; <?php echo date('Y'); ?> <strong>Motorlan</strong></p>
                                    <p>
                                        <a href="https://www.motorlan.es/mi-cuenta/dashboard/notifications">Preferencias</a> • 
                                        <a href="https://www.motorlan.es/politica-de-privacidad/">Privacidad</a>
                                    </p>
                                    <p style="margin-top:20px; font-size: 11px; opacity: 0.7;">
                                        Este es un mensaje automático, por favor no respondas a este correo.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </center>
    </div>
</body>
</html>