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

    $request->set_params($params);
    
    return motorlan_get_publicaciones_callback($request);
}