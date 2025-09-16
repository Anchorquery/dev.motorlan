<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class Motorlan_Notification_Manager {

    /**
     * Crea una notificación en la base de datos y la envía por los canales especificados.
     *
     * @param int    $user_id ID del usuario.
     * @param string $type    Tipo de notificación (p. ej., 'new_purchase').
     * @param string $title   Título de la notificación.
     * @param string $message Mensaje de la notificación.
     * @param array  $data    Datos adicionales (p. ej., ['purchase_id' => 123, 'url' => '/purchases/123']).
     * @param array  $channels Canales por los que enviar la notificación (p. ej., ['email', 'web']).
     * @return bool True si se creó correctamente, false en caso de error.
     */
    public function create_notification( $user_id, $type, $title, $message, $data = [], $channels = ['web'] ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'motorlan_notifications';

        $encoded_data = wp_json_encode( $data );

        $result = $wpdb->insert(
            $table_name,
            [
                'user_id' => $user_id,
                'type'    => $type,
                'title'   => $title,
                'message' => $message,
                'data'    => $encoded_data,
            ],
            [
                '%d',
                '%s',
                '%s',
                '%s',
                '%s',
            ]
        );

        if ( ! $result ) {
            return false;
        }

        if ( in_array( 'email', $channels, true ) ) {
            $this->send_email_notification( $user_id, $type, $title, $message, $data );
        }

        do_action( 'motorlan_notification_created', $user_id, $type, $data );

        return true;
    }

    /**
     * Obtiene las notificaciones de un usuario.
     *
     * @param int $user_id ID del usuario.
     * @param array $args Argumentos de consulta (p. ej., ['per_page' => 10, 'status' => 'unread']).
     * @return array Lista de notificaciones.
     */
    public function get_user_notifications( $user_id, $args = [] ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'motorlan_notifications';

        $defaults = [
            'per_page' => 20,
            'page'     => 1,
            'status'   => 'all', // 'all', 'read', 'unread'
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        ];
        $args = wp_parse_args( $args, $defaults );

        $where = $wpdb->prepare( "WHERE user_id = %d", $user_id );

        if ( 'read' === $args['status'] ) {
            $where .= " AND is_read = 1";
        } elseif ( 'unread' === $args['status'] ) {
            $where .= " AND is_read = 0";
        }

        $limit = (int) $args['per_page'];
        $offset = ( (int) $args['page'] - 1 ) * $limit;

        $query = "SELECT * FROM {$table_name} {$where} ORDER BY {$args['orderby']} {$args['order']} LIMIT {$limit} OFFSET {$offset}";

        $results = $wpdb->get_results( $query, ARRAY_A );

        // Decode JSON data for each notification
        if ( ! empty( $results ) ) {
            foreach ( $results as $key => $result ) {
                if ( ! empty( $result['data'] ) ) {
                    $results[ $key ]['data'] = json_decode( $result['data'], true );
                }
            }
        }

        return $results;
    }

    /**
     * Marca una o varias notificaciones como leídas.
     *
     * @param int|array $notification_ids ID(s) de las notificaciones.
     * @param int       $user_id          ID del usuario (para seguridad).
     * @return bool Éxito o fracaso.
     */
    public function mark_as_read( $notification_ids, $user_id ) {
        return $this->update_read_status( $notification_ids, $user_id, 1 );
    }

    /**
     * Marca una o varias notificaciones como no leídas.
     *
     * @param int|array $notification_ids ID(s) de las notificaciones.
     * @param int       $user_id          ID del usuario (para seguridad).
     * @return bool Éxito o fracaso.
     */
    public function mark_as_unread( $notification_ids, $user_id ) {
        return $this->update_read_status( $notification_ids, $user_id, 0 );
    }

    /**
     * Helper function to update the read status of notifications.
     *
     * @param int|array $notification_ids
     * @param int       $user_id
     * @param int       $status (1 for read, 0 for unread)
     * @return bool
     */
    private function update_read_status( $notification_ids, $user_id, $status ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'motorlan_notifications';

        if ( ! is_array( $notification_ids ) ) {
            $notification_ids = [ $notification_ids ];
        }

        if ( empty( $notification_ids ) ) {
            return false;
        }

        $ids_placeholder = implode( ',', array_fill( 0, count( $notification_ids ), '%d' ) );

        $query = $wpdb->prepare(
            "UPDATE {$table_name} SET is_read = %d WHERE user_id = %d AND id IN ({$ids_placeholder})",
            array_merge( [ $status, $user_id ], $notification_ids )
        );

        $result = $wpdb->query( $query );

        return $result !== false;
    }

    /**
     * Envía una notificación por correo electrónico.
     *
     * @param int    $user_id
     * @param string $type
     * @param string $title
     * @param string $message
     * @param array  $data
     */
    private function send_email_notification( $user_id, $type, $title, $message, $data ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        $to = $user->user_email;
        $subject = "[Motorlan] " . $title;
        
        $body = $this->get_email_template( $type, [
            'title'   => $title,
            'message' => $message,
            'data'    => $data,
            'user'    => $user,
        ] );

        if ( empty( $body ) ) {
            return;
        }

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        wp_mail( $to, $subject, $body, $headers );
    }

    /**
     * Carga y renderiza una plantilla de correo electrónico.
     *
     * @param string $template_name Nombre de la plantilla (corresponde al 'type').
     * @param array  $args          Argumentos para pasar a la plantilla.
     * @return string Contenido del correo renderizado.
     */
    private function get_email_template( $template_name, $args = [] ) {
        $template_path = MOTORLAN_API_VUE_PATH . 'includes/email-templates/' . sanitize_file_name( $template_name ) . '.php';

        if ( ! file_exists( $template_path ) ) {
            return '';
        }

        ob_start();
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        include $template_path;
        $content = ob_get_clean();

        $base_template_path = MOTORLAN_API_VUE_PATH . 'includes/email-templates/base.php';
        if ( ! file_exists( $base_template_path ) ) {
            return $content; // Fallback to content only if base is missing
        }

        $base_html = file_get_contents( $base_template_path );

        // Replace placeholders
        $subject = $args['title'] ?? 'Notificación de Motorlan';
        $full_html = str_replace( '{{subject}}', $subject, $base_html );
        $full_html = str_replace( '{{content}}', $content, $full_html );

        return $full_html;
    }
}