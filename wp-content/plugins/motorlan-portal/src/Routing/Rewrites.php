<?php

namespace MotorlanPortal\Routing;

use MotorlanPortal\Sections;

/**
 * URLs propias del plugin: /{BASE}/{sección}/{param}. Solo se registran las de las
 * secciones ENCENDIDAS; apagadas, la URL no existe (404 normal de WordPress).
 *
 * No choca con el history mode de la SPA (motorlan-api-vue.php, motorlan_vue_history_rewrite_rules):
 * aquellas reglas capturan ^{página Vue}(/.*)?/?$ (mi-cuenta, store, login…) y BASE no es
 * ninguna de esas páginas. Las URLs de la SPA que una sección migrada sustituye no se
 * reescriben aquí: las toma Routing\Takeover en parse_request, después de la SPA.
 */
class Rewrites
{
    const BASE       = 'ml-portal';
    const QV_SECTION = 'ml_section';
    const QV_PARAM   = 'ml_param';
    const SIG_OPTION = 'motorlan_portal_rewrite_sig';
    /** Subir al cambiar el formato de las reglas: se regeneran una vez (también en producción). */
    const VERSION = '1';

    public function register()
    {
        add_action('init', [$this, 'add_rules']);
        add_filter('query_vars', [$this, 'query_vars']);
    }

    public function add_rules()
    {
        foreach (array_keys(Sections::enabled()) as $key) {
            add_rewrite_rule(
                '^' . self::BASE . '/' . preg_quote($key, '#') . '(?:/(.+?))?/?$',
                'index.php?' . self::QV_SECTION . '=' . $key . '&' . self::QV_PARAM . '=$matches[1]',
                'top'
            );
        }
    }

    public function query_vars($vars)
    {
        $vars[] = self::QV_SECTION;
        $vars[] = self::QV_PARAM;
        return $vars;
    }

    /** Huella de las reglas: versión + secciones encendidas. */
    public static function signature(): string
    {
        return md5(self::VERSION . '|' . implode(',', array_keys(Sections::enabled())));
    }

    /**
     * Regenera las reglas una sola vez cuando cambia la huella (se enciende o apaga una
     * sección, o sube VERSION). Escribe en la BD solo en ese caso.
     */
    public static function maybe_flush()
    {
        $sig = self::signature();
        if (get_option(self::SIG_OPTION) === $sig) {
            return;
        }
        flush_rewrite_rules(false);
        update_option(self::SIG_OPTION, $sig, false);
    }
}
