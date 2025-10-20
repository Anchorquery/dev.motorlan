<?php
/**
 * ACF Field Group for Garantia CPT.
 *
 * @package motorlan-api-vue
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

if( function_exists('acf_add_local_field_group') ):

acf_add_local_field_group(array(
    'key' => 'group_garantia_details',
    'title' => 'Detalles de la Garantía',
    'fields' => array(
        array(
            'key' => 'field_garantia_status',
            'label' => 'Estado de la Garantía',
            'name' => 'garantia_status',
            'type' => 'select',
            'instructions' => 'Seleccione el estado de la solicitud de garantía.',
            'required' => 1,
            'choices' => array(
                'pendiente' => 'Pendiente',
                'aceptada' => 'Aceptada',
                'rechazada' => 'Rechazada',
            ),
            'default_value' => 'pendiente',
            'allow_null' => 0,
            'multiple' => 0,
            'ui' => 1,
            'ajax' => 0,
            'return_format' => 'value',
            'placeholder' => '',
        ),
        array(
            'key' => 'field_motor_id',
            'label' => 'ID del Motor',
            'name' => 'motor_id',
            'type' => 'post_object',
            'post_type' => array('publicacion'),
            'allow_null' => 1,
            'multiple' => 0,
            'return_format' => 'id',
            'ui' => 1,
        ),
        array(
            'key' => 'field_direccion_motor',
            'label' => 'Dirección de Recogida',
            'name' => 'direccion_motor',
            'type' => 'text',
        ),
        array(
            'key' => 'field_cp_motor',
            'label' => 'Código Postal',
            'name' => 'cp_motor',
            'type' => 'text',
        ),
        array(
            'key' => 'field_agencia_transporte',
            'label' => 'Agencia de Transporte',
            'name' => 'agencia_transporte',
            'type' => 'text',
        ),
        array(
            'key' => 'field_modalidad_pago',
            'label' => 'Modalidad de Pago',
            'name' => 'modalidad_pago',
            'type' => 'text',
        ),
        array(
            'key' => 'field_comentarios',
            'label' => 'Comentarios',
            'name' => 'comentarios',
            'type' => 'textarea',
        ),
        array(
            'key' => 'field_garantia_motor_info',
            'label' => 'Información del Motor',
            'name' => 'motor_info',
            'type' => 'group',
            'instructions' => 'Datos de la publicación asociada a esta garantía.',
            'layout' => 'block',
            'sub_fields' => array(
                array(
                    'key' => 'field_motor_title',
                    'label' => 'Título de la Publicación',
                    'name' => 'motor_title',
                    'type' => 'text',
                    'readonly' => 1,
                ),
                array(
                    'key' => 'field_motor_reference',
                    'label' => 'Referencia',
                    'name' => 'motor_reference',
                    'type' => 'text',
                    'readonly' => 1,
                ),
                array(
                    'key' => 'field_motor_link',
                    'label' => 'Enlace a la Publicación',
                    'name' => 'motor_link',
                    'type' => 'message',
                    'message' => '',
                    'new_lines' => 'wpautop',
                    'esc_html' => 0,
                ),
            ),
        ),
        array(
            'key' => 'field_garantia_user_info',
            'label' => 'Información del Usuario',
            'name' => 'user_info',
            'type' => 'group',
            'instructions' => 'Datos del usuario que solicitó la garantía.',
            'layout' => 'block',
            'sub_fields' => array(
                array(
                    'key' => 'field_user_name',
                    'label' => 'Nombre',
                    'name' => 'user_name',
                    'type' => 'text',
                    'readonly' => 1,
                ),
                array(
                    'key' => 'field_user_email',
                    'label' => 'Email',
                    'name' => 'user_email',
                    'type' => 'email',
                    'readonly' => 1,
                ),
                array(
                    'key' => 'field_user_phone',
                    'label' => 'Teléfono',
                    'name' => 'user_phone',
                    'type' => 'text',
                    'readonly' => 1,
                ),
            ),
        ),
    ),
    'location' => array(
        array(
            array(
                'param' => 'post_type',
                'operator' => '==',
                'value' => 'garantia',
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
 * Populate read-only fields with motor and user data.
 *
 * @param mixed $value The value of the field.
 * @param int   $post_id The post ID.
 * @param array $field The field object.
 * @return mixed
 */
function motorlan_populate_garantia_readonly_fields( $value, $post_id, $field ) {
    // Check if we are on a 'garantia' post type
    if ( get_post_type( $post_id ) !== 'garantia' ) {
        return $value;
    }

    $motor_id = get_field( 'motor_id', $post_id );
    $user_id = get_post_field( 'post_author', $post_id );

    // Populate Motor Info
    if ( $field['name'] === 'motor_title' && $motor_id ) {
        return get_the_title( $motor_id );
    }
    if ( $field['name'] === 'motor_reference' && $motor_id ) {
        return get_field( 'tipo_o_referencia', $motor_id );
    }
    if ( $field['name'] === 'motor_link' && $motor_id ) {
        $link = get_edit_post_link( $motor_id );
        if ( $link ) {
            return '<a href="' . esc_url( $link ) . '" target="_blank">Ver publicación del motor</a>';
        }
        return 'N/A';
    }

    // Populate User Info
    if ( $user_id ) {
        $user_data = get_userdata( $user_id );
        if ( $user_data ) {
            if ( $field['name'] === 'user_name' ) {
                return $user_data->display_name;
            }
            if ( $field['name'] === 'user_email' ) {
                return $user_data->user_email;
            }
            if ( $field['name'] === 'user_phone' ) {
                return get_user_meta( $user_id, 'billing_phone', true );
            }
        }
    }

    return $value;
}

add_filter('acf/load_value/name=motor_title', 'motorlan_populate_garantia_readonly_fields', 10, 3);
add_filter('acf/load_value/name=motor_reference', 'motorlan_populate_garantia_readonly_fields', 10, 3);
add_filter('acf/load_value/name=motor_link', 'motorlan_populate_garantia_readonly_fields', 10, 3);
add_filter('acf/load_value/name=user_name', 'motorlan_populate_garantia_readonly_fields', 10, 3);
add_filter('acf/load_value/name=user_email', 'motorlan_populate_garantia_readonly_fields', 10, 3);
add_filter('acf/load_value/name=user_phone', 'motorlan_populate_garantia_readonly_fields', 10, 3);

/**
 * Populate warranty fields on save.
 *
 * @param int $post_id The post ID.
 */
function motorlan_populate_garantia_fields_on_save( $post_id ) {
    // Check if the post type is 'garantia'
    if ( get_post_type( $post_id ) !== 'garantia' ) {
        return;
    }

    // Check if we have an action and it is the correct one.
    if ( ! isset( $_POST['action'] ) || $_POST['action'] !== 'editpost' ) {
        return;
    }

    // Get motor and user IDs
    $motor_id = get_field( 'motor_id', $post_id );
    $user_id  = get_post_field( 'post_author', $post_id );

    if ( $motor_id ) {
        // Populate motor info if not already set
        $motor_info = get_field( 'motor_info', $post_id );
        if ( empty( $motor_info['motor_title'] ) ) {
            update_field( 'field_motor_title', get_the_title( $motor_id ), $post_id );
        }
        if ( empty( $motor_info['motor_reference'] ) ) {
            update_field( 'field_motor_reference', get_field( 'tipo_o_referencia', $motor_id ), $post_id );
        }
    }

    if ( $user_id ) {
        // Populate user info if not already set
        $user_info = get_field( 'user_info', $post_id );
        $user_data = get_userdata( $user_id );
        if ( $user_data ) {
            if ( empty( $user_info['user_name'] ) ) {
                update_field( 'field_user_name', $user_data->display_name, $post_id );
            }
            if ( empty( $user_info['user_email'] ) ) {
                update_field( 'field_user_email', $user_data->user_email, $post_id );
            }
            if ( empty( $user_info['user_phone'] ) ) {
                update_field( 'field_user_phone', get_user_meta( $user_id, 'billing_phone', true ), $post_id );
            }
        }
    }
}
add_action( 'acf/save_post', 'motorlan_populate_garantia_fields_on_save', 20 );