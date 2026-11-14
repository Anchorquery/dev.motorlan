<?php

namespace MotorlanPortal\Rest;

use MotorlanPortal\Sections;
use MotorlanPortal\Support\Api;
use MotorlanPortal\Support\View;

/**
 * Parciales HTMX de la tienda (namespace motorlan-portal/v1). Sesión: cookie + X-WP-Nonce.
 *
 *  POST /tienda/favorito/{id}  state=1|0 (estado actual) → lo invierte con /motorlan/v1/favorites
 *                              y devuelve el botón (partials/ficha-favorito.twig) + aviso.
 */
class TiendaApi
{
    const NS = 'motorlan-portal/v1';

    public function register()
    {
        if (!Sections::is_enabled('tienda')) {
            return;
        }
        add_action('rest_api_init', function () {
            register_rest_route(self::NS, '/tienda/favorito/(?P<id>\d+)', [
                'methods'             => 'POST',
                'callback'            => [self::class, 'favorite'],
                'permission_callback' => 'is_user_logged_in',
                'args' => [
                    'id'    => ['type' => 'integer', 'required' => true, 'minimum' => 1],
                    'state' => ['type' => 'string', 'default' => '0', 'enum' => ['0', '1']],
                ],
            ]);
        });
    }

    public static function favorite(\WP_REST_Request $request)
    {
        $id  = (int) $request['id'];
        $was = $request['state'] === '1';

        $r = $was
            ? Api::call('DELETE', Api::route('favorites/' . $id))
            : Api::call('POST', Api::route('favorites'), [], ['publicacion_id' => $id]);

        // "Ya estaba" / "no estaba" cuentan como éxito: el estado final es el pedido.
        $ok  = $r['ok'] || in_array((int) $r['status'], [404, 409], true);
        $now = $ok ? !$was : $was;

        $toast = $ok
            ? ['text' => $now ? __('Añadido a favoritos', 'motorlan-portal') : __('Quitado de favoritos', 'motorlan-portal'), 'color' => 'success']
            : ['text' => __('No se pudo actualizar el favorito. Inténtalo de nuevo.', 'motorlan-portal'), 'color' => 'error'];

        $html = View::compile('partials/ficha-favorito.twig', ['product_id' => $id, 'favorite' => $now]);
        View::html_response($html, 200, ['HX-Trigger' => wp_json_encode(['ml:toast' => $toast])]);
    }
}
