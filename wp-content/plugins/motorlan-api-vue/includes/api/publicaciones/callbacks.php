<?php
/**
 * Main loader for Publicaciones REST API callbacks.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

$callbacks_path = plugin_dir_path(__FILE__) . 'callbacks/';

// Load all callback files
require_once $callbacks_path . 'get-publicaciones.php';
require_once $callbacks_path . 'get-public-publicaciones.php';
require_once $callbacks_path . 'get-publicacion-by-uuid.php';
require_once $callbacks_path . 'get-publicacion-by-slug.php';
require_once $callbacks_path . 'create-publicacion.php';
require_once $callbacks_path . 'update-publicacion-by-uuid.php';
require_once $callbacks_path . 'delete-publicacion.php';
require_once $callbacks_path . 'bulk-delete-publicaciones.php';
require_once $callbacks_path . 'duplicate-publicacion.php';
require_once $callbacks_path . 'update-publicacion-status.php';
require_once $callbacks_path . 'get-categories.php';
require_once $callbacks_path . 'get-tipos.php';
require_once $callbacks_path . 'get-marcas.php';
require_once $callbacks_path . 'get-user-favorites.php';
require_once $callbacks_path . 'add-user-favorite.php';
require_once $callbacks_path . 'remove-user-favorite.php';