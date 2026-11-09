<?php

namespace MotorlanPortal\Support;

/**
 * Menú del panel: el mismo que la SPA (app/src/navigation/vertical/apps-and-pages.ts y
 * layouts/components/DashboardMobileNav.vue). Cada entrada va a la SPA hasta que su
 * sección se migra (Urls::screen). `match` = prefijos de la ruta que la marcan activa.
 */
class Nav
{
    public static function vertical(): array
    {
        $items = [
            ['heading' => __('Gestión de Ventas', 'motorlan-portal')],
            self::item(__('Mis Publicaciones', 'motorlan-portal'), 'list', 'vendedor', 'publicaciones', 'dashboard/publications/publication/list'),
            self::item(__('Ventas', 'motorlan-portal'), 'currency-euro', 'vendedor', 'ventas', 'dashboard/publications/sales'),
            self::item(__('Ofertas Recibidas', 'motorlan-portal'), 'tag', 'vendedor', 'ofertas', 'dashboard/publications/offers-received'),
            self::item(__('Interesados', 'motorlan-portal'), 'users', 'vendedor', 'interesados', 'dashboard/inquiries'),

            ['heading' => __('Gestión de Compras', 'motorlan-portal')],
            self::item(__('Mis Compras', 'motorlan-portal'), 'shopping-bag', 'comprador', 'compras', 'dashboard/purchases/purchases'),
            self::item(__('Consultas Enviadas', 'motorlan-portal'), 'message-share', 'comprador', 'consultas', 'dashboard/purchases/inquiries'),
            self::item(__('Ofertas de Precio', 'motorlan-portal'), 'send', 'comprador', 'ofertas', 'dashboard/purchases/offers-sent'),
            self::item(__('Favoritos', 'motorlan-portal'), 'heart', 'comprador', 'favoritos', 'dashboard/favorites/list'),
        ];

        if (current_user_can('manage_options')) {
            $items[] = ['heading' => __('Administración', 'motorlan-portal')];
            $items[] = self::item(__('Publicaciones', 'motorlan-portal'), 'list', 'admin', 'publicaciones', 'dashboard/admin/publications');
            $items[] = self::item(__('Aprobaciones', 'motorlan-portal'), 'check', 'admin', 'aprobaciones', 'dashboard/admin/approvals');
            if (\MotorlanPortal\Sections::is_enabled('ui-kit')) {
                $items[] = [
                    'title' => __('Componentes (UI kit)', 'motorlan-portal'),
                    'icon'  => 'components',
                    'url'   => Urls::portal('ui-kit'),
                    'match' => [Urls::portal('ui-kit')],
                ];
            }
        }

        return self::mark_active($items);
    }

    /** Barra inferior en móvil (< 960 px): 5 accesos, el central destacado. */
    public static function mobile(): array
    {
        $publish = self::item(__('Publicar', 'motorlan-portal'), 'circle-plus', 'vendedor', 'publicar', 'dashboard/publications/publication/add') + ['primary' => true];
        $profile = self::item(__('Perfil', 'motorlan-portal'), 'user-circle', 'comprador', 'perfil', 'dashboard/user/account');

        if (current_user_can('manage_options')) {
            $items = [
                self::item(__('Anuncios', 'motorlan-portal'), 'list-check', 'admin', 'publicaciones', 'dashboard/admin/publications'),
                self::item(__('Aprobar', 'motorlan-portal'), 'shield-check', 'admin', 'aprobaciones', 'dashboard/admin/approvals'),
                $publish,
                self::item(__('Ventas', 'motorlan-portal'), 'currency-euro', 'vendedor', 'ventas', 'dashboard/publications/sales'),
                $profile,
            ];
        } else {
            $items = [
                self::item(__('Anuncios', 'motorlan-portal'), 'list', 'vendedor', 'publicaciones', 'dashboard/publications/publication/list'),
                self::item(__('Compras', 'motorlan-portal'), 'shopping-bag', 'comprador', 'compras', 'dashboard/purchases/purchases'),
                $publish,
                self::item(__('Consultas', 'motorlan-portal'), 'message-share', 'comprador', 'consultas', 'dashboard/purchases/inquiries'),
                $profile,
            ];
        }
        return self::mark_active($items);
    }

    /** Menú del avatar (UserProfile.vue). */
    public static function user_menu(): array
    {
        return [
            self::item(__('Mi perfil', 'motorlan-portal'), 'user', 'comprador', 'perfil', 'dashboard/user/account'),
            self::item(__('Notificaciones', 'motorlan-portal'), 'bell', 'comprador', 'notificaciones', 'dashboard/user/notifications'),
        ];
    }

    protected static function item(string $title, string $icon, string $section, string $portal_path, string $spa_path): array
    {
        $url = Urls::screen($section, $portal_path, $spa_path);
        return ['title' => $title, 'icon' => $icon, 'url' => $url, 'match' => [$url]];
    }

    protected static function mark_active(array $items): array
    {
        $current = strtok(Urls::current(), '?');
        foreach ($items as &$item) {
            if (empty($item['url'])) {
                continue;
            }
            $item['active'] = false;
            foreach ($item['match'] as $prefix) {
                if (strpos($current, rtrim($prefix, '/')) === 0) {
                    $item['active'] = true;
                }
            }
        }
        return $items;
    }
}
