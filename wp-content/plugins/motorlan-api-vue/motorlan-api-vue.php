<?php
/**
 * Plugin Name: motorlan-api-vue
 * Plugin URI:  https://motorlan.com
 * Description: API para conectar con VUE
 * Version:     1.1
 * Author:      Motorlan
 * Author URI:  https://motorlan.com
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}


// Define plugin constants
define( 'MOTORLAN_API_VUE_VERSION', '1.1' );
define( 'MOTORLAN_API_VUE_PATH', plugin_dir_path( __FILE__ ) );


// Include required files
require_once MOTORLAN_API_VUE_PATH . 'includes/cpt-setup.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/acf-setup.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/motor-routes.php';

require_once MOTORLAN_API_VUE_PATH . 'includes/admin-mods.php';

