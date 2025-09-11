<?php
/**
 * Setup for Custom Post Type "Garantia".
 *
 * @package motorlan-api-vue
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Register a custom post type called "garantia".
 */
function motorlan_register_garantia_cpt() {
    $labels = array(
        'name'               => _x( 'Garantías', 'post type general name', 'motorlan-api-vue' ),
        'singular_name'      => _x( 'Garantía', 'post type singular name', 'motorlan-api-vue' ),
        'menu_name'          => _x( 'Garantías', 'admin menu', 'motorlan-api-vue' ),
        'name_admin_bar'     => _x( 'Garantía', 'add new on admin bar', 'motorlan-api-vue' ),
        'add_new'            => _x( 'Añadir Nueva', 'garantia', 'motorlan-api-vue' ),
        'add_new_item'       => __( 'Añadir Nueva Garantía', 'motorlan-api-vue' ),
        'new_item'           => __( 'Nueva Garantía', 'motorlan-api-vue' ),
        'edit_item'          => __( 'Editar Garantía', 'motorlan-api-vue' ),
        'view_item'          => __( 'Ver Garantía', 'motorlan-api-vue' ),
        'all_items'          => __( 'Todas las Garantías', 'motorlan-api-vue' ),
        'search_items'       => __( 'Buscar Garantías', 'motorlan-api-vue' ),
        'not_found'          => __( 'No se encontraron garantías.', 'motorlan-api-vue' ),
        'not_found_in_trash' => __( 'No se encontraron garantías en la papelera.', 'motorlan-api-vue' )
    );

    $args = array(
        'labels'             => $labels,
        'public'             => false,
        'publicly_queryable' => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => false,
        'rewrite'            => false,
        'capability_type'    => 'post',
        'capabilities' => array(
            'edit_post'          => 'manage_options',
            'read_post'          => 'manage_options',
            'delete_post'        => 'manage_options',
            'edit_posts'         => 'manage_options',
            'edit_others_posts'  => 'manage_options',
            'publish_posts'      => 'manage_options',
            'read_private_posts' => 'manage_options',
        ),
        'has_archive'        => false,
        'hierarchical'       => false,
        'menu_position'      => 6,
        'menu_icon'          => 'dashicons-shield-alt',
        'supports'           => array( 'title', 'custom-fields' ),
        'show_in_rest'       => true,
    );

    register_post_type( 'garantia', $args );
}
add_action( 'init', 'motorlan_register_garantia_cpt' );

/**
 * Register custom meta fields for the 'garantia' post type.
 */
function motorlan_register_garantia_meta() {
    register_post_meta( 'garantia', 'motor_id', array(
        'show_in_rest' => true,
        'single' => true,
        'type' => 'integer',
    ) );
    register_post_meta( 'garantia', 'direccion_motor', array(
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string',
    ) );
    register_post_meta( 'garantia', 'cp_motor', array(
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string',
    ) );
    register_post_meta( 'garantia', 'agencia_transporte', array(
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string',
    ) );
    register_post_meta( 'garantia', 'modalidad_pago', array(
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string',
    ) );
    register_post_meta( 'garantia', 'comentarios', array(
        'show_in_rest' => true,
        'single' => true,
        'type' => 'string',
    ) );
}
add_action( 'init', 'motorlan_register_garantia_meta' );
