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

if( function_exists('acf_add_local_field_group') ):

acf_add_local_field_group(array(
	'key' => 'group_6643d3c3a9a58',
	'title' => 'Detalles del Motor',
	'fields' => array(
		array(
			'key' => 'field_6643d3c3b0340',
			'label' => 'Título entrada',
			'name' => 'titulo_entrada',
			'type' => 'text',
		),
		array(
			'key' => 'field_6643d3c3b0341',
			'label' => 'Marca',
			'name' => 'marca',
			'type' => 'text',
		),
		array(
			'key' => 'field_6643d3c3b0342',
			'label' => 'Tipo o referencia',
			'name' => 'tipo_o_referencia',
			'type' => 'text',
		),
		array(
			'key' => 'field_6643d3c3b0343',
			'label' => 'Imagen destacada (motor_image)',
			'name' => 'motor_image',
			'type' => 'image',
			'return_format' => 'array',
			'preview_size' => 'medium',
			'library' => 'all',
		),
		array(
			'key' => 'field_6643d3c3b0344',
			'label' => 'Imagen 1',
			'name' => 'imagen_1',
			'type' => 'image',
			'return_format' => 'array',
			'preview_size' => 'medium',
			'library' => 'all',
		),
		array(
			'key' => 'field_6643d3c3b0345',
			'label' => 'Imagen 2',
			'name' => 'imagen_2',
			'type' => 'image',
			'return_format' => 'array',
			'preview_size' => 'medium',
			'library' => 'all',
		),
		array(
			'key' => 'field_6643d3c3b0346',
			'label' => 'Imagen 3',
			'name' => 'imagen_3',
			'type' => 'image',
			'return_format' => 'array',
			'preview_size' => 'medium',
			'library' => 'all',
		),
		array(
			'key' => 'field_6643d3c3b0347',
			'label' => 'Potencia',
			'name' => 'potencia',
			'type' => 'number',
			'append' => 'kW',
		),
		array(
			'key' => 'field_6643d3c3b0348',
			'label' => 'Velocidad',
			'name' => 'velocidad',
			'type' => 'number',
			'append' => 'rpm',
		),
		array(
			'key' => 'field_6643d3c3b0349',
			'label' => 'PAR Nominal',
			'name' => 'par_nominal',
			'type' => 'number',
			'append' => 'Nm',
		),
		array(
			'key' => 'field_6643d3c3b034a',
			'label' => 'Voltaje',
			'name' => 'voltaje',
			'type' => 'number',
			'append' => 'V',
		),
		array(
			'key' => 'field_6643d3c3b034b',
			'label' => 'Intensidad',
			'name' => 'intensidad',
			'type' => 'number',
			'append' => 'A',
		),
		array(
			'key' => 'field_6643d3c3b034c',
			'label' => 'País (localización)',
			'name' => 'pais',
			'type' => 'select',
			'choices' => array(
				'España' => 'España',
				'Portugal' => 'Portugal',
				'Francia' => 'Francia',
			),
			'allow_null' => 1,
		),
		array(
			'key' => 'field_6643d3c3b034d',
			'label' => 'Provincia',
			'name' => 'provincia',
			'type' => 'text',
		),
		array(
			'key' => 'field_6643d3c3b034e',
			'label' => 'Estado del artículo',
			'name' => 'estado_del_articulo',
			'type' => 'select',
			'choices' => array(
				'Nuevo' => 'Nuevo',
				'Usado' => 'Usado',
				'Restaurado' => 'Restaurado',
			),
		),
		array(
			'key' => 'field_6643d3c3b034f',
			'label' => 'Informe de reparación',
			'name' => 'informe_de_reparacion',
			'type' => 'file',
			'return_format' => 'array',
		),
		array(
			'key' => 'field_6643d3c3b0350',
			'label' => 'Descripción',
			'name' => 'descripcion',
			'type' => 'textarea',
		),
		array(
			'key' => 'field_6643d3c3b0351',
			'label' => 'Posibilidad de alquiler',
			'name' => 'posibilidad_de_alquiler',
			'type' => 'radio',
			'choices' => array(
				'Sí' => 'Sí',
				'No' => 'No',
			),
			'layout' => 'horizontal',
		),
		array(
			'key' => 'field_6643d3c3b0352',
			'label' => 'Tipo de alimentación',
			'name' => 'tipo_de_alimentacion',
			'type' => 'radio',
			'choices' => array(
				'Continua (C.C.)' => 'Continua (C.C.)',
				'Alterna (C.A.)' => 'Alterna (C.A.)',
			),
			'layout' => 'horizontal',
		),
		array(
			'key' => 'field_6643d3c3b0353',
			'label' => 'Servomotores',
			'name' => 'servomotores',
			'type' => 'checkbox',
			'choices' => array(
				'Sí' => 'Sí',
			),
		),
		array(
			'key' => 'field_6643d3c3b0354',
			'label' => 'Regulación electrónica/Drivers',
			'name' => 'regulacion_electronica_drivers',
			'type' => 'checkbox',
			'choices' => array(
				'Sí' => 'Sí',
			),
		),
		array(
			'key' => 'field_6643d3c3b0355',
			'label' => 'Precio de venta',
			'name' => 'precio_de_venta',
			'type' => 'number',
			'prepend' => '€',
		),
		array(
			'key' => 'field_6643d3c3b0356',
			'label' => 'Precio negociable',
			'name' => 'precio_negociable',
			'type' => 'radio',
			'choices' => array(
				'Sí' => 'Sí',
				'No' => 'No',
			),
			'layout' => 'horizontal',
		),
		array(
			'key' => 'field_6643d3c3b0357',
			'label' => 'Documentación adjunta',
			'name' => 'documentacion_adjunta',
			'type' => 'file',
			'return_format' => 'array',
		),
		array(
			'key' => 'field_6643d3c3b0358',
			'label' => 'Publicar (ACF)',
			'name' => 'publicar_acf',
			'type' => 'radio',
			'choices' => array(
				'publish' => 'Publicar',
				'draft' => 'Borrador',
			),
			'default_value' => 'publish',
			'layout' => 'horizontal',
		),
	),
	'location' => array(
		array(
			array(
				'param' => 'post_type',
				'operator' => '==',
				'value' => 'motor',
			),
		),
	),
	'menu_order' => 0,
	'position' => 'normal',
	'style' => 'default',
	'label_placement' => 'top',
	'instruction_placement' => 'label',
	'hide_on_screen' => '',
	'active' => true,
	'description' => '',
));

endif;


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
 * Callback function to get a list of motors with pagination, using ACF.
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

    // Initialize meta query
    $meta_query = array('relation' => 'AND');

    // Get filter parameters from the request
    $marca = $request->get_param('marca');
    $potencia_min = $request->get_param('potencia_min');
    $potencia_max = $request->get_param('potencia_max');
    $velocidad_min = $request->get_param('velocidad_min');
    $velocidad_max = $request->get_param('velocidad_max');
    $pais = $request->get_param('pais');
    $tipo_de_alimentacion = $request->get_param('tipo_de_alimentacion');
    $servomotores = $request->get_param('servomotores');

    // Add filters to meta query
    if (!empty($marca)) {
        $meta_query[] = array(
            'key'     => 'marca',
            'value'   => sanitize_text_field($marca),
            'compare' => 'LIKE',
        );
    }

    if (!empty($potencia_min) && !empty($potencia_max)) {
        $meta_query[] = array(
            'key'     => 'potencia',
            'value'   => array(floatval($potencia_min), floatval($potencia_max)),
            'type'    => 'NUMERIC',
            'compare' => 'BETWEEN',
        );
    } elseif (!empty($potencia_min)) {
        $meta_query[] = array(
            'key'     => 'potencia',
            'value'   => floatval($potencia_min),
            'type'    => 'NUMERIC',
            'compare' => '>=',
        );
    } elseif (!empty($potencia_max)) {
        $meta_query[] = array(
            'key'     => 'potencia',
            'value'   => floatval($potencia_max),
            'type'    => 'NUMERIC',
            'compare' => '<=',
        );
    }

    if (!empty($velocidad_min) && !empty($velocidad_max)) {
        $meta_query[] = array(
            'key'     => 'velocidad',
            'value'   => array(intval($velocidad_min), intval($velocidad_max)),
            'type'    => 'NUMERIC',
            'compare' => 'BETWEEN',
        );
    } elseif (!empty($velocidad_min)) {
        $meta_query[] = array(
            'key'     => 'velocidad',
            'value'   => intval($velocidad_min),
            'type'    => 'NUMERIC',
            'compare' => '>=',
        );
    } elseif (!empty($velocidad_max)) {
        $meta_query[] = array(
            'key'     => 'velocidad',
            'value'   => intval($velocidad_max),
            'type'    => 'NUMERIC',
            'compare' => '<=',
        );
    }

    if (!empty($pais)) {
        $meta_query[] = array(
            'key'     => 'pais',
            'value'   => sanitize_text_field($pais),
            'compare' => '=',
        );
    }

    if (!empty($tipo_de_alimentacion)) {
        $meta_query[] = array(
            'key'     => 'tipo_de_alimentacion',
            'value'   => sanitize_text_field($tipo_de_alimentacion),
            'compare' => '=',
        );
    }

    if (!empty($servomotores)) {
        $meta_query[] = array(
            'key'     => 'servomotores',
            'value'   => '"' . sanitize_text_field($servomotores) . '"',
            'compare' => 'LIKE',
        );
    }

    // If we have meta queries, add them to the main query args
    if (count($meta_query) > 1) {
        $args['meta_query'] = $meta_query;
    }

    $query = new WP_Query( $args );
    $motors_data = array();

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $post_id = get_the_ID();

            $motor_item = array(
                'id'           => $post_id,
                'title'        => get_the_title(),
                'slug'         => get_post_field( 'post_name', $post_id ),
                'content'      => get_the_content(),
                'excerpt'      => get_the_excerpt(),
                'status'       => get_post_status( $post_id ),
                'author_id'    => get_post_field( 'post_author', $post_id ),
                'categories'   => wp_get_post_categories( $post_id ),
                'featured_image_id' => get_post_thumbnail_id( $post_id ),
                'acf'          => array(),
            );

            // Populate ACF fields if ACF is active
            if ( function_exists('get_field') ) {
                $acf_fields = [
                    'titulo_entrada', 'marca', 'tipo_o_referencia', 'motor_image', 'imagen_1', 'imagen_2', 'imagen_3',
                    'potencia', 'velocidad', 'par_nominal', 'voltaje', 'intensidad', 'pais', 'provincia', 'estado_del_articulo',
                    'informe_de_reparacion', 'descripcion', 'posibilidad_de_alquiler', 'tipo_de_alimentacion',
                    'servomotores', 'regulacion_electronica_drivers', 'precio_de_venta', 'precio_negociable',
                    'documentacion_adjunta', 'publicar_acf'
                ];

                foreach($acf_fields as $field_name) {
                    $motor_item['acf'][$field_name] = get_field($field_name, $post_id);
                }
            } else {
                 $motor_item['acf_error'] = 'Advanced Custom Fields plugin is not active.';
            }

            $motors_data[] = $motor_item;
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
