<?php

namespace MotorlanPortal;

/**
 * Registro de secciones e interruptor de coexistencia con la SPA.
 *
 * Cada sección se enciende por separado. Encendida = el plugin atiende sus URLs; apagada
 * = el plugin no registra reglas, no engancha parse_request ni template_include para
 * ella y la SPA (motorlan-api-vue) sigue sirviéndola. Por defecto TODAS apagadas.
 *
 * Fuentes del interruptor, por orden:
 *  1. Constante MOTORLAN_PORTAL_SECTIONS en wp-config.php ('ui-kit,tienda'): para probar en
 *     local sin escribir en la base de datos (el WordPress local usa la de producción).
 *  2. Opción motorlan_portal_sections (array de claves), editable en Ajustes › Motorlan Portal.
 *
 * Las etiquetas van sin __(): el registro se lee antes de init (textdomain aún sin cargar).
 * Una sección solo puede encenderse si tiene controlador. Las que aún no se han migrado
 * figuran en el registro (controller => null) para que el ajuste las muestre deshabilitadas.
 *
 * Claves del registro:
 *  - label       Nombre en el ajuste.
 *  - auth        'public' | 'user' | 'admin' (manage_options). Lo aplica Routing\Router.
 *  - controller  Clase con método handle(string $param): void, o null si no está migrada.
 *  - spa_paths   Regex sobre la ruta sin idioma (p. ej. '#^/store(?:/(?<param>.*))?/?$#')
 *                que la sección le quita a la SPA cuando está encendida. Vacío = solo vive
 *                bajo /{Rewrites::BASE}/{clave}/.
 */
class Sections
{
    const OPTION = 'motorlan_portal_sections';

    public static function registry(): array
    {
        $sections = [
            'ui-kit' => [
                'label'      => 'Catálogo de componentes (solo administradores)',
                'auth'       => 'admin',
                'controller' => Controllers\UiKitController::class,
                'spa_paths'  => [],
            ],
            'tienda' => [
                'label'      => 'Tienda y ficha de producto',
                'auth'       => 'public',
                'controller' => null,
                'spa_paths'  => [],
            ],
            'comprador' => [
                'label'      => 'Panel del comprador',
                'auth'       => 'user',
                'controller' => null,
                'spa_paths'  => [],
            ],
            'vendedor' => [
                'label'      => 'Panel del vendedor',
                'auth'       => 'user',
                'controller' => null,
                'spa_paths'  => [],
            ],
            'chat' => [
                'label'      => 'Chat',
                'auth'       => 'user',
                'controller' => null,
                'spa_paths'  => [],
            ],
            'admin' => [
                'label'      => 'Administración',
                'auth'       => 'admin',
                'controller' => null,
                'spa_paths'  => [],
            ],
            'ia' => [
                'label'      => 'Asistente IA',
                'auth'       => 'user',
                'controller' => null,
                'spa_paths'  => [],
            ],
        ];

        return (array) apply_filters('motorlan_portal_sections_registry', $sections);
    }

    public static function get(string $key): ?array
    {
        $all = self::registry();
        return isset($all[$key]) ? $all[$key] : null;
    }

    /** Claves pedidas (constante u opción), sin validar. */
    public static function requested(): array
    {
        if (defined('MOTORLAN_PORTAL_SECTIONS')) {
            $raw = is_array(MOTORLAN_PORTAL_SECTIONS) ? MOTORLAN_PORTAL_SECTIONS : explode(',', (string) MOTORLAN_PORTAL_SECTIONS);
        } else {
            $raw = get_option(self::OPTION, []);
        }
        return array_values(array_filter(array_map('trim', array_map('strval', (array) $raw))));
    }

    /** ¿Se gestiona por constante? (el ajuste se muestra de solo lectura). */
    public static function locked(): bool
    {
        return defined('MOTORLAN_PORTAL_SECTIONS');
    }

    /** Secciones encendidas y utilizables (con controlador). */
    public static function enabled(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $registry = self::registry();
        $on = [];
        foreach (self::requested() as $key) {
            $s = $registry[$key] ?? null;
            if ($s && !empty($s['controller']) && class_exists($s['controller'])) {
                $on[$key] = $s;
            }
        }
        return $cache = $on;
    }

    public static function is_enabled(string $key): bool
    {
        return isset(self::enabled()[$key]);
    }

    public static function any_enabled(): bool
    {
        return self::enabled() !== [];
    }
}
