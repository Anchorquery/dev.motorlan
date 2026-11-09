<?php

namespace MotorlanPortal;

use MotorlanPortal\Routing\Rewrites;
use MotorlanPortal\Routing\Takeover;

/**
 * Orquestador: interruptor de secciones, rutas, plantilla, REST y Twig.
 *
 * Coexistencia con la SPA: si no hay ninguna sección encendida (lo normal hasta migrar
 * la primera), el plugin solo carga su página de ajustes en wp-admin. No registra reglas
 * de reescritura, ni toca parse_request/template_include, ni encola nada en el front.
 */
class Plugin
{
    public static function boot()
    {
        load_plugin_textdomain('motorlan-portal', false, dirname(plugin_basename(MOTORLAN_PORTAL_FILE)) . '/languages');

        if (is_admin()) {
            (new Admin\Settings())->register();
            if (!class_exists('Timber\\Timber')) {
                add_action('admin_notices', [self::class, 'notice_no_timber']);
            }
        }

        // Las reglas se regeneran cuando cambian las secciones encendidas (también al apagar
        // la última, para quitar las suyas).
        add_action('init', [Rewrites::class, 'maybe_flush'], 99);

        if (!Sections::any_enabled()) {
            return;
        }

        (new Rewrites())->register();
        (new Takeover())->register();
        (new Security\StaleNonce())->register();
        (new Rest\UiKitApi())->register();

        add_filter('template_include', [self::class, 'template_include'], 1000);
        add_filter('redirect_canonical', function ($redirect) {
            return get_query_var(Rewrites::QV_SECTION) ? false : $redirect;
        });

        if (class_exists('Timber\\Timber')) {
            \Timber\Timber::init();
            add_filter('timber/locations', [self::class, 'twig_locations']);
            add_filter('timber/twig', [Support\View::class, 'twig_extend']);
        }
    }

    public static function twig_locations($paths)
    {
        $paths[] = [MOTORLAN_PORTAL_DIR . 'views'];
        return $paths;
    }

    public static function template_include($template)
    {
        $section = (string) get_query_var(Rewrites::QV_SECTION);
        if ($section !== '' && Sections::is_enabled($section)) {
            return MOTORLAN_PORTAL_DIR . 'templates/template-portal.php';
        }
        return $template;
    }

    public static function activate()
    {
        // Solo reglas de las secciones encendidas (ninguna por defecto).
        if (Sections::any_enabled()) {
            (new Rewrites())->add_rules();
        }
        flush_rewrite_rules(false);
        update_option(Rewrites::SIG_OPTION, Rewrites::signature(), false);
    }

    public static function deactivate()
    {
        delete_option(Rewrites::SIG_OPTION);
        // Quita nuestras reglas: se regeneran sin ellas en la siguiente carga.
        add_action('shutdown', function () {
            flush_rewrite_rules(false);
        });
    }

    public static function notice_no_timber()
    {
        echo '<div class="notice notice-error"><p><strong>Motorlan Portal:</strong> falta Timber. Ejecuta <code>composer install</code> en <code>wp-content/plugins/motorlan-portal</code>.</p></div>';
    }
}
