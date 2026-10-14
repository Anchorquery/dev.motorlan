<?php
/**
 * Helper functions for motor entities shared across REST routes.
 *
 * @package motorlan-api-vue
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

if ( ! function_exists( 'motorlan_resolve_lang_for_user' ) ) {
    /**
     * Resolve WPML language code for given user (fallback default lang).
     */
    function motorlan_resolve_lang_for_user( $user_id = null ) {
        if ( $user_id ) {
            $locale = get_user_locale( (int) $user_id );
            $map    = [
                'es_ES' => 'es',
                'es'    => 'es',
                'en_US' => 'en',
                'en_GB' => 'en',
                'en'    => 'en',
                'eu'    => 'eu',
                'eu_ES' => 'eu',
            ];
            if ( isset( $map[ $locale ] ) ) {
                return $map[ $locale ];
            }
        }
        if ( function_exists( 'apply_filters' ) ) {
            $default = apply_filters( 'wpml_default_language', null );
            if ( $default ) return $default;
        }
        return 'es';
    }
}

if ( ! function_exists( 'motorlan_localized_page_url' ) ) {
    /**
     * Build path (relative URL) for base page + sub-path in given language.
     * Returns relative path so existing email templates can prepend site_url
     * sin romperse con doble dominio.
     */
    function motorlan_localized_page_url( $base_slug, $sub = '', $user_id = null ) {
        $lang = motorlan_resolve_lang_for_user( $user_id );
        $page = get_page_by_path( $base_slug );

        $fallback = '/' . $base_slug . '/' . ltrim( $sub, '/' );

        if ( ! $page ) {
            return $fallback;
        }

        $target_id = $page->ID;
        if ( function_exists( 'apply_filters' ) ) {
            $tx_id = apply_filters( 'wpml_object_id', $page->ID, 'page', true, $lang );
            if ( $tx_id ) $target_id = $tx_id;
        }

        $permalink = get_permalink( $target_id );
        if ( ! $permalink ) return $fallback;

        $path = wp_parse_url( $permalink, PHP_URL_PATH );
        if ( ! $path ) return $fallback;

        return trailingslashit( $path ) . ltrim( $sub, '/' );
    }
}

if ( ! function_exists( 'motorlan_localized_page_url_absolute' ) ) {
    /**
     * Versión absoluta (con esquema + host) para usar directamente en emails sin templates.
     */
    function motorlan_localized_page_url_absolute( $base_slug, $sub = '', $user_id = null ) {
        return home_url( motorlan_localized_page_url( $base_slug, $sub, $user_id ) );
    }
}

if ( ! function_exists( 'motorlan_account_url' ) ) {
    function motorlan_account_url( $sub = '', $user_id = null ) {
        return motorlan_localized_page_url( 'mi-cuenta', $sub, $user_id );
    }
}

if ( ! function_exists( 'motorlan_get_store_url' ) ) {
    /**
     * Get the public store URL for a publication (WPML-aware, ABSOLUTE).
     * Retrocompatible: callers previos esperan URL absoluta.
     *
     * @param int|string $post_id_or_slug Post ID or post slug.
     * @param int|null   $user_id Recipient user id for language resolution.
     * @return string Full absolute URL to the publication in the store.
     */
    function motorlan_get_store_url( $post_id_or_slug, $user_id = null ) {
        if ( is_numeric( $post_id_or_slug ) ) {
            $slug = get_post_field( 'post_name', (int) $post_id_or_slug );
        } else {
            $slug = $post_id_or_slug;
        }

        return motorlan_localized_page_url_absolute( 'marketplace-motorlan', $slug ? trailingslashit( $slug ) : '', $user_id );
    }
}

if ( ! function_exists( 'motorlan_handle_db_error' ) ) {
    /**
     * Handle database errors securely with logging.
     *
     * @param string $operation Description of the operation.
     * @param mixed  $error_details Error details (string or array).
     * @return WP_Error
     */
    function motorlan_handle_db_error( $operation, $error_details ) {
        if ( class_exists( 'Motorlan_Security_Logger' ) ) {
            Motorlan_Security_Logger::log( 'database_error', array(
                'operation' => $operation,
                'error'     => $error_details,
            ), 'critical' );
        }

        $message = ( defined( 'WP_DEBUG' ) && WP_DEBUG )
            ? 'DB Error: ' . ( is_string( $error_details ) ? $error_details : json_encode( $error_details ) )
            : 'Error procesando la solicitud. Intente nuevamente.';

        return new WP_Error( 'db_error', $message, array( 'status' => 500 ) );
    }
}

if ( ! function_exists( 'motorlan_validate_json_content_type' ) ) {
    /**
     * Validate that the request Content-Type is application/json.
     *
     * @param WP_REST_Request $request Request object.
     * @return true|WP_Error
     */
    function motorlan_validate_json_content_type( $request ) {
        $content_type = $request->get_header( 'content-type' );
        if ( empty( $content_type ) || false === strpos( $content_type, 'application/json' ) ) {
            return new WP_Error( 'invalid_content_type', 'Content-Type must be application/json', array( 'status' => 415 ) );
        }
        return true;
    }
}

if ( ! function_exists( 'motorlan_format_image_for_frontend' ) ) {
    /**
     * Format an image ID or ACF image array into a consistent structure for the frontend.
     *
     * @param mixed $image Image ID or ACF array.
     * @return array|null
     */
    function motorlan_format_image_for_frontend( $image ) {
        if ( ! $image ) {
            return null;
        }

        $image_id = $image;
        if ( is_array( $image ) && isset( $image['ID'] ) ) {
            $image_id = $image['ID'];
        }

        if ( ! is_numeric( $image_id ) ) {
            // If it's already a URL or something else, return as is (wrapped in the expected structure if possible)
            if ( is_string( $image ) && ! empty( $image ) ) {
                return array(
                    'url'   => $image,
                    'sizes' => array(
                        'thumbnail' => $image,
                    ),
                );
            }
            return $image;
        }

        $url = wp_get_attachment_image_url( $image_id, 'medium_large' );
        if ( ! $url ) {
            $url = wp_get_attachment_image_url( $image_id, 'large' );
        }
        if ( ! $url ) {
            $url = wp_get_attachment_image_url( $image_id, 'full' );
        }
        if ( ! $url ) {
            return null;
        }

        $thumbnail_url = wp_get_attachment_image_url( $image_id, 'thumbnail' ) ?: $url;

        return array(
            'id'    => (int) $image_id,
            'url'   => $url,
            'sizes' => array(
                'thumbnail'    => $thumbnail_url,
                'medium_large' => $url,
            ),
        );
    }
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
            'permalink'  => motorlan_get_store_url( $motor_id ),
            'date'       => get_post_time( DATE_ATOM, true, $motor_id ),
            'modified'   => get_post_modified_time( DATE_ATOM, true, $motor_id ),
            'imagen_destacada' => motorlan_format_image_for_frontend( 
                function_exists( 'get_field' ) 
                    ? get_field( 'motor_image', $motor_id, true ) 
                    : get_post_meta( $motor_id, 'motor_image', true ) 
            ),
            'acf'        => array(),
            'categories' => array(),
            'tipo'       => array(),
        );

        if ( function_exists( 'get_fields' ) ) {
            $fields = get_fields( $motor_id );
            if ( is_array( $fields ) ) {
                $data['acf'] = $fields;
                // Ocultar precio si "Consultar precio" está marcado
                $consultar_precio = isset($data['acf']['precio_negociable']) ? $data['acf']['precio_negociable'] : 'no';
                $is_consult = in_array($consultar_precio, ['yes', 'Sí', 'si', 'true', true], true);
                if ($is_consult && isset($data['acf']['precio_de_venta'])) {
                    unset($data['acf']['precio_de_venta']);
                }
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

        // 'categories' DESACTIVADO (taxonomía categoria sustituida por tipo).
        if ( function_exists( 'motorlan_get_post_taxonomy_details' ) ) {
            $data['tipo']       = motorlan_get_post_taxonomy_details( $motor_id, 'tipo' );
        } else {
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

if ( ! function_exists( 'motorlan_create_purchase' ) ) {
    /**
     * Create a purchase post (compra) and set up related metadata.
     *
     * @param int   $motor_id   ID of the publication (motor).
     * @param int   $buyer_id   ID of the buyer user.
     * @param float $amount     Purchase amount.
     * @param int   $seller_id  ID of the seller user.
     * @param int   $offer_id   Optional. ID of the related offer.
     * @return array|WP_Error Array with uuid and id on success, WP_Error on failure.
     */
    function motorlan_create_purchase( $motor_id, $buyer_id, $amount, $seller_id, $offer_id = 0 ) {
        $motor_title = get_the_title( $motor_id );
        if ( ! $motor_title ) {
            return new WP_Error( 'invalid_publication', 'La publicación no existe.', array( 'status' => 404 ) );
        }

        $buyer      = get_userdata( $buyer_id );
        $buyer_name = $buyer ? $buyer->display_name : '';

        $purchase_id = wp_insert_post( array(
            'post_type'   => 'compra',
            'post_status' => 'publish',
            'post_name'   => 'Compra ' . $motor_title,
            'post_title'  => $motor_title . ' - ' . $buyer_name,
        ) );

        if ( is_wp_error( $purchase_id ) ) {
            // New Error Handling Integration
            if ( function_exists( 'motorlan_handle_db_error' ) ) {
                return motorlan_handle_db_error( 'create_purchase_insert_post', $purchase_id->get_error_message() );
            }
            return $purchase_id;
        }

        $uuid  = wp_generate_uuid4();
        $today = current_time( 'd/m/Y' );

        if ( function_exists( 'update_field' ) ) {
            update_field( 'uuid', $uuid, $purchase_id );
            update_field( 'motor', $motor_id, $purchase_id );
            update_field( 'publicacion', $motor_id, $purchase_id ); // New robust field
            update_field( 'vendedor', $seller_id, $purchase_id );
            update_field( 'comprador', $buyer_id, $purchase_id );
            update_field( 'usuario', $buyer_id, $purchase_id );
            update_field( 'precio_compra', $amount, $purchase_id );
            update_field( 'estado', 'completed', $purchase_id );
            update_field( 'fecha_compra', $today, $purchase_id );
        } else {
            update_post_meta( $purchase_id, 'uuid', $uuid );
            update_post_meta( $purchase_id, 'motor', $motor_id );
            update_post_meta( $purchase_id, 'publicacion', $motor_id );
            update_post_meta( $purchase_id, 'vendedor', $seller_id );
            update_post_meta( $purchase_id, 'comprador', $buyer_id );
            update_post_meta( $purchase_id, 'usuario', $buyer_id );
            update_post_meta( $purchase_id, 'precio_compra', $amount );
            update_post_meta( $purchase_id, 'estado', 'completed' );
            update_post_meta( $purchase_id, 'fecha_compra', $today );
        }

        update_post_meta( $purchase_id, 'vendedor_id', $seller_id );
        update_post_meta( $purchase_id, 'comprador_id', $buyer_id );
        update_post_meta( $purchase_id, 'precio_compra', $amount );
        update_post_meta( $purchase_id, 'tipo_venta', 'sale' );

        if ( $offer_id ) {
            update_post_meta( $purchase_id, 'offer_id', (int) $offer_id );
            if ( function_exists( 'update_field' ) ) {
                 update_field( 'offer', $offer_id, $purchase_id );
            }
        }

        return array(
            'uuid' => $uuid,
            'id'   => $purchase_id,
        );
    }
}

if ( ! function_exists( 'motorlan_mb_upper' ) ) {
    /**
     * Mayúsculas multibyte (strtoupper deja "ñ", "ü"... en minúscula: "ESPAñA").
     *
     * @param string $value
     * @return string
     */
    function motorlan_mb_upper( $value ) {
        $value = (string) $value;
        return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $value, 'UTF-8' ) : strtoupper( $value );
    }
}

if ( ! function_exists( 'motorlan_format_motor_name' ) ) {
    /**
     * Format the motor name based on its attributes (Type, Brand, Model, Specs).
     *
     * @param int|WP_Post $post_id Post ID or object.
     * @return string Formatted name or original title.
     */
    function motorlan_format_motor_name( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return '';
        }

        $parts = array();

        /*
        // 1. Type (Taxonomy 'tipo')
        // Using wp_get_post_terms instead of get_field to avoid dependency issues if ACF is not fully loaded contextually or prefer native terms
        $terms = wp_get_post_terms( $post->ID, 'tipo' );
        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            $parts[] = strtoupper( $terms[0]->name );
        }
        */

        // 2. Brand (ACF 'marca')
        $marca = function_exists( 'get_field' ) ? get_field( 'marca', $post->ID ) : get_post_meta( $post->ID, 'marca', true );
        if ( $marca ) {
            if ( is_object( $marca ) && isset( $marca->name ) ) {
                $parts[] = motorlan_mb_upper( $marca->name );
            } elseif ( is_array( $marca ) && isset( $marca['name'] ) ) {
                $parts[] = motorlan_mb_upper( $marca['name'] );
            } elseif ( is_numeric( $marca ) ) {
                $term = get_term( $marca );
                if ( $term && ! is_wp_error( $term ) ) {
                    $parts[] = motorlan_mb_upper( $term->name );
                }
            } elseif ( is_string( $marca ) ) {
                $parts[] = motorlan_mb_upper( $marca );
            }
        }

        // Marca nueva pendiente de aprobación (marca = -1, sin término): usar el nombre propuesto
        // para que el título y los avisos al admin no salgan sin marca.
        if ( empty( $parts ) ) {
            $pending_brand = get_post_meta( $post->ID, '_pending_brand_name', true );
            if ( $pending_brand ) {
                $parts[] = motorlan_mb_upper( $pending_brand );
            }
        }

        // 3. Model/Reference (ACF 'tipo_o_referencia')
        $ref = function_exists( 'get_field' ) ? get_field( 'tipo_o_referencia', $post->ID ) : get_post_meta( $post->ID, 'tipo_o_referencia', true );
        if ( $ref ) {
            $parts[] = motorlan_mb_upper( $ref );
        }

        // 4. Power or Torque (ACF 'potencia' / 'par_nominal')
        $potencia = function_exists( 'get_field' ) ? get_field( 'potencia', $post->ID ) : get_post_meta( $post->ID, 'potencia', true );
        if ( $potencia ) {
            $parts[] = $potencia . ' KW';
        } else {
            $par = function_exists( 'get_field' ) ? get_field( 'par_nominal', $post->ID ) : get_post_meta( $post->ID, 'par_nominal', true );
            if ( $par ) {
                $parts[] = $par . ' NM';
            }
        }

        if ( empty( $parts ) ) {
            return $post->post_title;
        }

        return implode( ' ', array_unique( array_filter( $parts ) ) );
    }
}

if ( ! function_exists( 'motorlan_validate_password_strength' ) ) {
    /**
     * Validate password strength with strict rules.
     *
     * @param string $password Password to validate.
     * @param array  $user_data User data to avoid personal info usage.
     * @return true|WP_Error
     */
    function motorlan_validate_password_strength( $password, $user_data = array() ) {
        $errors = array();

        // 1. Length (Min 8)
        if ( strlen( $password ) < 8 ) {
            $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        }

        // 2. Uppercase
        if ( ! preg_match( '/[A-Z]/', $password ) ) {
            $errors[] = 'Debe contener al menos una letra mayúscula.';
        }

        // 3. Lowercase
        if ( ! preg_match( '/[a-z]/', $password ) ) {
            $errors[] = 'Debe contener al menos una letra minúscula.';
        }

        // 4. Number
        if ( ! preg_match( '/[0-9]/', $password ) ) {
            $errors[] = 'Debe contener al menos un número.';
        }

        // 5. Special Char
        if ( ! preg_match( '/[^A-Za-z0-9]/', $password ) ) {
            $errors[] = 'Debe contener al menos un carácter especial (!@#$%^&*()_+-=[]{}|;:,.<>?).';
        }

        // 6. Common Patterns
        $common_passwords = array(
            'password', 'Password123!', '12345678', 'qwerty123',
            'abc123456', 'motorlan', 'Motorlan123!', 'admin123',
        );

        foreach ( $common_passwords as $common ) {
            if ( false !== stripos( $password, $common ) ) {
                $errors[] = 'La contraseña contiene patrones demasiado comunes.';
                break;
            }
        }

        // 7. Personal Data
        if ( ! empty( $user_data ) ) {
            $personal_data = array_filter( array(
                isset( $user_data['username'] ) ? $user_data['username'] : '',
                isset( $user_data['email'] ) ? $user_data['email'] : '',
                isset( $user_data['first_name'] ) ? $user_data['first_name'] : '',
                isset( $user_data['name'] ) ? $user_data['name'] : '',
            ) );

            foreach ( $personal_data as $data ) {
                if ( strlen( $data ) >= 3 && false !== stripos( $password, $data ) ) {
                    $errors[] = 'La contraseña no debe contener información personal.';
                    break;
                }
            }
        }

        if ( ! empty( $errors ) ) {
            return new WP_Error(
                'weak_password',
                'La contraseña no cumple los requisitos de seguridad.',
                array( 'status' => 400, 'errors' => $errors )
            );
        }

        return true;
    }
}


// ---------------------------------------------------------------------------
// Helpers compartidos por el alta y la edición de publicaciones
// (create-publicacion.php / update-publicacion-by-uuid.php).
// ---------------------------------------------------------------------------

if ( ! function_exists( 'motorlan_publicacion_payload_too_large' ) ) {
    /**
     * Detecta un envío multipart que PHP ha descartado por superar post_max_size:
     * en ese caso $_POST y $_FILES llegan vacíos y, sin este aviso, el usuario
     * veía "El título es obligatorio" (alta) o se borraban imágenes (edición).
     *
     * @param WP_REST_Request $request
     * @return WP_Error|null
     */
    function motorlan_publicacion_payload_too_large( $request ) {
        $content_length = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
        $content_type   = $request->get_content_type();
        $is_multipart   = is_array( $content_type ) && ! empty( $content_type['value'] ) && false !== strpos( $content_type['value'], 'multipart/form-data' );

        if ( $is_multipart && $content_length > 0 && empty( $_POST ) && empty( $_FILES ) ) {
            return new WP_Error(
                'payload_too_large',
                sprintf(
                    'Los archivos superan el tamaño máximo permitido (%s en total). Reduce el número o el tamaño de las imágenes. No se ha guardado nada.',
                    size_format( wp_convert_hr_to_bytes( ini_get( 'post_max_size' ) ) )
                ),
                array( 'status' => 413 )
            );
        }

        return null;
    }
}

if ( ! function_exists( 'motorlan_publicacion_rearrange_files' ) ) {
    /**
     * Normaliza una entrada de $_FILES (simple o múltiple "campo[]") a una lista de archivos.
     *
     * @param array $entry
     * @return array[] Lista de ['name','type','tmp_name','error','size'].
     */
    function motorlan_publicacion_rearrange_files( $entry ) {
        if ( empty( $entry ) || ! is_array( $entry ) || ! isset( $entry['name'] ) ) {
            return array();
        }

        if ( ! is_array( $entry['name'] ) ) {
            return array( $entry );
        }

        $files = array();
        foreach ( $entry['name'] as $key => $name ) {
            $files[] = array(
                'name'     => $name,
                'type'     => isset( $entry['type'][ $key ] ) ? $entry['type'][ $key ] : '',
                'tmp_name' => isset( $entry['tmp_name'][ $key ] ) ? $entry['tmp_name'][ $key ] : '',
                'error'    => isset( $entry['error'][ $key ] ) ? (int) $entry['error'][ $key ] : UPLOAD_ERR_NO_FILE,
                'size'     => isset( $entry['size'][ $key ] ) ? (int) $entry['size'][ $key ] : 0,
            );
        }

        return $files;
    }
}

if ( ! function_exists( 'motorlan_publicacion_upload_error_message' ) ) {
    /**
     * Mensaje legible para un código de error de subida de PHP.
     *
     * @param int    $code
     * @param string $name Nombre del archivo.
     * @return string
     */
    function motorlan_publicacion_upload_error_message( $code, $name = '' ) {
        $prefix = $name !== '' ? $name . ': ' : '';
        if ( in_array( (int) $code, array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ) {
            return $prefix . sprintf( 'el archivo supera el tamaño máximo permitido (%s por archivo).', size_format( wp_max_upload_size() ) );
        }
        if ( (int) $code === UPLOAD_ERR_PARTIAL ) {
            return $prefix . 'el archivo se subió de forma incompleta. Inténtalo de nuevo.';
        }
        return $prefix . 'no se pudo subir el archivo.';
    }
}

if ( ! function_exists( 'motorlan_publicacion_find_upload_error' ) ) {
    /**
     * Busca errores de subida de PHP en los campos de archivo indicados.
     * UPLOAD_ERR_NO_FILE (campo vacío) no se considera error.
     *
     * @param array    $files       $request->get_file_params().
     * @param string[] $field_names Campos a revisar.
     * @return WP_Error|null
     */
    function motorlan_publicacion_find_upload_error( $files, $field_names ) {
        foreach ( (array) $field_names as $field ) {
            if ( empty( $files[ $field ] ) ) {
                continue;
            }
            foreach ( motorlan_publicacion_rearrange_files( $files[ $field ] ) as $file ) {
                $error = (int) $file['error'];
                if ( $error !== UPLOAD_ERR_OK && $error !== UPLOAD_ERR_NO_FILE ) {
                    return new WP_Error(
                        'upload_error',
                        motorlan_publicacion_upload_error_message( $error, (string) $file['name'] ) . ' No se ha guardado nada.',
                        array( 'status' => 413 )
                    );
                }
            }
        }

        return null;
    }
}

if ( ! function_exists( 'motorlan_publicacion_image_mimes' ) ) {
    /**
     * Tipos admitidos para la imagen principal y la galería.
     *
     * @return array
     */
    function motorlan_publicacion_image_mimes() {
        return array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'gif'          => 'image/gif',
            'webp'         => 'image/webp',
        );
    }
}

if ( ! function_exists( 'motorlan_publicacion_doc_mimes' ) ) {
    /**
     * Tipos admitidos para la documentación adicional (lo que anuncia el formulario).
     *
     * @return array
     */
    function motorlan_publicacion_doc_mimes() {
        return array(
            'pdf'          => 'application/pdf',
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
        );
    }
}

if ( ! function_exists( 'motorlan_publicacion_file_type_allowed' ) ) {
    /**
     * Comprueba el tipo REAL del archivo subido (contenido + extensión) contra una lista de mimes.
     *
     * @param array $file  ['name','tmp_name',...].
     * @param array $mimes Lista extensión => mime.
     * @return bool
     */
    function motorlan_publicacion_file_type_allowed( $file, $mimes ) {
        if ( empty( $file['tmp_name'] ) || empty( $file['name'] ) ) {
            return false;
        }
        if ( ! function_exists( 'wp_check_filetype_and_ext' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], $mimes );
        return ! empty( $check['type'] ) && in_array( $check['type'], array_values( $mimes ), true );
    }
}

if ( ! function_exists( 'motorlan_publicacion_attachment_usable' ) ) {
    /**
     * ¿Puede esta publicación referenciar el adjunto? Debe ser un attachment de la
     * propia publicación o subido por el usuario actual o por el autor de la publicación.
     * Evita enlazar fotos/documentos de publicaciones ajenas por ID.
     *
     * @param int $attachment_id
     * @param int $post_id
     * @return bool
     */
    function motorlan_publicacion_attachment_usable( $attachment_id, $post_id ) {
        $attachment_id = (int) $attachment_id;
        if ( $attachment_id <= 0 || get_post_type( $attachment_id ) !== 'attachment' ) {
            return false;
        }
        if ( current_user_can( 'administrator' ) ) {
            return true;
        }
        if ( (int) wp_get_post_parent_id( $attachment_id ) === (int) $post_id ) {
            return true;
        }
        $attachment_author = (int) get_post_field( 'post_author', $attachment_id );
        return $attachment_author === get_current_user_id()
            || $attachment_author === (int) get_post_field( 'post_author', $post_id );
    }
}

if ( ! function_exists( 'motorlan_publicacion_normalize_acf_values' ) ) {
    /**
     * Valida y normaliza los valores ACF de alta/edición (solo los presentes en $acf_data):
     * - potencia, velocidad, par nominal, voltaje e intensidad: número > 0 (vacío = sin valor).
     * - precio: número >= 0. Stock: entero >= 0.
     * - Alquiler 'Sí'/'No' y "consultar precio" 'yes'/'no' como en las choices de ACF.
     * - Alimentación solo 'ac'/'dc'.
     *
     * @param array $acf_data
     * @return array|WP_Error Datos normalizados o error 400.
     */
    function motorlan_publicacion_normalize_acf_values( $acf_data ) {
        // Metas antiguas guardadas como checkbox llegan como array desde el GET: sin esto
        // (string) $raw daba "Array to string conversion" y "Consultar precio" se perdía
        foreach ( array( 'posibilidad_de_alquiler', 'precio_negociable' ) as $choice_field ) {
            if ( isset( $acf_data[ $choice_field ] ) && is_array( $acf_data[ $choice_field ] ) ) {
                $first = reset( $acf_data[ $choice_field ] );
                $acf_data[ $choice_field ] = false === $first ? '' : $first;
            }
        }

        $positive_fields = array(
            'potencia'    => 'La potencia',
            'velocidad'   => 'La velocidad',
            'par_nominal' => 'El par nominal',
            'voltaje'     => 'El voltaje',
            'intensidad'  => 'La intensidad',
        );

        foreach ( $positive_fields as $field => $label ) {
            if ( ! array_key_exists( $field, $acf_data ) ) {
                continue;
            }
            $value = $acf_data[ $field ];
            if ( $value === null || $value === '' || $value === false ) {
                continue;
            }
            if ( is_string( $value ) ) {
                $value = str_replace( ',', '.', trim( $value ) );
            }
            if ( ! is_numeric( $value ) || (float) $value <= 0 ) {
                return new WP_Error( 'invalid_' . $field, $label . ' debe ser un número mayor que 0.', array( 'status' => 400 ) );
            }
            $acf_data[ $field ] = $value + 0;
        }

        if ( array_key_exists( 'precio_de_venta', $acf_data ) && $acf_data['precio_de_venta'] !== null && $acf_data['precio_de_venta'] !== '' ) {
            $price = is_string( $acf_data['precio_de_venta'] ) ? str_replace( ',', '.', trim( $acf_data['precio_de_venta'] ) ) : $acf_data['precio_de_venta'];
            if ( ! is_numeric( $price ) || (float) $price < 0 ) {
                return new WP_Error( 'invalid_precio_de_venta', 'El precio debe ser un número mayor o igual que 0.', array( 'status' => 400 ) );
            }
            $acf_data['precio_de_venta'] = $price + 0;
        }

        if ( array_key_exists( 'stock', $acf_data ) && $acf_data['stock'] !== null && $acf_data['stock'] !== '' ) {
            if ( ! is_numeric( $acf_data['stock'] ) || (int) $acf_data['stock'] < 0 || (float) $acf_data['stock'] != (int) $acf_data['stock'] ) {
                return new WP_Error( 'invalid_stock', 'El stock debe ser un número entero mayor o igual que 0.', array( 'status' => 400 ) );
            }
            $acf_data['stock'] = (int) $acf_data['stock'];
        }

        if ( array_key_exists( 'posibilidad_de_alquiler', $acf_data ) && $acf_data['posibilidad_de_alquiler'] !== null ) {
            $raw = strtolower( remove_accents( trim( (string) $acf_data['posibilidad_de_alquiler'] ) ) );
            $acf_data['posibilidad_de_alquiler'] = in_array( $raw, array( 'si', 'yes', '1', 'true' ), true ) ? 'Sí' : 'No';
        }

        if ( array_key_exists( 'precio_negociable', $acf_data ) && $acf_data['precio_negociable'] !== null ) {
            $raw = $acf_data['precio_negociable'];
            $raw = is_bool( $raw ) ? ( $raw ? 'yes' : 'no' ) : strtolower( remove_accents( trim( (string) $raw ) ) );
            $acf_data['precio_negociable'] = in_array( $raw, array( 'yes', 'si', '1', 'true' ), true ) ? 'yes' : 'no';
        }

        if ( array_key_exists( 'tipo_de_alimentacion', $acf_data ) && ! in_array( $acf_data['tipo_de_alimentacion'], array( 'ac', 'dc' ), true ) ) {
            unset( $acf_data['tipo_de_alimentacion'] );
        }

        return $acf_data;
    }
}

if ( ! function_exists( 'motorlan_publicacion_mark_approved' ) ) {
    /**
     * Marca la publicación como aprobada por un administrador al menos una vez.
     * Permite que el dueño la reactive (pausada/vendida -> publicada) sin volver a revisión.
     *
     * @param int $post_id
     * @return void
     */
    function motorlan_publicacion_mark_approved( $post_id ) {
        if ( ! get_post_meta( $post_id, '_motorlan_approved_at', true ) ) {
            update_post_meta( $post_id, '_motorlan_approved_at', current_time( 'mysql' ) );
        }
    }
}

if ( ! function_exists( 'motorlan_publicacion_was_approved' ) ) {
    /**
     * ¿La publicación ya pasó la revisión? Publicada en WP o con marca de aprobación.
     *
     * @param int $post_id
     * @return bool
     */
    function motorlan_publicacion_was_approved( $post_id ) {
        return get_post_status( $post_id ) === 'publish' || (bool) get_post_meta( $post_id, '_motorlan_approved_at', true );
    }
}

if ( ! function_exists( 'motorlan_publicacion_has_purchases' ) ) {
    /**
     * ¿Tiene compras asociadas? El historial del comprador depende del post.
     *
     * @param int $post_id
     * @return bool
     */
    function motorlan_publicacion_has_purchases( $post_id ) {
        $query = new WP_Query( array(
            'post_type'      => 'compra',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => array(
                'relation' => 'OR',
                array( 'key' => 'publicacion', 'value' => (int) $post_id ),
                array( 'key' => 'motor', 'value' => (int) $post_id ),
            ),
        ) );
        return $query->have_posts();
    }
}
