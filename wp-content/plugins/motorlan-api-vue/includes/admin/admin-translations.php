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
        motorlan_sync_vue_i18n_strings_to_wpml();
        $sync_status       = __( 'Las cadenas se sincronizaron con WPML.', 'motorlan-api-vue' );
        $sync_notice_class = 'notice-success';
    }

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
                <h2 style="margin-top:0;"><?php esc_html_e( 'Cadenas', 'motorlan-api-vue' ); ?></h2>
                <p><?php echo esc_html( (string) motorlan_count_message_strings( $messages ) ); ?></p>
                <p class="description"><?php esc_html_e( 'Se sincronizan automáticamente con el contexto motorlan-vue-<locale>.', 'motorlan-api-vue' ); ?></p>
            </div>
        </div>

        <form method="post" style="margin-bottom:24px;">
            <?php wp_nonce_field( 'motorlan_sync_wpml', 'motorlan_sync_wpml_nonce' ); ?>
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Resincronizar cadenas con WPML', 'motorlan-api-vue' ); ?></button>
        </form>

        <h2><?php esc_html_e( 'Cómo editar', 'motorlan-api-vue' ); ?></h2>
        <ol>
            <li><?php esc_html_e( 'Abre WPML String Translation.', 'motorlan-api-vue' ); ?></li>
            <li><?php esc_html_e( 'Busca el contexto motorlan-vue-es, motorlan-vue-en, motorlan-vue-eu, motorlan-vue-fr o motorlan-vue-ar.', 'motorlan-api-vue' ); ?></li>
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
