<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Motorlan_Notification_Listener {

    private $notification_manager;

    public function __construct() {
        // We will instantiate the manager when needed or here if it's lightweight.
        // Given existing code does `new Motorlan_Notification_Manager()`, we can do it here.
        if ( class_exists( 'Motorlan_Notification_Manager' ) ) {
            $this->notification_manager = new Motorlan_Notification_Manager();
        }
    }
    
    /**
     * Helper to switch locale, create notification, and restore locale.
     */
    private function create_notification_localized( $user_id, $type, $title, $message, $data = [], $channels = ['web', 'email'] ) {
        // Switch to user locale
        $user_locale = get_user_locale( $user_id );
        $original_locale = determine_locale(); // Current locale
        
        // Only switch if different (optimization)
        $switched = false;
        if ( $user_locale !== $original_locale ) {
            $switched = switch_to_locale( $user_locale );
        }

        // We assume the title/message passed might be generated dynamically.
        // If the caller didn't translate them *before* calling this, they will be translated here?
        // No, PHP strings are evaluated at call time. 
        // Example: on_user_welcome calls: create...('Welcome', ...)
        // 'Welcome' is evaluated inside on_user_welcome BEFORE we switch locale here if we passed a string string.
        // BUT wait! 
        // If I use __() inside on_user_welcome, it uses the CURRENT locale logic usually (which might be admin's locale).
        // So I must switch locale inside the specific handlers BEFORE generating the text.
        
        // Actually, the best way is to move the logic inside the handlers, OR pass keys/closures.
        // But closures are messy here.
        // So I will NOT use this helper to *translate* the content.
        // I will use `switch_to_locale` inside each handler function manually to be precise.
        // Then I call the manager.
        
        // However, I can still use this wrapper to ensure the *template rendering* (which happens in manager) uses the correct locale.
        // Because manager->create_notification -> send_email -> include template -> template calls __().
        // So this wrapper IS useful for the template part.
        
        try {
            $this->notification_manager->create_notification( $user_id, $type, $title, $message, $data, $channels );
        } finally {
            if ( $switched ) {
                restore_previous_locale();
            }
        }
    }

    public function init() {
        // Register all hooks
        add_action( 'motorlan_user_welcome', [ $this, 'on_user_welcome' ], 10, 1 );
        add_action( 'motorlan_publication_approved', [ $this, 'on_publication_approved' ], 10, 1 );
        add_action( 'motorlan_publication_rejected', [ $this, 'on_publication_rejected' ], 10, 2 );
        add_action( 'motorlan_publication_pending_approval', [ $this, 'on_publication_pending_approval' ], 10, 1 );
        add_action( 'motorlan_admin_contact_publisher', [ $this, 'on_admin_contact_publisher' ], 10, 3 );
        add_action( 'motorlan_offer_created', [ $this, 'on_offer_created' ], 10, 1 );
        add_action( 'motorlan_offer_status_updated', [ $this, 'on_offer_status_updated' ], 10, 2 );
        add_action( 'motorlan_manual_sale_created', [ $this, 'on_manual_sale_created' ], 10, 1 );
        add_action( 'motorlan_new_purchase', [ $this, 'on_new_purchase' ], 10, 1 );
        add_action( 'motorlan_new_question', [ $this, 'on_new_question' ], 10, 1 );
        add_action( 'motorlan_password_reset_code', [ $this, 'on_password_reset_code' ], 10, 3 );
        add_action( 'motorlan_new_chat_message', [ $this, 'on_new_chat_message' ], 10, 4 );
        add_action( 'motorlan_new_purchase_message', [ $this, 'on_new_purchase_message' ], 10, 5 );
        add_action( 'motorlan_user_interested', [ $this, 'on_user_interested' ], 10, 2 );
        add_action( 'motorlan_publication_sold_manually', [ $this, 'on_publication_sold_manually' ], 10, 1 );
        add_action( 'motorlan_new_review', [ $this, 'on_new_review' ], 10, 3 );
        add_action( 'motorlan_user_verify_email', [ $this, 'on_user_verify_email' ], 10, 2 );
        add_action( 'motorlan_seller_reply', [ $this, 'on_seller_reply' ], 10, 5 );
    }

    // --- Callbacks ---

    public function on_user_welcome( $user_id ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) return;
        
        $switched = switch_to_locale( get_user_locale( $user_id ) );
        
        try {
            $subject = __( 'Bienvenido a Motorlan', 'motorlan-api-vue' );
            $message = sprintf( __( 'Hola %s, gracias por registrarte en Motorlan. Tu cuenta ha sido verificada con éxito.', 'motorlan-api-vue' ), $user->display_name );

            $this->notification_manager->create_notification(
                $user_id,
                'welcome_message',
                $subject,
                $message,
                [],
                ['email'] 
            );
        } finally {
            if ( $switched ) restore_previous_locale();
        }
    }

    public function on_user_verify_email( $user_id, $token ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) return;
        
        $switched = switch_to_locale( get_user_locale( $user_id ) );
        
        try {
            $subject = __( 'Verifica tu cuenta en Motorlan', 'motorlan-api-vue' );
            $message = sprintf( __( 'Hola %s, por favor verifica tu cuenta haciendo clic en el siguiente enlace.', 'motorlan-api-vue' ), $user->display_name );

            $this->notification_manager->create_notification(
                $user_id,
                'verify_email',
                $subject,
                $message,
                [
                    'token' => $token,
                    'url' => '/mi-cuenta/verify-email?token=' . $token
                ],
                ['email'] 
            );
        } finally {
            if ( $switched ) restore_previous_locale();
        }
    }

    public function on_publication_approved( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) return;
        $author_id = $post->post_author;

        $switched = switch_to_locale( get_user_locale( $author_id ) );

        try {
            $this->notification_manager->create_notification(
                $author_id,
                'publication_approved',
                __( 'Publicación aprobada', 'motorlan-api-vue' ),
                sprintf( __( 'Tu publicación "%s" ha sido aprobada.', 'motorlan-api-vue' ), motorlan_format_motor_name( $post_id ) ),
                [
                    'post_id' => $post_id,
                    'product_title' => motorlan_format_motor_name( $post_id ),
                    'url' => '/publicacion/' . $post->post_name
                ],
                ['web', 'email']
            );
        } finally {
            if ( $switched ) restore_previous_locale();
        }
    }

    public function on_publication_rejected( $post_id, $reason = '' ) {
        $post = get_post( $post_id );
        if ( ! $post ) return;
        $author_id = $post->post_author;
        
        $switched = switch_to_locale( get_user_locale( $author_id ) );

        try {
            $formatted_title = motorlan_format_motor_name( $post_id );
            $message = sprintf( __( 'Tu publicación "%s" ha sido rechazada.', 'motorlan-api-vue' ), $formatted_title );
            if ( ! empty( $reason ) ) {
                $message .= ' ' . sprintf( __( 'Motivo: %s', 'motorlan-api-vue' ), $reason );
            }

            $this->notification_manager->create_notification(
                $author_id,
                'publication_rejected',
                __( 'Publicación rechazada', 'motorlan-api-vue' ),
                $message,
                [
                    'post_id' => $post_id,
                    'reason' => $reason,
                    'product_title' => $formatted_title,
                    'url' => '/dashboard/publications'
                ],
                ['web', 'email']
            );
        } finally {
            if ( $switched ) restore_previous_locale();
        }
    }

    public function on_admin_contact_publisher( $post_id, $message_content, $subject_line ) {
        $post = get_post( $post_id );
        if ( ! $post ) return;
        $author_id = $post->post_author;

        $this->notification_manager->create_notification(
            $author_id,
            'admin_contact',
            $subject_line,
            $message_content,
            [
                'post_id' => $post_id
            ],
            ['web', 'email']
        );
    }

    public function on_offer_created( $offer_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'motorlan_offers';
        $offer = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $offer_id ) );
        if ( ! $offer ) return;

        $seller_id = get_post_field( 'post_author', $offer->publication_id );
        $buyer = get_userdata( $offer->user_id );
        $buyer_name = $buyer ? $buyer->display_name : 'Usuario';
        $publication_title = motorlan_format_motor_name( $offer->publication_id );

        $image = get_the_post_thumbnail_url( $offer->publication_id, 'medium' );
        $price = get_post_meta( $offer->publication_id, 'precio', true );
        
        $switched = switch_to_locale( get_user_locale( $seller_id ) );

        try {
            $this->notification_manager->create_notification(
                $seller_id,
                'new_offer',
                sprintf( __( 'Nueva oferta de %s', 'motorlan-api-vue' ), $buyer_name ),
                sprintf( __( 'Has recibido una oferta de %s€ por "%s"', 'motorlan-api-vue' ), $offer->offer_amount, $publication_title ),
                array(
                    'offer_id'       => $offer_id,
                    'publication_id' => $offer->publication_id,
                    'url'            => '/dashboard/publications/offers-received',
                    'product_image'  => $image,
                    'product_price'  => $price,
                    'product_title'  => $publication_title,
                    'offer_amount'   => $offer->offer_amount
                ),
                array( 'web', 'email' )
            );
        } finally {
            if ( $switched ) restore_previous_locale();
        }
    }

    public function on_offer_status_updated( $offer_id, $status ) {
        if ( $status !== 'accepted' ) return;

        global $wpdb;
        $table_name = $wpdb->prefix . 'motorlan_offers';
        $offer = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $offer_id ) );
        if ( ! $offer ) return;

        // Notify Buyer
        $buyer_id = $offer->user_id;
        $publication_title = motorlan_format_motor_name( $offer->publication_id );
        
        $image = get_the_post_thumbnail_url( $offer->publication_id, 'medium' );
        $price = get_post_meta( $offer->publication_id, 'precio', true );
        
        $switched = switch_to_locale( get_user_locale( $buyer_id ) );

        try {
            $this->notification_manager->create_notification(
                $buyer_id,
                'offer_accepted',
                sprintf( __( '¡Oferta aceptada para "%s"!', 'motorlan-api-vue' ), $publication_title ),
                __( 'El vendedor ha aceptado tu oferta. Tienes 24 horas para confirmar la compra.', 'motorlan-api-vue' ),
                array(
                    'offer_id'       => $offer_id,
                    'publication_id' => $offer->publication_id,
                    'url'            => '/dashboard/purchases/offers-sent',
                    'product_title'  => $publication_title,
                    'product_image'  => $image,
                    'product_price'  => $price,
                    'offer_amount'   => $offer->offer_amount
                ),
                array( 'web', 'email' )
            );
        } finally {
            if ( $switched ) restore_previous_locale();
        }
    }

    public function on_manual_sale_created( $purchase_data ) {
        // $purchase_data is array ['buyer_id', 'product_title', 'uuid', 'product_id']
        // Wait, on_manual_sale_created was mapped to 'motorlan_manual_sale_created' in init()
        // And I refactored sales-routes.php to pass an array.
        
        $buyer_id = isset($purchase_data['buyer_id']) ? $purchase_data['buyer_id'] : 0;
        if (!$buyer_id) return;
        
        $pid = isset($purchase_data['product_id']) ? $purchase_data['product_id'] : 0;
        $title = ($pid) ? motorlan_format_motor_name( $pid ) : (isset($purchase_data['product_title']) ? $purchase_data['product_title'] : 'Producto');
        $uuid = isset($purchase_data['uuid']) ? $purchase_data['uuid'] : '';

        $image = ($pid) ? get_the_post_thumbnail_url( $pid, 'medium' ) : '';
        $price = ($pid) ? get_post_meta( $pid, 'precio', true ) : '';
        
        $switched = switch_to_locale( get_user_locale( $buyer_id ) );

        try {
            $this->notification_manager->create_notification(
                $buyer_id,
                'offer_accepted', // Using offer_accepted template as fallback
                __( '¡Compra registrada!', 'motorlan-api-vue' ),
                sprintf( __( 'El vendedor ha registrado tu compra de "%s".', 'motorlan-api-vue' ), $title ),
                array(
                    'purchase_uuid' => $uuid,
                    'publication_id' => $pid,
                    'url'            => '/dashboard/purchases/purchases',
                    'product_title'  => $title,
                    'product_image'  => $image,
                    'product_price'  => $price,
                ),
                array( 'web', 'email' )
            );
        } finally {
            if ( $switched ) restore_previous_locale();
        }
    }

    public function on_new_purchase( $purchase_id ) {
        // Fetch Seller
        $seller_id = get_field( 'vendedor', $purchase_id ); 
        // Fallback for seller ID
        if ( ! $seller_id ) $seller_id = get_post_meta( $purchase_id, 'vendedor', true );
        if ( ! $seller_id ) $seller_id = get_post_meta( $purchase_id, 'vendedor_id', true );
        
        // Fetch Buyer
        $buyer_id = get_field( 'comprador', $purchase_id );
        if ( ! $buyer_id ) $buyer_id = get_post_meta( $purchase_id, 'comprador', true );
        if ( ! $buyer_id ) $buyer_id = get_post_meta( $purchase_id, 'comprador_id', true );
        
        $buyer = get_userdata( $buyer_id );
        $buyer_name = $buyer ? $buyer->display_name : 'Usuario';

        // Fetch Publication Title
        // The purchase title usually contains it, or we can get it from related publication
        $publicacion_id = get_field( 'publicacion', $purchase_id );
        if ( ! $publicacion_id ) $publicacion_id = get_post_meta( $purchase_id, 'publicacion', true );
        
        $publicacion_title = $publicacion_id ? motorlan_format_motor_name( $publicacion_id ) : get_the_title( $purchase_id );
        $uuid = get_field( 'uuid', $purchase_id ) ?: get_post_meta( $purchase_id, 'uuid', true );

        $image = ($publicacion_id) ? get_the_post_thumbnail_url( $publicacion_id, 'medium' ) : '';
        $price = ($publicacion_id) ? get_post_meta( $publicacion_id, 'precio', true ) : '';
        
        $switched = switch_to_locale( get_user_locale( $seller_id ) );

        try {
            $this->notification_manager->create_notification(
                $seller_id,
                'new_purchase',
                sprintf( __( 'Nueva compra de %s en "%s"', 'motorlan-api-vue' ), $buyer_name, $publicacion_title ),
                sprintf( __( 'El usuario %s ha iniciado una compra para tu publicación.', 'motorlan-api-vue' ), $buyer_name ),
                array(
                    'purchase_uuid' => $uuid,
                    'purchase_id'   => $purchase_id,
                    'url'           => '/purchases/' . $uuid,
                    'product_title' => $publicacion_title,
                    'product_image' => $image,
                    'product_price' => $price,
                    'buyer_name'    => $buyer_name,
                ),
                array( 'web', 'email' )
            );
        } finally {
            if ( $switched ) restore_previous_locale();
        }
    }

    public function on_new_question( $question_id ) {
        $publication_id = function_exists('get_field') ? get_field( 'publicacion', $question_id ) : get_post_meta($question_id, 'publicacion', true);
        if ( ! $publication_id ) return;
        
        $user_id_asker = function_exists('get_field') ? get_field( 'usuario', $question_id ) : get_post_meta($question_id, 'usuario', true);
        if ( is_array( $user_id_asker ) ) $user_id_asker = $user_id_asker['ID'];
        
        $pregunta = function_exists('get_field') ? get_field( 'pregunta', $question_id ) : get_post_meta($question_id, 'pregunta', true);
        
        $publication_author_id = get_post_field( 'post_author', $publication_id );
        $publication_title = motorlan_format_motor_name( $publication_id );
        $user_who_asked = get_userdata( $user_id_asker );

        $asker_name = $user_who_asked ? $user_who_asked->display_name : 'Usuario';

        $image = get_the_post_thumbnail_url( $publication_id, 'medium' );
        
        $switched = switch_to_locale( get_user_locale( $publication_author_id ) );

        try {
            $this->notification_manager->create_notification(
                $publication_author_id,
                'new_question',
                sprintf( __( 'Nueva pregunta de %s en "%s"', 'motorlan-api-vue' ), $asker_name, $publication_title ),
                $pregunta,
                [
                    'publication_id' => $publication_id,
                    'question_id'    => $question_id,
                    'url'            => '/dashboard/publications/questions',
                    'product_title'  => $publication_title,
                    'product_image'  => $image,
                    'asker_name'     => $asker_name,
                    'question_text'  => $pregunta
                ],
                ['web', 'email']
            );
        } finally {
            if ( $switched ) restore_previous_locale();
        }
    }

    public function on_password_reset_code( $user_id, $code, $expiry ) {
        $this->notification_manager->create_notification(
            $user_id,
            'password_reset_code',
            'Código de restablecimiento de contraseña',
            'Tu código es: ' . $code,
            [ 'code' => $code ],
            ['email']
        );
    }

    public function on_new_chat_message( $recipient_id, $message_data, $sender_name, $product_id ) {
        // $message_data is object row from get_row
        $room_key = is_object($message_data) ? $message_data->room_key : (isset($message_data['room_key']) ? $message_data['room_key'] : '');
        $message_text = is_object($message_data) ? $message_data->message : (isset($message_data['message']) ? $message_data['message'] : '');

        $title = motorlan_format_motor_name( $product_id );
        
        $image = get_the_post_thumbnail_url( $product_id, 'medium' );

        $switched = switch_to_locale( get_user_locale( $recipient_id ) );

        try {
            $this->notification_manager->create_notification(
                $recipient_id,
                'new_message',
                sprintf( __( 'Nuevo mensaje en "%s"', 'motorlan-api-vue' ), $title ),
                "{$sender_name}: " . wp_trim_words( $message_text, 10, '...' ),
                [
                    'url' => '/dashboard/inquiries?room_key=' . $room_key,
                    'room_key' => $room_key,
                    'product_id' => $product_id,
                    'sender_name' => $sender_name,
                    'product_title' => $title,
                    'product_image' => $image,
                    'message_full'  => $message_text
                ],
                ['web', 'email']
            );
        } finally {
            if ( $switched ) restore_previous_locale();
        }
    }
    
    public function on_new_purchase_message( $recipient_id, $sender_name, $message_text, $purchase_id, $uuid ) {
        // Try to find product_id from purchase if possible, or pass it?
        // Actually, purchase_id has 'publicacion' meta.
        $pid = get_field( 'publicacion', $purchase_id );
        if ( ! $pid ) $pid = get_post_meta( $purchase_id, 'publicacion', true );
        $image = ($pid) ? get_the_post_thumbnail_url( $pid, 'medium' ) : '';
        $title = ($pid) ? motorlan_format_motor_name( $pid ) : '';
        
        $switched = switch_to_locale( get_user_locale( $recipient_id ) );

        try {
            $this->notification_manager->create_notification(
                $recipient_id,
                'new_message',
                sprintf( __( 'Nuevo mensaje de compra (%s)', 'motorlan-api-vue' ), $sender_name ),
                "{$sender_name}: " . wp_trim_words( $message_text, 10, '...' ),
                array(
                    'purchase_uuid' => $uuid,
                    'purchase_id'   => $purchase_id,
                    'url'           => '/purchases/' . $uuid,
                    'product_title' => $title,
                    'product_image' => $image,
                    'sender_name'   => $sender_name,
                    'message_full'  => $message_text
                ),
                array( 'web', 'email' )
            );
        } finally {
            if ( $switched ) restore_previous_locale();
        }
    }

    public function on_user_interested( $post_id, $user_id ) {
        // Notify Seller that someone favorited their item?
        // This is often good for engagement.
        $author_id = get_post_field( 'post_author', $post_id );
        if ( $author_id == $user_id ) return; // Don't notify self
        
        $user = get_userdata( $user_id );
        $name = $user ? $user->display_name : 'Alguien';
        $title = motorlan_format_motor_name( $post_id );

        $image = get_the_post_thumbnail_url( $post_id, 'medium' );
        
        $switched = switch_to_locale( get_user_locale( $author_id ) );

        try {
            $this->notification_manager->create_notification(
                $author_id,
                'user_interested',
                __( '¡A alguien le interesa tu producto!', 'motorlan-api-vue' ),
                sprintf( __( '%s ha agregado "%s" a sus favoritos.', 'motorlan-api-vue' ), $name, $title ),
                [
                     'post_id' => $post_id,
                     'interested_user_id' => $user_id,
                     'url' => '/dashboard/publications/list', // Analyze engagement
                     'product_title' => $title,
                     'product_image' => $image
                ],
                ['web'] // Maybe email too? Let's stick to web to avoid spam
            );
        } finally {
            if ( $switched ) restore_previous_locale();
        }
    }

    public function on_publication_sold_manually( $post_id ) {
        // Notify users who favorited this item?
        // Logic: Get all users who have this post_id in 'motorlan_favorites' meta.
        // This can be heavy if many users.
        /* 
        $args = [
             'meta_key' => 'motorlan_favorites',
             'meta_value' => $post_id,
             'meta_compare' => 'LIKE' // Serialized array
        ];
        // ... user query ...
        // This might be too expensive to do synchronously.
        // For now, maybe just notify the owner confirmation?
        */
        
        // Actually, 'sold' status usually means it's gone.
        // Maybe notify owner "Product marked as sold".
        $author_id = get_post_field( 'post_author', $post_id );
        $title = motorlan_format_motor_name( $post_id );
        
        $this->notification_manager->create_notification(
            $author_id,
            'publication_sold',
            "Publicación marcada como vendida",
            "Has marcado \"{$title}\" como vendida.",
            [
                'post_id' => $post_id,
                'url' => '/dashboard/publications/list'
            ],
            ['web'] 
        );
    }

    public function on_new_review( $reviewer_id, $reviewed_id, $purchase_id ) {
        $reviewer = get_userdata( $reviewer_id );
        $reviewer_name = $reviewer ? $reviewer->display_name : 'Usuario';

        $this->notification_manager->create_notification(
            $reviewed_id,
            'new_review',
            "Nueva valoración de {$reviewer_name}",
            "{$reviewer_name} te ha dejado una valoración por una compra reciente.",
            [
                'purchase_id' => $purchase_id,
                'reviewer_id' => $reviewer_id,
                'url'         => '/dashboard/profile' // Or reviews list
            ],
            ['web', 'email']
        );
    }
    
    public function on_publication_pending_approval( $post_id ) {
        // Notify Admins
        $admins = get_users(['role' => 'administrator']);
        $author_id = get_post_field( 'post_author', $post_id );
        $author = get_userdata( $author_id );
        $author_name = $author ? $author->display_name : 'Usuario';
        $title = motorlan_format_motor_name( $post_id );

        foreach ($admins as $admin) {
            $this->notification_manager->create_notification(
                $admin->ID,
                'pending_approval',
                'Solicitud de publicación',
                "El usuario {$author_name} ha solicitado publicar \"{$title}\".",
                [
                    'post_id' => $post_id,
                    'product_title' => $title,
                    'url' => '/dashboard/admin/approvals',
                ],
                ['web', 'email']
            );
        }
    }

    public function on_seller_reply( $buyer_id, $buyer_email, $message_data, $sender_name, $product_id ) {
        $room_key = is_object($message_data) ? $message_data->room_key : (isset($message_data['room_key']) ? $message_data['room_key'] : '');
        $message_text = is_object($message_data) ? $message_data->message : (isset($message_data['message']) ? $message_data['message'] : '');

        $title = motorlan_format_motor_name( $product_id );
        $image = get_the_post_thumbnail_url( $product_id, 'medium' );

        if ( $buyer_id ) {
            $switched = switch_to_locale( get_user_locale( $buyer_id ) );
            try {
                $this->notification_manager->create_notification(
                    $buyer_id,
                    'new_message',
                    sprintf( __( 'Respuesta de vendedor en "%s"', 'motorlan-api-vue' ), $title ),
                    "{$sender_name}: " . wp_trim_words( $message_text, 10, '...' ),
                    [
                        'url' => '/dashboard/purchases/inquiries?room_key=' . $room_key,
                        'room_key' => $room_key,
                        'product_id' => $product_id,
                        'sender_name' => $sender_name,
                        'product_title' => $title,
                        'product_image' => $image,
                        'message_full'  => $message_text
                    ],
                    ['web', 'email']
                );
            } finally {
                if ( $switched ) restore_previous_locale();
            }
        } elseif ( ! empty( $buyer_email ) ) {
            // Guest notification (Email only)
            $this->notification_manager->send_email_notification_direct(
                0, // No user ID
                'new_message',
                sprintf( __( 'Respuesta de vendedor en "%s"', 'motorlan-api-vue' ), $title ),
                "{$sender_name}: " . wp_trim_words( $message_text, 10, '...' ),
                [
                    'url' => trailingslashit(get_site_url()) . 'marketplace-motorlan/product/' . get_post_field('post_name', $product_id), // Better link for guests?
                    'direct_email' => $buyer_email, // We'll need to modify manager to use this
                    'product_title' => $title,
                    'product_image' => $image,
                    'message_full'  => $message_text,
                    'sender_name' => $sender_name
                ]
            );
        }
    }

}
