<?php
/**
 * Callback for the public store endpoint.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Handle the request for the public store endpoint.
 * Forces status to 'publish' and removes any author filtering.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function motorlan_get_public_publicaciones_callback($request) {
    $params = $request->get_params();
    $params['status'] = 'publish';
    unset($params['author']);

    // Create a new request object to avoid modifying the original one.
    $new_request = new WP_REST_Request($request->get_method(), $request->get_route());
    $new_request->set_query_params($params);
    
    return motorlan_get_publicaciones_callback($new_request);
}