<?php

namespace MotorlanPortal\Controllers;

use MotorlanPortal\Rest\UiKitApi;
use MotorlanPortal\Support\Api;

/**
 * /ml-portal/ui-kit/ — catálogo de componentes para comparar a ojo con la SPA.
 * Solo administradores (auth 'admin' en Sections). /ml-portal/ui-kit/auth/ y
 * /ml-portal/ui-kit/public/ muestran los otros dos layouts.
 */
class UiKitController extends Controller
{
    public function handle(string $param): void
    {
        $layouts = ['' => 'pages/ui-kit.twig', 'auth' => 'pages/ui-kit-auth.twig', 'public' => 'pages/ui-kit-public.twig'];
        if (!isset($layouts[$param])) {
            $this->not_found();
            return;
        }

        $this->render($layouts[$param], [
            'page_title' => __('Componentes', 'motorlan-portal'),
            // Sin JS, el formulario y la paginación recargan la página con ?q=&estado=&page=.
            'table'      => UiKitApi::table_context($param === '' ? wp_unslash($_GET) : []),
            'table_url'  => rest_url('motorlan-portal/v1/ui-kit/table'),
            'api_check'  => $param === '' ? $this->api_check() : null,
        ]);
    }

    /** Prueba de Support\Api: lecturas internas de motorlan/v1 (sin HTTP, sin escribir). */
    protected function api_check(): array
    {
        $session = Api::get('/motorlan/v1/session');
        $session_error = Api::last_error();
        $tipos = Api::get('/motorlan/v1/tipos');
        return [
            ['route' => 'GET /motorlan/v1/session', 'ok' => $session !== null, 'detail' => $session !== null ? (!empty($session['is_logged_in']) ? 'sesión reconocida' : 'respuesta sin sesión') : ($session_error['code'] ?? 'error')],
            ['route' => 'GET /motorlan/v1/tipos', 'ok' => is_array($tipos), 'detail' => is_array($tipos) ? count($tipos) . ' elementos' : (Api::last_error()['code'] ?? 'error')],
        ];
    }
}
