<?php
/**
 * Helper functions for motor entities shared across REST routes.
 *
 * @package motorlan-api-vue
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

if ( ! function_exists( 'motorlan_get_motor_data' ) ) {
    /**
     * Build a normalized data payload for a motor/publicacion post.
     *
     * @param int $motor_id Motor post ID.
     * @return array|null
     */
    function motorlan_get_motor_data( $motor_id ) {
        if ( $motor_id instanceof WP_Post ) {
            $motor_id = $motor_id->ID;
        } elseif ( is_array( $motor_id ) && isset( $motor_id['ID'] ) ) {
            $motor_id = $motor_id['ID'];
        }

        $motor_id = absint( $motor_id );
        if ( ! $motor_id ) {
            return null;
        }

        $post = get_post( $motor_id );
        if ( ! $post ) {
            return null;
        }

        $data = array(
            'id'         => $motor_id,
            'uuid'       => get_post_meta( $motor_id, 'uuid', true ),
            'title'      => get_the_title( $motor_id ),
            'slug'       => $post->post_name,
            'status'     => get_post_status( $motor_id ),
            'permalink'  => get_permalink( $motor_id ),
            'date'       => get_post_time( DATE_ATOM, true, $motor_id ),
            'modified'   => get_post_modified_time( DATE_ATOM, true, $motor_id ),
            'imagen_destacada' => function_exists( 'get_field' )
                ? get_field( 'motor_image', $motor_id, true )
                : get_post_meta( $motor_id, 'motor_image', true ),
            'acf'        => array(),
            'categories' => array(),
            'tipo'       => array(),
        );

        if ( function_exists( 'get_fields' ) ) {
            $fields = get_fields( $motor_id );
            if ( is_array( $fields ) ) {
                $data['acf'] = $fields;
            }
        }

        if ( empty( $data['acf'] ) ) {
            $fallback_keys = array(
                'precio_de_venta',
                'precio_negociable',
                'pais',
                'provincia',
                'marca',
                'tipo_o_referencia',
                'descripcion',
            );

            foreach ( $fallback_keys as $meta_key ) {
                $value = get_post_meta( $motor_id, $meta_key, true );
                if ( '' !== $value && null !== $value ) {
                    $data['acf'][ $meta_key ] = $value;
                }
            }
        }

        if ( function_exists( 'motorlan_get_post_taxonomy_details' ) ) {
            $data['categories'] = motorlan_get_post_taxonomy_details( $motor_id, 'categoria' );
            $data['tipo']       = motorlan_get_post_taxonomy_details( $motor_id, 'tipo' );
        } else {
            $categories = wp_get_post_terms( $motor_id, 'categoria' );
            if ( ! is_wp_error( $categories ) ) {
                foreach ( $categories as $term ) {
                    $data['categories'][] = array(
                        'id'   => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                    );
                }
            }

            $types = wp_get_post_terms( $motor_id, 'tipo' );
            if ( ! is_wp_error( $types ) ) {
                foreach ( $types as $term ) {
                    $data['tipo'][] = array(
                        'id'   => $term->term_id,
                        'name' => $term->name,
                        'slug' => $term->slug,
                    );
                }
            }
        }

        $author_id = (int) $post->post_author;
        $author    = null;

        if ( $author_id ) {
            $user = get_userdata( $author_id );
            if ( $user ) {
                $author = array(
                    'id'       => $author_id,
                    'name'     => $user->display_name ? $user->display_name : $user->user_login,
                    'email'    => $user->user_email,
                    'username' => $user->user_login,
                    'acf'      => array(),
                );

                if ( function_exists( 'get_fields' ) ) {
                    $user_fields = get_fields( 'user_' . $author_id );
                    if ( is_array( $user_fields ) ) {
                        $author['acf'] = $user_fields;
                    }
                }

                if ( empty( $author['acf']['avatar'] ) ) {
                    $author['acf']['avatar'] = get_avatar_url( $author_id );
                }
            }
        }

        $data['author'] = $author;

        return $data;
    }
}
