<?php
/**
 * Setup for Custom Post Type "Motor".
 *
 * @package motorlan-api-vue
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Register a custom post type called "motor".
 *
 * @see get_post_type_labels() for label keys.
 */
function motorlan_register_motor_cpt() {
    $labels = array(
        'name'               => _x( 'Motores', 'post type general name', 'motorlan-api-vue' ),
        'singular_name'      => _x( 'Motor', 'post type singular name', 'motorlan-api-vue' ),
        'menu_name'          => _x( 'Motores', 'admin menu', 'motorlan-api-vue' ),
        'name_admin_bar'     => _x( 'Motor', 'add new on admin bar', 'motorlan-api-vue' ),
        'add_new'            => _x( 'Añadir Nuevo', 'motor', 'motorlan-api-vue' ),
        'add_new_item'       => __( 'Añadir Nuevo Motor', 'motorlan-api-vue' ),
        'new_item'           => __( 'Nuevo Motor', 'motorlan-api-vue' ),
        'edit_item'          => __( 'Editar Motor', 'motorlan-api-vue' ),
        'view_item'          => __( 'Ver Motor', 'motorlan-api-vue' ),
        'all_items'          => __( 'Todos los Motores', 'motorlan-api-vue' ),
        'search_items'       => __( 'Buscar Motores', 'motorlan-api-vue' ),
        'parent_item_colon'  => __( 'Motores Padre:', 'motorlan-api-vue' ),
        'not_found'          => __( 'No se encontraron motores.', 'motorlan-api-vue' ),
        'not_found_in_trash' => __( 'No se encontraron motores en la papelera.', 'motorlan-api-vue' )
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'motor' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 5,
        'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
        'show_in_rest'       => true, // This is crucial for the REST API
    );

    register_post_type( 'motor', $args );
}
add_action( 'init', 'motorlan_register_motor_cpt' );
