<?php
/**
 * Admin page for synchronizing tipo from legacy categoria terms.
 *
 * @package motorlan-api-vue
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

function motorlan_register_tipo_migration_menu() {
    $hook = add_menu_page(
        __( 'Migración tipo/categoría', 'motorlan-api-vue' ),
        __( 'Migración tipo/categoría', 'motorlan-api-vue' ),
        'manage_options',
        'motorlan-tipo-migration',
        'motorlan_render_tipo_migration_page',
        'dashicons-update',
        7
    );

    add_action(
        'admin_enqueue_scripts',
        function ( $hook_suffix ) use ( $hook ) {
            if ( $hook_suffix !== $hook ) {
                return;
            }
            motorlan_enqueue_tipo_migration_assets();
        }
    );
}
add_action( 'admin_menu', 'motorlan_register_tipo_migration_menu' );

function motorlan_enqueue_tipo_migration_assets() {
    $css_file = MOTORLAN_API_VUE_PATH . 'assets/css/admin-tipo-migration.css';
    $js_file  = MOTORLAN_API_VUE_PATH . 'assets/js/admin-tipo-migration.js';

    wp_enqueue_style(
        'motorlan-tipo-migration-admin',
        MOTORLAN_API_VUE_URL . 'assets/css/admin-tipo-migration.css',
        [],
        file_exists( $css_file ) ? filemtime( $css_file ) : MOTORLAN_API_VUE_VERSION
    );

    wp_enqueue_script(
        'motorlan-tipo-migration-admin',
        MOTORLAN_API_VUE_URL . 'assets/js/admin-tipo-migration.js',
        [],
        file_exists( $js_file ) ? filemtime( $js_file ) : MOTORLAN_API_VUE_VERSION,
        true
    );

    wp_localize_script(
        'motorlan-tipo-migration-admin',
        'motorlanTipoMigration',
        [
            'restNonce' => wp_create_nonce( 'wp_rest' ),
            'restRoot'   => esc_url_raw( rest_url() ),
            'endpoint'   => rest_url( 'motorlan/v1/migration/sync-tipos-from-categories' ),
            'strings'    => [
                'loading'        => __( 'Analizando publicaciones...', 'motorlan-api-vue' ),
                'applyConfirm'   => __( 'Esto aplicará los cambios detectados. ¿Quieres continuar?', 'motorlan-api-vue' ),
                'noPreview'      => __( 'Primero ejecuta un dry-run para revisar el impacto.', 'motorlan-api-vue' ),
                'noChanges'      => __( 'No hay cambios pendientes para aplicar.', 'motorlan-api-vue' ),
                'donePreview'    => __( 'Dry-run completado.', 'motorlan-api-vue' ),
                'doneApply'      => __( 'Migración aplicada.', 'motorlan-api-vue' ),
                'networkError'   => __( 'No se pudo contactar al servidor.', 'motorlan-api-vue' ),
            ],
        ]
    );
}

function motorlan_render_tipo_migration_page() {
    ?>
    <div class="wrap motorlan-tipo-migration-wrap">
        <div class="motorlan-tipo-migration-hero">
            <div>
                <p class="motorlan-tipo-migration-kicker"><?php esc_html_e( 'Migración de taxonomía', 'motorlan-api-vue' ); ?></p>
                <h1><?php esc_html_e( 'Sincronizar Tipo desde Categorías legacy', 'motorlan-api-vue' ); ?></h1>
                <p class="description">
                    <?php esc_html_e( 'Esta pantalla compara la categoría guardada históricamente con el tipo actual. Primero ejecuta un dry-run, revisa qué publicaciones cambiarán y luego aplica la migración.', 'motorlan-api-vue' ); ?>
                </p>
            </div>
            <div class="motorlan-tipo-migration-hero__meta">
                <span class="motorlan-badge motorlan-badge--dryrun"><?php esc_html_e( 'Dry-run primero', 'motorlan-api-vue' ); ?></span>
                <span class="motorlan-badge"><?php esc_html_e( 'AC/DC respetado', 'motorlan-api-vue' ); ?></span>
            </div>
        </div>

        <div class="motorlan-tipo-migration-panel">
            <div class="motorlan-tipo-migration-controls">
                <div class="motorlan-field">
                    <label for="motorlan-tipo-per-page"><?php esc_html_e( 'Registros por bloque', 'motorlan-api-vue' ); ?></label>
                    <input type="number" id="motorlan-tipo-per-page" min="1" max="200" value="100">
                    <p class="description"><?php esc_html_e( 'Se procesan varios bloques hasta cubrir todas las publicaciones.', 'motorlan-api-vue' ); ?></p>
                </div>

                <div class="motorlan-actions">
                    <button type="button" class="button button-primary" id="motorlan-tipo-run-preview">
                        <?php esc_html_e( 'Ejecutar dry-run', 'motorlan-api-vue' ); ?>
                    </button>
                    <button type="button" class="button" id="motorlan-tipo-run-apply" disabled>
                        <?php esc_html_e( 'Aplicar cambios', 'motorlan-api-vue' ); ?>
                    </button>
                </div>
            </div>

            <div class="motorlan-progress">
                <div class="motorlan-progress__bar">
                    <div class="motorlan-progress__fill" id="motorlan-tipo-progress-fill" style="width:0%"></div>
                </div>
                <p id="motorlan-tipo-status" class="motorlan-status">
                    <?php esc_html_e( 'Aún no se ha ejecutado ningún análisis.', 'motorlan-api-vue' ); ?>
                </p>
            </div>
        </div>

        <div id="motorlan-tipo-summary" class="motorlan-summary" hidden></div>

        <div id="motorlan-tipo-results" class="motorlan-results" hidden>
            <div class="motorlan-results__header">
                <div>
                    <h2 id="motorlan-tipo-results-title"><?php esc_html_e( 'Resultados', 'motorlan-api-vue' ); ?></h2>
                    <p class="description" id="motorlan-tipo-results-subtitle"></p>
                </div>
                <div class="motorlan-results__legend">
                    <span class="motorlan-dot motorlan-dot--update"></span><span><?php esc_html_e( 'Se actualizará', 'motorlan-api-vue' ); ?></span>
                    <span class="motorlan-dot motorlan-dot--match"></span><span><?php esc_html_e( 'Ya coincide', 'motorlan-api-vue' ); ?></span>
                    <span class="motorlan-dot motorlan-dot--skip"></span><span><?php esc_html_e( 'Sin categoría / sin resolver', 'motorlan-api-vue' ); ?></span>
                </div>
            </div>
            <div class="motorlan-table-wrap">
                <table class="widefat fixed striped motorlan-results__table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'ID', 'motorlan-api-vue' ); ?></th>
                            <th><?php esc_html_e( 'Publicación', 'motorlan-api-vue' ); ?></th>
                            <th><?php esc_html_e( 'Categoría legacy', 'motorlan-api-vue' ); ?></th>
                            <th><?php esc_html_e( 'Tipo actual', 'motorlan-api-vue' ); ?></th>
                            <th><?php esc_html_e( 'Como quedará', 'motorlan-api-vue' ); ?></th>
                            <th><?php esc_html_e( 'Estado', 'motorlan-api-vue' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="motorlan-tipo-results-body"></tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}
