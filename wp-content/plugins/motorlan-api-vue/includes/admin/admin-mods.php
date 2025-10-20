<?php
/**
 * Admin modifications for the Motor CPT list.
 *
 * @package motorlan-api-vue
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Add custom columns to the motor CPT list.
 *
 * @param array $columns The existing columns.
 * @return array The modified columns.
 */
function motorlan_add_motor_columns( $columns ) {
    $new_columns = array();
    foreach ( $columns as $key => $value ) {
        $new_columns[ $key ] = $value;
        if ( 'title' === $key ) {
            $new_columns['motor_image'] = __( 'Imagen', 'motorlan-api-vue' );
            $new_columns['marca'] = __( 'Marca', 'motorlan-api-vue' );
            $new_columns['potencia'] = __( 'Potencia', 'motorlan-api-vue' );
            $new_columns['velocidad'] = __( 'Velocidad', 'motorlan-api-vue' );
            $new_columns['precio_de_venta'] = __( 'Precio', 'motorlan-api-vue' );
            $new_columns['estado_del_articulo'] = __( 'Estado', 'motorlan-api-vue' );
        }
    }
    return $new_columns;
}
add_filter( 'manage_edit-motor_columns', 'motorlan_add_motor_columns' );

/**
 * Populate the custom columns with data.
 *
 * @param string $column The column name.
 * @param int    $post_id The post ID.
 */
function motorlan_populate_motor_columns( $column, $post_id ) {
    switch ( $column ) {
        case 'motor_image':
            $image = get_field( 'motor_image', $post_id );
            if ( $image ) {
                echo '<img src="' . esc_url( $image['sizes']['thumbnail'] ) . '" width="60" />';
            }
            break;
        case 'marca':
            echo esc_html( get_field( 'marca', $post_id ) );
            break;
        case 'potencia':
            $potencia = get_field( 'potencia', $post_id );
            if ( $potencia ) {
                echo esc_html( $potencia . ' kW' );
            }
            break;
        case 'velocidad':
            $velocidad = get_field( 'velocidad', $post_id );
            if ( $velocidad ) {
                echo esc_html( $velocidad . ' rpm' );
            }
            break;
        case 'precio_de_venta':
            $precio = get_field( 'precio_de_venta', $post_id );
            if ( $precio ) {
                echo esc_html( '€' . number_format_i18n( $precio, 2 ) );
            }
            break;
        case 'estado_del_articulo':
            echo esc_html( get_field( 'estado_del_articulo', $post_id ) );
            break;
    }
}
add_action( 'manage_motor_posts_custom_column', 'motorlan_populate_motor_columns', 10, 2 );

/**
 * Add custom filters to the motor CPT list.
 */
function motorlan_add_motor_filters() {
    global $typenow;
    if ( 'motor' === $typenow ) {
        // Filter by Brand
        $marcas = array();
        $posts = get_posts( array( 'post_type' => 'motor', 'posts_per_page' => -1 ) );
        foreach ( $posts as $post ) {
            $marca = get_field( 'marca', $post->ID );
            if ( $marca ) {
                $marcas[] = $marca;
            }
        }
        $marcas = array_unique( $marcas );
        $current_marca = isset( $_GET['marca_filter'] ) ? $_GET['marca_filter'] : '';
        echo '<select name="marca_filter">';
        echo '<option value="">' . __( 'Todas las marcas', 'motorlan-api-vue' ) . '</option>';
        foreach ( $marcas as $marca ) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $marca ),
                selected( $current_marca, $marca, false ),
                esc_html( $marca )
            );
        }
        echo '</select>';

        // Filter by Condition
        $estados = array( 'Nuevo', 'Usado', 'Restaurado' );
        $current_estado = isset( $_GET['estado_filter'] ) ? $_GET['estado_filter'] : '';
        echo '<select name="estado_filter">';
        echo '<option value="">' . __( 'Todos los estados', 'motorlan-api-vue' ) . '</option>';
        foreach ( $estados as $estado ) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $estado ),
                selected( $current_estado, $estado, false ),
                esc_html( $estado )
            );
        }
        echo '</select>';

        // Filter by Country
        $paises = array( 'España', 'Portugal', 'Francia' );
        $current_pais = isset( $_GET['pais_filter'] ) ? $_GET['pais_filter'] : '';
        echo '<select name="pais_filter">';
        echo '<option value="">' . __( 'Todos los países', 'motorlan-api-vue' ) . '</option>';
        foreach ( $paises as $pais ) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $pais ),
                selected( $current_pais, $pais, false ),
                esc_html( $pais )
            );
        }
        echo '</select>';
    }
}
add_action( 'restrict_manage_posts', 'motorlan_add_motor_filters' );

/**
 * Apply the custom filters.
 *
 * @param WP_Query $query The WordPress query object.
 */
function motorlan_apply_motor_filters( $query ) {
    global $pagenow;
    if ( is_admin() && 'edit.php' === $pagenow && 'motor' === $query->get( 'post_type' ) ) {
        $meta_query = array();

        if ( ! empty( $_GET['marca_filter'] ) ) {
            $meta_query[] = array(
                'key'     => 'marca',
                'value'   => sanitize_text_field( $_GET['marca_filter'] ),
                'compare' => '=',
            );
        }

        if ( ! empty( $_GET['estado_filter'] ) ) {
            $meta_query[] = array(
                'key'     => 'estado_del_articulo',
                'value'   => sanitize_text_field( $_GET['estado_filter'] ),
                'compare' => '=',
            );
        }

        if ( ! empty( $_GET['pais_filter'] ) ) {
            $meta_query[] = array(
                'key'     => 'pais',
                'value'   => sanitize_text_field( $_GET['pais_filter'] ),
                'compare' => '=',
            );
        }

        if ( count( $meta_query ) > 1 ) {
            $meta_query['relation'] = 'AND';
        }

        if ( ! empty( $meta_query ) ) {
            $query->set( 'meta_query', $meta_query );
        }
    }
}
add_action( 'pre_get_posts', 'motorlan_apply_motor_filters' );

