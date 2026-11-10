<?php
/**
 * Plugin Name: Motorlan Portal
 * Description: Pantallas de Motorlan server-rendered (Timber/Twig + HTMX + Alpine + Tailwind). Sustituye la SPA Vue sección a sección; todas las secciones están apagadas por defecto.
 * Version:     0.1.0
 * Author:      Adaki - Daniel H
 * Text Domain: motorlan-portal
 * Requires PHP: 8.1
 */

defined('ABSPATH') || exit;

define('MOTORLAN_PORTAL_FILE', __FILE__);
define('MOTORLAN_PORTAL_DIR', plugin_dir_path(__FILE__));
define('MOTORLAN_PORTAL_URL', plugin_dir_url(__FILE__));
define('MOTORLAN_PORTAL_VERSION', '0.1.0');

// Composer (Timber/Twig) si está instalado.
if (file_exists(MOTORLAN_PORTAL_DIR . 'vendor/autoload.php')) {
    require_once MOTORLAN_PORTAL_DIR . 'vendor/autoload.php';
}

// Autoloader propio para MotorlanPortal\* (no depende de composer: el plugin carga y
// avisa en wp-admin aunque falte vendor/).
spl_autoload_register(function ($class) {
    $prefix = 'MotorlanPortal\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $rel  = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = MOTORLAN_PORTAL_DIR . 'src/' . $rel . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

register_activation_hook(__FILE__, ['MotorlanPortal\\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['MotorlanPortal\\Plugin', 'deactivate']);

add_action('plugins_loaded', ['MotorlanPortal\\Plugin', 'boot']);
