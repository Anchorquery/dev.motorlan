<?php

namespace MotorlanPortal\Controllers;

use MotorlanPortal\Support\SpaPages;
use MotorlanPortal\Support\Urls;

/**
 * Sección `panel`: solo redirige, en el servidor, lo que la SPA redirige en el navegador.
 *  - /mi-cuenta/ y /mi-cuenta/dashboard → Mis compras (pages/dashboard/index.vue y el
 *    guard de la base de la cuenta).
 *  - URLs antiguas del panel (plugins/1.router/additional-routes.ts), que siguen en
 *    emails, avisos y marcadores.
 * El destino es la URL de la SPA: la sirve el portal si su sección está encendida.
 */
class PanelController extends Controller
{
    /** Ruta antigua (relativa a dashboard/) => ruta nueva. {uuid} se copia. */
    const LEGACY = [
        ''                                              => 'dashboard/purchases/purchases',
        'chat'                                          => 'dashboard/inquiries',
        'purchases'                                     => 'dashboard/purchases/purchases',
        'purchases/list'                                => 'dashboard/purchases/purchases',
        'purchases/favorites'                           => 'dashboard/favorites/list',
        'purchases/chat/{uuid}'                         => 'dashboard/purchases/{uuid}?openChat=true',
        'user/company'                                  => 'dashboard/user/account',
        'publications/questions'                        => 'dashboard/inquiries',
        'publications/publication/questions/{uuid}'     => 'dashboard/inquiries',
        'questions/list'                                => 'dashboard/inquiries',
        'purchases/questions'                           => 'dashboard/purchases/inquiries',
    ];

    /** spa_paths: raíz de /mi-cuenta/, /dashboard y las rutas antiguas. */
    public static function patterns(): array
    {
        $quote = function ($s) { return preg_quote($s, '#'); };
        $account = implode('|', array_map($quote, SpaPages::account_slugs()));
        $bases = implode('|', array_map($quote, array_unique(array_merge(SpaPages::account_slugs(), SpaPages::store_slugs()))));
        $legacy = [];
        foreach (array_keys(self::LEGACY) as $route) {
            if ($route !== '') {
                $legacy[] = str_replace(preg_quote('{uuid}', '#'), '[A-Za-z0-9-]+', preg_quote($route, '#'));
            }
        }
        return [
            '#^/(?:' . $account . ')/?$#',
            '#^/(?:' . $bases . ')/dashboard(?:/(?<param>' . implode('|', $legacy) . '))?/?$#',
        ];
    }

    public function handle(string $param): void
    {
        $route = trim($param, '/');
        foreach (self::LEGACY as $from => $to) {
            $re = '#^' . str_replace(preg_quote('{uuid}', '#'), '(?<uuid>[A-Za-z0-9-]+)', preg_quote($from, '#')) . '$#';
            if (preg_match($re, $route, $m)) {
                $target = isset($m['uuid']) ? str_replace('{uuid}', $m['uuid'], $to) : $to;
                $this->redirect(Urls::spa($target), 302, true);
            }
        }
        $this->not_found();
    }
}
