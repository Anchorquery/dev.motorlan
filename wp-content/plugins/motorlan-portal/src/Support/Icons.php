<?php

namespace MotorlanPortal\Support;

/**
 * Iconos Tabler (los mismos de la SPA: tabler-*) como SVG inline.
 *
 * dist/icons.json lo genera scripts/build-icons.mjs con SOLO los iconos que aparecen en
 * views/ y src/ (nombres literales: icon('heart'), 'icon' => 'heart'). Un nombre
 * construido en tiempo de ejecución no entra en el build: escríbelo literal o añádelo a
 * EXTRA en el script. Sin SVG para un nombre, se pinta un hueco del mismo tamaño.
 */
class Icons
{
    /** @var array<string,string>|null nombre => contenido interior del <svg> */
    protected static $map = null;

    public static function svg(string $name, string $class = 'size-5', array $attrs = []): string
    {
        $name  = preg_replace('/^tabler-/', '', $name);
        $inner = self::map()[$name] ?? '';
        $extra = '';
        foreach ($attrs as $k => $v) {
            $extra .= ' ' . esc_attr($k) . '="' . esc_attr($v) . '"';
        }
        if (!isset($attrs['aria-label'])) {
            $extra .= ' aria-hidden="true"';
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="ml-icon ' . esc_attr($class) . '" data-icon="' . esc_attr($name) . '"' . $extra . '>' . $inner . '</svg>';
    }

    public static function map(): array
    {
        if (self::$map === null) {
            $file = MOTORLAN_PORTAL_DIR . 'dist/icons.json';
            $data = file_exists($file) ? json_decode((string) file_get_contents($file), true) : null;
            self::$map = is_array($data) ? $data : [];
        }
        return self::$map;
    }
}
