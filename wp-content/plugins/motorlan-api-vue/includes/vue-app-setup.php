<?php

function motorlan_get_language_info() {
    $locale = get_locale();

    if (defined('ICL_LANGUAGE_CODE') && ICL_LANGUAGE_CODE) {
        $language_code = ICL_LANGUAGE_CODE;
    } elseif (function_exists('pll_current_language')) {
        $language_code = pll_current_language();
    } else {
        $language_parts = preg_split('/[-_]/', $locale);
        $language_code = $language_parts[0] ?? '';
    }

    $language_code = strtolower($language_code ?: 'en');

    $available_languages = [];
    if (function_exists('icl_get_languages')) {
        $languages = icl_get_languages('skip_missing=0&orderby=code');
        if (is_array($languages)) {
            foreach ($languages as $lang) {
                $code = isset($lang['language_code']) ? strtolower($lang['language_code']) : (isset($lang['code']) ? strtolower($lang['code']) : '');
                $available_languages[] = [
                    'code' => $code,
                    'locale' => $lang['default_locale'] ?? '',
                    'native_name' => $lang['native_name'] ?? $lang['translated_name'] ?? '',
                    'translated_name' => $lang['translated_name'] ?? '',
                    'flag_url' => $lang['country_flag_url'] ?? '',
                    'active' => !empty($lang['active']),
                ];
            }
        }
        $available_languages = array_values(array_filter($available_languages, function ($language) {
            return !empty($language['code']);
        }));
    }

    return [
        'current' => $language_code,
        'locale' => $locale ?: '',
        'available' => $available_languages,
    ];
}

function motorlan_enqueue_vue_app() {
    // URL del servidor de desarrollo de Vite
    $vite_dev_server = 'http://localhost:5173/';

    // Comprueba si el servidor de Vite está activo
    $headers = @get_headers($vite_dev_server);
    $is_vite_running = $headers && strpos($headers[0], '200');

    // 1. Registrar un script "puente" vacío.
    // WordPress adjuntará los datos a este script.
    wp_register_script('wp-data-bridge', false);
    wp_enqueue_script('wp-data-bridge');

    // 2. Preparar y adjuntar los datos
    $language_info = motorlan_get_language_info();

    $user_data = [
        'is_logged_in' => is_user_logged_in(),
        'user' => null,
    ];

    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $user_data['user'] = [
            'id' => $current_user->ID,
            'email' => $current_user->user_email,
            'display_name' => $current_user->display_name,
        ];
    }

    wp_localize_script('wp-data-bridge', 'wpData', [
        'site_url' => get_site_url(),
        'nonce' => wp_create_nonce('wp_rest'),
        'rest_nonce' => wp_create_nonce('wp_rest'),
        'user_data' => $user_data,
        'language' => $language_info['current'],
        'language_locale' => $language_info['locale'],
        'languages' => $language_info['available'],
        'session_endpoint' => rest_url('motorlan/v1/session'),
        'login_endpoint' => rest_url('wp/v2/custom/login'),
    ]);


    // 3. Encolar los scripts de la aplicación Vue
    if ($is_vite_running) {
        // --- MODO DESARROLLO ---
        wp_enqueue_script(
            'vite-client',
            $vite_dev_server . '@vite/client',
            [],
            null,
            true
        );
        wp_enqueue_script(
            'vue-app-main',
            $vite_dev_server . 'src/main.js',
            ['wp-data-bridge'], // Depende del puente
            null,
            true
        );
    } else {
        // --- MODO PRODUCCIÓN ---
        wp_enqueue_style(
            'motorlan-vue-app-css',
            plugin_dir_url(__FILE__) . '../app/dist/css/style.css',
            [],
            MOTORLAN_API_VUE_VERSION,
            'all'
        );
        wp_enqueue_style(
            'motorlan-vue-app-loader-css',
            plugin_dir_url(__FILE__) . '../app/dist/loader.css',
            [],
            MOTORLAN_API_VUE_VERSION,
            'all'
        );
        wp_enqueue_script(
            'motorlan-vue-app-js',
            plugin_dir_url(__FILE__) . '../app/dist/js/app.js',
            ['wp-data-bridge'], // Depende del puente
            MOTORLAN_API_VUE_VERSION,
            true
        );
    }
}

// Añade type="module" a los scripts de Vite
function add_module_type_to_vite_scripts($tag, $handle, $src) {
    if (in_array($handle, ['vite-client', 'vue-app-main'])) {
        return '<script type="module" src="' . esc_url($src) . '" id="' . $handle . '-js"></script>';
    }
    return $tag;
}
add_filter('script_loader_tag', 'add_module_type_to_vite_scripts', 10, 3);

function motorlan_vue_app_shortcode($atts = []) {
    $atts = shortcode_atts([
        'route' => '',
    ], $atts, 'motorlan_vue_app');

    motorlan_enqueue_vue_app();

    $route = trim((string) $atts['route']);
    $routeScript = '';

    if ($route !== '') {
        $route = ltrim($route, '#/');
        $hash = '#/' . $route;
        $routeScript = sprintf(
            '<script>if (!window.location.hash || window.location.hash === \'#/\' || window.location.hash === \'\') window.location.hash = \'%s\';</script>',
            esc_js($hash)
        );
    }

    return $routeScript . '<div id="motorlan-app" class="motorlan-app-root"></div>';
}
add_shortcode('motorlan_vue_app', 'motorlan_vue_app_shortcode');


function motorlan_dequeue_theme_styles() {
    if (!is_singular()) {
        return;
    }

    $post = get_post();
    if (!$post) {
        return;
    }

    if (has_shortcode($post->post_content, 'motorlan_vue_app')) {
        global $wp_styles;
        $styles_to_dequeue = [];

        $excluded_handles = [
            'motorlan-vue-app-css',
            'motorlan-vue-app-loader-css',
            'admin-bar',
            'dashicons'
        ];

        foreach ($wp_styles->registered as $handle => $style) {
            if (in_array($handle, $excluded_handles)) {
                continue;
            }

            $is_theme_style = strpos($style->src, '/themes/generatepress/') !== false;
            $is_premium_style = strpos($style->src, '/plugins/gp-premium/') !== false;
            $is_child_theme_style = strpos($style->src, '/themes/generatepress-child/') !== false;

            if ($is_theme_style || $is_premium_style || $is_child_theme_style) {
                $styles_to_dequeue[] = $handle;
            }
        }

        foreach ($styles_to_dequeue as $handle) {
            wp_dequeue_style($handle);
            wp_deregister_style($handle);
        }
    }
}
//add_action('wp_enqueue_scripts', 'motorlan_dequeue_theme_styles', 999);
