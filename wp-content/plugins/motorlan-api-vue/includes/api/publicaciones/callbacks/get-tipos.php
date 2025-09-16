<?php
/**
 * Callback to get a list of tipos.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Handle the request to get a list of tipos.
 *
 * @return WP_REST_Response
 */
function motorlan_get_tipos_callback() {
    return motorlan_get_taxonomy_terms_callback('tipo');
}