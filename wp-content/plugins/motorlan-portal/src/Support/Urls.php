<?php

namespace MotorlanPortal\Support;

use MotorlanPortal\Routing\Rewrites;
use MotorlanPortal\Sections;

/**
 * URLs del portal y de la SPA. Las vistas nunca escriben rutas a mano: home_url() pasa
 * por WPML y añade el prefijo de idioma.
 */
class Urls
{
    /** /ml-portal/{section}/{path}: ml_url('ui-kit') */
    public static function portal(string $section, string $path = ''): string
    {
        $path = trim($path, '/');
        return home_url('/' . Rewrites::BASE . '/' . trim($section, '/') . '/' . ($path !== '' ? $path . '/' : ''));
    }

    /**
     * Ruta de la SPA (history mode) bajo su página: spa('dashboard/purchases/purchases')
     * -> /mi-cuenta/dashboard/purchases/purchases. $page = slug base de la página Vue.
     */
    public static function spa(string $path = '', string $page = 'mi-cuenta'): string
    {
        // Permalink de la página traducida (ya con el prefijo de idioma). Antes se pasaba
        // la ruta de motorlan_get_vue_page_urls_current_lang() por home_url(), que añade el
        // prefijo otra vez (/en/en/my-account/…).
        return SpaPages::page_url($page, $path);
    }

    /**
     * Enlace a una pantalla que puede estar migrada o no: si la sección está encendida va
     * al portal, si no a la SPA. Así el menú no cambia al ir encendiendo secciones.
     */
    public static function screen(string $section, string $portal_path, string $spa_path, string $spa_page = 'mi-cuenta'): string
    {
        return Sections::is_enabled($section) ? self::portal($section, $portal_path) : self::spa($spa_path, $spa_page);
    }

    /**
     * Acceso que vuelve a $back. Por defecto wp-login.php (misma cookie de WordPress); el
     * login de la SPA (/mi-cuenta/login?to=) solo sabe volver a rutas de la SPA. Cuando
     * se migre el acceso, el filtro apunta a la pantalla nueva.
     */
    public static function login(string $back = ''): string
    {
        $url = wp_login_url($back);
        return (string) apply_filters('motorlan_portal_login_url', $url, $back);
    }

    public static function logout(string $back = ''): string
    {
        return wp_logout_url($back !== '' ? $back : home_url('/'));
    }

    /** URL absoluta de la petición actual. */
    public static function current(): string
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/';
        return set_url_scheme('http://' . wp_parse_url(home_url(), PHP_URL_HOST) . $uri);
    }

    /**
     * Ruta de la petición sin la carpeta de WordPress ni el prefijo de idioma de WPML:
     * /en/store/abc/?x=1 -> /store/abc/. Es lo que comparan los spa_paths de las secciones.
     */
    public static function request_path(): string
    {
        $path = (string) wp_parse_url(isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '/', PHP_URL_PATH);
        $home = rtrim((string) wp_parse_url(home_url('/'), PHP_URL_PATH), '/');
        if ($home !== '' && strpos($path, $home . '/') === 0) {
            $path = substr($path, strlen($home));
        }
        $langs = apply_filters('wpml_active_languages', null, []);
        if (is_array($langs) && $langs && preg_match('#^/([a-z]{2}(?:-[a-z]{2,4})?)(/.*)?$#i', $path, $m) && isset($langs[$m[1]])) {
            $path = isset($m[2]) && $m[2] !== '' ? $m[2] : '/';
        }
        return '/' . ltrim($path, '/');
    }
}
