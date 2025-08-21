<?php
/**
 * Setup for Custom Post Types related to user activities.
 *
 * @package motorlan-api-vue
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Register a custom post type for "purchase".
 */
function motorlan_register_purchase_cpt() {
    $labels = array(
        'name'               => _x( 'Purchases', 'post type general name', 'motorlan-api-vue' ),
        'singular_name'      => _x( 'Purchase', 'post type singular name', 'motorlan-api-vue' ),
        'menu_name'          => _x( 'Purchases', 'admin menu', 'motorlan-api-vue' ),
        'name_admin_bar'     => _x( 'Purchase', 'add new on admin bar', 'motorlan-api-vue' ),
        'add_new'            => _x( 'Add New', 'purchase', 'motorlan-api-vue' ),
        'add_new_item'       => __( 'Add New Purchase', 'motorlan-api-vue' ),
        'new_item'           => __( 'New Purchase', 'motorlan-api-vue' ),
        'edit_item'          => __( 'Edit Purchase', 'motorlan-api-vue' ),
        'view_item'          => __( 'View Purchase', 'motorlan-api-vue' ),
        'all_items'          => __( 'All Purchases', 'motorlan-api-vue' ),
        'search_items'       => __( 'Search Purchases', 'motorlan-api-vue' ),
        'not_found'          => __( 'No purchases found.', 'motorlan-api-vue' ),
        'not_found_in_trash' => __( 'No purchases found in Trash.', 'motorlan-api-vue' )
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'purchase' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 6,
        'supports'           => array( 'title', 'editor', 'author', 'custom-fields' ),
        'show_in_rest'       => true,
    );

    register_post_type( 'purchase', $args );
}
add_action( 'init', 'motorlan_register_purchase_cpt' );

/**
 * Register a custom post type for "question".
 */
function motorlan_register_question_cpt() {
    $labels = array(
        'name'               => _x( 'Questions', 'post type general name', 'motorlan-api-vue' ),
        'singular_name'      => _x( 'Question', 'post type singular name', 'motorlan-api-vue' ),
        'menu_name'          => _x( 'Questions', 'admin menu', 'motorlan-api-vue' ),
        'name_admin_bar'     => _x( 'Question', 'add new on admin bar', 'motorlan-api-vue' ),
        'add_new'            => _x( 'Add New', 'question', 'motorlan-api-vue' ),
        'add_new_item'       => __( 'Add New Question', 'motorlan-api-vue' ),
        'new_item'           => __( 'New Question', 'motorlan-api-vue' ),
        'edit_item'          => __( 'Edit Question', 'motorlan-api-vue' ),
        'view_item'          => __( 'View Question', 'motorlan-api-vue' ),
        'all_items'          => __( 'All Questions', 'motorlan-api-vue' ),
        'search_items'       => __( 'Search Questions', 'motorlan-api-vue' ),
        'not_found'          => __( 'No questions found.', 'motorlan-api-vue' ),
        'not_found_in_trash' => __( 'No questions found in Trash.', 'motorlan-api-vue' )
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'question' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 7,
        'supports'           => array( 'title', 'editor', 'author', 'custom-fields' ),
        'show_in_rest'       => true,
    );

    register_post_type( 'question', $args );
}
add_action( 'init', 'motorlan_register_question_cpt' );

/**
 * Register a custom post type for "review".
 */
function motorlan_register_review_cpt() {
    $labels = array(
        'name'               => _x( 'Reviews', 'post type general name', 'motorlan-api-vue' ),
        'singular_name'      => _x( 'Review', 'post type singular name', 'motorlan-api-vue' ),
        'menu_name'          => _x( 'Reviews', 'admin menu', 'motorlan-api-vue' ),
        'name_admin_bar'     => _x( 'Review', 'add new on admin bar', 'motorlan-api-vue' ),
        'add_new'            => _x( 'Add New', 'review', 'motorlan-api-vue' ),
        'add_new_item'       => __( 'Add New Review', 'motorlan-api-vue' ),
        'new_item'           => __( 'New Review', 'motorlan-api-vue' ),
        'edit_item'          => __( 'Edit Review', 'motorlan-api-vue' ),
        'view_item'          => __( 'View Review', 'motorlan-api-vue' ),
        'all_items'          => __( 'All Reviews', 'motorlan-api-vue' ),
        'search_items'       => __( 'Search Reviews', 'motorlan-api-vue' ),
        'not_found'          => __( 'No reviews found.', 'motorlan-api-vue' ),
        'not_found_in_trash' => __( 'No reviews found in Trash.', 'motorlan-api-vue' )
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'review' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 8,
        'supports'           => array( 'title', 'editor', 'author', 'custom-fields' ),
        'show_in_rest'       => true,
    );

    register_post_type( 'review', $args );
}
add_action( 'init', 'motorlan_register_review_cpt' );


/**
 * Register a custom post type for "favorite".
 */
function motorlan_register_favorite_cpt() {
    $labels = array(
        'name'               => _x( 'Favorites', 'post type general name', 'motorlan-api-vue' ),
        'singular_name'      => _x( 'Favorite', 'post type singular name', 'motorlan-api-vue' ),
        'menu_name'          => _x( 'Favorites', 'admin menu', 'motorlan-api-vue' ),
        'name_admin_bar'     => _x( 'Favorite', 'add new on admin bar', 'motorlan-api-vue' ),
        'add_new'            => _x( 'Add New', 'favorite', 'motorlan-api-vue' ),
        'add_new_item'       => __( 'Add New Favorite', 'motorlan-api-vue' ),
        'new_item'           => __( 'New Favorite', 'motorlan-api-vue' ),
        'edit_item'          => __( 'Edit Favorite', 'motorlan-api-vue' ),
        'view_item'          => __( 'View Favorite', 'motorlan-api-vue' ),
        'all_items'          => __( 'All Favorites', 'motorlan-api-vue' ),
        'search_items'       => __( 'Search Favorites', 'motorlan-api-vue' ),
        'not_found'          => __( 'No favorites found.', 'motorlan-api-vue' ),
        'not_found_in_trash' => __( 'No favorites found in Trash.', 'motorlan-api-vue' )
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'favorite' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 9,
        'supports'           => array( 'title', 'author', 'custom-fields' ),
        'show_in_rest'       => true,
    );

    register_post_type( 'favorite', $args );
}
add_action( 'init', 'motorlan_register_favorite_cpt' );
