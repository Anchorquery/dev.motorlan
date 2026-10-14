<?php
/**
 * Admin UI for Motorlan store cache controls.
 *
 * @package motorlan-api-vue
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Return the store cache options with defaults applied.
 *
 * @return array
 */
function motorlan_get_store_cache_options() {
    $saved = get_option( 'motorlan_store_cache_options', [] );
    if ( ! is_array( $saved ) ) {
        $saved = [];
    }

    return wp_parse_args( $saved, motorlan_get_store_cache_default_options() );
}

/**
 * Default store cache options.
 *
 * @return array
 */
function motorlan_get_store_cache_default_options() {
    return [
        'enabled'          => 1,
        'ttl_minutes'      => 10,
        'cache_all_pages'  => 1,
    ];
}

/**
 * RENDIMIENTO: la caché del store debe estar activa aunque nunca se hayan guardado los
 * ajustes. El 'default' de register_setting() solo se aplica tras admin_init, así que en
 * la API REST get_option() devolvía [] y motorlan_should_cache_store_publicaciones_request()
 * la daba por desactivada (en producción no salía la cabecera X-Motorlan-Cache).
 *
 * @return array
 */
function motorlan_filter_store_cache_default_option() {
    return motorlan_get_store_cache_default_options();
}
add_filter( 'default_option_motorlan_store_cache_options', 'motorlan_filter_store_cache_default_option' );

/**
 * Sanitize store cache options.
 *
 * @param array $input Raw submitted options.
 * @return array
 */
function motorlan_sanitize_store_cache_options( $input ) {
    $current = motorlan_get_store_cache_options();

    return [
        'enabled'         => ! empty( $input['enabled'] ) ? 1 : 0,
        'ttl_minutes'     => max( 1, min( 1440, absint( $input['ttl_minutes'] ?? $current['ttl_minutes'] ) ) ),
        'cache_all_pages' => ! empty( $input['cache_all_pages'] ) ? 1 : 0,
    ];
}

/**
 * Register the cache settings page under Tools.
 *
 * @return void
 */
function motorlan_register_store_cache_menu() {
    add_management_page(
        __( 'Caché tienda', 'motorlan-api-vue' ),
        __( 'Caché tienda', 'motorlan-api-vue' ),
        'manage_options',
        'motorlan-store-cache',
        'motorlan_render_store_cache_page'
    );
}
add_action( 'admin_menu', 'motorlan_register_store_cache_menu' );

/**
 * Register the cache settings so the Settings API can render the form.
 *
 * @return void
 */
function motorlan_register_store_cache_settings() {
    register_setting(
        'motorlan_store_cache_group',
        'motorlan_store_cache_options',
        [
            'sanitize_callback' => 'motorlan_sanitize_store_cache_options',
            'default'           => motorlan_get_store_cache_options(),
        ]
    );

    add_settings_section(
        'motorlan_store_cache_params',
        __( 'Parámetros de caché', 'motorlan-api-vue' ),
        function () {
            echo '<p>' . esc_html__( 'Ajusta cuánto tiempo se conserva la respuesta pública del store y qué páginas se cachean.', 'motorlan-api-vue' ) . '</p>';
        },
        'motorlan_store_cache'
    );

    add_settings_field(
        'enabled',
        __( 'Habilitar caché', 'motorlan-api-vue' ),
        'motorlan_render_store_cache_enabled_field',
        'motorlan_store_cache',
        'motorlan_store_cache_params'
    );

    add_settings_field(
        'ttl_minutes',
        __( 'TTL (minutos)', 'motorlan-api-vue' ),
        'motorlan_render_store_cache_ttl_field',
        'motorlan_store_cache',
        'motorlan_store_cache_params'
    );

    add_settings_field(
        'cache_all_pages',
        __( 'Cachear todas las páginas', 'motorlan-api-vue' ),
        'motorlan_render_store_cache_scope_field',
        'motorlan_store_cache',
        'motorlan_store_cache_params'
    );
}
add_action( 'admin_init', 'motorlan_register_store_cache_settings' );

/**
 * Flush cache when store cache settings change.
 *
 * @param mixed  $old_value Previous value.
 * @param mixed  $value New value.
 * @return void
 */
function motorlan_flush_store_cache_on_settings_change( $old_value, $value ) {
    if ( $old_value !== $value ) {
        motorlan_invalidate_store_publicaciones_cache();
    }
}
add_action( 'update_option_motorlan_store_cache_options', 'motorlan_flush_store_cache_on_settings_change', 10, 2 );

/**
 * Render the enabled checkbox.
 *
 * @return void
 */
function motorlan_render_store_cache_enabled_field() {
    $options = motorlan_get_store_cache_options();
    ?>
    <label>
        <input type="checkbox" name="motorlan_store_cache_options[enabled]" value="1" <?php checked( 1, (int) $options['enabled'] ); ?>>
        <?php esc_html_e( 'Activar la caché para el endpoint público del store.', 'motorlan-api-vue' ); ?>
    </label>
    <?php
}

/**
 * Render the TTL field.
 *
 * @return void
 */
function motorlan_render_store_cache_ttl_field() {
    $options = motorlan_get_store_cache_options();
    ?>
    <input
        type="number"
        min="1"
        max="1440"
        name="motorlan_store_cache_options[ttl_minutes]"
        value="<?php echo esc_attr( (int) $options['ttl_minutes'] ); ?>"
    >
    <p class="description"><?php esc_html_e( 'Valor recomendado: 5 a 15 minutos.', 'motorlan-api-vue' ); ?></p>
    <?php
}

/**
 * Render the scope checkbox.
 *
 * @return void
 */
function motorlan_render_store_cache_scope_field() {
    $options = motorlan_get_store_cache_options();
    ?>
    <label>
        <input type="checkbox" name="motorlan_store_cache_options[cache_all_pages]" value="1" <?php checked( 1, (int) $options['cache_all_pages'] ); ?>>
        <?php esc_html_e( 'Cachear también páginas 2, 3, 4... del listado público.', 'motorlan-api-vue' ); ?>
    </label>
    <p class="description"><?php esc_html_e( 'Si lo desactivas, solo se cacheará la primera página.', 'motorlan-api-vue' ); ?></p>
    <?php
}

/**
 * Handle the purge action for store cache.
 *
 * @return void
 */
function motorlan_handle_store_cache_purge() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( empty( $_POST['motorlan_store_cache_purge'] ) ) {
        return;
    }

    check_admin_referer( 'motorlan_store_cache_purge_action', 'motorlan_store_cache_purge_nonce' );

    motorlan_invalidate_store_publicaciones_cache();

    wp_safe_redirect(
        add_query_arg(
            [
                'page'         => 'motorlan-store-cache',
                'cache_purged' => 1,
            ],
            admin_url( 'tools.php' )
        )
    );
    exit;
}
add_action( 'admin_init', 'motorlan_handle_store_cache_purge' );

/**
 * Render the cache settings page.
 *
 * @return void
 */
function motorlan_render_store_cache_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $options = motorlan_get_store_cache_options();
    $cache_version = motorlan_get_store_publicaciones_cache_version();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Caché de la tienda', 'motorlan-api-vue' ); ?></h1>

        <?php if ( ! empty( $_GET['settings-updated'] ) ) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e( 'Configuración guardada.', 'motorlan-api-vue' ); ?></p>
            </div>
        <?php endif; ?>

        <?php if ( ! empty( $_GET['cache_purged'] ) ) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e( 'La caché se purgó correctamente.', 'motorlan-api-vue' ); ?></p>
            </div>
        <?php endif; ?>

        <p>
            <?php
            echo esc_html(
                sprintf(
                    /* translators: %d cache version */
                    __( 'Versión actual de caché: %d', 'motorlan-api-vue' ),
                    $cache_version
                )
            );
            ?>
        </p>

        <form method="post" action="options.php">
            <?php
            settings_fields( 'motorlan_store_cache_group' );
            do_settings_sections( 'motorlan_store_cache' );
            submit_button( __( 'Guardar parámetros', 'motorlan-api-vue' ) );
            ?>
        </form>

        <hr>

        <h2><?php esc_html_e( 'Purgar caché', 'motorlan-api-vue' ); ?></h2>
        <p><?php esc_html_e( 'Borra todas las respuestas cacheadas del endpoint público del store.', 'motorlan-api-vue' ); ?></p>
        <form method="post">
            <?php wp_nonce_field( 'motorlan_store_cache_purge_action', 'motorlan_store_cache_purge_nonce' ); ?>
            <input type="hidden" name="motorlan_store_cache_purge" value="1">
            <?php submit_button( __( 'Purgar caché ahora', 'motorlan-api-vue' ), 'delete', 'submit', false ); ?>
        </form>
    </div>
    <?php
}
