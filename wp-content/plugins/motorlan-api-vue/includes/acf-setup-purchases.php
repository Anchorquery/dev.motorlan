<?php
/**
 * Setup for ACF Field Groups for Purchases, Questions, and Opinions.
 *
 * @package motorlan-api-vue
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

if( function_exists('acf_add_local_field_group') ):

// Field group for "Compra"
acf_add_local_field_group(array(
    'key' => 'group_compras',
    'title' => 'Detalles de la Compra',
    'fields' => array(
        array(
            'key' => 'field_compra_usuario',
            'label' => 'Usuario',
            'name' => 'usuario',
            'type' => 'user',
            'required' => 1,
        ),
        array(
            'key' => 'field_compra_motor',
            'label' => 'Motor',
            'name' => 'motor',
            'type' => 'post_object',
            'post_type' => array(
                0 => 'motor',
            ),
            'allow_null' => 0,
            'multiple' => 0,
            'return_format' => 'object',
            'ui' => 1,
        ),
        array(
            'key' => 'field_compra_fecha',
            'label' => 'Fecha de Compra',
            'name' => 'fecha_compra',
            'type' => 'date_picker',
            'display_format' => 'd/m/Y',
            'return_format' => 'd/m/Y',
            'first_day' => 1,
        ),
        array(
            'key' => 'field_compra_vendedor',
            'label' => 'Vendedor',
            'name' => 'vendedor',
            'type' => 'user',
            'required' => 1,
        ),
        array(
            'key' => 'field_compra_precio',
            'label' => 'Precio de Compra',
            'name' => 'precio_compra',
            'type' => 'number',
            'required' => 1,
        ),
        array(
            'key' => 'field_compra_estado',
            'label' => 'Estado',
            'name' => 'estado',
            'type' => 'select',
            'choices' => array(
                'pending' => 'Pendiente',
                'completed' => 'Completada',
                'cancelled' => 'Cancelada',
            ),
            'default_value' => 'pending',
            'required' => 1,
        ),
    ),
    'location' => array(
        array(
            array(
                'param' => 'post_type',
                'operator' => '==',
                'value' => 'compra',
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

// Field group for "Pregunta"
acf_add_local_field_group(array(
    'key' => 'group_preguntas',
    'title' => 'Detalles de la Pregunta',
    'fields' => array(
        array(
            'key' => 'field_pregunta_usuario',
            'label' => 'Usuario',
            'name' => 'usuario',
            'type' => 'user',
            'required' => 1,
        ),
        array(
            'key' => 'field_pregunta_publicacion',
            'label' => 'Publicacion',
            'name' => 'publicacion',
            'type' => 'post_object',
            'post_type' => array(
                0 => 'publicacion',
            ),
            'allow_null' => 0,
            'multiple' => 0,
            'return_format' => 'object',
            'ui' => 1,
        ),
        array(
            'key' => 'field_pregunta_publication_owner',
            'label' => 'Propietario de la Publicación',
            'name' => 'publication_owner',
            'type' => 'user',
            'required' => 1,
        ),
        array(
            'key' => 'field_pregunta_pregunta',
            'label' => 'Pregunta',
            'name' => 'pregunta',
            'type' => 'textarea',
            'required' => 1,
        ),
        array(
            'key' => 'field_pregunta_respuesta',
            'label' => 'Respuesta',
            'name' => 'respuesta',
            'type' => 'textarea',
        ),
    ),
    'location' => array(
        array(
            array(
                'param' => 'post_type',
                'operator' => '==',
                'value' => 'pregunta',
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

// Field group for "Opinion"
acf_add_local_field_group(array(
    'key' => 'group_opiniones',
    'title' => 'Detalles de la Opinión',
    'fields' => array(
        array(
            'key' => 'field_opinion_usuario',
            'label' => 'Usuario',
            'name' => 'usuario',
            'type' => 'user',
            'required' => 1,
        ),
        array(
            'key' => 'field_opinion_motor',
            'label' => 'Motor',
            'name' => 'motor',
            'type' => 'post_object',
            'post_type' => array(
                0 => 'motor',
            ),
            'allow_null' => 0,
            'multiple' => 0,
            'return_format' => 'object',
            'ui' => 1,
        ),
        array(
            'key' => 'field_opinion_valoracion',
            'label' => 'Valoración',
            'name' => 'valoracion',
            'type' => 'range',
            'min' => 1,
            'max' => 5,
            'step' => 1,
            'required' => 1,
        ),
        array(
            'key' => 'field_opinion_comentario',
            'label' => 'Comentario',
            'name' => 'comentario',
            'type' => 'textarea',
            'required' => 1,
        ),
    ),
    'location' => array(
        array(
            array(
                'param' => 'post_type',
                'operator' => '==',
                'value' => 'opinion',
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

// Field group for "Oferta"
acf_add_local_field_group(array(
    'key' => 'group_ofertas',
    'title' => 'Detalles de la Oferta',
    'fields' => array(
        array(
            'key' => 'field_oferta_usuario',
            'label' => 'Usuario',
            'name' => 'usuario',
            'type' => 'user',
            'required' => 1,
        ),
        array(
            'key' => 'field_oferta_motor',
            'label' => 'Motor',
            'name' => 'motor',
            'type' => 'post_object',
            'post_type' => array(
                0 => 'motor',
            ),
            'allow_null' => 0,
            'multiple' => 0,
            'return_format' => 'object',
            'ui' => 1,
        ),
        array(
            'key' => 'field_oferta_monto',
            'label' => 'Monto',
            'name' => 'monto',
            'type' => 'number',
            'required' => 1,
        ),
        array(
            'key' => 'field_oferta_justificacion',
            'label' => 'Justificación',
            'name' => 'justificacion',
            'type' => 'textarea',
        ),
    ),
    'location' => array(
        array(
            array(
                'param' => 'post_type',
                'operator' => '==',
                'value' => 'oferta',
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
