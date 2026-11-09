<?php

namespace MotorlanPortal\Support;

/**
 * Assets del build (Vite -> dist/) con nombre fijo, versionados por filemtime.
 * El portal es standalone: los <link>/<script> los pone layout/base.twig.
 */
class Assets
{
    public static function url(string $rel): string
    {
        $path = MOTORLAN_PORTAL_DIR . 'dist/' . $rel;
        $ver  = file_exists($path) ? (string) filemtime($path) : MOTORLAN_PORTAL_VERSION;
        return MOTORLAN_PORTAL_URL . 'dist/' . $rel . '?v=' . $ver;
    }

    public static function built(): bool
    {
        return file_exists(MOTORLAN_PORTAL_DIR . 'dist/js/app.js') && file_exists(MOTORLAN_PORTAL_DIR . 'dist/css/app.css');
    }
}
