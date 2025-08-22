<?php
/**
 * Setup for ACF Field Groups.
 *
 * @package motorlan-api-vue
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

if( function_exists('acf_add_local_field_group') ):

acf_add_local_field_group(array(
	'key' => 'group_6643d3c3a9a58',
	'title' => 'Detalles del Motor',
	'fields' => array(
		array(
			'key' => 'field_6643d3c3b0341',
			'label' => 'Marca',
			'name' => 'marca',
			'type' => 'taxonomy',
			'taxonomy' => 'marca',
			'field_type' => 'select',
			'allow_null' => 0,
			'add_term' => 1,
			'save_terms' => 1,
			'load_terms' => 1,
			'return_format' => 'id',
			'multiple' => 0,
		),
		array(
			'key' => 'field_6643d3c3b0342',
			'label' => 'Tipo o referencia',
			'name' => 'tipo_o_referencia',
			'type' => 'text',
		),
		array(
			'key' => 'field_motor_image',
			'label' => 'Imagen del Motor',
			'name' => 'motor_image',
			'type' => 'image',
			'instructions' => 'Añada la imagen principal del motor.',
			'return_format' => 'array',
			'preview_size' => 'thumbnail',
			'library' => 'all',
		),
		array(

			'key' => 'field_motor_gallery',
			'label' => 'Galería de Imágenes',
			'name' => 'motor_gallery',
			'type' => 'gallery',
			'instructions' => 'Añada hasta 5 imágenes del motor. La "Imagen Destacada" principal de WordPress se gestiona por separado.',
			'max' => 5,
			'insert' => 'append',

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
			'key' => 'field_stock',
			'label' => 'Stock',
			'name' => 'stock',
			'type' => 'number',
			'default_value' => 1,
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
			'key' => 'field_documentacion_adicional',
			'label' => 'Documentación Adicional',
			'name' => 'documentacion_adicional',
			'type' => 'repeater',
			'instructions' => 'Añada hasta 5 documentos adicionales.',
			'layout' => 'table',
			'min' => 0,
			'max' => 5,
			'button_label' => 'Añadir Documento',
			'sub_fields' => array(
				array(
					'key' => 'field_documento_archivo',
					'label' => 'Archivo',
					'name' => 'archivo',
					'type' => 'file',
					'return_format' => 'array',
				),
				array(
					'key' => 'field_documento_nombre',
					'label' => 'Nombre del Documento',
					'name' => 'nombre',
					'type' => 'text',
				),
			),
		),
		array(
			'key' => 'field_6643d3c3b0358',
			'label' => 'Status',
			'name' => 'publicar_acf',
			'type' => 'radio',
			'choices' => array(
				'publish' => 'Publicado',
				'draft' => 'Borrador',
				'paused' => 'Pausado',
				'sold' => 'Vendido',
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
