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
require_once MOTORLAN_API_VUE_PATH . 'includes/cpt-setup-garantia.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/cpt-setup-purchases.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/acf-setup.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/acf-setup-purchases.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/publicaciones-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/garantia-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/my-account-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/questions-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/offers-routes.php';

require_once MOTORLAN_API_VUE_PATH . 'includes/admin-mods.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/vue-app-setup.php';

/**
 * Add CORS headers to the REST API.
 */
function motorlan_add_cors_headers() {
    header( 'Access-Control-Allow-Origin: *' );
    header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
    header( 'Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-WP-Nonce, Authorization' );
    header( 'Access-Control-Allow-Credentials: true' );

    if ( 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
        status_header( 200 );
        exit();
    }
}
add_action( 'rest_api_init', 'motorlan_add_cors_headers', 15 );
