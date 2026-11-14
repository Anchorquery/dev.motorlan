<?php

namespace MotorlanPortal\Routing;

use MotorlanPortal\Sections;
use MotorlanPortal\Support\Urls;

/**
 * Toma URLs de la SPA para las secciones encendidas que declaran `spa_paths`.
 *
 * La SPA resuelve sus rutas en parse_request con prioridad 1
 * (motorlan_parse_vue_app_request: pagename = página Vue). Aquí, con prioridad 20, si la
 * ruta pertenece a una sección encendida se sustituyen las query vars por las del portal y
 * template_include (prioridad 1000) carga nuestra plantilla. Con la sección apagada este
 * hook ni se registra (Plugin::boot) o no casa con nada: la SPA sigue igual.
 *
 * Al migrar una sección, sus hooks de la SPA que actúen en esas URLs (SEO de la ficha en
 * template_redirect, encolado del bundle…) se neutralizan en la acción
 * `motorlan_portal_takeover` desde el controlador de la sección.
 */
class Takeover
{
    public function register()
    {
        add_action('parse_request', [$this, 'parse'], 20);
    }

    public function parse($wp)
    {
        if (!empty($wp->query_vars[Rewrites::QV_SECTION])) {
            return; // URL propia (/ml-portal/…), ya resuelta por las reglas.
        }
        $path = Urls::request_path();
        foreach (Sections::enabled() as $key => $section) {
            foreach (self::patterns($section) as $pattern) {
                if (!preg_match($pattern, $path, $m)) {
                    continue;
                }
                // Se conservan las query vars que resolvió la SPA (pagename = su página de
                // WordPress): la consulta principal sigue siendo esa página, así que el tema
                // (cabecera de página), Rank Math y WPML (hreflang) la reconocen igual que hoy.
                // Sin página detrás (URL que la SPA no resolvió) quedan solo las del portal.
                $keep = array_intersect_key($wp->query_vars, array_flip(['pagename', 'page_id', 'lang']));
                $wp->query_vars = $keep + [
                    Rewrites::QV_SECTION => $key,
                    Rewrites::QV_PARAM   => isset($m['param']) ? (string) $m['param'] : '',
                ];
                $wp->matched_rule  = 'motorlan-portal:' . $key;
                $wp->matched_query = '';
                do_action('motorlan_portal_takeover', $key, $path);
                return;
            }
        }
    }

    /** spa_paths de una sección: lista de regex o callable que la devuelve. */
    public static function patterns(array $section): array
    {
        $paths = $section['spa_paths'] ?? [];
        if (is_callable($paths)) {
            $paths = call_user_func($paths);
        }
        return array_values(array_filter((array) $paths, 'is_string'));
    }
}
