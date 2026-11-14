<?php

namespace MotorlanPortal\Support;

/**
 * Menús del panel, copia de la SPA:
 *  - vertical(): navigation/vertical/apps-and-pages.ts (Ventas / Compras / Administración,
 *    esta solo con manage_options).
 *  - mobile():   layouts/components/DashboardMobileNav.vue (5 accesos, distintos para admin).
 *  - user_menu(): layouts/components/UserProfile.vue.
 *
 * Cada elemento apunta a la URL de la SPA (/mi-cuenta/dashboard/…). Una sección migrada
 * toma esa misma URL (spa_paths + Routing\Takeover), así que el enlace no cambia al
 * encenderla o apagarla: el servidor decide si la pinta el portal o la SPA.
 *
 * `match` = rutas (relativas a la página de la SPA) que marcan el elemento como activo,
 * por prefijo; son las de la SPA (DashboardMobileNav.vue → paths).
 *
 * Una sección que necesite un elemento nuevo lo añade desde su `boot` con los filtros
 * motorlan_portal_nav_vertical / motorlan_portal_nav_mobile / motorlan_portal_nav_user
 * (reciben la lista y Nav::item() construye el elemento), sin editar este archivo.
 */
class Nav
{
    /**
     * Selector de idioma de la barra (I18n.vue de la SPA): solo si WPML tiene más de un
     * idioma activo. Cada idioma enlaza a la misma pantalla traducida (página de la SPA en
     * ese idioma + la misma subruta). Sin WPML o con un idioma: [] y no se pinta.
     */
    public static function languages(): array
    {
        $langs = apply_filters('wpml_active_languages', null, ['skip_missing' => 0]);
        if (!is_array($langs) || count($langs) < 2) {
            return [];
        }
        $path = trim(Urls::request_path(), '/');
        $first = explode('/', $path)[0] ?? '';
        $page = in_array($first, SpaPages::store_slugs(), true) ? SpaPages::STORE : SpaPages::ACCOUNT;
        $route = SpaPages::current_route();
        $query = isset($_SERVER['QUERY_STRING']) ? (string) wp_unslash($_SERVER['QUERY_STRING']) : '';
        $out = [];
        foreach ($langs as $code => $lang) {
            if (!is_array($lang)) {
                continue;
            }
            $code = (string) ($lang['code'] ?? $lang['language_code'] ?? $code);
            $url = SpaPages::page_url($page, $route, $code);
            $out[] = [
                'code'   => $code,
                'label'  => (string) ($lang['native_name'] ?? $lang['translated_name'] ?? strtoupper($code)),
                'active' => !empty($lang['active']),
                'url'    => $query !== '' ? $url . (strpos($url, '?') === false ? '?' : '&') . $query : $url,
            ];
        }
        return $out;
    }

    public static function vertical(): array
    {
        $items = [
            ['heading' => __('Gestión de Ventas', 'motorlan-portal')],
            self::item(__('Mis Publicaciones', 'motorlan-portal'), 'list', 'dashboard/publications/publication/list', ['dashboard/publications/publication']),
            self::item(__('Ventas', 'motorlan-portal'), 'currency-euro', 'dashboard/publications/sales'),
            self::item(__('Ofertas Recibidas', 'motorlan-portal'), 'tag', 'dashboard/publications/offers-received'),
            self::item(__('Interesados', 'motorlan-portal'), 'users', 'dashboard/inquiries'),

            ['heading' => __('Gestión de Compras', 'motorlan-portal')],
            self::item(__('Mis Compras', 'motorlan-portal'), 'shopping-bag', 'dashboard/purchases/purchases'),
            self::item(__('Consultas Enviadas', 'motorlan-portal'), 'message-share', 'dashboard/purchases/inquiries'),
            self::item(__('Ofertas de Precio', 'motorlan-portal'), 'send', 'dashboard/purchases/offers-sent'),
            self::item(__('Favoritos', 'motorlan-portal'), 'heart', 'dashboard/favorites/list'),
        ];

        if (current_user_can('manage_options')) {
            $items[] = ['heading' => __('Administración', 'motorlan-portal')];
            $items[] = self::item(__('Publicaciones', 'motorlan-portal'), 'list', 'dashboard/admin/publications');
            $items[] = self::item(__('Aprobaciones', 'motorlan-portal'), 'check', 'dashboard/admin/approvals');
            if (\MotorlanPortal\Sections::is_enabled('ui-kit')) {
                $items[] = ['title' => __('Componentes (UI kit)', 'motorlan-portal'), 'icon' => 'components', 'url' => Urls::portal('ui-kit'), 'match' => []];
            }
        }

        return self::mark_active((array) apply_filters('motorlan_portal_nav_vertical', $items));
    }

    /** Barra inferior en móvil (< 960 px): 5 accesos, el central destacado. */
    public static function mobile(): array
    {
        $publish = self::item(__('Publicar', 'motorlan-portal'), 'circle-plus', 'dashboard/publications/publication/add') + ['primary' => true];
        $profile = self::item(__('Perfil', 'motorlan-portal'), 'user-circle', 'dashboard/user/account', ['dashboard/user']);

        if (current_user_can('manage_options')) {
            $items = [
                self::item(__('Anuncios', 'motorlan-portal'), 'list-check', 'dashboard/admin/publications'),
                self::item(__('Aprobar', 'motorlan-portal'), 'shield-check', 'dashboard/admin/approvals'),
                $publish,
                self::item(__('Ventas', 'motorlan-portal'), 'currency-euro', 'dashboard/publications/sales'),
                $profile,
            ];
        } else {
            $items = [
                self::item(__('Anuncios', 'motorlan-portal'), 'list', 'dashboard/publications/publication/list', ['dashboard/publications/publication/list', 'dashboard/publications/publication/edit']),
                self::item(__('Compras', 'motorlan-portal'), 'shopping-bag', 'dashboard/purchases/purchases', ['dashboard/purchases/purchases', 'dashboard/purchases/opinions', 'dashboard/purchases/offers-sent', 'dashboard/favorites']),
                $publish,
                self::item(__('Consultas', 'motorlan-portal'), 'message-share', 'dashboard/purchases/inquiries', ['dashboard/purchases/inquiries', 'dashboard/inquiries']),
                $profile,
            ];
        }
        return self::mark_active((array) apply_filters('motorlan_portal_nav_mobile', $items));
    }

    /**
     * Menú del avatar (UserProfile.vue): solo «Mi perfil». La versión anterior enlazaba
     * también a dashboard/user/notifications, que no existe en la SPA (404).
     */
    public static function user_menu(): array
    {
        $items = [self::item(__('Mi perfil', 'motorlan-portal'), 'user', 'dashboard/user/account', ['dashboard/user'])];
        return self::mark_active((array) apply_filters('motorlan_portal_nav_user', $items));
    }

    /**
     * Elemento del menú: $route relativa a la página de la SPA ('dashboard/…'). $match
     * (por defecto la propia ruta) = prefijos que lo marcan activo.
     */
    public static function item(string $title, string $icon, string $route, array $match = []): array
    {
        return ['title' => $title, 'icon' => $icon, 'url' => Urls::spa($route), 'match' => $match ?: [$route]];
    }

    protected static function mark_active(array $items): array
    {
        $route = SpaPages::current_route();
        $current = strtok(Urls::current(), '?');
        foreach ($items as &$item) {
            if (empty($item['url'])) {
                continue;
            }
            $item['active'] = false;
            foreach ((array) ($item['match'] ?? []) as $prefix) {
                $prefix = trim((string) $prefix, '/');
                if ($route !== '' && ($route === $prefix || strpos($route, $prefix . '/') === 0)) {
                    $item['active'] = true;
                }
            }
            // Páginas propias del portal (/ml-portal/…): por URL.
            if (!$item['active'] && strpos($item['url'], '/' . \MotorlanPortal\Routing\Rewrites::BASE . '/') !== false && strpos($current, rtrim($item['url'], '/')) === 0) {
                $item['active'] = true;
            }
        }
        return $items;
    }
}
