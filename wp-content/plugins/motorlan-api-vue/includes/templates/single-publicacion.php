<?php
/**
 * Template for displaying a single "Publicacion" post type,
 * serving as a bridge for the Vue application.
 *
 * @package motorlan-api-vue
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_id = get_the_ID();
$post = get_post($post_id);
$publicacion_data = motorlan_get_publicacion_data($post_id);

// Prepare SEO data
$title_parts = [
    $publicacion_data['title'] ?? '',
    $publicacion_data['marca_name'] ?? '',
    $publicacion_data['acf']['tipo_o_referencia'] ?? '',
];
$seo_title = implode(' ', array_filter($title_parts));
$seo_description = $publicacion_data['acf']['resumen'] ?? get_the_excerpt($post_id);
$seo_image_raw = $publicacion_data['imagen_destacada'] ?: '';

// Handle ACF Image Array or URL
$seo_image = '';
if (is_array($seo_image_raw) && isset($seo_image_raw['url'])) {
    $seo_image = $seo_image_raw['url'];
} elseif (is_string($seo_image_raw)) {
    $seo_image = $seo_image_raw;
}

// Disable GeneratePress Elements
add_filter('generate_sidebar_layout', function() { return 'no-sidebar'; }, 100);
add_filter('generate_show_title', '__return_false');
add_filter('generate_show_entry_header', '__return_false');

// Disable Page Header of GeneratePress if active
add_action('wp', function() {
    remove_action('generate_after_header', 'generate_page_header', 10);
    remove_action('generate_before_content', 'generate_page_header', 10);
    // Disable Page Header Element from GP Premium
    add_filter('generate_get_element_display', function($display, $element_id) {
        if (get_post_type(get_the_ID()) === 'element' && get_post_meta($element_id, '_generate_element_type', true) === 'header') {
            return false;
        }
        return $display;
    }, 100, 2);
}, 20);

// Add Meta Tags to wp_head
add_action('wp_head', function() use ($seo_description, $seo_image, $seo_title) {
    echo '<meta name="description" content="' . esc_attr($seo_description) . '">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($seo_title) . '">' . "\n";
    echo '<meta property="og:description" content="' . esc_attr($seo_description) . '">' . "\n";
    if ($seo_image) {
        echo '<meta property="og:image" content="' . esc_url($seo_image) . '">' . "\n";
    }
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    
    // Custom CSS to ensure full width and no extra margins
    echo '<style>
        .single-publicacion .site-content { padding: 0 !important; max-width: 100% !important; }
        .single-publicacion .site-main { margin: 0 !important; }
        .single-publicacion .entry-content { margin: 0 !important; }
        .single-publicacion .grid-container { max-width: 100% !important; padding: 0 !important; }
        .page-header-content, .generate-page-header, .page-header, .page-hero { display: none !important; } /* Hide ALL GP header/hero variants */
                /* Base Styles for Skeleton */
        .motorlan-app-root { min-height: 800px; position: relative; }
        .motorlan-ssr-placeholder {
            display: block;
            width: 100%;
            max-width: 1440px;
            margin: 0 auto;
            background-color: #fbfbfc;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            min-height: 100vh;
        }

        /* Base Skeleton Styles */
        .motorlan-app-root { 
            min-height: 800px; 
            display: flex; 
            flex-direction: column; 
            position: relative;
            background: #fbfbfc;
        }

        /* Fade-in transition for the app once it mounts */
        .motorlan-app-root.is-loading > *:not(.motorlan-ssr-placeholder) { opacity: 0; }
        .motorlan-app-root.is-mounted > *:not(.motorlan-ssr-placeholder) { 
            opacity: 1; 
            transition: opacity 0.5s ease-in-out; 
        }

        .motorlan-ssr-placeholder { width: 100%; height: 100%; background: #fbfbfc; font-family: sans-serif; min-height: 100vh; }
        .skeleton-pulse { animation: skeleton-loading 1.2s infinite alternate; border-radius: 6px; }
        @keyframes skeleton-loading { 0% { background-color: #f0f2f5; } 100% { background-color: #e4e6e9; } }
        
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
        .sk-card-item { background: white; border-radius: 12px; border: 1px solid #eee; }

        /* Header Skeleton */
        .sk-header {
            height: 64px;
            background: white;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            padding: 0 2rem;
            margin-bottom: 2rem;
            gap: 1rem;
        }
        .sk-logo { width: 40px; height: 40px; border-radius: 50%; }
        .sk-nav-item { width: 100px; height: 20px; }

        /* Product Layout */
        .sk-product-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
            padding: 0 2rem 2rem;
        }
        
        @media (max-width: 960px) {
            .sk-product-container { grid-template-columns: 1fr; }
        }

        /* Main Content Column */
        .sk-main-col { display: flex; flex-direction: column; gap: 1.5rem; }
        
        .sk-breadcrumb { width: 200px; height: 16px; margin-bottom: 0.5rem; }
        .sk-title { width: 70%; height: 40px; margin-bottom: 1rem; }
        .sk-gallery { 
            width: 100%; 
            aspect-ratio: 16/9; 
            border-radius: 12px; 
        }
        
        .sk-tabs { display: flex; gap: 1rem; margin-top: 1rem; }
        .sk-tab { width: 120px; height: 40px; border-radius: 8px 8px 0 0; }
        
        .sk-desc-line { height: 16px; margin-bottom: 0.8rem; }
        .sk-desc-line:last-child { width: 80%; }

        /* Sidebar Column */
        .sk-sidebar-col { display: flex; flex-direction: column; gap: 1rem; }
        .sk-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid #eee;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .sk-price { width: 150px; height: 32px; }
        .sk-btn { width: 100%; height: 48px; border-radius: 8px; }
        .sk-meta-row { display: flex; justify-content: space-between; }
        .sk-meta-label { width: 80px; height: 16px; }
        .sk-meta-value { width: 60px; height: 16px; }

    </style>' . "\n";
}, 1);

get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <?php
        // We render the container with data-attributes to help Vue hydrate faster if needed,
        // although Vue will still fetch data from the API to ensure real-time consistency.
        ?>
        <div id="motorlan-app" 
             class="motorlan-app-root is-loading" 
             data-slug="<?php echo esc_attr($post->post_name); ?>"
             data-post-id="<?php echo esc_attr($post_id); ?>">
            
            <div class="motorlan-ssr-placeholder">
                <!-- Header -->
                <div class="sk-header">
                    <div class="skeleton-pulse sk-logo"></div>
                    <div class="skeleton-pulse sk-nav-item"></div>
                    <div style="flex:1"></div>
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <div class="sk-spinner-small"></div>
                        <div class="skeleton-pulse sk-nav-item" style="width: 150px"></div>
                    </div>
                </div>

                <!-- Product Content -->
                <div class="sk-product-container">
                    <!-- Main Column -->
                    <div class="sk-main-col">
                        <div class="skeleton-pulse sk-breadcrumb"></div>
                        <div class="skeleton-pulse sk-title"></div>
                        
                        <!-- Gallery Placeholder -->
                        <div class="skeleton-pulse sk-gallery"></div>
                        
                        <!-- Specs / Description Tabs -->
                        <div class="sk-tabs">
                            <div class="skeleton-pulse sk-tab"></div>
                            <div class="skeleton-pulse sk-tab"></div>
                        </div>
                        
                        <div class="sk-card" style="border:none; padding: 0;">
                            <div class="skeleton-pulse sk-desc-line"></div>
                            <div class="skeleton-pulse sk-desc-line"></div>
                            <div class="skeleton-pulse sk-desc-line"></div>
                            <div class="skeleton-pulse sk-desc-line"></div>
                        </div>
                    </div>

                    <!-- Sidebar Column -->
                    <div class="sk-sidebar-col">
                        <!-- Price Card -->
                        <div class="sk-card">
                            <div class="skeleton-pulse sk-price"></div>
                            <div class="skeleton-pulse sk-desc-line" style="width: 100px"></div>
                            <div style="border-top: 1px solid #eee; margin: 0.5rem 0;"></div>
                            <div class="skeleton-pulse sk-btn"></div>
                            <div class="skeleton-pulse sk-btn" style="height: 40px; opacity: 0.7"></div>
                        </div>

                        <!-- Seller Info Card -->
                        <div class="sk-card">
                            <div style="display:flex; gap:1rem; align-items:center;">
                                <div class="skeleton-pulse sk-logo"></div>
                                <div class="skeleton-pulse sk-nav-item"></div>
                            </div>
                            <div class="skeleton-pulse sk-desc-line"></div>
                        </div>

                        <!-- Specs Card -->
                        <div class="sk-card">
                            <div class="sk-meta-row">
                                <div class="skeleton-pulse sk-meta-label"></div>
                                <div class="skeleton-pulse sk-meta-value"></div>
                            </div>
                            <div class="sk-meta-row">
                                <div class="skeleton-pulse sk-meta-label"></div>
                                <div class="skeleton-pulse sk-meta-value"></div>
                            </div>
                            <div class="sk-meta-row">
                                <div class="skeleton-pulse sk-meta-label"></div>
                                <div class="skeleton-pulse sk-meta-value"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php
        // Force enqueue of the Vue App
        if (function_exists('motorlan_enqueue_vue_app')) {
            motorlan_enqueue_vue_app();
        }
        ?>
        

    </main>
</div>

<?php get_footer(); ?>
