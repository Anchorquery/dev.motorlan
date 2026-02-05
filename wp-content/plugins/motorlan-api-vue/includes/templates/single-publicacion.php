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
        .motorlan-ssr-placeholder { display: none; }
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
             class="motorlan-app-root" 
             data-slug="<?php echo esc_attr($post->post_name); ?>"
             data-post-id="<?php echo esc_attr($post_id); ?>">
            
            <div class="motorlan-ssr-placeholder" style="padding: 2rem; text-align: center;">
                <h1 style="text-transform: uppercase;"><?php echo esc_html($seo_title); ?></h1>
                <p><?php echo esc_html($seo_description); ?></p>
                <!-- This content will be replaced by Vue once it loads -->
                <div class="v-progress-circular v-progress-circular--indeterminate" style="height: 64px; width: 64px;">
                    <svg viewBox="22.857142857142858 22.857142857142858 45.714285714285715 45.714285714285715" style="transform: rotate(0deg);">
                        <circle fill="transparent" cx="45.714285714285715" cy="45.714285714285715" r="20" stroke="currentColor" stroke-width="5.714285714285714" stroke-dasharray="125.66370614359172" stroke-dashoffset="125.66370614359172px"></circle>
                    </svg>
                </div>
            </div>
        </div>

        <?php
        // Force enqueue of the Vue App
        if (function_exists('motorlan_enqueue_vue_app')) {
            motorlan_enqueue_vue_app();
        }
        ?>
        
        <script>
            // History mode: Redirigir a la tienda con URL limpia
            window.location.href = '/marketplace-motorlan/<?php echo esc_attr($post->post_name); ?>';
        </script>
    </main>
</div>

<?php get_footer(); ?>
