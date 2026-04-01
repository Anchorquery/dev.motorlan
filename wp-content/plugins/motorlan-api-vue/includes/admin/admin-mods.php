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
 * Add custom columns to the publicacion CPT list.
 *
 * @param array $columns The existing columns.
 * @return array The modified columns.
 */
function motorlan_add_publicacion_columns( $columns ) {
    $new_columns = array();
    foreach ( $columns as $key => $value ) {
        if ( 'title' === $key ) {
            $new_columns['motor_image'] = __( 'Imagen', 'motorlan-api-vue' );
            $new_columns['formatted_title'] = __( 'Título Formateado', 'motorlan-api-vue' );
        }
        $new_columns[ $key ] = $value;
        if ( 'title' === $key ) {
            $new_columns['marca'] = __( 'Marca', 'motorlan-api-vue' );
            $new_columns['potencia'] = __( 'Potencia', 'motorlan-api-vue' );
            $new_columns['velocidad'] = __( 'Velocidad', 'motorlan-api-vue' );
            $new_columns['precio_de_venta'] = __( 'Precio', 'motorlan-api-vue' );
            $new_columns['estado_del_articulo'] = __( 'Estado', 'motorlan-api-vue' );
        }
    }
    return $new_columns;
}
add_filter( 'manage_edit-publicacion_columns', 'motorlan_add_publicacion_columns' );

/**
 * Add custom columns to the compra CPT list.
 */
function motorlan_add_compra_columns( $columns ) {
    $new_columns = array();
    foreach ( $columns as $key => $value ) {
        if ( 'title' === $key ) {
            $new_columns['comprador'] = __( 'Comprador', 'motorlan-api-vue' );
            $new_columns['motor_comprado'] = __( 'Motor / Publicación', 'motorlan-api-vue' );
        }
        $new_columns[ $key ] = $value;
        if ( 'title' === $key ) {
            $new_columns['precio_compra'] = __( 'Precio Compra', 'motorlan-api-vue' );
        }
    }
    return $new_columns;
}
add_filter( 'manage_edit-compra_columns', 'motorlan_add_compra_columns' );

/**
 * Populate the custom columns with data.
 *
 * @param string $column The column name.
 * @param int    $post_id The post ID.
 */
function motorlan_populate_publicacion_columns( $column, $post_id ) {
    switch ( $column ) {
        case 'motor_image':
            $image = get_field( 'motor_image', $post_id );
            if ( $image ) {
                echo '<img src="' . esc_url( $image['sizes']['thumbnail'] ) . '" width="60" />';
            }
            break;
        case 'formatted_title':
            $formatted = function_exists( 'motorlan_format_motor_name' ) ? motorlan_format_motor_name( $post_id ) : get_the_title( $post_id );
            echo '<strong>' . esc_html( $formatted ) . '</strong>';
            break;
        case 'marca':
            $marca = get_field( 'marca', $post_id );
            if ( is_object( $marca ) ) echo esc_html( $marca->name );
            else echo esc_html( $marca );
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
add_action( 'manage_publicacion_posts_custom_column', 'motorlan_populate_publicacion_columns', 10, 2 );

/**
 * Populate custom columns for Compra.
 */
function motorlan_populate_compra_columns( $column, $post_id ) {
    switch ( $column ) {
        case 'comprador':
            $user_id = get_post_meta( $post_id, 'comprador_id', true );
            if ( ! $user_id ) $user_id = get_field( 'comprador', $post_id );
            if ( $user_id ) {
                $user = get_userdata( $user_id );
                if ( $user ) echo esc_html( $user->display_name );
            }
            break;
        case 'motor_comprado':
            $motor_id = get_field( 'publicacion', $post_id );
            if ( ! $motor_id ) $motor_id = get_post_meta( $post_id, 'motor', true );
            
            if ( $motor_id ) {
                $formatted = function_exists( 'motorlan_format_motor_name' ) ? motorlan_format_motor_name( $motor_id ) : get_the_title( $motor_id );
                printf( '<strong><a href="%s">%s</a></strong>', get_edit_post_link($motor_id), esc_html( $formatted ) );
            }
            break;
        case 'precio_compra':
            $precio = get_post_meta( $post_id, 'precio_compra', true );
            if ( $precio ) echo esc_html( '€' . number_format_i18n( $precio, 2 ) );
            break;
    }
}
add_action( 'manage_compra_posts_custom_column', 'motorlan_populate_compra_columns', 10, 2 );

/**
 * Add custom filters to the publicacion CPT list.
 */
function motorlan_add_motor_filters() {
    global $typenow;
    if ( 'publicacion' === $typenow ) {
        // Filter by Brand
        $marcas = array();
        $posts = get_posts( array( 'post_type' => 'publicacion', 'posts_per_page' => -1 ) );
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
        if ( function_exists( 'motorlan_get_countries_list' ) ) {
            $paises = motorlan_get_countries_list();
        } else {
            $paises = array( 'es' => 'España', 'pt' => 'Portugal', 'fr' => 'Francia' );
        }
        $current_pais = isset( $_GET['pais_filter'] ) ? sanitize_text_field( $_GET['pais_filter'] ) : '';
        echo '<select name="pais_filter">';
        echo '<option value="">' . __( 'Todos los países', 'motorlan-api-vue' ) . '</option>';
        foreach ( $paises as $code => $name ) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( strtolower( $code ) ),
                selected( $current_pais, strtolower( $code ), false ),
                esc_html( $name )
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
    if ( is_admin() && 'edit.php' === $pagenow && 'publicacion' === $query->get( 'post_type' ) ) {
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

