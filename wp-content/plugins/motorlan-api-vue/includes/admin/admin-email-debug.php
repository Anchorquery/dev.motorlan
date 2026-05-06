<?php
/**
 * Admin Email Debug Page.
 *
 * @package motorlan-api-vue
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Register the "Motorlan Emails" submenu under Tools via hook.
 */
function motorlan_register_email_debug_menu() {
    add_management_page(
        'Motorlan Debug Emails',
        'Motorlan Emails',
        'manage_options',
        'motorlan-email-debug',
        'motorlan_render_email_debug_page'
    );
}
add_action( 'admin_menu', 'motorlan_register_email_debug_menu' );

/**
 * Render the Email Debug page.
 */
function motorlan_render_email_debug_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $manager = new Motorlan_Notification_Manager();
    $templates_dir = MOTORLAN_API_VUE_PATH . 'includes/email-templates/';
    $files = glob( $templates_dir . '*.php' );
    $templates = [];
    
    foreach ( $files as $file ) {
        $basename = basename( $file, '.php' );
        if ( 'base' !== $basename ) { // Skip base template
            $templates[] = $basename;
        }
    }

    $selected_template = isset( $_POST['template'] ) ? sanitize_text_field( $_POST['template'] ) : '';
    $test_email = isset( $_POST['test_email'] ) ? sanitize_email( $_POST['test_email'] ) : get_option( 'admin_email' );
    $preview_html = '';
    $message = '';

    // Handle Actions
    if ( isset( $_POST['action_type'] ) && check_admin_referer( 'motorlan_email_debug_action', 'motorlan_email_debug_nonce' ) ) {
        
        // Mock Data based on template
        $mock_data = motorlan_get_mock_data_for_template( $selected_template );
        $mock_args = [
            'title'   => 'Debug: ' . ucfirst( str_replace( '_', ' ', $selected_template ) ),
            'message' => 'Este es un mensaje de prueba generado desde el debug.',
            'data'    => $mock_data,
            'user'    => wp_get_current_user(),
        ];

        if ( 'preview' === $_POST['action_type'] ) {
            $preview_html = $manager->get_email_template( $selected_template, $mock_args );
        } elseif ( 'send' === $_POST['action_type'] ) {
            if ( is_email( $test_email ) ) {
                $manager->send_email_notification_direct( 
                    0, 
                    $selected_template, 
                    $mock_args['title'], 
                    $mock_args['message'], 
                    array_merge( $mock_data, ['direct_email' => $test_email] )
                );
                $message = '<div class="notice notice-success is-dismissible"><p>Correo enviado a ' . esc_html( $test_email ) . '</p></div>';
            } else {
                $message = '<div class="notice notice-error is-dismissible"><p>Email inválido.</p></div>';
            }
            // Also generate preview to see what was sent
            $preview_html = $manager->get_email_template( $selected_template, $mock_args );
        }
    }

    ?>
    <div class="wrap">
        <h1>🔍 Motorlan Email Debugger</h1>
        <?php echo $message; ?>
        
        <div style="display: flex; gap: 20px; align-items: flex-start;">
            <!-- Controls -->
            <div style="width: 300px; padding: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <form method="post">
                    <?php wp_nonce_field( 'motorlan_email_debug_action', 'motorlan_email_debug_nonce' ); ?>
                    
                    <p>
                        <label><strong>Plantilla:</strong></label><br>
                        <select name="template" style="width: 100%;">
                            <?php foreach ( $templates as $tpl ) : ?>
                                <option value="<?php echo esc_attr( $tpl ); ?>" <?php selected( $selected_template, $tpl ); ?>>
                                    <?php echo esc_html( $tpl ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <p>
                        <label><strong>Email de prueba:</strong></label><br>
                        <input type="email" name="test_email" value="<?php echo esc_attr( $test_email ); ?>" style="width: 100%;">
                    </p>

                    <hr>

                    <p>
                        <button type="submit" name="action_type" value="preview" class="button button-primary" style="width: 100%; margin-bottom: 10px;">
                            👁️ Previsualizar
                        </button>
                        <button type="submit" name="action_type" value="send" class="button button-secondary" style="width: 100%;">
                            ✉️ Enviar Prueba
                        </button>
                    </p>
                </form>

                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                    <h3>ℹ️ Notas</h3>
                    <p class="description">
                        Este debug usa datos "ficticios" para rellenar las variables de la plantilla. 
                        El envío usa la configuración `wp_mail` de tu sitio.
                    </p>
                    <p class="description">
                        Si el correo no llega a Gmail, verifica SPF/DKIM o instala un plugin SMTP.
                    </p>
                </div>
            </div>

            <!-- Preview Area -->
            <div style="flex: 1; min-height: 600px; background: #fff; border: 1px solid #ccd0d4;">
                <?php if ( $preview_html ) : ?>
                    <iframe srcdoc="<?php echo esc_attr( $preview_html ); ?>" style="width: 100%; height: 800px; border: none;"></iframe>
                <?php else : ?>
                    <div style="display: flex; align-items: center; justify-content: center; height: 600px; color: #aaa;">
                        <p>Selecciona una plantilla y pulsa Previsualizar</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Helper to generate mock data for templates.
 */
function motorlan_get_mock_data_for_template( $template ) {
    $base_data = [
        'purchase_id' => 999,
        'url' => home_url( '/mi-cuenta/pedidos/999' ),
        'product_name' => 'Motor Siemens 45kW (Demo)',
        'amount' => 1250.00,
        'buyer_name' => 'Comprador Demo',
        'seller_name' => 'Vendedor Demo',
    ];

    switch ( $template ) {
        case 'password_reset':
            return [
                'url' => home_url( '/mi-cuenta/reset-password?key=demo&login=usuario' ),
                'key' => 'demo_key_12345',
                'login' => 'usuario_demo'
            ];
        case 'verify_email':
            return [
                'url' => home_url( '/verify-email?token=demo_token_123' ),
                'token' => 'demo_token_123'
            ];
        case 'new_message':
            return array_merge( $base_data, [
                'sender_name' => 'Juan Pérez',
                'preview' => 'Hola, ¿sigue disponible este motor?',
                'chat_url' => home_url( '/chat/123' )
            ] );
        case 'new_offer':
            return array_merge( $base_data, [
                'offer_amount' => 1100.00,
                'offer_id' => 55
            ] );
        case 'new_purchase':
            return array_merge( $base_data, [
                'order_id' => 'ORD-2024-001',
                'dashboard_url' => home_url( '/mi-cuenta/pedidos' )
            ] );
        case 'new_question':
            return array_merge( $base_data, [
                'question_text' => '¿Tiene garantía este motor?',
                'question_url' => home_url( '/mi-cuenta/preguntas' )
            ] );
        case 'offer_accepted':
            return array_merge( $base_data, [
                'accepted_amount' => 1200.00,
                'payment_url' => home_url( '/checkout?offer=55' )
            ] );
        case 'pending_approval':
            return array_merge( $base_data, [
                'edit_url' => home_url( '/mi-cuenta/publicaciones/edit/999' )
            ] );
        case 'publication_approved':
            return array_merge( $base_data, [
                'public_url' => home_url( '/producto/motor-siemens-45kw' )
            ] );
        case 'publication_rejected':
            return array_merge( $base_data, [
                'rejection_reason' => 'Las fotos no son claras. Por favor sube nuevas fotos.',
                'edit_url' => home_url( '/mi-cuenta/publicaciones/edit/999' )
            ] );
        case 'welcome_email':
            return [
                'login_url' => home_url( '/login' ),
                'user_login' => 'usuario_demo'
            ];
        default:
            return $base_data;
    }
}
