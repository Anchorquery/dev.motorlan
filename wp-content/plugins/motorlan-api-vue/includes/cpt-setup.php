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

/**
 * Register a custom post type called "regulador".
 */
function motorlan_register_regulador_cpt() {
    $labels = array(
        'name'               => _x( 'Reguladores', 'post type general name', 'motorlan-api-vue' ),
        'singular_name'      => _x( 'Regulador', 'post type singular name', 'motorlan-api-vue' ),
        'menu_name'          => _x( 'Reguladores', 'admin menu', 'motorlan-api-vue' ),
        'name_admin_bar'     => _x( 'Regulador', 'add new on admin bar', 'motorlan-api-vue' ),
        'add_new'            => _x( 'Añadir Nuevo', 'regulador', 'motorlan-api-vue' ),
        'add_new_item'       => __( 'Añadir Nuevo Regulador', 'motorlan-api-vue' ),
        'new_item'           => __( 'Nuevo Regulador', 'motorlan-api-vue' ),
        'edit_item'          => __( 'Editar Regulador', 'motorlan-api-vue' ),
        'view_item'          => __( 'Ver Regulador', 'motorlan-api-vue' ),
        'all_items'          => __( 'Todos los Reguladores', 'motorlan-api-vue' ),
        'search_items'       => __( 'Buscar Reguladores', 'motorlan-api-vue' ),
        'parent_item_colon'  => __( 'Reguladores Padre:', 'motorlan-api-vue' ),
        'not_found'          => __( 'No se encontraron reguladores.', 'motorlan-api-vue' ),
        'not_found_in_trash' => __( 'No se encontraron reguladores en la papelera.', 'motorlan-api-vue' )
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'regulador' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 6,
        'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
        'show_in_rest'       => true,
    );

    register_post_type( 'regulador', $args );
}
add_action( 'init', 'motorlan_register_regulador_cpt' );

/**
 * Register a custom post type called "otro_repuesto".
 */
function motorlan_register_otro_repuesto_cpt() {
    $labels = array(
        'name'               => _x( 'Otros Repuestos', 'post type general name', 'motorlan-api-vue' ),
        'singular_name'      => _x( 'Otro Repuesto', 'post type singular name', 'motorlan-api-vue' ),
        'menu_name'          => _x( 'Otros Repuestos', 'admin menu', 'motorlan-api-vue' ),
        'name_admin_bar'     => _x( 'Otro Repuesto', 'add new on admin bar', 'motorlan-api-vue' ),
        'add_new'            => _x( 'Añadir Nuevo', 'otro_repuesto', 'motorlan-api-vue' ),
        'add_new_item'       => __( 'Añadir Nuevo Otro Repuesto', 'motorlan-api-vue' ),
        'new_item'           => __( 'Nuevo Otro Repuesto', 'motorlan-api-vue' ),
        'edit_item'          => __( 'Editar Otro Repuesto', 'motorlan-api-vue' ),
        'view_item'          => __( 'Ver Otro Repuesto', 'motorlan-api-vue' ),
        'all_items'          => __( 'Todos los Otros Repuestos', 'motorlan-api-vue' ),
        'search_items'       => __( 'Buscar Otros Repuestos', 'motorlan-api-vue' ),
        'parent_item_colon'  => __( 'Otros Repuestos Padre:', 'motorlan-api-vue' ),
        'not_found'          => __( 'No se encontraron otros repuestos.', 'motorlan-api-vue' ),
        'not_found_in_trash' => __( 'No se encontraron otros repuestos en la papelera.', 'motorlan-api-vue' )
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'otro-repuesto' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 7,
        'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
        'show_in_rest'       => true,
    );

    register_post_type( 'otro_repuesto', $args );
}
add_action( 'init', 'motorlan_register_otro_repuesto_cpt' );


/**
 * Create two taxonomies, 'categoria' and 'marca' for the post type 'motor'.
 */
function motorlan_register_taxonomies() {
    // The post types to register the taxonomies for.
    $post_types = array( 'motor', 'regulador', 'otro_repuesto' );

    // Taxonomy: Categoria
    $labels_categoria = array(
        'name'              => _x( 'Categorías', 'taxonomy general name', 'motorlan-api-vue' ),
        'singular_name'     => _x( 'Categoría', 'taxonomy singular name', 'motorlan-api-vue' ),
        'search_items'      => __( 'Buscar Categorías', 'motorlan-api-vue' ),
        'all_items'         => __( 'Todas las Categorías', 'motorlan-api-vue' ),
        'parent_item'       => __( 'Categoría Padre', 'motorlan-api-vue' ),
        'parent_item_colon' => __( 'Categoría Padre:', 'motorlan-api-vue' ),
        'edit_item'         => __( 'Editar Categoría', 'motorlan-api-vue' ),
        'update_item'       => __( 'Actualizar Categoría', 'motorlan-api-vue' ),
        'add_new_item'      => __( 'Añadir Nueva Categoría', 'motorlan-api-vue' ),
        'new_item_name'     => __( 'Nuevo Nombre de Categoría', 'motorlan-api-vue' ),
        'menu_name'         => __( 'Categorías', 'motorlan-api-vue' ),
    );

    $args_categoria = array(
        'hierarchical'      => true,
        'labels'            => $labels_categoria,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'categoria-motor' ),
        'show_in_rest'      => true,
    );

    register_taxonomy( 'categoria', $post_types, $args_categoria );

    // Taxonomy: Marca
    $labels_marca = array(
        'name'              => _x( 'Marcas', 'taxonomy general name', 'motorlan-api-vue' ),
        'singular_name'     => _x( 'Marca', 'taxonomy singular name', 'motorlan-api-vue' ),
        'search_items'      => __( 'Buscar Marcas', 'motorlan-api-vue' ),
        'all_items'         => __( 'Todas las Marcas', 'motorlan-api-vue' ),
        'parent_item'       => null,
        'parent_item_colon' => null,
        'edit_item'         => __( 'Editar Marca', 'motorlan-api-vue' ),
        'update_item'       => __( 'Actualizar Marca', 'motorlan-api-vue' ),
        'add_new_item'      => __( 'Añadir Nueva Marca', 'motorlan-api-vue' ),
        'new_item_name'     => __( 'Nuevo Nombre de Marca', 'motorlan-api-vue' ),
        'menu_name'         => __( 'Marcas', 'motorlan-api-vue' ),
    );

    $args_marca = array(
        'hierarchical'      => false,
        'labels'            => $labels_marca,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'marca-motor' ),
        'show_in_rest'      => true,
    );

    register_taxonomy( 'marca', $post_types, $args_marca );
}
add_action( 'init', 'motorlan_register_taxonomies', 0 );

/**
 * Add a UUID to the post if it doesn't have one.
 *
 * @param int $post_id The post ID.
 * @param WP_Post $post The post object.
 */
function motorlan_add_uuid_to_post( $post_id, $post ) {
    // If this is just a revision, don't send the email.
    if ( wp_is_post_revision( $post_id ) ) {
        return;
    }

    // Check if the 'uuid' meta key exists and is not empty.
    $uuid = get_post_meta( $post_id, 'uuid', true );
    if ( empty( $uuid ) ) {
        // Generate a UUID.
        $uuid = wp_generate_uuid4();
        // Add the UUID as a custom field.
        update_post_meta( $post_id, 'uuid', $uuid );
    }
}
add_action( 'save_post_motor', 'motorlan_add_uuid_to_post', 10, 2 );
add_action( 'save_post_regulador', 'motorlan_add_uuid_to_post', 10, 2 );
add_action( 'save_post_otro_repuesto', 'motorlan_add_uuid_to_post', 10, 2 );
