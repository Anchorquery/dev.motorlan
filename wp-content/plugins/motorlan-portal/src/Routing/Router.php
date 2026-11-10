<?php

namespace MotorlanPortal\Routing;

use MotorlanPortal\Sections;
use MotorlanPortal\Support\Urls;
use MotorlanPortal\Support\View;

/**
 * Sección -> controlador, con el control de sesión en el servidor.
 *
 * Sesión = la de WordPress (cookie). En el navegador, las peticiones a la REST llevan
 * X-WP-Nonce (assets/js/nonce.js, también en HTMX). El JWT de la SPA no se toca.
 */
class Router
{
    public function dispatch()
    {
        $key     = (string) get_query_var(Rewrites::QV_SECTION);
        $param   = trim((string) get_query_var(Rewrites::QV_PARAM), '/');
        $section = Sections::enabled()[$key] ?? null;

        if (!$section) {
            View::error(404);
            return;
        }

        $auth = $section['auth'] ?? 'user';
        if ($auth !== 'public' && !is_user_logged_in()) {
            wp_safe_redirect(Urls::login(Urls::current()));
            exit;
        }
        if ($auth === 'admin' && !current_user_can('manage_options')) {
            View::error(403);
            return;
        }

        $controller = new $section['controller']();
        $controller->handle($param);
    }
}
