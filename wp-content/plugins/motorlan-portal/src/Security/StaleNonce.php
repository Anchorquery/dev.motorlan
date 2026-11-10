<?php

namespace MotorlanPortal\Security;

/**
 * Nonce REST que ya no vale para la sesión del navegador (403 rest_cookie_invalid_nonce):
 * la pestaña lleva más de 12-24 h abierta, o se entró/salió en otra. WordPress rechaza
 * entonces TODO, también lo que se podría ver como invitado.
 *
 * Solo en LECTURAS (GET/HEAD) del namespace del portal: se atienden como invitado (igual
 * que sin nonce) y la cabecera X-Motorlan-Nonce-Stale avisa al JavaScript para que pida
 * uno nuevo y repita la petición (assets/js/nonce.js). No se mantiene la sesión sin
 * nonce: el nonce es la protección CSRF de la API con cookie. Las escrituras siguen
 * dando 403 y el JavaScript las reintenta con el nonce nuevo.
 *
 * Las rutas de motorlan/v1 ya tienen su propio tratamiento en motorlan-api-vue
 * (motorlan_allow_public_rest_routes); aquí no se tocan.
 */
class StaleNonce
{
    const NAMESPACES = ['motorlan-portal/v1/'];
    const HEADER = 'X-Motorlan-Nonce-Stale';

    public function register()
    {
        // Después de rest_cookie_check_errors (100) y del filtro de motorlan-api-vue (100).
        add_filter('rest_authentication_errors', [self::class, 'as_guest'], 110);
    }

    public static function as_guest($result)
    {
        if (!is_wp_error($result) || $result->get_error_code() !== 'rest_cookie_invalid_nonce') {
            return $result;
        }
        $method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : '');
        if (!in_array($method, ['GET', 'HEAD'], true) || !self::is_portal_route()) {
            return $result;
        }
        wp_set_current_user(0);
        if (!headers_sent()) {
            header(self::HEADER . ': 1');
        }
        return true;
    }

    private static function is_portal_route(): bool
    {
        $route = isset($GLOBALS['wp']->query_vars['rest_route']) ? ltrim((string) $GLOBALS['wp']->query_vars['rest_route'], '/') : '';
        foreach (self::NAMESPACES as $ns) {
            if (strpos($route, $ns) === 0) {
                return true;
            }
        }
        return false;
    }
}
