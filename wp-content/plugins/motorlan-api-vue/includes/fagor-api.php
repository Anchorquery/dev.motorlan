<?php

/**
 * Handles API requests to the Fagor Automation product downloads API.
 */
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class FagorApi {

    /**
     * Makes a GET request to the Fagor API.
     *
     * @param array $params An array of query parameters.
     * @return array|null The API response as an array, or null on error.
     */
    public function getProducts(array $params = []): ?array {
        $url = 'https://fagorautomation.test/wp/v2/productos/descargas';

        // Build the full URL.
        $fullUrl = $url;
        if ( ! empty( $params ) ) {
            if ( function_exists( 'add_query_arg' ) ) {
                $fullUrl = add_query_arg( $params, $url );
            } else {
                $queryString = http_build_query( $params );
                $fullUrl = $url . '?' . $queryString;
            }
        }

        // Perform the API request using file_get_contents.
        $response = file_get_contents($fullUrl);

        if ($response === false) {
            error_log('File get contents error: ' . error_get_last()['message']);
            return null;
        }

        // Decode the JSON response.
        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON Decode Error: ' . json_last_error_msg());
            return null;
        }

        return $data;
    }
}
