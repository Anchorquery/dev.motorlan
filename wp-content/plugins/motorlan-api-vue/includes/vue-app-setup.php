<?php

function motorlan_enqueue_vue_app() {
    // Enqueue the CSS file
    wp_enqueue_style(
        'motorlan-vue-app-css',
        plugin_dir_url( __FILE__ ) . '../app/dist/assets/index-BDdWQf78.css',
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
        plugin_dir_url( __FILE__ ) . '../app/dist/assets/index-8q2sCTLg.js',
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

function motorlan_vue_app_shortcode() {
    motorlan_enqueue_vue_app();
    return '<div id="app"></div>';
}

add_shortcode( 'motorlan_vue_app', 'motorlan_vue_app_shortcode' );
