<?php

function motorlan_get_language_info() {
    $payload = motorlan_get_vue_i18n_payload();

    return [
        'current' => $payload['current_locale'],
        'locale' => $payload['language_locale'],
        'available' => $payload['languages'],
        'messages' => $payload['i18n_messages'],
        'supported_locales' => $payload['supported_locales'],
        'rtl_locales' => $payload['rtl_locales'],
    ];
}

function motorlan_enqueue_vue_app($args = []) {
    // URL del servidor de desarrollo de Vite
    $vite_dev_server = 'http://localhost:5173/';

    // Comprobamos si estamos en entorno local para intentar conectar con Vite
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $is_local = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false || (defined('MOTORLAN_VUE_DEV') && MOTORLAN_VUE_DEV));

    $is_vite_running = false;
    if ($is_local) {
        $context = stream_context_create(['http' => ['timeout' => 0.5]]);
        $headers = @get_headers($vite_dev_server, 0, $context);
        $is_vite_running = $headers && strpos($headers[0], '200');
    }

    // 1. Registrar un script "puente" vacío.
    // WordPress adjuntará los datos a este script.
    wp_register_script('wp-data-bridge', false);
    wp_enqueue_script('wp-data-bridge');

    // 2. Preparar y adjuntar los datos
    $language_info = motorlan_get_language_info();

    // Get current user from WordPress session only (no JWT)
    $user_data = [
        'is_logged_in' => false,
        'user' => null,
    ];

    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();

        if ($current_user && $current_user->ID > 0) {
            $user_data = [
                'is_logged_in' => true,
                'user' => [
                    'id' => $current_user->ID,
                    'email' => $current_user->user_email,
                    'display_name' => $current_user->display_name,
                    'nicename' => $current_user->user_nicename,
                    'is_admin' => in_array('administrator', (array) $current_user->roles),
                ],
            ];
        }
    }

    wp_localize_script('wp-data-bridge', 'wpData', [
        'site_url' => get_site_url(),
        'nonce' => wp_create_nonce('wp_rest'),
        'rest_nonce' => wp_create_nonce('wp_rest'),
        'user_data' => $user_data,
        'vue_base' => trailingslashit(wp_parse_url(get_permalink(), PHP_URL_PATH) ?: '/'),
        'language' => $language_info['current'],
        'language_locale' => $language_info['locale'],
        'languages' => $language_info['available'],
        'i18n_messages' => $language_info['messages'],
        'supported_locales' => $language_info['supported_locales'],
        'rtl_locales' => $language_info['rtl_locales'],
        'session_endpoint' => rest_url('motorlan/v1/session'),
        'login_endpoint' => rest_url('wp/v2/custom/login'),
        'initial_route' => $args['route'] ?? '',
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
        // Versión dinámica basada en mtime del archivo → cache-bust automático en cada build
        $dist = plugin_dir_path(__FILE__) . '../app/dist/';
        $css_ver = file_exists($dist . 'assets/app.css') ? filemtime($dist . 'assets/app.css') : MOTORLAN_API_VUE_VERSION;
        $js_ver  = file_exists($dist . 'assets/app.js')  ? filemtime($dist . 'assets/app.js')  : MOTORLAN_API_VUE_VERSION;

        wp_enqueue_style(
            'motorlan-vue-app-css',
            MOTORLAN_API_VUE_URL . 'app/dist/assets/app.css',
            [],
            $css_ver,
            'all'
        );
        if (file_exists($dist . 'loader.css')) {
            wp_enqueue_style(
                'motorlan-vue-app-loader-css',
                MOTORLAN_API_VUE_URL . 'app/dist/loader.css',
                [],
                $css_ver,
                'all'
            );
        }
        wp_enqueue_script(
            'motorlan-vue-app-js',
            MOTORLAN_API_VUE_URL . 'app/dist/assets/app.js',
            ['wp-data-bridge'],
            $js_ver,
            true
        );
    }
}

// Añade type="module" a los scripts de Vite
function add_module_type_to_vite_scripts($tag, $handle, $src) {
    if (in_array($handle, ['vite-client', 'vue-app-main', 'motorlan-vue-app-js'])) {
        return '<script type="module" src="' . esc_url($src) . '" id="' . $handle . '-js"></script>';
    }
    return $tag;
}
add_filter('script_loader_tag', 'add_module_type_to_vite_scripts', 10, 3);

function motorlan_vue_app_shortcode($atts = []) {
    $atts = shortcode_atts([
        'route' => '',
    ], $atts, 'motorlan_vue_app');

    motorlan_enqueue_vue_app(['route' => $atts['route']]);

    // Detección del contexto para el Skeleton
    $current_url = $_SERVER['REQUEST_URI'];
    $is_login_page = (strpos($current_url, '/login') !== false);
    $is_product_page = (strpos($current_url, '/marketplace-motorlan/') !== false && strlen(trim($current_url, '/')) > strlen('marketplace-motorlan'));
    $is_logged_in = is_user_logged_in();
    
    // Output Buffer para el HTML del Skeleton
    ob_start();
    ?>
    <style>
        /* Wrapper que contiene skeleton y app */
        .motorlan-app-wrapper {
            min-height: 800px;
            display: flex;
            flex-direction: column;
            position: relative;
            background: #fbfbfc;
        }

        /* Skeleton como overlay absoluto sobre la app */
        .motorlan-skeleton-overlay {
            position: absolute;
            inset: 0;
            z-index: 9999;
            transition: opacity 0.3s ease-out;
        }
        .motorlan-skeleton-overlay.is-hidden {
            opacity: 0;
            pointer-events: none;
        }

        /* La app de Vue ocupa todo el espacio */
        .motorlan-app-root {
            width: 100%;
            min-height: 100vh;
        }

        .motorlan-skeleton { width: 100%; height: 100%; background: #fbfbfc; font-family: sans-serif; min-height: 100vh; }
        .sk-pulse { animation: sk-loading 1.2s infinite alternate; border-radius: 6px; }
        @keyframes sk-loading { 0% { background-color: #f0f2f5; } 100% { background-color: #e4e6e9; } }
        
        /* Discrete Spinner */
        .sk-spinner-small {
            width: 20px;
            height: 20px;
            border: 2px solid #eee;
            border-top: 2px solid #9155fd;
            border-radius: 50%;
            animation: sk-spin 0.8s linear infinite;
        }
        @keyframes sk-spin { to { transform: rotate(360deg); } }

        /* Shared Components */
        .sk-grid { display: grid; gap: 2rem; padding: 2rem; }
        .sk-card-item { background: white; border-radius: 12px; border: 1px solid #eee; }

        /* Login Skeleton */
        .sk-login-container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #28243d; 
        }
        .sk-login-card {
            width: 400px;
            padding: 2.5rem;
            background: white;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .sk-login-logo { height: 48px; width: 140px; background: #f0f2f5; margin: 0 auto; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
        .sk-input { height: 52px; background: #f0f2f5; border-radius: 8px; }
        .sk-btn { height: 52px; background: #9155fd; border-radius: 8px; opacity: 0.6; }

        /* Dashboard/Store Skeleton */
        .sk-dashboard-layout { display: flex; min-height: 100vh; }
        .sk-sidebar {
            width: 260px;
            background: white;
            border-right: 1px solid #eee;
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
            gap: 1.2rem;
            flex-shrink: 0;
        }
        @media (max-width: 1280px) { .sk-sidebar { display: none; } }
        
        .sk-main { flex: 1; display: flex; flex-direction: column; }
        .sk-topbar {
            height: 64px;
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid #eee;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            padding: 0 2rem;
            justify-content: flex-end;
        }
        .sk-content { padding: 2rem; display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 2rem; }
        .sk-nav-db-item { height: 44px; background: #f8f9fa; border-radius: 8px; }
    </style>

    <div class="motorlan-app-wrapper">
        <!-- Skeleton Overlay (fuera de Vue, no será reemplazado) -->
        <div id="motorlan-skeleton-overlay" class="motorlan-skeleton-overlay">
        <?php if ($is_login_page && !$is_logged_in): ?>
            <!-- Login Skeleton -->
            <div class="motorlan-skeleton sk-login-container">
                <div class="sk-login-card">
                    <div class="sk-login-logo">
                        <div class="sk-spinner-small"></div>
                    </div>
                    <div class="sk-pulse sk-input" style="margin-top: 1rem;"></div>
                    <div class="sk-pulse sk-input"></div>
                    <div class="sk-pulse sk-btn" style="margin-top: 1rem;"></div>
                </div>
            </div>
        <?php elseif ($is_product_page): ?>
            <!-- Product Detail Skeleton -->
            <div class="motorlan-skeleton">
                <div style="height: 64px; border-bottom: 1px solid #eee; display: flex; align-items: center; padding: 0 2rem; justify-content: flex-end;">
                     <div class="sk-spinner-small"></div>
                </div>
                <div style="padding: 2rem; max-width: 1440px; margin: 0 auto;">
                    <div class="sk-pulse" style="height: 40px; width: 60%; margin-bottom: 2rem;"></div>
                    <div style="display: grid; grid-template-columns: 1fr 400px; gap: 2rem;">
                        <div class="sk-pulse" style="aspect-ratio: 16/9; border-radius: 12px;"></div>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <div class="sk-card-item" style="padding: 2rem; height: 300px; display: flex; flex-direction: column; gap: 1rem;">
                                <div class="sk-pulse" style="height: 32px; width: 40%;"></div>
                                <div class="sk-pulse" style="height: 48px; width: 100%; border-radius: 8px; margin-top: auto;"></div>
                                <div class="sk-pulse" style="height: 48px; width: 100%; border-radius: 8px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Dashboard/Store Skeleton -->
            <div class="motorlan-skeleton sk-dashboard-layout">
                <!-- Sidebar -->
                <div class="sk-sidebar">
                    <div class="sk-pulse" style="height: 48px; width: 140px; background: #f0f2f5; margin-bottom: 2rem; border-radius: 8px;"></div>
                    <div class="sk-pulse sk-nav-db-item"></div>
                    <div class="sk-pulse sk-nav-db-item"></div>
                    <div class="sk-pulse sk-nav-db-item"></div>
                    <div class="sk-pulse sk-nav-db-item"></div>
                    <div class="sk-pulse sk-nav-db-item" style="margin-top: auto;"></div>
                </div>
                
                <!-- Main Content -->
                <div class="sk-main">
                    <div class="sk-topbar">
                        <div class="sk-spinner-small"></div>
                    </div>
                    <div class="sk-content">
                        <!-- Simulated Cards -->
                        <div class="sk-pulse sk-card-item" style="aspect-ratio: 0.8;"></div>
                        <div class="sk-pulse sk-card-item" style="aspect-ratio: 0.8;"></div>
                        <div class="sk-pulse sk-card-item" style="aspect-ratio: 0.8;"></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        </div>
        <!-- Vue App Container (separado del skeleton) -->
        <div id="motorlan-app" class="motorlan-app-root"></div>
    </div>
    <?php
    return ob_get_clean();
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

