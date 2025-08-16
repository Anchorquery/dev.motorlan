<?php

function motorlan_enqueue_vue_app() {
    // URL del servidor de desarrollo de Vite
    $vite_dev_server = 'http://localhost:5173/';

    // Comprueba si el servidor de Vite está activo
    // La @ suprime errores si el servidor está caído
    $headers = @get_headers($vite_dev_server);
    $is_vite_running = $headers && strpos($headers[0], '200');


    if ($is_vite_running) {
        // --- MODO DESARROLLO ---

        // 1. Inyecta el cliente de Vite para HMR (Hot Module Replacement)
        wp_enqueue_script(
            'vite-client',
            $vite_dev_server . '@vite/client',
            [],
            null,
            true
        );

        // 2. Inyecta el punto de entrada principal de tu app Vue
        wp_enqueue_script(
            'vue-app-main',
            $vite_dev_server . 'src/main.js', // Asegúrate que la ruta a tu main.js es correcta
            [],
            null,
            true
        );

    } else {
        // --- MODO PRODUCCIÓN ---

    wp_enqueue_style(
        'motorlan-vue-app-css',
        plugin_dir_url( __FILE__ ) . '../app/dist/css/style.css',
        array(),
        '1.0.0',
        'all'
    );

    // Enqueue the loader CSS file
    wp_enqueue_style(
        'motorlan-vue-app-loader-css',
        plugin_dir_url( __FILE__ ) . '../app/dist/loader.css',
        array(),
        '1.0.0',
        'all'
    );

    // Enqueue the JS file
    wp_enqueue_script(
        'motorlan-vue-app-js',
        plugin_dir_url( __FILE__ ) . '../app/dist/js/app.js',
        array(),
        '1.0.0',
        true
    );

    // Localize script data
    wp_localize_script('motorlan-vue-app-js', 'wpData', array(
        'site_url' => get_site_url(),
        'rest_nonce' => wp_create_nonce('wp_rest'),
    ));
    }
}




// Añade type="module" a los scripts de Vite
function add_module_type_to_vite_scripts($tag, $handle, $src) {
    if (in_array($handle, ['vite-client', 'vue-app-main'])) {
        // Cambia el tag del script para que incluya type="module"
        return '<script type="module" src="' . esc_url($src) . '" id="' . $handle . '-js"></script>';
    }
    return $tag;
}

add_filter('script_loader_tag', 'add_module_type_to_vite_scripts', 10, 3);
function motorlan_vue_app_shortcode() {
    motorlan_enqueue_vue_app();
    return '<div id="app"></div>';
}

add_shortcode( 'motorlan_vue_app', 'motorlan_vue_app_shortcode' );
