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
require_once MOTORLAN_API_VUE_PATH . 'includes/acf-setup-garantia.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/acf-setup-purchases.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/publicaciones-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/garantia-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/purchases-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/questions-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/offers-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/session-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/sales-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/notifications-routes.php';

require_once MOTORLAN_API_VUE_PATH . 'includes/classes/class-motorlan-notification-manager.php';
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

/**
 * Permission callback to check if the user is authenticated.
 *
 * @return bool
 */
function motorlan_is_user_authenticated() {
    return is_user_logged_in();
}

/**
 * Permission callback that always returns true.
 *
 * @return bool
 */
function motorlan_permission_callback_true() {
    return true;
}

/**
 * Create the notifications table on plugin activation.
 */
function motorlan_create_notifications_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'motorlan_notifications';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        data JSON DEFAULT NULL,
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'motorlan_create_notifications_table' );