<?php
/**
 * Callback to get a list of publicacion categories.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Handle the request to get a list of publication categories.
 *
 * @return WP_REST_Response
 */
function motorlan_get_publicacion_categories_callback() {
    return motorlan_get_taxonomy_terms_callback('categoria');
}