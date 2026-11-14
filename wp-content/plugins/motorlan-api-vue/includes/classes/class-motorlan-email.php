<?php
/**
 * Componentes y utilidades para los emails de Motorlan.
 *
 * Los clientes de correo (Outlook de escritorio, Gmail, Apple Mail, móviles…) descartan o
 * reescriben el <style> de cabecera, así que cada componente se devuelve con estilos
 * INLINE en modo claro y con clases (ml-*) que la plantilla base redefine en modo oscuro
 * (base.php). Los colores salen de self::palette() y su contraste se comprueba en los
 * tests (WCAG AA, 4,5:1).
 *
 * Todo lo que reciben los componentes como «texto» se escapa aquí; los parámetros *_html
 * se entienden ya seguros (los construye la plantilla con esc_html()).
 *
 * @package motorlan-api-vue
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class Motorlan_Email {

    const PRIMARY  = '#DA291C';
    const FONT     = "'Inter','Segoe UI',-apple-system,BlinkMacSystemFont,Roboto,'Helvetica Neue',Arial,sans-serif";
    const LOGO_URL = 'https://www.motorlan.es/wp-content/uploads/2025/11/logo-motorlan-trans-1.png';

    // ------------------------------------------------------------------
    // Paleta
    // ------------------------------------------------------------------

    /**
     * Colores por modo. Los pares (texto, fondo) usados en el correo cumplen AA en ambos.
     *
     * @return array{light:array<string,string>,dark:array<string,string>}
     */
    public static function palette() {
        return array(
            'light' => array(
                'page'    => '#F8F7FA',
                'card'    => '#FFFFFF',
                'soft'    => '#F3F2F7',
                'text'    => '#2F2B3D',
                'muted'   => '#6B6880',
                'border'  => '#E6E4EC',
                'link'    => '#C41E12',
                'red_bg'  => '#FDEAE8',
                'red'     => '#A3150B',
                'green_bg' => '#E6F5EC',
                'green'   => '#166534',
                'amber_bg' => '#FEF3E2',
                'amber'   => '#92400E',
                'info_bg' => '#EEF0F7',
                'info'    => '#3D4560',
            ),
            'dark'  => array(
                'page'    => '#16151C',
                'card'    => '#211F29',
                'soft'    => '#2A2833',
                'text'    => '#F1F0F5',
                'muted'   => '#B7B4C4',
                'border'  => '#38354A',
                'link'    => '#FF8F85',
                'red_bg'  => '#4A1E1B',
                'red'     => '#FFB4AC',
                'green_bg' => '#173B27',
                'green'   => '#86EFAC',
                'amber_bg' => '#45300F',
                'amber'   => '#FCD34D',
                'info_bg' => '#2B2F40',
                'info'    => '#C7CCE0',
            ),
        );
    }

    /** Tonos de aviso/estado → prefijo de la paleta. */
    private static function tone_key( $tone ) {
        $map = array(
            'error'   => 'red',
            'red'     => 'red',
            'danger'  => 'red',
            'success' => 'green',
            'green'   => 'green',
            'warning' => 'amber',
            'amber'   => 'amber',
            'info'    => 'info',
            'neutral' => 'info',
        );
        return isset( $map[ $tone ] ) ? $map[ $tone ] : 'info';
    }

    // ------------------------------------------------------------------
    // Idioma / dirección del texto
    // ------------------------------------------------------------------

    public static function is_rtl() {
        return function_exists( 'is_rtl' ) && is_rtl();
    }

    /** Lado de inicio de línea para text-align y bordes de acento. */
    public static function start() {
        return self::is_rtl() ? 'right' : 'left';
    }

    public static function lang() {
        $locale = function_exists( 'determine_locale' ) ? determine_locale() : ( function_exists( 'get_locale' ) ? get_locale() : 'es_ES' );
        return str_replace( '_', '-', (string) $locale );
    }

    // ------------------------------------------------------------------
    // Datos de la petición
    // ------------------------------------------------------------------

    /** $args['data'] siempre como array. */
    public static function data( $args ) {
        return ( is_array( $args ) && isset( $args['data'] ) && is_array( $args['data'] ) ) ? $args['data'] : array();
    }

    /** Valor de texto de $data (nunca array/objeto). */
    public static function text( $data, $key, $default = '' ) {
        if ( ! is_array( $data ) || ! isset( $data[ $key ] ) || is_array( $data[ $key ] ) || is_object( $data[ $key ] ) ) {
            return $default;
        }
        $v = trim( (string) $data[ $key ] );
        return '' === $v ? $default : $v;
    }

    // ------------------------------------------------------------------
    // Nombres
    // ------------------------------------------------------------------

    /**
     * Nombre «humano» de un usuario: nombre y apellidos, nombre, empresa, o un
     * display_name que no sea un email. Cadena vacía si no hay ninguno: NUNCA devuelve
     * el email ni el login (en muchas cuentas display_name es el email).
     *
     * @param WP_User|object|int|null $user
     * @return string
     */
    public static function user_name( $user ) {
        if ( is_numeric( $user ) && function_exists( 'get_userdata' ) ) {
            $user = get_userdata( (int) $user );
        }
        if ( ! is_object( $user ) ) {
            return '';
        }

        $first   = isset( $user->first_name ) ? trim( (string) $user->first_name ) : '';
        $last    = isset( $user->last_name ) ? trim( (string) $user->last_name ) : '';
        $company = '';
        if ( ! empty( $user->ID ) && function_exists( 'get_user_meta' ) ) {
            $company = trim( (string) get_user_meta( (int) $user->ID, 'company_nombre', true ) );
        }
        $display = isset( $user->display_name ) ? trim( (string) $user->display_name ) : '';
        $login   = isset( $user->user_login ) ? trim( (string) $user->user_login ) : '';

        $candidates = array(
            trim( $first . ' ' . $last ),
            $first,
            $company,
            ( '' !== $display && $display !== $login ) ? $display : '',
        );
        foreach ( $candidates as $name ) {
            $name = self::clean_name( $name );
            if ( '' !== $name ) {
                return $name;
            }
        }
        return '';
    }

    /** Un nombre sin etiquetas, sin saltos de línea y que no parezca un email. */
    public static function clean_name( $name ) {
        $name = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( (string) $name ) : strip_tags( (string) $name );
        $name = trim( preg_replace( '/\s+/u', ' ', $name ) );
        if ( '' === $name || false !== strpos( $name, '@' ) ) {
            return '';
        }
        if ( function_exists( 'mb_substr' ) && mb_strlen( $name ) > 80 ) {
            $name = mb_substr( $name, 0, 80 );
        }
        return $name;
    }

    /**
     * Nombre de un tercero para mostrar a la otra parte (comprador, remitente…): el del
     * usuario si lo hay o la etiqueta genérica traducida.
     *
     * @param WP_User|int|null $user
     * @param string           $fallback Ya traducido.
     */
    public static function party_name( $user, $fallback ) {
        $name = self::user_name( $user );
        return '' !== $name ? $name : $fallback;
    }

    /** Nombre de remitente para mostrar a la otra parte: sin emails y con etiqueta genérica. */
    public static function sender_label( $name ) {
        $name = self::clean_name( $name );
        return '' !== $name ? $name : __( 'Un usuario', 'motorlan-api-vue' );
    }

    /** «Hola Nombre,» o «Hola,» si no hay nombre real. */
    public static function greeting( $user ) {
        $name = self::user_name( $user );
        if ( '' === $name ) {
            return self::p( esc_html__( 'Hola,', 'motorlan-api-vue' ) );
        }
        /* translators: %s: nombre de la persona */
        return self::p( sprintf( esc_html__( 'Hola %s,', 'motorlan-api-vue' ), esc_html( $name ) ) );
    }

    // ------------------------------------------------------------------
    // URLs
    // ------------------------------------------------------------------

    /** esquema + host (+ puerto) de home_url(), sin ruta ni prefijo de idioma. */
    public static function origin() {
        $parts = wp_parse_url( home_url( '/' ) );
        $out   = ( isset( $parts['scheme'] ) ? $parts['scheme'] : 'https' ) . '://' . ( isset( $parts['host'] ) ? $parts['host'] : '' );
        if ( ! empty( $parts['port'] ) ) {
            $out .= ':' . $parts['port'];
        }
        return $out;
    }

    /**
     * URL absoluta para un enlace de email.
     *
     * Las rutas que genera el plugin (motorlan_account_url(), motorlan_localized_page_url())
     * YA llevan carpeta de WordPress y prefijo de idioma de WPML: se les antepone solo
     * esquema+host. Con get_site_url()/home_url() delante, WPML volvía a añadir el prefijo.
     *
     * @param string $url  Ruta (/en/mi-cuenta/…), URL absoluta o vacío.
     * @param string $context Identifica el enlace para el filtro (p. ej. 'account', 'product').
     * @return string
     */
    public static function abs_url( $url, $context = '' ) {
        $original = (string) $url;
        $url      = trim( $original );

        if ( '' === $url ) {
            $out = home_url( '/' );
        } elseif ( preg_match( '#^https?://#i', $url ) ) {
            $out = $url;
        } elseif ( 0 === strpos( $url, '//' ) ) {
            $out = 'https:' . $url;
        } elseif ( '/' === $url[0] ) {
            $out = self::origin() . $url;
        } else {
            $out = home_url( '/' . $url );
        }

        // El portal (motorlan-portal) puede remapear los enlaces cuando una pantalla migre.
        return (string) apply_filters( 'motorlan_email_url', $out, $original, $context );
    }

    /** URL absoluta a una pantalla de la cuenta del usuario (idioma del destinatario). */
    public static function account_url( $sub = '', $user = null ) {
        $user_id = is_object( $user ) && ! empty( $user->ID ) ? (int) $user->ID : null;
        if ( function_exists( 'motorlan_account_url' ) ) {
            return self::abs_url( motorlan_account_url( $sub, $user_id ), 'account' );
        }
        return self::abs_url( '/mi-cuenta/' . ltrim( $sub, '/' ), 'account' );
    }

    /**
     * Enlace principal de un email: $data['url'] absoluto o, si falta, una pantalla de la
     * cuenta (nunca un enlace roto).
     */
    public static function link( $data, $args, $fallback_sub = 'dashboard' ) {
        $url  = self::text( $data, 'url', '' );
        $user = ( is_array( $args ) && isset( $args['user'] ) ) ? $args['user'] : null;
        if ( '' === $url ) {
            return self::account_url( $fallback_sub, $user );
        }
        return self::abs_url( $url, 'link' );
    }

    /**
     * Importe: «1.250 €» / «1.250,50 €» con espacio duro; vacío si no hay valor.
     * Con $symbol = false devuelve solo el número (para textos que ya llevan «€»).
     */
    public static function price( $value, $symbol = true ) {
        if ( null === $value || '' === $value || is_array( $value ) || is_object( $value ) ) {
            return '';
        }
        if ( is_numeric( $value ) ) {
            $f   = (float) $value;
            $dec = ( floor( $f ) == $f ) ? 0 : 2;
            $s   = function_exists( 'number_format_i18n' ) ? number_format_i18n( $f, $dec ) : number_format( $f, $dec, ',', '.' );
            return $symbol ? $s . "\xC2\xA0€" : $s;
        }
        $s = trim( preg_replace( '/\s*€\s*$/u', '', wp_strip_all_tags( (string) $value ) ) );
        if ( '' === $s ) {
            return '';
        }
        return $symbol ? $s . "\xC2\xA0€" : $s;
    }

    // ------------------------------------------------------------------
    // Asunto y cabeceras
    // ------------------------------------------------------------------

    /** Asunto: sin etiquetas ni saltos de línea y con el prefijo «[Motorlan] » una vez. */
    public static function subject( $title ) {
        $t = wp_strip_all_tags( (string) $title );
        $t = trim( preg_replace( '/[\r\n\t]+|\s{2,}/u', ' ', $t ) );
        if ( '' === $t ) {
            $t = __( 'Notificación de Motorlan', 'motorlan-api-vue' );
        }
        if ( function_exists( 'mb_strlen' ) && mb_strlen( $t ) > 150 ) {
            $t = mb_substr( $t, 0, 147 ) . '...';
        }
        return 0 === strpos( $t, '[Motorlan]' ) ? $t : '[Motorlan] ' . $t;
    }

    /** @return string[] */
    public static function headers( $automatic = true ) {
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        if ( $automatic ) {
            $headers[] = 'Auto-Submitted: auto-generated';
            $headers[] = 'X-Auto-Response-Suppress: OOF, AutoReply';
        }
        return $headers;
    }

    /** Texto plano alternativo (AltBody) a partir del HTML final. */
    public static function html_to_text( $html ) {
        $html = (string) $html;
        $html = preg_replace( '#<(head|style|script)\b[^>]*>.*?</\1>#is', '', $html );
        $html = preg_replace( '#<div[^>]*display:\s*none[^>]*>.*?</div>#is', '', $html );
        $html = preg_replace( '#<!--.*?-->#s', '', $html );
        $html = preg_replace_callback(
            '#<a\b[^>]*href=("|\')(.*?)\1[^>]*>(.*?)</a>#is',
            function ( $m ) {
                $label = trim( strip_tags( $m[3] ) );
                $href  = html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' );
                if ( '' === $label || $label === $href ) {
                    return $href;
                }
                return $label . ' (' . $href . ')';
            },
            $html
        );
        $html = preg_replace( '#<br\s*/?>|</(p|h[1-6]|tr|table|li)>#i', "\n", $html );
        $text = html_entity_decode( strip_tags( $html ), ENT_QUOTES, 'UTF-8' );
        $text = str_replace( "\xC2\xA0", ' ', $text );
        $text = preg_replace( "/[ \t]+/", ' ', $text );
        $text = preg_replace( "/ *\n */", "\n", $text );
        $text = preg_replace( "/\n{3,}/", "\n\n", $text );
        return trim( $text );
    }

    // ------------------------------------------------------------------
    // Componentes
    // ------------------------------------------------------------------

    private static function font() {
        return 'font-family:' . self::FONT . ';';
    }

    public static function h1( $text ) {
        $c = self::palette();
        return '<h1 class="ml-text" style="margin:0 0 18px;padding:0;' . self::font() . 'font-size:24px;line-height:30px;font-weight:700;color:' . $c['light']['text'] . ';text-align:' . self::start() . ';">'
            . esc_html( $text ) . '</h1>';
    }

    public static function h2( $text ) {
        $c = self::palette();
        return '<h2 class="ml-text" style="margin:0 0 12px;padding:0;' . self::font() . 'font-size:20px;line-height:26px;font-weight:700;color:' . $c['light']['text'] . ';text-align:center;">'
            . esc_html( $text ) . '</h2>';
    }

    /** Panel centrado con fondo suave ($inner_html ya seguro). */
    public static function panel( $inner_html ) {
        $c = self::palette();
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:28px 0 0;"><tr>'
            . '<td class="ml-soft ml-border" align="center" bgcolor="' . $c['light']['soft'] . '" style="background-color:' . $c['light']['soft'] . ';border:1px solid ' . $c['light']['border'] . ';border-radius:12px;padding:26px 22px;text-align:center;">'
            . $inner_html
            . '</td></tr></table>';
    }

    /** Párrafo; $html ya es seguro (la plantilla escapa lo variable). */
    public static function p( $html, $muted = false ) {
        $c     = self::palette();
        $class = $muted ? 'ml-muted' : 'ml-text';
        $color = $muted ? $c['light']['muted'] : $c['light']['text'];
        $size  = $muted ? '14px' : '16px';
        $lh    = $muted ? '21px' : '24px';
        return '<p class="' . $class . '" style="margin:0 0 16px;padding:0;' . self::font() . 'font-size:' . $size . ';line-height:' . $lh . ';color:' . $color . ';text-align:' . self::start() . ';">'
            . $html . '</p>';
    }

    /** Texto resaltado dentro de un párrafo. */
    public static function strong( $text ) {
        return '<strong>' . esc_html( $text ) . '</strong>';
    }

    /** Importe destacado dentro de una tarjeta. */
    public static function amount( $text ) {
        $c = self::palette();
        return '<span class="ml-link" style="font-weight:700;color:' . $c['light']['link'] . ';">' . esc_html( $text ) . '</span>';
    }

    /**
     * Cita / mensaje de un tercero (texto plano con saltos de línea) con barra de acento.
     */
    public static function quote( $text, $tone = 'neutral' ) {
        $c    = self::palette();
        $k    = self::tone_key( $tone );
        $side = self::start();
        $bg   = 'info' === $k ? $c['light']['soft'] : $c['light'][ $k . '_bg' ];
        $fg   = 'info' === $k ? $c['light']['text'] : $c['light'][ $k ];
        $cls  = 'info' === $k ? 'ml-soft ml-text' : 'ml-st-' . $k;
        $bar  = 'info' === $k ? self::PRIMARY : $fg;
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px;"><tr>'
            . '<td class="' . $cls . ' ml-bar-' . $k . '" bgcolor="' . $bg . '" style="background-color:' . $bg . ';border-' . $side . ':4px solid ' . $bar . ';padding:14px 18px;' . self::font() . 'font-size:15px;line-height:23px;color:' . $fg . ';text-align:' . $side . ';">'
            . nl2br( esc_html( $text ) )
            . '</td></tr></table>';
    }

    /** Aviso con texto ya seguro ($html). */
    public static function notice( $html, $tone = 'info' ) {
        $c    = self::palette();
        $k    = self::tone_key( $tone );
        $side = self::start();
        $bg   = $c['light'][ $k . '_bg' ];
        $fg   = $c['light'][ $k ];
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px;"><tr>'
            . '<td class="ml-st-' . $k . '" bgcolor="' . $bg . '" style="background-color:' . $bg . ';border-' . $side . ':4px solid ' . $fg . ';padding:14px 18px;' . self::font() . 'font-size:14px;line-height:21px;color:' . $fg . ';text-align:' . $side . ';">'
            . $html
            . '</td></tr></table>';
    }

    /** Etiqueta de estado (fondo + texto propios: legible en claro, oscuro e inversión). */
    public static function chip( $text, $tone = 'info' ) {
        $c  = self::palette();
        $k  = self::tone_key( $tone );
        $bg = $c['light'][ $k . '_bg' ];
        $fg = $c['light'][ $k ];
        return '<span class="ml-st-' . $k . '" style="display:inline-block;padding:3px 10px;border-radius:999px;background-color:' . $bg . ';color:' . $fg . ';' . self::font() . 'font-size:13px;line-height:18px;font-weight:600;">'
            . esc_html( $text ) . '</span>';
    }

    /**
     * Tarjeta de producto con tablas (sin div/calc: funciona en Outlook).
     *
     * @param string   $title  Título del producto (texto).
     * @param string   $image  URL de la imagen (opcional).
     * @param string[] $lines_html Líneas bajo el título (HTML seguro).
     * @param string   $chip_html  Etiqueta de estado (de self::chip()).
     */
    public static function product_card( $title, $image = '', $lines_html = array(), $chip_html = '' ) {
        $c    = self::palette();
        $side = self::start();
        $img  = '';
        if ( '' !== trim( (string) $image ) ) {
            $src = esc_url( $image );
            if ( '' !== $src ) {
                $img = '<td class="ml-card-img" width="88" valign="top" style="width:88px;padding:0 16px 0 0;">'
                    . '<img src="' . $src . '" width="72" height="72" alt="' . esc_attr( $title ) . '" style="display:block;width:72px;height:72px;border:0;border-radius:6px;background-color:' . $c['light']['border'] . ';object-fit:cover;">'
                    . '</td>';
            }
        }
        $inner = '';
        if ( '' !== trim( (string) $title ) ) {
            $inner .= '<div class="ml-text" style="margin:0 0 4px;' . self::font() . 'font-size:16px;line-height:22px;font-weight:700;color:' . $c['light']['text'] . ';text-align:' . $side . ';">' . esc_html( $title ) . '</div>';
        }
        foreach ( $lines_html as $line ) {
            if ( '' === trim( (string) $line ) ) {
                continue;
            }
            $inner .= '<div class="ml-muted" style="margin:0 0 4px;' . self::font() . 'font-size:14px;line-height:21px;color:' . $c['light']['muted'] . ';text-align:' . $side . ';">' . $line . '</div>';
        }
        if ( '' !== $chip_html ) {
            $inner .= '<div style="margin:6px 0 0;text-align:' . $side . ';">' . $chip_html . '</div>';
        }
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:4px 0 22px;"><tr>'
            . '<td class="ml-soft ml-border" bgcolor="' . $c['light']['soft'] . '" style="background-color:' . $c['light']['soft'] . ';border:1px solid ' . $c['light']['border'] . ';border-radius:10px;padding:14px;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"><tr>'
            . $img
            . '<td valign="top" style="text-align:' . $side . ';">' . $inner . '</td>'
            . '</tr></table>'
            . '</td></tr></table>';
    }

    /** Tabla de pares etiqueta/valor (HTML ya escapado en $rows[label] => valor texto). */
    public static function key_values( $rows ) {
        $c    = self::palette();
        $side = self::start();
        $out  = '';
        foreach ( $rows as $label => $value ) {
            if ( '' === trim( (string) $value ) ) {
                continue;
            }
            $out .= '<tr>'
                . '<td class="ml-muted ml-border" style="padding:7px 12px 7px 0;border-bottom:1px solid ' . $c['light']['border'] . ';' . self::font() . 'font-size:14px;line-height:20px;color:' . $c['light']['muted'] . ';text-align:' . $side . ';">' . esc_html( $label ) . '</td>'
                . '<td class="ml-text ml-border" style="padding:7px 0;border-bottom:1px solid ' . $c['light']['border'] . ';' . self::font() . 'font-size:14px;line-height:20px;font-weight:600;color:' . $c['light']['text'] . ';text-align:' . ( self::is_rtl() ? 'left' : 'right' ) . ';">' . esc_html( $value ) . '</td>'
                . '</tr>';
        }
        if ( '' === $out ) {
            return '';
        }
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 20px;">' . $out . '</table>';
    }

    /**
     * Botón a prueba de clientes: celda con bgcolor + estilo inline y texto con color
     * inline (no depende del <style>); VML solo para Outlook de escritorio.
     */
    public static function button( $label, $url ) {
        $href = esc_url( $url );
        if ( '' === $href ) {
            return '';
        }
        $label_html = esc_html( $label );
        $p          = self::PRIMARY;
        $font       = self::font();
        $width      = max( 220, min( 440, 64 + 9 * ( function_exists( 'mb_strlen' ) ? mb_strlen( $label ) : strlen( $label ) ) ) );

        $html  = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" class="ml-btn-table" style="margin:8px auto 24px;"><tr><td align="center">';
        $html .= '<!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="' . $href . '" style="height:48px;v-text-anchor:middle;width:' . $width . 'px;" arcsize="17%" strokecolor="' . $p . '" fillcolor="' . $p . '"><w:anchorlock/><center style="color:#ffffff;' . $font . 'font-size:16px;font-weight:bold;">' . $label_html . '</center></v:roundrect><![endif]-->';
        $html .= '<!--[if !mso]><!-->';
        $html .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" class="ml-btn-inner"><tr>'
            . '<td class="ml-btn-td" align="center" bgcolor="' . $p . '" style="border-radius:8px;background-color:' . $p . ';mso-padding-alt:14px 28px;">'
            . '<a class="ml-btn-a" href="' . $href . '" target="_blank" style="display:inline-block;padding:14px 28px;' . $font . 'font-size:16px;line-height:20px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:8px;background-color:' . $p . ';">'
            . '<span style="color:#ffffff;text-decoration:none;">' . $label_html . '</span></a>'
            . '</td></tr></table>';
        $html .= '<!--<![endif]-->';
        $html .= '</td></tr></table>';
        return $html;
    }

    /** «Si el botón no funciona, copia y pega este enlace» + URL. */
    public static function link_fallback( $url, $intro_text ) {
        $href = esc_url( $url );
        if ( '' === $href ) {
            return '';
        }
        $c = self::palette();
        return self::p( esc_html( $intro_text ), true )
            . '<p class="ml-muted" style="margin:0 0 16px;padding:0;' . self::font() . 'font-size:13px;line-height:19px;color:' . $c['light']['muted'] . ';word-break:break-all;text-align:' . self::start() . ';">'
            . '<a class="ml-link" href="' . $href . '" target="_blank" style="color:' . $c['light']['link'] . ';text-decoration:underline;">' . esc_html( $url ) . '</a></p>';
    }

    /** Código de verificación grande. */
    public static function code_box( $code ) {
        $c = self::palette();
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 22px;"><tr>'
            . '<td class="ml-soft ml-border ml-text" align="center" bgcolor="' . $c['light']['soft'] . '" style="background-color:' . $c['light']['soft'] . ';border:1px solid ' . $c['light']['border'] . ';border-radius:10px;padding:22px 12px;font-family:\'Courier New\',Courier,monospace;font-size:34px;line-height:40px;font-weight:700;letter-spacing:8px;color:' . $c['light']['text'] . ';">'
            . esc_html( $code )
            . '</td></tr></table>';
    }

    /** Lista de viñetas (textos planos). */
    public static function bullets( $items ) {
        $c    = self::palette();
        $side = self::start();
        $lis  = '';
        foreach ( $items as $item ) {
            $lis .= '<li class="ml-text" style="margin:0 0 6px;color:' . $c['light']['text'] . ';">' . esc_html( $item ) . '</li>';
        }
        $pad = self::is_rtl() ? 'padding:0 22px 0 0;' : 'padding:0 0 0 22px;';
        return '<ul style="margin:0 0 18px;' . $pad . self::font() . 'font-size:16px;line-height:24px;text-align:' . $side . ';">' . $lis . '</ul>';
    }

    // ------------------------------------------------------------------
    // Layout
    // ------------------------------------------------------------------

    /** Texto del preheader (resumen que ve la bandeja de entrada), ≤ 110 caracteres. */
    public static function preheader_text( $args ) {
        $pre = '';
        if ( is_array( $args ) ) {
            if ( ! empty( $args['preheader'] ) ) {
                $pre = (string) $args['preheader'];
            } elseif ( ! empty( $args['message'] ) ) {
                $pre = (string) $args['message'];
            } elseif ( ! empty( $args['title'] ) ) {
                $pre = (string) $args['title'];
            }
        }
        $pre = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $pre ) ) );
        if ( function_exists( 'mb_strlen' ) && mb_strlen( $pre ) > 110 ) {
            $pre = mb_substr( $pre, 0, 107 ) . '...';
        }
        return $pre;
    }

    /** URL del logo (filtrable: p. ej. una variante con fondo blanco opaco). */
    public static function logo_url() {
        return (string) apply_filters( 'motorlan_email_logo_url', self::LOGO_URL );
    }

    /**
     * Envuelve $content con la plantilla base (base.php).
     *
     * @param string $content HTML ya seguro del cuerpo.
     * @param array  $args    title, message, preheader, user…
     * @return string
     */
    public static function render_layout( $content, $args = array() ) {
        $base = ( defined( 'MOTORLAN_API_VUE_PATH' ) ? MOTORLAN_API_VUE_PATH : dirname( __DIR__, 2 ) . '/' ) . 'includes/email-templates/base.php';
        if ( ! file_exists( $base ) ) {
            return (string) $content;
        }
        $subject   = ( is_array( $args ) && ! empty( $args['title'] ) ) ? (string) $args['title'] : __( 'Notificación de Motorlan', 'motorlan-api-vue' );
        $preheader = self::preheader_text( $args );
        ob_start();
        include $base;
        return (string) ob_get_clean();
    }
}
