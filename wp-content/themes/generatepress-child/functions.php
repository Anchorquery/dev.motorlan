<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if ( !function_exists( 'chld_thm_cfg_locale_css' ) ):
    function chld_thm_cfg_locale_css( $uri ){
        if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) )
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );

// END ENQUEUE PARENT ACTION
add_filter('use_block_editor_for_post', '__return_false', 10);

// Enqueue gallery assets for motor detail template
function motorlan_enqueue_motor_detail_assets() {
    if (is_singular('motor')) {
        wp_enqueue_style(
            'lightgallery',
            'https://cdn.jsdelivr.net/npm/lightgallery@2.7.1/css/lightgallery-bundle.min.css',
            array(),
            '2.7.1'
        );
        wp_enqueue_script(
            'lightgallery',
            'https://cdn.jsdelivr.net/npm/lightgallery@2.7.1/lightgallery.umd.min.js',
            array(),
            '2.7.1',
            true
        );
        wp_enqueue_script(
            'lightgallery-thumbnail',
            'https://cdn.jsdelivr.net/npm/lightgallery@2.7.1/plugins/thumbnail/lg-thumbnail.umd.min.js',
            array('lightgallery'),
            '2.7.1',
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'motorlan_enqueue_motor_detail_assets');
