<?php
/**
 * Helper functions for motor entities shared across REST routes.
 *
 * @package motorlan-api-vue
 */

if ( ! defined( 'WPINC' ) ) {
    die;
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

        $url = wp_get_attachment_image_url( $image_id, 'thumbnail' );
        if ( ! $url ) {
            return null;
        }

        return array(
            'id'    => (int) $image_id,
            'url'   => $url,
            'sizes' => array(
                'thumbnail' => $url,
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
            'permalink'  => get_permalink( $motor_id ),
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
                // Remove price if it exists
                if (isset($data['acf']['precio_de_venta'])) {
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

        // 1. Type (Taxonomy 'tipo')
        // Using wp_get_post_terms instead of get_field to avoid dependency issues if ACF is not fully loaded contextually or prefer native terms
        $terms = wp_get_post_terms( $post->ID, 'tipo' );
        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            $parts[] = strtoupper( $terms[0]->name );
        }

        // 2. Brand (ACF 'marca')
        $marca = function_exists( 'get_field' ) ? get_field( 'marca', $post->ID ) : get_post_meta( $post->ID, 'marca', true );
        if ( $marca ) {
            if ( is_object( $marca ) && isset( $marca->name ) ) {
                $parts[] = strtoupper( $marca->name );
            } elseif ( is_array( $marca ) && isset( $marca['name'] ) ) {
                $parts[] = strtoupper( $marca['name'] );
            } elseif ( is_numeric( $marca ) ) {
                $term = get_term( $marca );
                if ( $term && ! is_wp_error( $term ) ) {
                    $parts[] = strtoupper( $term->name );
                }
            } elseif ( is_string( $marca ) ) {
                $parts[] = strtoupper( $marca );
            }
        }

        // 3. Model/Reference (ACF 'tipo_o_referencia')
        $ref = function_exists( 'get_field' ) ? get_field( 'tipo_o_referencia', $post->ID ) : get_post_meta( $post->ID, 'tipo_o_referencia', true );
        if ( $ref ) {
            $parts[] = strtoupper( $ref );
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

        // 5. Speed (ACF 'velocidad')
        $velocidad = function_exists( 'get_field' ) ? get_field( 'velocidad', $post->ID ) : get_post_meta( $post->ID, 'velocidad', true );
        if ( $velocidad ) {
            $parts[] = $velocidad . ' RPM';
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


// Include Password Validation function if not already present here (it was added in previous turn but I'm overwriting file)
// Wait, I am using write_to_file with overwriting. I must include previous changes!
// I need to make sure I don't lose `motorlan_validate_password_strength`.
// I will view the file first to copy it?
// Ah, I already viewed the file in Step 222.
// Wait, Step 222 output DOES NOT SHOW `motorlan_validate_password_strength`.
// It shows `motorlan_get_motor_data` (lines 12-141), `motorlan_create_purchase` (143-216), `motorlan_format_motor_name` (218-286).
// Where is `motorlan_validate_password_strength`?
// The summary said: "Added the `motorlan_validate_password_strength` function to `includes/api/motor-helpers.php`".
// Maybe it was added at the END? Or maybe I missed it?
// Step 222 showed lines 1 to 287.
// Total Bytes: 10905.
// If it was added, it should be there.
// Maybe it was added to another file? `session-routes.php`?
// NO, summary says `includes/api/motor-helpers.php`.
// Let me check if there are more lines.
// Step 222 output says "Showing lines 1 to 287".
// Is that the whole file?
// The output ended with `}` (line 286).
// Maybe `motorlan_validate_password_strength` is MISSING from my view because the previous turn failed to write it properly?
// Or maybe I am blind.
// Let's scroll up in Step 222.
// It starts with `<?php` and defines `motorlan_get_motor_data`, `motorlan_create_purchase`, `motorlan_format_motor_name`.
// IT IS MISSING `motorlan_validate_password_strength`.
// This is critical. If I overwrite, I don't lose it because it wasn't there (or maybe it was below line 287?).
// But `view_file` says "Total Lines: 287".
// So it is NOT in the file.
// The previous session summary might have claimed it was done, but maybe the tool call failed or I am misinterpreting.
// OR maybe it was in a previous version of the file?
// Wait, `session-routes.php` calls it: `if ( function_exists( 'motorlan_validate_password_strength' ) )`.
// If it's missing, `register_user` will fail validation (or skip it?).
// `session-routes.php` line 327 checks `function_exists`. If not exists, it skips it.
// So currently password validation is NOT active?
// I MUST ADD it too.

// So I will add:
// 1. `motorlan_handle_db_error`
// 2. `motorlan_validate_json_content_type`
// 3. `motorlan_validate_password_strength` (RE-ADD or ADD)

