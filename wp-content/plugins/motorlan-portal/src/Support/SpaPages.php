<?php

namespace MotorlanPortal\Support;

/**
 * Páginas de WordPress donde vive la SPA (con el shortcode [motorlan_vue_app]) y las URLs
 * que el portal les quita al encender una sección.
 *
 *  - Tienda:  /marketplace-motorlan/            listado
 *             /marketplace-motorlan/{slug}/     ficha
 *  - Acceso:  /mi-cuenta/{login|register|…}/    (y bajo /marketplace-motorlan/, como la SPA)
 *
 * Con WPML la página tiene un slug por idioma (motorlan_get_marketplace_page_slugs() de
 * motorlan-api-vue); sin la SPA, solo el slug base. Las rutas de la SPA que cuelgan de la
 * tienda (login, dashboard…) no son fichas.
 */
class SpaPages
{
    const STORE = 'marketplace-motorlan';
    const ACCOUNT = 'mi-cuenta';

    /** Pantallas de acceso (nombre de ruta de la SPA = segmento de la URL). */
    const AUTH_SCREENS = ['login', 'register', 'register-success', 'forgot-password', 'reset-password', 'verify-email'];

    /** Rutas de primer nivel de la SPA bajo la página de la tienda: nunca son una ficha. */
    public static function spa_routes(): array
    {
        $routes = ['dashboard', 'login', 'register', 'register-success', 'forgot-password', 'reset-password',
            'verify-email', 'store', 'public-store', 'ai-search', 'assistant', 'access-control', 'not-authorized'];
        return (array) apply_filters('motorlan_store_spa_routes', $routes);
    }

    /** Slugs de la página de la tienda (base + traducciones). */
    public static function store_slugs(): array
    {
        $slugs = function_exists('motorlan_get_marketplace_page_slugs') ? motorlan_get_marketplace_page_slugs() : [self::STORE];
        return array_values(array_filter(array_map('strval', (array) $slugs)));
    }

    /** Slugs de la página de la cuenta (base + traducciones WPML). */
    public static function account_slugs(): array
    {
        $slugs = [self::ACCOUNT];
        $page = get_page_by_path(self::ACCOUNT);
        $langs = $page ? apply_filters('wpml_active_languages', null, []) : null;
        if (is_array($langs)) {
            foreach ($langs as $lang) {
                $code = is_array($lang) && isset($lang['code']) ? $lang['code'] : '';
                $tx = $code ? apply_filters('wpml_object_id', $page->ID, 'page', false, $code) : 0;
                if ($tx && (int) $tx !== (int) $page->ID) {
                    $name = get_post_field('post_name', (int) $tx);
                    if ($name) {
                        $slugs[] = (string) $name;
                    }
                }
            }
        }
        return array_values(array_unique($slugs));
    }

    /** spa_paths de la sección tienda: listado y ficha (no las rutas de la SPA). */
    public static function store_patterns(): array
    {
        $bases = implode('|', array_map(function ($s) { return preg_quote($s, '#'); }, self::store_slugs()));
        $skip = implode('|', array_map(function ($s) { return preg_quote($s, '#'); }, self::spa_routes()));
        return [
            '#^/(?:' . $bases . ')/?$#',
            '#^/(?:' . $bases . ')/(?!(?:' . $skip . ')(?:/|$))(?<param>[^/]+)/?$#',
        ];
    }

    /** spa_paths de la sección acceso. */
    public static function auth_patterns(): array
    {
        $bases = implode('|', array_map(function ($s) { return preg_quote($s, '#'); }, array_unique(array_merge(self::account_slugs(), self::store_slugs()))));
        return ['#^/(?:' . $bases . ')/(?<param>' . implode('|', self::AUTH_SCREENS) . ')/?$#'];
    }

    /** URL absoluta de la página de la tienda en el idioma actual (+ subruta). */
    public static function store_url(string $sub = ''): string
    {
        return self::page_url(self::STORE, $sub);
    }

    /** URL absoluta de la ficha de una publicación (por slug). */
    public static function product_url(string $slug): string
    {
        return self::store_url($slug !== '' ? $slug . '/' : '');
    }

    /**
     * spa_paths de una sección del panel: rutas bajo /{cuenta|tienda}/dashboard/ (la SPA
     * monta el panel en las dos páginas). $routes = rutas relativas a dashboard/ (regex
     * permitidas: 'user/(?:account|profile)'); el grupo `param` recibe la ruta casada.
     *   SpaPages::panel_patterns(['favorites/list']) → /mi-cuenta/dashboard/favorites/list/
     */
    public static function panel_patterns(array $routes): array
    {
        $bases = implode('|', array_map(function ($s) { return preg_quote($s, '#'); }, array_unique(array_merge(self::account_slugs(), self::store_slugs()))));
        return ['#^/(?:' . $bases . ')/dashboard/(?<param>' . implode('|', $routes) . ')/?$#'];
    }

    /**
     * Ruta del panel de la petición actual relativa a la página de la SPA
     * (/en/my-account/dashboard/user/account/ → dashboard/user/account), '' si no es del
     * panel. La usa Support\Nav para marcar el elemento activo.
     */
    public static function current_route(): string
    {
        $path = trim(Urls::request_path(), '/');
        foreach (array_unique(array_merge(self::account_slugs(), self::store_slugs())) as $base) {
            if ($path === $base || strpos($path, $base . '/') === 0) {
                return trim(substr($path, strlen($base)), '/');
            }
        }
        return '';
    }

    /**
     * URL absoluta de una página de la SPA en el idioma actual (+ subruta).
     *
     * Se parte del permalink de la página traducida, que ya lleva el prefijo de idioma de
     * WPML. Antes se pasaba su ruta por home_url(), que vuelve a añadir el prefijo: en
     * inglés salía /en/en/my-account/… (motorlan_localized_page_url_absolute tiene el mismo
     * fallo en motorlan-api-vue; no se usa aquí).
     */
    public static function page_url(string $page, string $sub = '', ?string $lang = null): string
    {
        $sub = ltrim($sub, '/');
        $base = self::page_base_url($page, $lang);
        return $base !== '' ? trailingslashit($base) . $sub : home_url('/' . $page . '/' . $sub);
    }

    /** Permalink de la página de la SPA ($slug en español) en el idioma pedido o el actual. */
    public static function page_base_url(string $slug, ?string $lang = null): string
    {
        static $cache = [];
        $lang = $lang ?: (string) apply_filters('wpml_current_language', null);
        $key = $slug . '|' . $lang;
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $page = get_page_by_path($slug);
        if (!$page) {
            return $cache[$key] = '';
        }
        $id = $lang !== '' ? (int) apply_filters('wpml_object_id', $page->ID, 'page', true, $lang) : (int) $page->ID;
        if ($lang !== '' && $lang !== (string) apply_filters('wpml_current_language', null)) {
            // El permalink de otro idioma: WPML lo convierte si se le pide en ese idioma.
            $url = (string) apply_filters('wpml_permalink', get_permalink($id ?: $page->ID), $lang, true);
        } else {
            $url = (string) get_permalink($id ?: $page->ID);
        }
        return $cache[$key] = $url;
    }
}
