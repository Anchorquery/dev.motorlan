<?php
/**
 * Plugin Name: motorlan-api-vue
 * Plugin URI:  https://motorlan.com
 * Description: API para conectar con VUE
 * Version:     1.0
 * Author:      Motorlan
 * Author URI:  https://motorlan.com
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
 * Adds a meta box to the motor post type editor.
 */
function motorlan_add_motor_meta_box() {
    add_meta_box(
        'motorlan_motor_details',
        __( 'Detalles del Motor', 'motorlan-api-vue' ),
        'motorlan_render_motor_meta_box',
        'motor',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'motorlan_add_motor_meta_box' );

/**
 * Renders the meta box for motor details.
 *
 * @param WP_Post $post The post object.
 */
function motorlan_render_motor_meta_box( $post ) {
    // Add a nonce field so we can check for it later.
    wp_nonce_field( 'motorlan_save_motor_meta_data', 'motorlan_motor_meta_box_nonce' );

    // Get existing meta data.
    $fields_data = [
        'tipo_o_referencia' => get_post_meta( $post->ID, '_motor_tipo_o_referencia', true ),
        'potencia' => get_post_meta( $post->ID, '_motor_potencia', true ),
        'velocidad' => get_post_meta( $post->ID, '_motor_velocidad', true ),
        'par_nominal' => get_post_meta( $post->ID, '_motor_par_nominal', true ),
        'voltaje' => get_post_meta( $post->ID, '_motor_voltaje', true ),
        'estado_del_articulo' => get_post_meta( $post->ID, '_motor_estado_del_articulo', true ),
    ];
    ?>
    <style>
        .motor-meta-box-table { width: 100%; border-collapse: collapse; }
        .motor-meta-box-table td { padding: 8px 5px; }
        .motor-meta-box-table tr { border-bottom: 1px solid #eee; }
        .motor-meta-box-table label { font-weight: bold; padding-right: 10px; }
        .motor-meta-box-table input[type="text"] { width: 100%; }
    </style>
    <table class="motor-meta-box-table">
        <tr>
            <td><label for="motor_tipo_o_referencia"><?php _e( 'Tipo o Referencia', 'motorlan-api-vue' ); ?></label></td>
            <td><input type="text" id="motor_tipo_o_referencia" name="motor_tipo_o_referencia" value="<?php echo esc_attr( $fields_data['tipo_o_referencia'] ); ?>" /></td>
        </tr>
        <tr>
            <td><label for="motor_potencia"><?php _e( 'Potencia (kW)', 'motorlan-api-vue' ); ?></label></td>
            <td><input type="text" id="motor_potencia" name="motor_potencia" value="<?php echo esc_attr( $fields_data['potencia'] ); ?>" /></td>
        </tr>
        <tr>
            <td><label for="motor_velocidad"><?php _e( 'Velocidad (rpm)', 'motorlan-api-vue' ); ?></label></td>
            <td><input type="text" id="motor_velocidad" name="motor_velocidad" value="<?php echo esc_attr( $fields_data['velocidad'] ); ?>" /></td>
        </tr>
        <tr>
            <td><label for="motor_par_nominal"><?php _e( 'Par Nominal (Nm)', 'motorlan-api-vue' ); ?></label></td>
            <td><input type="text" id="motor_par_nominal" name="motor_par_nominal" value="<?php echo esc_attr( $fields_data['par_nominal'] ); ?>" /></td>
        </tr>
        <tr>
            <td><label for="motor_voltaje"><?php _e( 'Voltaje (V)', 'motorlan-api-vue' ); ?></label></td>
            <td><input type="text" id="motor_voltaje" name="motor_voltaje" value="<?php echo esc_attr( $fields_data['voltaje'] ); ?>" /></td>
        </tr>
        <tr>
            <td><label for="motor_estado_del_articulo"><?php _e( 'Estado del Artículo', 'motorlan-api-vue' ); ?></label></td>
            <td><input type="text" id="motor_estado_del_articulo" name="motor_estado_del_articulo" value="<?php echo esc_attr( $fields_data['estado_del_articulo'] ); ?>" /></td>
        </tr>
    </table>
    <?php
}

/**
 * Saves the custom meta data when the post is saved.
 *
 * @param int $post_id The ID of the post being saved.
 */
function motorlan_save_motor_meta_data( $post_id ) {
    if ( ! isset( $_POST['motorlan_motor_meta_box_nonce'] ) || ! wp_verify_nonce( $_POST['motorlan_motor_meta_box_nonce'], 'motorlan_save_motor_meta_data' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $fields = [
        'motor_tipo_o_referencia',
        'motor_potencia',
        'motor_velocidad',
        'motor_par_nominal',
        'motor_voltaje',
        'motor_estado_del_articulo',
    ];

    foreach ( $fields as $field ) {
        if ( array_key_exists( $field, $_POST ) ) {
            update_post_meta( $post_id, '_' . $field, sanitize_text_field( $_POST[ $field ] ) );
        }
    }
}
add_action( 'save_post', 'motorlan_save_motor_meta_data' );

/**
 * Register custom REST API routes.
 */
function motorlan_register_rest_routes() {
    register_rest_route( 'motorlan/v1', '/motors', array(
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_motors',
    ) );
}
add_action( 'rest_api_init', 'motorlan_register_rest_routes' );

/**
 * Callback function to get a list of motors with pagination.
 *
 * @param WP_REST_Request $request The request object.
 * @return WP_REST_Response The response object.
 */
function motorlan_get_motors( $request ) {
    // Get pagination parameters from the request, with defaults.
    $page = $request->get_param( 'page' ) ? absint( $request->get_param( 'page' ) ) : 1;
    $per_page = $request->get_param( 'per_page' ) ? absint( $request->get_param( 'per_page' ) ) : 10;

    $args = array(
        'post_type'      => 'motor',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
    );

    $query = new WP_Query( $args );
    $motors_data = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();

            $meta_keys = [
                'tipo_o_referencia',
                'potencia',
                'velocidad',
                'par_nominal',
                'voltaje',
                'estado_del_articulo',
            ];
            $motor_meta = [];
            foreach($meta_keys as $key) {
                $motor_meta[$key] = get_post_meta( $post_id, '_motor_' . $key, true );
            }

            $motors_data[] = array(
                'id'                 => $post_id,
                'title'              => get_the_title(),
                'content'            => get_the_content(),
                'excerpt'            => get_the_excerpt(),
                'featured_image_url' => get_the_post_thumbnail_url( $post_id, 'full' ),
                'meta'               => $motor_meta,
            );
        }
        wp_reset_postdata();
    }

    // Create the response object.
    $response = new WP_REST_Response( $motors_data, 200 );

    // Add pagination headers for client-side rendering.
    $response->header( 'X-WP-Total', $query->found_posts );
    $response->header( 'X-WP-TotalPages', $query->max_num_pages );

    return $response;
}
