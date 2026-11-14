<?php

namespace MotorlanPortal\Controllers;

use MotorlanPortal\Support\View;

/**
 * Base de los controladores de sección. Router::dispatch() ya ha comprobado la sesión
 * según el `auth` de la sección; handle() recibe el resto de la ruta (sin barras).
 *
 * Formularios del panel: POST a la propia URL de la pantalla con el campo `_mlnonce`
 * (nonce_field()) — o, con HTMX, la cabecera X-WP-Nonce que app.js añade a toda petición
 * y renueva si caduca. valid_nonce() acepta cualquiera de los dos.
 */
abstract class Controller
{
    /** Acción del nonce de los formularios del panel (`_mlnonce`). */
    const PANEL_NONCE = 'motorlan_portal';

    abstract public function handle(string $param): void;

    protected function render(string $template, array $context = []): void
    {
        View::render($template, $context);
    }

    protected function not_found(): void
    {
        View::error(404);
    }

    /** ¿Petición de HTMX? (pide solo el parcial, no la página entera). */
    protected function is_htmx(): bool
    {
        return !empty($_SERVER['HTTP_HX_REQUEST']);
    }

    protected function is_post(): bool
    {
        return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /** Nonce del formulario (`_mlnonce`) o, con HTMX, la cabecera X-WP-Nonce de la sesión. */
    protected function valid_nonce(): bool
    {
        $header = isset($_SERVER['HTTP_X_WP_NONCE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_WP_NONCE'])) : '';
        if ($header !== '' && wp_verify_nonce($header, 'wp_rest')) {
            return true;
        }
        $field = isset($_POST['_mlnonce']) ? sanitize_text_field(wp_unslash($_POST['_mlnonce'])) : '';
        return $field !== '' && (bool) wp_verify_nonce($field, static::PANEL_NONCE);
    }

    /** Campo del POST. $clean = false para contraseñas (sin sanitize_text_field). */
    protected function field(string $name, bool $clean = true): string
    {
        $v = isset($_POST[$name]) && is_string($_POST[$name]) ? wp_unslash($_POST[$name]) : '';
        return $clean ? trim(sanitize_text_field($v)) : $v;
    }

    /** Parámetro de la query (GET), saneado. */
    protected function query(string $name, string $default = ''): string
    {
        return isset($_GET[$name]) && is_string($_GET[$name]) ? trim(sanitize_text_field(wp_unslash($_GET[$name]))) : $default;
    }

    /** Redirección: HX-Redirect con HTMX, si no 302/303. Conserva la query si $keep_query. */
    protected function redirect(string $url, int $status = 302, bool $keep_query = false): void
    {
        if ($keep_query && !empty($_SERVER['QUERY_STRING'])) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . (string) wp_unslash($_SERVER['QUERY_STRING']);
        }
        if ($this->is_htmx()) {
            View::html_response('', 200, ['HX-Redirect' => $url]);
        }
        wp_safe_redirect($url, $status);
        exit;
    }

    /** Aviso flotante desde el servidor (HX-Trigger → ml:toast, ver app.js). */
    protected static function toast_header(string $text, string $color = 'success'): array
    {
        return ['HX-Trigger' => (string) wp_json_encode(['ml:toast' => ['text' => $text, 'color' => $color]])];
    }
}
