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
        'vue_page_urls' => function_exists('motorlan_get_vue_page_urls_current_lang') ? motorlan_get_vue_page_urls_current_lang() : [],
        'language' => $language_info['current'],
        'language_locale' => $language_info['locale'],
        'languages' => $language_info['available'],
        'i18n_messages' => $language_info['messages'],
        'supported_locales' => $language_info['supported_locales'],
        'rtl_locales' => $language_info['rtl_locales'],
        'session_endpoint' => rest_url('motorlan/v1/session'),
        'nonce_refresh_url' => admin_url('admin-ajax.php?action=motorlan_rest_nonce'),
        'login_endpoint' => rest_url('wp/v2/custom/login'),
        'privacy_url' => get_privacy_policy_url() ?: home_url('/politica-de-privacidad/'),
        'initial_route' => $args['route'] ?? '',
        // Límites de subida (el asistente de publicaciones avisa antes de enviar)
        'max_upload_size' => wp_max_upload_size(),
        'post_max_size' => wp_convert_hr_to_bytes(ini_get('post_max_size')),
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
        $css_ver = file_exists($dist . 'css/style.css') ? filemtime($dist . 'css/style.css') : MOTORLAN_API_VUE_VERSION;
        $js_ver  = file_exists($dist . 'js/app.js') ? filemtime($dist . 'js/app.js') : MOTORLAN_API_VUE_VERSION;

        wp_enqueue_style(
            'motorlan-vue-app-css',
            MOTORLAN_API_VUE_URL . 'app/dist/css/style.css',
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
            MOTORLAN_API_VUE_URL . 'app/dist/js/app.js',
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

    // Detecta página producto comprobando contra todos los slugs traducidos de marketplace-motorlan
    $is_product_page = false;
    foreach ( motorlan_get_marketplace_page_slugs() as $mslug ) {
        if ( strpos($current_url, '/' . $mslug . '/') !== false && strlen(trim($current_url, '/')) > strlen($mslug) ) {
            $is_product_page = true;
            break;
        }
    }
    $is_logged_in = is_user_logged_in();

    // SEO: contenido real de la ficha dentro del contenedor de Vue (ver motorlan_render_store_publicacion_fallback)
    $store_publicacion = motorlan_current_store_publicacion();
    
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
        <!-- Vue App Container (separado del skeleton). En las fichas lleva el contenido
             de la publicación en HTML para buscadores y sin JS; Vue lo sustituye al montar. -->
        <div id="motorlan-app" class="motorlan-app-root"><?php
            if ( $store_publicacion ) {
                echo motorlan_render_store_publicacion_fallback( $store_publicacion ); // phpcs:ignore WordPress.Security.EscapeOutput -- escapado dentro
            }
        ?></div>
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

/**
 * Devuelve un nonce REST nuevo. Va por admin-ajax porque la API REST con cookie exige
 * un nonce válido para reconocer al usuario: con el nonce caducado (pestaña abierta
 * más de 12-24 h) todas las peticiones daban 403 rest_cookie_invalid_nonce.
 */
function motorlan_ajax_rest_nonce() {
    nocache_headers();
    // También para anónimos (mismo nonce que WP imprime en cualquier página pública);
    // logged_in permite al front detectar una sesión caducada.
    wp_send_json_success(['nonce' => wp_create_nonce('wp_rest'), 'logged_in' => is_user_logged_in()]);
}
add_action('wp_ajax_motorlan_rest_nonce', 'motorlan_ajax_rest_nonce');
add_action('wp_ajax_nopriv_motorlan_rest_nonce', 'motorlan_ajax_rest_nonce');

/* ============================================================================
 * SEO de las fichas de la tienda: /marketplace-motorlan/{slug}/
 *
 * La ficha la pinta la SPA (history mode) dentro de la página "marketplace-motorlan",
 * así que WordPress/Rank Math solo veían la página de la tienda: mismo <title>, misma
 * descripción y canonical a /marketplace-motorlan/ en las ~400 fichas (duplicados), el
 * sitemap apuntaba a /{slug}/ (404, rewrite del CPT con slug '/') y cualquier
 * /marketplace-motorlan/lo-que-sea/ respondía 200 (soft-404).
 *
 * Enfoque (sin SSR ni cambios en el build: sigue el bundle único app.js?ver=mtime):
 *  1. get_permalink() de una publicación = URL de la ficha en la SPA → sitemap, admin y
 *     enlaces correctos. /{slug}/ antiguos (404) y la plantilla del CPT redirigen (301).
 *  2. En la ficha: title, description, canonical, og:* y robots propios de la
 *     publicación (filtros de Rank Math o, sin Rank Math, etiquetas propias) + JSON-LD
 *     Product (sin precio si es "Consultar precio") + hreflang de WPML con el slug.
 *  3. El HTML lleva el contenido básico de la ficha (h1, imagen, descripción, datos)
 *     dentro de #motorlan-app; Vue lo reemplaza al montar. Google lo indexa aunque no
 *     ejecute el JS y no hay salto visual (el skeleton lo tapa hasta que monta la app).
 *  4. 404 real (status + noindex) para /marketplace-motorlan/{x}/ que no es ruta de la
 *     SPA ni publicación visible. La página se sigue sirviendo (la SPA muestra "no
 *     encontrada"). Vendidas/pausadas/sin stock: 200 con noindex.
 * ========================================================================== */

/**
 * Slugs de la página de la tienda (base ES + traducciones WPML).
 *
 * @return string[]
 */
function motorlan_get_marketplace_page_slugs() {
    static $slugs = null;
    if ( null !== $slugs ) {
        return $slugs;
    }

    $slugs = ['marketplace-motorlan'];
    $page  = get_page_by_path('marketplace-motorlan');
    if ( $page ) {
        $langs = apply_filters('wpml_active_languages', null, []);
        if ( is_array($langs) ) {
            foreach ( $langs as $lang ) {
                $code = is_array($lang) && isset($lang['code']) ? $lang['code'] : '';
                if ( ! $code ) {
                    continue;
                }
                $tx = apply_filters('wpml_object_id', $page->ID, 'page', false, $code);
                if ( $tx && (int) $tx !== (int) $page->ID ) {
                    $tx_post = get_post($tx);
                    if ( $tx_post && $tx_post->post_name ) {
                        $slugs[] = $tx_post->post_name;
                    }
                }
            }
        }
    }

    $slugs = array_values(array_unique($slugs));
    return $slugs;
}

/**
 * Segmento {x} de la URL /[idioma/]marketplace-motorlan/{x}/ actual, o null.
 *
 * @return array{slug: string, path: string, base: string}|null
 */
function motorlan_get_store_request_segment() {
    static $resolved = false, $segment = null;
    if ( $resolved ) {
        return $segment;
    }
    $resolved = true;

    if ( is_admin() || empty($_SERVER['REQUEST_URI']) ) {
        return null;
    }

    $path = trim((string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH), '/');
    // Atajo: sin subruta (o API REST) no hay ficha; evita resolver los slugs WPML
    if ( '' === $path || false === strpos($path, '/') || 0 === strpos($path, 'wp-json/') ) {
        return null;
    }

    foreach ( motorlan_get_marketplace_page_slugs() as $base ) {
        if ( preg_match('#^(?:[a-z]{2}(?:-[a-z]{2})?/)?' . preg_quote($base, '#') . '/([^/]+)$#i', $path, $m) ) {
            $segment = [
                'slug' => sanitize_title(urldecode($m[1])),
                'path' => $path,
                'base' => $base,
            ];
            break;
        }
    }

    return $segment;
}

/**
 * Publicación pública (publicada y no en borrador/pendiente ACF) de la ficha actual, o null.
 *
 * @return WP_Post|null
 */
function motorlan_current_store_publicacion() {
    static $resolved = false, $post = null;
    if ( $resolved ) {
        return $post;
    }
    $resolved = true;

    $segment = motorlan_get_store_request_segment();
    if ( ! $segment || '' === $segment['slug'] ) {
        return null;
    }

    $candidate = get_page_by_path($segment['slug'], OBJECT, 'publicacion');
    if ( $candidate && in_array($candidate->post_status, ['publish', 'paused', 'sold'], true)
        && ! in_array(get_post_meta($candidate->ID, 'publicar_acf', true), ['draft', 'pending'], true) ) {
        $post = $candidate;
    }

    return $post;
}

/**
 * ¿Se puede comprar? Vendidas, pausadas o sin stock: ficha visible pero noindex.
 *
 * @param int $post_id Publicación.
 * @return bool
 */
function motorlan_store_publicacion_is_available($post_id) {
    if ( 'publish' !== get_post_status($post_id) || in_array(get_post_meta($post_id, 'publicar_acf', true), ['paused', 'sold'], true) ) {
        return false;
    }
    $stock = get_post_meta($post_id, 'stock', true);
    if ( '' !== $stock && null !== $stock && (int) $stock <= 0 ) {
        return false;
    }
    return true;
}

/**
 * Datos SEO de una publicación (cacheados por petición).
 *
 * @param WP_Post $post Publicación.
 * @return array
 */
function motorlan_get_store_publicacion_seo($post) {
    static $cache = [];
    if ( isset($cache[$post->ID]) ) {
        return $cache[$post->ID];
    }

    // Datos públicos (sin precio oculto ni datos del vendedor); sin galería
    $data = function_exists('motorlan_get_publicacion_data') ? motorlan_get_publicacion_data($post->ID, false, false, true) : [];
    $acf  = isset($data['acf']) && is_array($data['acf']) ? $data['acf'] : [];

    $title = isset($data['title']) && '' !== $data['title'] ? $data['title'] : get_the_title($post);
    $brand = isset($data['marca_name']) ? (string) $data['marca_name'] : '';
    $ref   = isset($acf['tipo_o_referencia']) && is_scalar($acf['tipo_o_referencia']) ? (string) $acf['tipo_o_referencia'] : '';
    $tipo  = ! empty($data['tipo'][0]['name']) ? (string) $data['tipo'][0]['name'] : '';

    $specs = [];
    $spec_map = [
        'marca'             => [__('Marca', 'motorlan-api-vue'), $brand, ''],
        'referencia'        => [__('Referencia', 'motorlan-api-vue'), $ref, ''],
        'tipo'              => [__('Tipo', 'motorlan-api-vue'), $tipo, ''],
        'potencia'          => [__('Potencia', 'motorlan-api-vue'), $acf['potencia'] ?? '', ' kW'],
        'velocidad'         => [__('Velocidad', 'motorlan-api-vue'), $acf['velocidad'] ?? '', ' rpm'],
        'par_nominal'       => [__('Par nominal', 'motorlan-api-vue'), $acf['par_nominal'] ?? '', ' Nm'],
        'voltaje'           => [__('Voltaje', 'motorlan-api-vue'), $acf['voltaje'] ?? '', ' V'],
        'intensidad'        => [__('Intensidad', 'motorlan-api-vue'), $acf['intensidad'] ?? '', ' A'],
        'provincia'         => [__('Ubicación', 'motorlan-api-vue'), $acf['provincia'] ?? '', ''],
    ];
    foreach ( $spec_map as $key => $spec ) {
        $value = is_scalar($spec[1]) ? trim((string) $spec[1]) : '';
        if ( '' !== $value ) {
            $specs[$key] = ['label' => $spec[0], 'value' => $value . $spec[2]];
        }
    }

    $description_html = isset($acf['descripcion']) && is_string($acf['descripcion']) ? $acf['descripcion'] : '';
    $description_text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($description_html)));
    if ( '' === $description_text ) {
        $parts = [];
        foreach ( $specs as $spec ) {
            $parts[] = $spec['label'] . ': ' . $spec['value'];
        }
        $description_text = $title . ( $parts ? '. ' . implode('. ', $parts) : '' );
    }
    $meta_description = wp_html_excerpt($description_text, 155, '…');

    $image = '';
    if ( ! empty($data['imagen_destacada']['url']) ) {
        $image = (string) $data['imagen_destacada']['url'];
    }

    // Precio solo si es público (motorlan_get_publicacion_data ya lo quita con "Consultar precio")
    $price = isset($acf['precio_de_venta']) && is_numeric($acf['precio_de_venta']) && (float) $acf['precio_de_venta'] > 0
        ? (float) $acf['precio_de_venta']
        : null;

    // Canonical: la propia URL de la ficha en el idioma actual (con el slug real)
    $canonical = function_exists('motorlan_get_store_url') ? motorlan_get_store_url($post->ID) : get_permalink($post);
    $segment   = motorlan_get_store_request_segment();
    if ( $segment ) {
        $origin    = preg_replace('#^(https?://[^/]+).*$#i', '$1', (string) get_option('home'));
        $base_path = substr($segment['path'], 0, strrpos($segment['path'], '/'));
        $canonical = $origin . '/' . $base_path . '/' . $post->post_name . '/';
    }

    $cache[$post->ID] = [
        'title'            => $title,
        'seo_title'        => wp_strip_all_tags($title . ( $brand && false === stripos($title, $brand) ? ' ' . $brand : '' ) . ' | Motorlan'),
        'meta_description' => $meta_description,
        'description_html' => $description_html,
        'image'            => $image,
        'price'            => $price,
        'brand'            => $brand,
        'reference'        => $ref,
        'condition'        => isset($acf['estado_del_articulo']) && is_scalar($acf['estado_del_articulo']) ? (string) $acf['estado_del_articulo'] : '',
        'specs'            => $specs,
        'canonical'        => $canonical,
        'available'        => motorlan_store_publicacion_is_available($post->ID),
    ];

    return $cache[$post->ID];
}

/**
 * HTML básico de la ficha dentro de #motorlan-app (Vue lo sustituye al montar).
 *
 * @param WP_Post $post Publicación.
 * @return string
 */
function motorlan_render_store_publicacion_fallback($post) {
    $seo = motorlan_get_store_publicacion_seo($post);
    $store_url = function_exists('motorlan_localized_page_url_absolute') ? motorlan_localized_page_url_absolute('marketplace-motorlan') : home_url('/marketplace-motorlan/');

    ob_start();
    ?>
    <article class="motorlan-ssr-product" style="max-width:1200px;margin:0 auto;padding:2rem 1rem;font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;">
        <h1><?php echo esc_html($seo['title']); ?></h1>
        <?php if ( $seo['image'] ) : ?>
            <img src="<?php echo esc_url($seo['image']); ?>" alt="<?php echo esc_attr($seo['title']); ?>" style="max-width:100%;height:auto;" loading="lazy">
        <?php endif; ?>
        <?php if ( ! $seo['available'] ) : ?>
            <p><strong><?php esc_html_e('Este artículo no está disponible actualmente.', 'motorlan-api-vue'); ?></strong></p>
        <?php elseif ( null !== $seo['price'] ) : ?>
            <p><strong><?php echo esc_html(number_format_i18n($seo['price'], 2)); ?> €</strong></p>
        <?php else : ?>
            <p><strong><?php esc_html_e('Consultar precio', 'motorlan-api-vue'); ?></strong></p>
        <?php endif; ?>
        <?php if ( $seo['specs'] ) : ?>
            <dl>
                <?php foreach ( $seo['specs'] as $spec ) : ?>
                    <dt><?php echo esc_html($spec['label']); ?></dt>
                    <dd><?php echo esc_html($spec['value']); ?></dd>
                <?php endforeach; ?>
            </dl>
        <?php endif; ?>
        <?php if ( '' !== trim(wp_strip_all_tags($seo['description_html'])) ) : ?>
            <div class="motorlan-ssr-product__description"><?php echo wp_kses_post(wpautop($seo['description_html'])); ?></div>
        <?php endif; ?>
        <p><a href="<?php echo esc_url($store_url); ?>"><?php esc_html_e('Ver más artículos en la tienda', 'motorlan-api-vue'); ?></a></p>
    </article>
    <?php
    return ob_get_clean();
}

/**
 * ¿Hay un plugin SEO que pinte title/description/canonical? (producción: Rank Math)
 *
 * @return bool
 */
function motorlan_seo_plugin_active() {
    return defined('RANK_MATH_VERSION') || class_exists('RankMath');
}

/**
 * 1. Permalink real de las publicaciones = ficha en la SPA (sitemap, admin, emails).
 */
function motorlan_publicacion_post_type_link($link, $post) {
    if ( $post instanceof WP_Post && 'publicacion' === $post->post_type && 'publish' === $post->post_status
        && $post->post_name && function_exists('motorlan_get_store_url') ) {
        return motorlan_get_store_url($post->ID);
    }
    return $link;
}
add_filter('post_type_link', 'motorlan_publicacion_post_type_link', 10, 2);

/**
 * /{slug}/ antiguos (los publicaba el sitemap y daban 404): 301 a la ficha.
 * 404 real para /marketplace-motorlan/{x}/ que no existe (antes 200 = soft-404).
 */
/**
 * ID de la publicación publicada cuyo slug ANTERIOR era $slug (WordPress guarda
 * _wp_old_slug al cambiarlo al editar/aprobar), o 0.
 */
function motorlan_current_query_suffix() {
    // Conserva ?open_chat=1&ct=... (enlace del email) al redirigir a la URL nueva
    $qs = isset($_SERVER['QUERY_STRING']) ? (string) $_SERVER['QUERY_STRING'] : '';
    return '' === $qs ? '' : '?' . $qs;
}

function motorlan_find_publicacion_by_old_slug($slug) {
    $ids = get_posts([
        'post_type'      => 'publicacion',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_key'       => '_wp_old_slug',
        'meta_value'     => sanitize_title($slug),
    ]);
    return $ids ? (int) $ids[0] : 0;
}

function motorlan_store_seo_template_redirect() {
    // /{slug}/ de una publicación publicada → 301 a la ficha
    if ( is_404() && ! empty($_SERVER['REQUEST_URI']) ) {
        $path = trim((string) wp_parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH), '/');
        if ( '' !== $path && false === strpos($path, '/') ) {
            $legacy = get_page_by_path(sanitize_title(urldecode($path)), OBJECT, 'publicacion');
            if ( $legacy && 'publish' === $legacy->post_status && function_exists('motorlan_get_store_url') ) {
                wp_safe_redirect(motorlan_get_store_url($legacy->ID) . motorlan_current_query_suffix(), 301);
                exit;
            }
            // Slug antiguo (cambió al editar): también 301 a la ficha actual
            $renamed_id = function_exists('motorlan_get_store_url') ? motorlan_find_publicacion_by_old_slug(urldecode($path)) : 0;
            if ( $renamed_id ) {
                wp_safe_redirect(motorlan_get_store_url($renamed_id) . motorlan_current_query_suffix(), 301);
                exit;
            }
        }
        return;
    }

    $segment = motorlan_get_store_request_segment();
    if ( ! $segment || motorlan_current_store_publicacion() ) {
        return;
    }

    // Rutas de primer nivel de la SPA (app/src/pages) que cuelgan de la tienda
    $spa_routes = apply_filters('motorlan_store_spa_routes', [
        'dashboard', 'login', 'register', 'register-success', 'forgot-password', 'reset-password',
        'verify-email', 'store', 'public-store', 'ai-search', 'assistant', 'access-control', 'not-authorized',
    ]);
    if ( in_array($segment['slug'], $spa_routes, true) ) {
        return;
    }

    // URL de ficha con slug antiguo (cambió al editar marca/referencia/potencia): 301
    $renamed_id = function_exists('motorlan_get_store_url') ? motorlan_find_publicacion_by_old_slug($segment['slug']) : 0;
    if ( $renamed_id ) {
        wp_safe_redirect(motorlan_get_store_url($renamed_id) . motorlan_current_query_suffix(), 301);
        exit;
    }

    // El dueño o un admin pueden ver su publicación no publicada (la SPA la muestra)
    if ( is_user_logged_in() ) {
        $own = get_page_by_path($segment['slug'], OBJECT, 'publicacion');
        if ( $own && ( current_user_can('manage_options') || (int) $own->post_author === get_current_user_id() ) ) {
            return;
        }
    }

    status_header(404);
    nocache_headers();
    add_filter('wp_robots', 'wp_robots_no_robots');
    add_filter('rank_math/frontend/robots', function () {
        return ['index' => 'noindex', 'follow' => 'nofollow'];
    }, 99);
}
add_action('template_redirect', 'motorlan_store_seo_template_redirect', 5);

/**
 * 2. Metadatos de la ficha.
 */
function motorlan_store_seo_setup() {
    $post = motorlan_current_store_publicacion();
    if ( ! $post ) {
        return;
    }
    $seo = motorlan_get_store_publicacion_seo($post);

    if ( motorlan_seo_plugin_active() ) {
        // Rank Math
        add_filter('rank_math/frontend/title', function () use ($seo) { return $seo['seo_title']; }, 99);
        add_filter('rank_math/frontend/description', function () use ($seo) { return $seo['meta_description']; }, 99);
        add_filter('rank_math/frontend/canonical', function () use ($seo) { return $seo['canonical']; }, 99);
        add_filter('rank_math/opengraph/url', function () use ($seo) { return $seo['canonical']; }, 99);
        add_filter('rank_math/opengraph/facebook/og_title', function () use ($seo) { return $seo['seo_title']; }, 99);
        add_filter('rank_math/opengraph/facebook/og_description', function () use ($seo) { return $seo['meta_description']; }, 99);
        add_filter('rank_math/opengraph/twitter/twitter_title', function () use ($seo) { return $seo['seo_title']; }, 99);
        add_filter('rank_math/opengraph/twitter/twitter_description', function () use ($seo) { return $seo['meta_description']; }, 99);
        if ( $seo['image'] ) {
            add_action('rank_math/opengraph/facebook/add_additional_images', function ($image) use ($seo) {
                if ( is_object($image) && method_exists($image, 'add_image') ) {
                    $image->add_image($seo['image']);
                }
            });
            add_action('rank_math/opengraph/twitter/add_additional_images', function ($image) use ($seo) {
                if ( is_object($image) && method_exists($image, 'add_image') ) {
                    $image->add_image($seo['image']);
                }
            });
        }
        if ( ! $seo['available'] ) {
            add_filter('rank_math/frontend/robots', function ($robots) {
                $robots = is_array($robots) ? $robots : [];
                $robots['index'] = 'noindex';
                $robots['follow'] = 'follow';
                return $robots;
            }, 99);
        }
    } else {
        // Sin plugin SEO: etiquetas propias
        add_filter('pre_get_document_title', function () use ($seo) { return $seo['seo_title']; }, 99);
        remove_action('wp_head', 'rel_canonical');
        add_action('wp_head', function () use ($seo) {
            echo '<meta name="description" content="' . esc_attr($seo['meta_description']) . '">' . "\n";
            echo '<link rel="canonical" href="' . esc_url($seo['canonical']) . '">' . "\n";
            echo '<meta property="og:type" content="product">' . "\n";
            echo '<meta property="og:title" content="' . esc_attr($seo['seo_title']) . '">' . "\n";
            echo '<meta property="og:description" content="' . esc_attr($seo['meta_description']) . '">' . "\n";
            echo '<meta property="og:url" content="' . esc_url($seo['canonical']) . '">' . "\n";
            if ( $seo['image'] ) {
                echo '<meta property="og:image" content="' . esc_url($seo['image']) . '">' . "\n";
            }
            echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        }, 1);
        if ( ! $seo['available'] ) {
            add_filter('wp_robots', function ($robots) {
                $robots['noindex'] = true;
                $robots['follow'] = true;
                unset($robots['index']);
                return $robots;
            }, 99);
        }
    }

    // hreflang de WPML: las alternativas de la página de la tienda + el slug de la ficha
    add_filter('wpml_hreflangs', function ($hreflangs) use ($post) {
        if ( ! is_array($hreflangs) ) {
            return $hreflangs;
        }
        foreach ( $hreflangs as $code => $url ) {
            if ( is_string($url) && false === strpos($url, '/' . $post->post_name . '/') ) {
                $hreflangs[$code] = trailingslashit($url) . $post->post_name . '/';
            }
        }
        return $hreflangs;
    }, 99);

    // JSON-LD Product (precio solo si es público)
    add_action('wp_head', function () use ($seo) {
        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => $seo['title'],
            'description' => $seo['meta_description'],
            'url'         => $seo['canonical'],
        ];
        if ( $seo['image'] ) {
            $schema['image'] = [$seo['image']];
        }
        if ( $seo['brand'] ) {
            $schema['brand'] = ['@type' => 'Brand', 'name' => $seo['brand']];
        }
        if ( $seo['reference'] ) {
            $schema['mpn'] = $seo['reference'];
        }
        if ( null !== $seo['price'] ) {
            $condition_map = [
                'nuevo'         => 'https://schema.org/NewCondition',
                'new'           => 'https://schema.org/NewCondition',
                'usado'         => 'https://schema.org/UsedCondition',
                'used'          => 'https://schema.org/UsedCondition',
                'restaurado'    => 'https://schema.org/RefurbishedCondition',
                'reacondicionado' => 'https://schema.org/RefurbishedCondition',
                'restored'      => 'https://schema.org/RefurbishedCondition',
            ];
            $condition = strtolower(remove_accents($seo['condition']));
            $offer = [
                '@type'         => 'Offer',
                'url'           => $seo['canonical'],
                'price'         => number_format($seo['price'], 2, '.', ''),
                'priceCurrency' => 'EUR',
                'availability'  => $seo['available'] ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            ];
            if ( isset($condition_map[$condition]) ) {
                $offer['itemCondition'] = $condition_map[$condition];
            }
            $schema['offers'] = $offer;
        }
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }, 20);
}
add_action('wp', 'motorlan_store_seo_setup');

/**
 * Sitemaps: los CPT privados (compras, opiniones, preguntas, ofertas) nunca.
 */
function motorlan_exclude_private_post_types_from_sitemaps($post_types) {
    foreach ( ['compra', 'opinion', 'pregunta', 'oferta'] as $private_type ) {
        unset($post_types[$private_type]);
    }
    return $post_types;
}
add_filter('wp_sitemaps_post_types', 'motorlan_exclude_private_post_types_from_sitemaps');
add_filter('rank_math/sitemap/exclude_post_type', function ($exclude, $type) {
    return in_array($type, ['compra', 'opinion', 'pregunta', 'oferta'], true) ? true : $exclude;
}, 10, 2);
