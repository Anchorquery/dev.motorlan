<?php
/**
 * REST API Routes for Reviews.
 *
 * @package motorlan-api-vue
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register review routes.
 */
function motorlan_register_reviews_rest_routes() {
    // Ensure Controller is available
    if ( ! class_exists( 'Motorlan_Reviews_Controller' ) ) {
        require_once MOTORLAN_API_VUE_PATH . 'includes/api/reviews/controllers/class-motorlan-reviews-controller.php';
    }

    $controller = new Motorlan_Reviews_Controller();
    $controller->register_routes();
}
add_action( 'rest_api_init', 'motorlan_register_reviews_rest_routes' );
