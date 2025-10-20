<?php
/**
 * Setup for Custom Post Type "Publicacion".
 *
 * @package motorlan-api-vue
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Register a custom post type called "publicaciones".
 *
 * @see get_post_type_labels() for label keys.
 */
function motorlan_register_publicaciones_cpt() {
    $labels = array(
        'name'               => _x('publicacion', 'post type general name', 'motorlan-api-vue' ),
        'singular_name'      => _x( 'Publicación', 'post type singular name', 'motorlan-api-vue' ),
        'menu_name'          => _x('publicacion', 'admin menu', 'motorlan-api-vue' ),
        'name_admin_bar'     => _x( 'Publicación', 'add new on admin bar', 'motorlan-api-vue' ),
        'add_new'            => _x( 'Añadir Nueva', 'publicacion', 'motorlan-api-vue' ),
        'add_new_item'       => __( 'Añadir Nueva Publicación', 'motorlan-api-vue' ),
        'new_item'           => __( 'Nueva Publicación', 'motorlan-api-vue' ),
        'edit_item'          => __( 'Editar Publicación', 'motorlan-api-vue' ),
        'view_item'          => __( 'Ver Publicación', 'motorlan-api-vue' ),
        'all_items'          => __( 'Todas las Publicaciones', 'motorlan-api-vue' ),
        'search_items'       => __( 'Buscar Publicaciones', 'motorlan-api-vue' ),
        'parent_item_colon'  => __( 'Publicaciones Padre:', 'motorlan-api-vue' ),
        'not_found'          => __( 'No se encontraron publicaciones.', 'motorlan-api-vue' ),
        'not_found_in_trash' => __( 'No se encontraron publicaciones en la papelera.', 'motorlan-api-vue' )
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'publicacion' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 5,
        'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
        'show_in_rest'       => true, // This is crucial for the REST API
    );

    register_post_type( 'publicacion', $args );
}
add_action( 'init', 'motorlan_register_publicaciones_cpt' );


/**
 * Create two taxonomies, 'categoria' and 'marca' for the post type'publicacion'.
 */
function motorlan_register_taxonomies() {
    // The post types to register the taxonomies for.
    $post_types = array( 'publicacion' );

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

    // Taxonomy: Tipo
    $labels_tipo = array(
        'name'              => _x( 'Tipos', 'taxonomy general name', 'motorlan-api-vue' ),
        'singular_name'     => _x( 'Tipo', 'taxonomy singular name', 'motorlan-api-vue' ),
        'search_items'      => __( 'Buscar Tipos', 'motorlan-api-vue' ),
        'all_items'         => __( 'Todos los Tipos', 'motorlan-api-vue' ),
        'parent_item'       => __( 'Tipo Padre', 'motorlan-api-vue' ),
        'parent_item_colon' => __( 'Tipo Padre:', 'motorlan-api-vue' ),
        'edit_item'         => __( 'Editar Tipo', 'motorlan-api-vue' ),
        'update_item'       => __( 'Actualizar Tipo', 'motorlan-api-vue' ),
        'add_new_item'      => __( 'Añadir Nuevo Tipo', 'motorlan-api-vue' ),
        'new_item_name'     => __( 'Nuevo Nombre de Tipo', 'motorlan-api-vue' ),
        'menu_name'         => __( 'Tipos', 'motorlan-api-vue' ),
    );

    $args_tipo = array(
        'hierarchical'      => true,
        'labels'            => $labels_tipo,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'tipo' ),
        'show_in_rest'      => true,
    );

    register_taxonomy( 'tipo', $post_types, $args_tipo );
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
add_action( 'save_post_publicaciones', 'motorlan_add_uuid_to_post', 10, 2 );
