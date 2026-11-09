<?php
/**
 * Cargador del portal. No es un page-template del tema: lo devuelve template_include
 * cuando la query var ml_section es de una sección encendida (Plugin::template_include).
 */

defined('ABSPATH') || exit;

// La query no corresponde a ningún post: 200 en vez del 404 de WordPress.
global $wp_query;
if ($wp_query instanceof WP_Query) {
    $wp_query->is_404 = false;
}
status_header(200);
nocache_headers();

(new \MotorlanPortal\Routing\Router())->dispatch();
exit;
