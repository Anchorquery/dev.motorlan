<?php
/**
 * Admin overview for WPML-backed Vue translations.
 *
 * @package motorlan-api-vue
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Register the translations admin page.
 */
function motorlan_register_translations_menu() {
    $hook = add_management_page(
        __( 'Traducciones Motorlan', 'motorlan-api-vue' ),
        __( 'Traducciones Motorlan', 'motorlan-api-vue' ),
        'manage_options',
        'motorlan-translations',
        'motorlan_render_translations_page'
    );

    add_action(
        'admin_enqueue_scripts',
        function ( $hook_suffix ) use ( $hook ) {
            if ( $hook !== $hook_suffix ) {
                return;
            }

            wp_enqueue_style( 'wp-components' );
        }
    );
}
add_action( 'admin_menu', 'motorlan_register_translations_menu' );

/**
 * Count scalar strings recursively.
 *
 * @param array<string, mixed> $messages Messages tree.
 * @return int
 */
function motorlan_count_message_strings( array $messages ) {
    $count = 0;

    foreach ( $messages as $value ) {
        if ( is_array( $value ) ) {
            $count += motorlan_count_message_strings( $value );
            continue;
        }

        $count++;
    }

    return $count;
}

/**
 * Render the translations admin page.
 */
function motorlan_render_translations_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $messages          = motorlan_get_base_vue_i18n_messages();
    $supported_locales  = motorlan_get_supported_locale_map();
    $wpml_active        = motorlan_is_wpml_active();
    $sync_status        = null;
    $sync_notice_class  = 'notice-info';

    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['motorlan_sync_wpml_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['motorlan_sync_wpml_nonce'] ) ), 'motorlan_sync_wpml' ) ) {
        $action = isset( $_POST['motorlan_sync_action'] ) ? sanitize_key( wp_unslash( $_POST['motorlan_sync_action'] ) ) : 'sync';

        if ( 'purge_legacy' === $action ) {
            $removed = motorlan_purge_legacy_wpml_contexts();
            $sync_status = sprintf(
                /* translators: %d: number of legacy strings removed. */
                __( 'Contextos legacy eliminados: %d cadenas.', 'motorlan-api-vue' ),
                $removed
            );
        } else {
            motorlan_vue_i18n_force_reseed();
            motorlan_sync_vue_i18n_strings_to_wpml();
            $migration = motorlan_migrate_legacy_wpml_contexts();
            $sync_status = sprintf(
                /* translators: 1: migrated strings, 2: migrated translations. */
                __( 'Cadenas sincronizadas. Migradas %1$d cadenas / %2$d traducciones desde contextos legacy. Las traducciones ya editadas en WPML no se sobrescriben.', 'motorlan-api-vue' ),
                $migration['migrated_strings'],
                $migration['migrated_translations']
            );
        }
        $sync_notice_class = 'notice-success';
    }

    $wpml_counts = motorlan_wpml_context_counts();

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Traducciones Motorlan', 'motorlan-api-vue' ); ?></h1>
        <p><?php esc_html_e( 'Las traducciones de la UI se gestionan en WPML String Translation. Esta pantalla solo muestra el estado y permite resincronizar las cadenas del frontend.', 'motorlan-api-vue' ); ?></p>

        <?php if ( null !== $sync_status ) : ?>
            <div class="notice <?php echo esc_attr( $sync_notice_class ); ?> is-dismissible">
                <p><?php echo esc_html( $sync_status ); ?></p>
            </div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin:24px 0;">
            <div class="postbox" style="padding:16px;">
                <h2 style="margin-top:0;"><?php esc_html_e( 'Estado', 'motorlan-api-vue' ); ?></h2>
                <p><?php echo esc_html( $wpml_active ? __( 'WPML activo', 'motorlan-api-vue' ) : __( 'WPML no detectado', 'motorlan-api-vue' ) ); ?></p>
                <p class="description"><?php esc_html_e( 'Si WPML no está activo, la app usa los JSON del plugin como fallback.', 'motorlan-api-vue' ); ?></p>
            </div>

            <div class="postbox" style="padding:16px;">
                <h2 style="margin-top:0;"><?php esc_html_e( 'Locales', 'motorlan-api-vue' ); ?></h2>
                <p><?php echo esc_html( implode( ', ', array_keys( $supported_locales ) ) ); ?></p>
            </div>

            <div class="postbox" style="padding:16px;">
                <h2 style="margin-top:0;"><?php esc_html_e( 'Cadenas (JSON)', 'motorlan-api-vue' ); ?></h2>
                <p><?php echo esc_html( (string) motorlan_count_message_strings( $messages[ motorlan_get_vue_i18n_source_locale() ] ?? [] ) ); ?></p>
                <p class="description"><?php esc_html_e( 'Cadenas en el JSON fuente (es).', 'motorlan-api-vue' ); ?></p>
            </div>
        </div>

        <h2><?php esc_html_e( 'Estado en WPML String Translation', 'motorlan-api-vue' ); ?></h2>
        <?php if ( empty( $wpml_counts ) ) : ?>
            <p><em><?php esc_html_e( 'Aún no hay cadenas registradas en WPML. Pulsa "Resincronizar".', 'motorlan-api-vue' ); ?></em></p>
        <?php else : ?>
            <table class="widefat striped" style="max-width:560px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Contexto WPML', 'motorlan-api-vue' ); ?></th>
                        <th><?php esc_html_e( 'Cadenas', 'motorlan-api-vue' ); ?></th>
                        <th><?php esc_html_e( 'Estado', 'motorlan-api-vue' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $wpml_counts as $context => $total ) :
                        $is_target = ( $context === motorlan_get_vue_i18n_wpml_context() );
                        ?>
                        <tr>
                            <td><code><?php echo esc_html( $context ); ?></code></td>
                            <td><?php echo esc_html( (string) $total ); ?></td>
                            <td>
                                <?php if ( $is_target ) : ?>
                                    <span style="color:#1a7f37;font-weight:bold;">✓ <?php esc_html_e( 'Activo', 'motorlan-api-vue' ); ?></span>
                                <?php else : ?>
                                    <span style="color:#a33;"><?php esc_html_e( 'Legacy (huérfano)', 'motorlan-api-vue' ); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <form method="post" style="margin:24px 0;display:flex;gap:12px;flex-wrap:wrap;">
            <?php wp_nonce_field( 'motorlan_sync_wpml', 'motorlan_sync_wpml_nonce' ); ?>
            <button type="submit" name="motorlan_sync_action" value="sync" class="button button-primary"><?php esc_html_e( 'Resincronizar cadenas con WPML', 'motorlan-api-vue' ); ?></button>
            <?php $has_legacy = false; foreach ( $wpml_counts as $ctx => $_ ) { if ( $ctx !== motorlan_get_vue_i18n_wpml_context() ) { $has_legacy = true; break; } } ?>
            <?php if ( $has_legacy ) : ?>
                <button type="submit" name="motorlan_sync_action" value="purge_legacy" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( '¿Eliminar los contextos legacy motorlan-vue-<locale>? Asegúrate de haber pulsado "Resincronizar" antes para migrar sus traducciones.', 'motorlan-api-vue' ) ); ?>');"><?php esc_html_e( 'Eliminar contextos legacy', 'motorlan-api-vue' ); ?></button>
            <?php endif; ?>
        </form>
        <p class="description" style="margin-top:-12px;"><?php esc_html_e( '"Resincronizar" registra todas las cadenas en el contexto motorlan-vue, importa traducciones del JSON y migra traducciones existentes desde contextos legacy. Tras migrar y verificar, usa "Eliminar legacy" para limpiar contextos huérfanos.', 'motorlan-api-vue' ); ?></p>

        <h2><?php esc_html_e( 'Cómo editar', 'motorlan-api-vue' ); ?></h2>
        <ol>
            <li><?php esc_html_e( 'Abre WPML String Translation.', 'motorlan-api-vue' ); ?></li>
            <li><?php esc_html_e( 'Filtra por el dominio "motorlan-vue".', 'motorlan-api-vue' ); ?></li>
            <li><?php esc_html_e( 'Edita la traducción por idioma y guarda en WPML.', 'motorlan-api-vue' ); ?></li>
        </ol>

        <h2><?php esc_html_e( 'Cobertura base', 'motorlan-api-vue' ); ?></h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Locale', 'motorlan-api-vue' ); ?></th>
                    <th><?php esc_html_e( 'Etiqueta', 'motorlan-api-vue' ); ?></th>
                    <th><?php esc_html_e( 'RTL', 'motorlan-api-vue' ); ?></th>
                    <th><?php esc_html_e( 'Cadenas base', 'motorlan-api-vue' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $supported_locales as $locale => $meta ) : ?>
                    <tr>
                        <td><?php echo esc_html( $locale ); ?></td>
                        <td><?php echo esc_html( $meta['label'] ); ?></td>
                        <td><?php echo esc_html( $meta['isRTL'] ? __( 'Sí', 'motorlan-api-vue' ) : __( 'No', 'motorlan-api-vue' ) ); ?></td>
                        <td><?php echo esc_html( (string) motorlan_count_message_strings( $messages[ $locale ] ?? [] ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
