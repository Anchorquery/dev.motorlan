<?php
/**
 * Setup for Custom Post Types "Compra", "Pregunta", "Opinion".
 *
 * @package motorlan-api-vue
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Register a custom post type called "compra".
 */
function motorlan_register_compra_cpt() {
    $labels = array(
        'name'               => _x( 'Compras', 'post type general name', 'motorlan-api-vue' ),
        'singular_name'      => _x( 'Compra', 'post type singular name', 'motorlan-api-vue' ),
        'menu_name'          => _x( 'Compras', 'admin menu', 'motorlan-api-vue' ),
        'name_admin_bar'     => _x( 'Compra', 'add new on admin bar', 'motorlan-api-vue' ),
        'add_new'            => _x( 'Añadir Nueva', 'compra', 'motorlan-api-vue' ),
        'add_new_item'       => __( 'Añadir Nueva Compra', 'motorlan-api-vue' ),
        'new_item'           => __( 'Nueva Compra', 'motorlan-api-vue' ),
        'edit_item'          => __( 'Editar Compra', 'motorlan-api-vue' ),
        'view_item'          => __( 'Ver Compra', 'motorlan-api-vue' ),
        'all_items'          => __( 'Todas las Compras', 'motorlan-api-vue' ),
        'search_items'       => __( 'Buscar Compras', 'motorlan-api-vue' ),
        'not_found'          => __( 'No se encontraron compras.', 'motorlan-api-vue' ),
        'not_found_in_trash' => __( 'No se encontraron compras en la papelera.', 'motorlan-api-vue' )
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'compra' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 6,
        'supports'           => array( 'title', 'editor', 'custom-fields' ),
        'show_in_rest'       => true,
    );

    register_post_type( 'compra', $args );
}
add_action( 'init', 'motorlan_register_compra_cpt' );

/**
 * Register a custom post type called "pregunta".
 */
function motorlan_register_pregunta_cpt() {
    $labels = array(
        'name'               => _x( 'Preguntas', 'post type general name', 'motorlan-api-vue' ),
        'singular_name'      => _x( 'Pregunta', 'post type singular name', 'motorlan-api-vue' ),
        'menu_name'          => _x( 'Preguntas', 'admin menu', 'motorlan-api-vue' ),
        'name_admin_bar'     => _x( 'Pregunta', 'add new on admin bar', 'motorlan-api-vue' ),
        'add_new'            => _x( 'Añadir Nueva', 'pregunta', 'motorlan-api-vue' ),
        'add_new_item'       => __( 'Añadir Nueva Pregunta', 'motorlan-api-vue' ),
        'new_item'           => __( 'Nueva Pregunta', 'motorlan-api-vue' ),
        'edit_item'          => __( 'Editar Pregunta', 'motorlan-api-vue' ),
        'view_item'          => __( 'Ver Pregunta', 'motorlan-api-vue' ),
        'all_items'          => __( 'Todas las Preguntas', 'motorlan-api-vue' ),
        'search_items'       => __( 'Buscar Preguntas', 'motorlan-api-vue' ),
        'not_found'          => __( 'No se encontraron preguntas.', 'motorlan-api-vue' ),
        'not_found_in_trash' => __( 'No se encontraron preguntas en la papelera.', 'motorlan-api-vue' )
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'pregunta' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 7,
        'supports'           => array( 'title', 'editor', 'custom-fields' ),
        'show_in_rest'       => true,
    );

    register_post_type( 'pregunta', $args );
}
add_action( 'init', 'motorlan_register_pregunta_cpt' );

/**
 * Register a custom post type called "opinion".
 */
function motorlan_register_opinion_cpt() {
    $labels = array(
        'name'               => _x( 'Opiniones', 'post type general name', 'motorlan-api-vue' ),
        'singular_name'      => _x( 'Opinión', 'post type singular name', 'motorlan-api-vue' ),
        'menu_name'          => _x( 'Opiniones', 'admin menu', 'motorlan-api-vue' ),
        'name_admin_bar'     => _x( 'Opinión', 'add new on admin bar', 'motorlan-api-vue' ),
        'add_new'            => _x( 'Añadir Nueva', 'opinion', 'motorlan-api-vue' ),
        'add_new_item'       => __( 'Añadir Nueva Opinión', 'motorlan-api-vue' ),
        'new_item'           => __( 'Nueva Opinión', 'motorlan-api-vue' ),
        'edit_item'          => __( 'Editar Opinión', 'motorlan-api-vue' ),
        'view_item'          => __( 'Ver Opinión', 'motorlan-api-vue' ),
        'all_items'          => __( 'Todas las Opiniones', 'motorlan-api-vue' ),
        'search_items'       => __( 'Buscar Opiniones', 'motorlan-api-vue' ),
        'not_found'          => __( 'No se encontraron opiniones.', 'motorlan-api-vue' ),
        'not_found_in_trash' => __( 'No se encontraron opiniones en la papelera.', 'motorlan-api-vue' )
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'opinion' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 8,
        'supports'           => array( 'title', 'editor', 'custom-fields' ),
        'show_in_rest'       => true,
    );

    register_post_type( 'opinion', $args );
}
add_action( 'init', 'motorlan_register_opinion_cpt' );

/**
 * Register a custom post type called "oferta".
 */
function motorlan_register_oferta_cpt() {
    $labels = array(
        'name'               => _x( 'Ofertas', 'post type general name', 'motorlan-api-vue' ),
        'singular_name'      => _x( 'Oferta', 'post type singular name', 'motorlan-api-vue' ),
        'menu_name'          => _x( 'Ofertas', 'admin menu', 'motorlan-api-vue' ),
        'name_admin_bar'     => _x( 'Oferta', 'add new on admin bar', 'motorlan-api-vue' ),
        'add_new'            => _x( 'Añadir Nueva', 'oferta', 'motorlan-api-vue' ),
        'add_new_item'       => __( 'Añadir Nueva Oferta', 'motorlan-api-vue' ),
        'new_item'           => __( 'Nueva Oferta', 'motorlan-api-vue' ),
        'edit_item'          => __( 'Editar Oferta', 'motorlan-api-vue' ),
        'view_item'          => __( 'Ver Oferta', 'motorlan-api-vue' ),
        'all_items'          => __( 'Todas las Ofertas', 'motorlan-api-vue' ),
        'search_items'       => __( 'Buscar Ofertas', 'motorlan-api-vue' ),
        'not_found'          => __( 'No se encontraron ofertas.', 'motorlan-api-vue' ),
        'not_found_in_trash' => __( 'No se encontraron ofertas en la papelera.', 'motorlan-api-vue' )
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'oferta' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 9,
        'supports'           => array( 'title', 'editor', 'custom-fields' ),
        'show_in_rest'       => true,
    );

    register_post_type( 'oferta', $args );
}
add_action( 'init', 'motorlan_register_oferta_cpt' );
