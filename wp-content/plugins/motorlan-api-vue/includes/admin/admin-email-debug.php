<?php
/**
 * Admin Email Debug Page.
 *
 * Herramientas › Motorlan Emails: previsualiza cada email con datos de ejemplo (los mismos
 * campos que rellena el listener), en ancho de escritorio o móvil, con nombre real, con
 * email como nombre, como invitado o con datos hostiles (para comprobar el escapado), y
 * permite enviarse una prueba a una dirección propia.
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
 * Plantillas disponibles (todas las de includes/email-templates menos la base).
 *
 * @return string[]
 */
function motorlan_email_debug_templates() {
    $templates = [];
    foreach ( (array) glob( MOTORLAN_API_VUE_PATH . 'includes/email-templates/*.php' ) as $file ) {
        $basename = basename( $file, '.php' );
        if ( 'base' !== $basename ) {
            $templates[] = $basename;
        }
    }
    sort( $templates );
    return $templates;
}

/**
 * Render the Email Debug page.
 */
function motorlan_render_email_debug_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $manager   = new Motorlan_Notification_Manager();
    $templates = motorlan_email_debug_templates();

    $selected_template = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : $templates[0];
    if ( ! in_array( $selected_template, $templates, true ) ) {
        $selected_template = $templates[0];
    }
    $test_email = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : get_option( 'admin_email' );
    $variant    = isset( $_POST['variant'] ) ? sanitize_key( wp_unslash( $_POST['variant'] ) ) : 'named';
    $width      = ( isset( $_POST['width'] ) && 'mobile' === $_POST['width'] ) ? 'mobile' : 'desktop';
    $preview_html = '';
    $message      = '';

    // Handle Actions
    if ( isset( $_POST['action_type'] ) && check_admin_referer( 'motorlan_email_debug_action', 'motorlan_email_debug_nonce' ) ) {
        $mock = motorlan_email_debug_payload( $selected_template, $variant );
        $mock_args = [
            'title'   => $mock['title'],
            'message' => $mock['message'],
            'data'    => $mock['data'],
            'user'    => $mock['user'],
        ];

        if ( 'send' === $_POST['action_type'] ) {
            if ( is_email( $test_email ) ) {
                $manager->send_email_notification_direct(
                    0,
                    $selected_template,
                    $mock['title'],
                    $mock['message'],
                    array_merge( $mock['data'], [ 'direct_email' => $test_email ] )
                );
                $message = '<div class="notice notice-success is-dismissible"><p>Correo enviado a ' . esc_html( $test_email ) . ' (sin cuenta asociada: el saludo sale neutro).</p></div>';
            } else {
                $message = '<div class="notice notice-error is-dismissible"><p>Email inválido.</p></div>';
            }
        }
        // En ambos casos se muestra lo que se ha generado
        $preview_html = $manager->get_email_template( $selected_template, $mock_args );
    }

    $variants = [
        'named'   => 'Usuario con nombre',
        'email'   => 'Usuario cuyo nombre es un email',
        'guest'   => 'Invitado (sin cuenta)',
        'hostile' => 'Datos hostiles (comprobar escapado)',
    ];
    ?>
    <div class="wrap">
        <h1>🔍 Motorlan Email Debugger</h1>
        <?php echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

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
                        <label><strong>Destinatario de ejemplo:</strong></label><br>
                        <select name="variant" style="width: 100%;">
                            <?php foreach ( $variants as $key => $label ) : ?>
                                <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $variant, $key ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <p>
                        <label><strong>Ancho de la vista previa:</strong></label><br>
                        <select name="width" style="width: 100%;">
                            <option value="desktop" <?php selected( $width, 'desktop' ); ?>>Escritorio</option>
                            <option value="mobile" <?php selected( $width, 'mobile' ); ?>>Móvil (375 px)</option>
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
                        Usa datos de ejemplo con los mismos campos que rellena el listener.
                        El envío usa la configuración <code>wp_mail</code> de tu sitio.
                    </p>
                    <p class="description">
                        El modo oscuro de la vista previa sigue el tema del navegador/sistema
                        (<code>prefers-color-scheme</code>). La inversión automática de Gmail u Outlook
                        solo se ve en esos clientes.
                    </p>
                    <p class="description">
                        Si el correo no llega a Gmail, verifica SPF/DKIM o instala un plugin SMTP.
                    </p>
                </div>
            </div>

            <!-- Preview Area -->
            <div style="flex: 1; min-height: 600px; background: #fff; border: 1px solid #ccd0d4; text-align: center;">
                <?php if ( $preview_html ) : ?>
                    <iframe srcdoc="<?php echo esc_attr( $preview_html ); ?>" style="width: <?php echo 'mobile' === $width ? '375px' : '100%'; ?>; height: 900px; border: none;"></iframe>
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
 * Datos de ejemplo por plantilla: las mismas claves que pone el listener en cada evento.
 *
 * @param string $template
 * @return array{0:array,1:string,2:string} data, título, mensaje
 */
function motorlan_email_debug_sample( $template ) {
    $img  = defined( 'MOTORLAN_EMAIL_SAMPLE_IMAGE' ) ? MOTORLAN_EMAIL_SAMPLE_IMAGE : Motorlan_Email::LOGO_URL;
    $prod = 'SIEMENS 1679-106/B6 110 KW';
    $acc  = '/mi-cuenta/dashboard/';
    switch ( $template ) {
        case 'new_purchase':
            return [ [ 'url' => $acc . 'purchases/3f2a-uuid', 'product_title' => $prod, 'product_image' => $img, 'product_price' => '4250', 'buyer_name' => 'Prueba Portal' ], 'Nueva compra de Prueba Portal en "' . $prod . '"', 'El usuario Prueba Portal ha iniciado una compra para tu publicación.' ];
        case 'new_offer':
            return [ [ 'url' => $acc . 'publications/offers-received', 'product_title' => $prod, 'product_image' => $img, 'product_price' => '4250', 'offer_amount' => '3900.50' ], 'Nueva oferta de Prueba Portal', 'Has recibido una oferta de 3900.50€ por "' . $prod . '"' ];
        case 'offer_accepted':
            return [ [ 'url' => $acc . 'purchases/offers-sent', 'product_title' => $prod, 'product_image' => $img, 'offer_amount' => '3900.50' ], '¡Oferta aceptada para "' . $prod . '"!', 'El vendedor ha aceptado tu oferta. Tienes 24 horas para confirmar la compra.' ];
        case 'manual_sale':
            return [ [ 'url' => $acc . 'purchases/purchases', 'product_title' => $prod, 'product_image' => $img, 'product_price' => '4250' ], '¡Compra registrada!', 'El vendedor ha registrado tu compra de "' . $prod . '".' ];
        case 'new_message':
            return [ [ 'url' => $acc . 'inquiries?room_key=abc', 'product_title' => $prod, 'product_image' => $img, 'sender_name' => 'María López', 'message_full' => "Hola, ¿sigue disponible el motor?\nMe interesa para una línea en Zaragoza." ], 'Nuevo mensaje en "' . $prod . '"', 'María López: Hola, ¿sigue disponible el motor?...' ];
        case 'message_sent':
            return [ [ 'recipient_name' => 'Taller Industrial Norte', 'product_title' => $prod, 'url' => '/marketplace-motorlan/siemens-1679/?open_chat=1&ct=tok123' ], 'Mensaje enviado', '' ];
        case 'new_question':
            return [ [ 'url' => $acc . 'inquiries', 'product_title' => $prod, 'product_image' => $img, 'asker_name' => 'María López', 'question_text' => '¿Tiene garantía este motor?' ], 'Nueva pregunta', '' ];
        case 'question_sent':
            return [ [ 'publication_title' => $prod, 'url' => $acc . 'purchases/inquiries' ], 'Pregunta enviada', '¿Tiene garantía este motor?' ];
        case 'question_answered':
            return [ [ 'publication_title' => $prod, 'answer' => 'Sí, seis meses de garantía.', 'url' => $acc . 'purchases/inquiries' ], 'Tienes una respuesta', '' ];
        case 'pending_approval':
            return [ [ 'post_id' => 0, 'url' => $acc . 'admin/approvals', 'product_title' => $prod, 'product_image' => $img, 'brand' => 'SIEMENS', 'reference' => '1679-106/B6', 'power' => '110 KW', 'speed' => '1500 RPM' ], 'Solicitud de publicación', 'El usuario Prueba Portal ha solicitado publicar "' . $prod . '".' ];
        case 'publication_approved':
            return [ [ 'post_id' => 0, 'url' => '/marketplace-motorlan/siemens-1679/', 'product_title' => $prod, 'product_image' => $img ], 'Publicación aprobada', 'Tu publicación "' . $prod . '" ha sido aprobada.' ];
        case 'publication_rejected':
            return [ [ 'post_id' => 0, 'url' => $acc . 'publications/publication/list', 'product_title' => $prod, 'product_image' => $img, 'reason' => "Las fotos no son claras.\nSube nuevas fotos." ], 'Publicación rechazada', 'Tu publicación "' . $prod . '" ha sido rechazada. Motivo: Las fotos no son claras.' . "\n" . 'Sube nuevas fotos.' ];
        case 'password_reset':
            return [ [ 'url' => '/mi-cuenta/reset-password?key=abc123&login=usuario' ], 'Restablecer contraseña', 'Has solicitado restablecer tu contraseña.' ];
        case 'password_reset_code':
            return [ [ 'code' => '482917', 'expires_in_minutes' => 15 ], 'Código de restablecimiento de contraseña', 'Tu código para confirmar el cambio de contraseña' ];
        case 'verify_email':
            return [ [ 'url' => '/mi-cuenta/verify-email?token=tok123' ], 'Verifica tu cuenta en Motorlan', '' ];
        case 'welcome_email':
            return [ [], 'Bienvenido a Motorlan', '' ];
    }
    return [ [], 'Notificación de prueba', '' ];
}

/**
 * Datos + usuario de ejemplo según la variante elegida.
 *
 * @param string $template
 * @param string $variant  named | email | guest | hostile
 * @return array{data:array,title:string,message:string,user:?object}
 */
function motorlan_email_debug_payload( $template, $variant ) {
    list( $data, $title, $message ) = motorlan_email_debug_sample( $template );

    switch ( $variant ) {
        case 'email':
            $user = (object) [ 'ID' => 999999, 'first_name' => '', 'last_name' => '', 'display_name' => 'vendedor.prueba@motorlan.test', 'user_login' => 'vendedor.prueba@motorlan.test', 'user_email' => 'vendedor.prueba@motorlan.test' ];
            break;
        case 'guest':
            $user = null;
            break;
        case 'hostile':
            $user  = (object) [ 'ID' => 999999, 'first_name' => '<b>Eva</b>', 'last_name' => '<script>alert(1)</script>', 'display_name' => 'x@x.test', 'user_login' => 'x', 'user_email' => 'x@x.test' ];
            $evil  = '<script>alert(1)</script> "><img src=x onerror=alert(1)> <a href="https://evil.test">x</a>';
            foreach ( [ 'product_title', 'sender_name', 'buyer_name', 'message_full', 'asker_name', 'question_text', 'answer', 'recipient_name', 'publication_title', 'reason', 'brand', 'reference', 'power', 'speed' ] as $key ) {
                if ( isset( $data[ $key ] ) ) {
                    $data[ $key ] = $evil;
                }
            }
            $title   = $evil;
            $message = $evil;
            break;
        case 'named':
        default:
            $user = (object) [ 'ID' => 999999, 'first_name' => 'Lucía', 'last_name' => 'Fernández', 'display_name' => 'lucia@example.com', 'user_login' => 'lucia', 'user_email' => 'lucia@example.com' ];
    }

    return [ 'data' => $data, 'title' => $title, 'message' => $message, 'user' => $user ];
}
