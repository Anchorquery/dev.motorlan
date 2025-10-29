<?php
/**
 * REST API routes for pre-purchase product chat.
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

require_once MOTORLAN_API_VUE_PATH . 'includes/api/products/controllers/class-product-chat-controller.php';

function motorlan_register_product_messages_rest_routes() {
    $controller = new Motorlan_Product_Chat_Controller();
    $controller->register_routes();
}
add_action( 'rest_api_init', 'motorlan_register_product_messages_rest_routes' );

