<?php
/**
 * Plugin Name: motorlan-api-vue
 * Plugin URI:  https://motorlan.com
 * Description: API para conectar con VUE
 * Version:     1.2
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
require_once MOTORLAN_API_VUE_PATH . 'includes/post-type/cpt-setup.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/post-type/cpt-setup-garantia.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/post-type/cpt-setup-purchases.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/custom-field/acf-setup.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/custom-field/acf-setup-garantia.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/custom-field/acf-setup-purchases.php';

require_once MOTORLAN_API_VUE_PATH . 'includes/api/publicaciones-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/garantia-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/motor-helpers.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/purchases-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/questions-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/offers-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/session-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/sales-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/notifications-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/my-publications-routes.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/api/product-messages-routes.php';

require_once MOTORLAN_API_VUE_PATH . 'includes/classes/class-motorlan-notification-manager.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/admin/admin-mods.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/admin/acf-purchases-sync.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/vue-app-setup.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/cron/cron-jobs.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/db/db-setup.php';

/**
 * Configuración global del plugin y funciones utilitarias.
 */

// CORS headers
function motorlan_add_cors_headers() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-WP-Nonce, Authorization');
    header('Access-Control-Allow-Credentials: true');

    if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
        status_header(200);
        exit();
    }
}
add_action('rest_api_init', 'motorlan_add_cors_headers', 15);

// Permisos básicos
function motorlan_is_user_authenticated() {
    return is_user_logged_in();
}

function motorlan_permission_callback_true() {
    return true;
}

require_once MOTORLAN_API_VUE_PATH . 'includes/db/db-create-purchase-messages.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/db/db-create-product-messages.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/db/db-create-product-room-reads.php';
require_once MOTORLAN_API_VUE_PATH . 'includes/db/db-create-notifications.php';

register_activation_hook( __FILE__, 'motorlan_create_notifications_table' );
register_activation_hook( __FILE__, 'motorlan_offers_create_table' );
register_activation_hook( __FILE__, 'motorlan_create_purchase_messages_table' );
register_activation_hook( __FILE__, 'motorlan_create_product_messages_table' );
register_activation_hook( __FILE__, 'motorlan_create_product_room_reads_table' );
/**
 * Filter the JWT payload to fix the "Not Before" (nbf) claim.
 * This prevents issues with server time synchronization.
 *
 * @param array $payload The JWT payload.
 * @param WP_User $user The user object.
 * @return array The modified JWT payload.
 */
function motorlan_jwt_payload_fix_nbf($payload, $user) {
    // Subtract 10 seconds from the 'nbf' claim to ensure the token is valid immediately.
    if (isset($payload['nbf'])) {
        $payload['nbf'] = $payload['nbf'] - 10;
    }
    
    // Also, ensure the 'iat' (Issued At) is not in the future.
    if (isset($payload['iat'])) {
        $payload['iat'] = min($payload['iat'], time());
    }

    return $payload;
}
add_filter('jwt_auth_payload', 'motorlan_jwt_payload_fix_nbf', 10, 2);
