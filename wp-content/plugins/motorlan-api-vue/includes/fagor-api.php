<?php

/**
 * Handles API requests to the Fagor Automation product downloads API.
 */
class FagorApi {

    /**
     * Makes a GET request to the Fagor API.
     *
     * @param array $params An array of query parameters.
     * @return array|null The API response as an array, or null on error.
     */
    public function getProducts(array $params = []): ?array {
        $url = 'https://fagorautomation.test/wp/v2/productos/descargas';

        // Build the query string.
        $queryString = http_build_query($params);

        // Construct the full URL.
        $fullUrl = $url . $queryString;

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

// Example usage (for testing):
if (isset($_GET['action']) && $_GET['action'] === 'get_products') {
    $api = new FagorApi();
    $params = [
        'sistemas' => '12345', // Example system ID
        'perPage' => 10,      // Example number of items per page
    ];

    $products = $api->getProducts($params);

    if ($products) {
        echo '<pre>';
        print_r($products);
        echo '</pre>';
    } else {
        echo 'Error fetching products.';
    }
}

?>