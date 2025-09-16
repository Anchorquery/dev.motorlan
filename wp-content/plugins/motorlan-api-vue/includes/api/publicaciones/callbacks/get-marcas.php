<?php
/**
 * Callback to get a list of brands.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Handle the request to get a list of brands.
 *
 * @return WP_REST_Response
 */
function motorlan_get_marcas_callback() {
    return motorlan_get_taxonomy_terms_callback('marca');
}