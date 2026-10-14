<?php
/**
 * Meta box "Tipo de Publicación" para el editor de `publicacion` en el admin.
 *
 * Da paridad total con el formulario de la app: un único selector radio en línea
 * con las 4 opciones (Motor AC / Motor DC / Regulador / Otros). Al guardar, escribe
 * a la vez:
 *   - el término de la taxonomía `tipo` (motor / regulador / otro-repuesto)
 *   - el campo ACF `tipo_de_alimentacion` (ac / dc) cuando es motor
 *
 * Mapeo idéntico al de la app (StepTechSpecs.vue):
 *   motor-ac      -> tipo=motor,        alimentacion=ac
 *   motor-dc      -> tipo=motor,        alimentacion=dc
 *   regulador     -> tipo=regulador
 *   otro-repuesto -> tipo=otro-repuesto
 *
 * Para evitar duplicidad, oculta los campos ACF "Tipo" y "Tipo de alimentación"
 * en este editor (siguen registrados, así get_field/update_field y la API siguen
 * funcionando igual).
 *
 * @package motorlan-api-vue
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Opciones del selector combinado: value (slug) => etiqueta + mapeo a taxonomía/alimentación.
 *
 * @return array
 */
function motorlan_tipo_pub_options() {
    return array(
        'motor-ac'      => array(
            'label' => __( 'Motor AC', 'motorlan-api-vue' ),
            'slug'  => 'motor',
            'name'  => 'Motor',
            'alim'  => 'ac',
        ),
        'motor-dc'      => array(
            'label' => __( 'Motor DC', 'motorlan-api-vue' ),
            'slug'  => 'motor',
            'name'  => 'Motor',
            'alim'  => 'dc',
        ),
        'regulador'     => array(
            'label' => __( 'Regulador', 'motorlan-api-vue' ),
            'slug'  => 'regulador',
            'name'  => 'Regulador',
            'alim'  => '',
        ),
        'otro-repuesto' => array(
            'label' => __( 'Otros', 'motorlan-api-vue' ),
            'slug'  => 'otro-repuesto',
            'name'  => 'Repuesto',
            'alim'  => '',
        ),
    );
}

/**
 * Calcula la opción seleccionada actual a partir del término y la alimentación.
 *
 * @param int $post_id
 * @return string|null
 */
function motorlan_tipo_pub_current_value( $post_id ) {
    $slugs = wp_get_object_terms( $post_id, 'tipo', array( 'fields' => 'slugs' ) );
    if ( is_wp_error( $slugs ) || empty( $slugs ) ) {
        return null;
    }
    $slug = $slugs[0];

    if ( 'motor' === $slug ) {
        $alim = function_exists( 'get_field' ) ? get_field( 'tipo_de_alimentacion', $post_id ) : get_post_meta( $post_id, 'tipo_de_alimentacion', true );
        return ( 'dc' === $alim ) ? 'motor-dc' : 'motor-ac';
    }

    if ( false !== stripos( $slug, 'regulador' ) ) {
        return 'regulador';
    }

    // Cualquier otro término no-motor (otro-repuesto, repuesto, otros...) = "Otros".
    return 'otro-repuesto';
}

/**
 * Registra el meta box.
 */
function motorlan_tipo_pub_add_meta_box() {
    add_meta_box(
        'motorlan_tipo_publicacion',
        __( 'Tipo de Publicación', 'motorlan-api-vue' ),
        'motorlan_tipo_pub_render_meta_box',
        'publicacion',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'motorlan_tipo_pub_add_meta_box' );

/**
 * Renderiza el meta box (radios en línea).
 *
 * @param WP_Post $post
 */
function motorlan_tipo_pub_render_meta_box( $post ) {
    wp_nonce_field( 'motorlan_tipo_pub_save', 'motorlan_tipo_pub_nonce' );

    $current = motorlan_tipo_pub_current_value( $post->ID );
    $options = motorlan_tipo_pub_options();
    ?>
    <p class="description" style="margin: 4px 0 10px;">
        <?php esc_html_e( 'Selecciona el tipo de publicación. Para motores define también AC/DC.', 'motorlan-api-vue' ); ?>
    </p>
    <div style="display:flex;flex-wrap:wrap;gap:8px 24px;">
        <?php foreach ( $options as $value => $opt ) : ?>
            <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer;">
                <input
                    type="radio"
                    name="motorlan_tipo_pub"
                    value="<?php echo esc_attr( $value ); ?>"
                    <?php checked( $current, $value ); ?>
                />
                <span><?php echo esc_html( $opt['label'] ); ?></span>
            </label>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * Resuelve el ID del término de la taxonomía `tipo` para la opción elegida, de
 * forma robusta (tolera variaciones de slug y evita crear duplicados):
 *   - motor-*       -> término 'motor'
 *   - regulador     -> término cuyo slug/nombre contiene 'regulador'
 *   - otro-repuesto -> 'otro-repuesto' / 'repuesto' / 'otros' o, si no, el término
 *                      que no es motor ni regulador. Sólo crea uno si no existe.
 *
 * @param string $value Valor de la opción (motor-ac, motor-dc, regulador, otro-repuesto).
 * @return int|null
 */
function motorlan_tipo_pub_resolve_term_id( $value ) {
    $by_slug = function ( $slug ) {
        $term = get_term_by( 'slug', $slug, 'tipo' );
        return ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
    };

    if ( 'motor-ac' === $value || 'motor-dc' === $value ) {
        $id = $by_slug( 'motor' );
        if ( ! $id ) {
            $created = wp_insert_term( 'Motor', 'tipo', array( 'slug' => 'motor' ) );
            $id      = ( ! is_wp_error( $created ) && isset( $created['term_id'] ) ) ? (int) $created['term_id'] : 0;
        }
        return $id ?: null;
    }

    $all = get_terms( array( 'taxonomy' => 'tipo', 'hide_empty' => false ) );
    $all = is_wp_error( $all ) ? array() : $all;

    if ( 'regulador' === $value ) {
        $id = $by_slug( 'regulador' );
        if ( ! $id ) {
            foreach ( $all as $t ) {
                if ( false !== stripos( $t->slug, 'regulador' ) || false !== stripos( $t->name, 'regulador' ) ) {
                    $id = (int) $t->term_id;
                    break;
                }
            }
        }
        if ( ! $id ) {
            $created = wp_insert_term( 'Regulador', 'tipo', array( 'slug' => 'regulador' ) );
            $id      = ( ! is_wp_error( $created ) && isset( $created['term_id'] ) ) ? (int) $created['term_id'] : 0;
        }
        return $id ?: null;
    }

    // otro-repuesto.
    foreach ( array( 'otro-repuesto', 'repuesto', 'otros' ) as $slug ) {
        $id = $by_slug( $slug );
        if ( $id ) {
            return $id;
        }
    }
    // Fallback: término que no es motor ni regulador.
    foreach ( $all as $t ) {
        if ( 'motor' !== $t->slug && false === stripos( $t->slug, 'regulador' ) ) {
            return (int) $t->term_id;
        }
    }
    // Si no existe ninguno, lo creamos.
    $created = wp_insert_term( 'Repuesto', 'tipo', array( 'slug' => 'otro-repuesto' ) );
    return ( ! is_wp_error( $created ) && isset( $created['term_id'] ) ) ? (int) $created['term_id'] : null;
}

/**
 * Guarda la selección: término `tipo` + `tipo_de_alimentacion`.
 *
 * @param int $post_id
 */
function motorlan_tipo_pub_save( $post_id ) {
    // Seguridad y guardas estándar.
    if ( ! isset( $_POST['motorlan_tipo_pub_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['motorlan_tipo_pub_nonce'] ) ), 'motorlan_tipo_pub_save' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }
    if ( ! isset( $_POST['motorlan_tipo_pub'] ) ) {
        return;
    }

    $value   = sanitize_text_field( wp_unslash( $_POST['motorlan_tipo_pub'] ) );
    $options = motorlan_tipo_pub_options();
    if ( ! isset( $options[ $value ] ) ) {
        return;
    }

    $opt     = $options[ $value ];
    $term_id = motorlan_tipo_pub_resolve_term_id( $value );

    if ( $term_id ) {
        wp_set_object_terms( $post_id, array( $term_id ), 'tipo', false );
    }

    // Sólo fijamos la alimentación cuando es motor (ac/dc).
    if ( '' !== $opt['alim'] ) {
        if ( function_exists( 'update_field' ) ) {
            update_field( 'tipo_de_alimentacion', $opt['alim'], $post_id );
        } else {
            update_post_meta( $post_id, 'tipo_de_alimentacion', $opt['alim'] );
        }
    }
}
add_action( 'save_post_publicacion', 'motorlan_tipo_pub_save', 20 );

/**
 * Oculta los campos ACF redundantes en el editor (sólo en admin):
 * "Tipo" (field_6643d3c3b0340) y "Tipo de alimentación" (field_6643d3c3b0352).
 * Siguen registrados: get_field/update_field y la API REST no se ven afectados.
 *
 * @param array $field
 * @return array|false
 */
function motorlan_tipo_pub_hide_acf_fields( $field ) {
    if ( ! is_admin() ) {
        return $field;
    }
    $ocultar = array( 'field_6643d3c3b0340', 'field_6643d3c3b0352' );
    if ( isset( $field['key'] ) && in_array( $field['key'], $ocultar, true ) ) {
        return false;
    }
    return $field;
}
add_filter( 'acf/prepare_field', 'motorlan_tipo_pub_hide_acf_fields' );
