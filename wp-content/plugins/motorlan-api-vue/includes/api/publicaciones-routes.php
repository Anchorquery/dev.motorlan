<?php
/**
 * Main loader for Publicaciones REST API routes.
 *
 * This file includes the necessary files for registering routes,
 * handling callbacks, and utility helper functions.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

$publicaciones_api_path = plugin_dir_path(__FILE__) . 'publicaciones/';

// Include the files for the refactored structure
require_once $publicaciones_api_path . 'helpers.php';
require_once $publicaciones_api_path . 'callbacks.php';
require_once $publicaciones_api_path . 'routes.php';

// Hook the route registration function into the REST API initialization
add_action('rest_api_init', 'motorlan_register_publicaciones_rest_routes');