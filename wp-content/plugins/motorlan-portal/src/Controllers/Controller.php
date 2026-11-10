<?php

namespace MotorlanPortal\Controllers;

use MotorlanPortal\Support\View;

/**
 * Base de los controladores de sección. Router::dispatch() ya ha comprobado la sesión
 * según el `auth` de la sección; handle() recibe el resto de la ruta (sin barras).
 */
abstract class Controller
{
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
}
