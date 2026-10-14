<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class Motorlan_Notification_Manager {

    /**
     * Emails pendientes de enviar al terminar la petición (ver queue_email()).
     *
     * @var array
     */
    private static $deferred_emails = [];

    /**
     * ¿Ya está enganchado el envío diferido al hook shutdown?
     *
     * @var bool
     */
    private static $shutdown_hooked = false;

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

        // La columna title es VARCHAR(255): wpdb rechaza el INSERT entero si se pasa
        if ( is_string( $title ) && mb_strlen( $title ) > 250 ) {
            $title = mb_substr( $title, 0, 247 ) . '...';
        }

        // Sin usuario no hay a quién mostrar la notificación web (p. ej. venta manual a un
        // invitado): antes quedaban filas huérfanas con user_id = 0
        $result = false;
        if ( (int) $user_id > 0 ) {
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
                error_log( 'Motorlan notifications: INSERT failed (' . $type . '): ' . $wpdb->last_error );
            }
        }

        // El email no depende de que la notificación web se haya guardado.
        // Asíncrono: el POST del chat ya no espera al SMTP (ver queue_email()).
        if ( in_array( 'email', $channels, true ) ) {
            $this->queue_email( $user_id, $type, $title, $message, $data );
        }

        if ( ! $result ) {
            return false;
        }

        do_action( 'motorlan_notification_created', $user_id, $type, $data );

        return true;
    }

    /**
     * Obtiene el número de notificaciones no leídas de un usuario.
     *
     * @param int $user_id ID del usuario.
     * @return int Número de notificaciones no leídas.
     */
    public function get_unread_count( $user_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'motorlan_notifications';

        $query = $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND is_read = 0", $user_id );

        return (int) $wpdb->get_var( $query );
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

        // Límites: page=0 daba OFFSET negativo (SQL inválido) y per_page no tenía tope
        $args['per_page'] = max( 1, min( 50, (int) $args['per_page'] ) );
        $args['page']     = max( 1, (int) $args['page'] );
        $args['orderby']  = in_array( $args['orderby'], [ 'created_at', 'id' ], true ) ? $args['orderby'] : 'created_at';
        $args['order']    = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';

        $where = $wpdb->prepare( "WHERE user_id = %d", $user_id );

        if ( 'read' === $args['status'] ) {
            $where .= " AND is_read = 1";
        } elseif ( 'unread' === $args['status'] ) {
            $where .= " AND is_read = 0";
        }

        $limit = (int) $args['per_page'];
        $offset = ( (int) $args['page'] - 1 ) * $limit;

        $query = "SELECT * FROM {$table_name} {$where} ORDER BY {$args['orderby']} {$args['order']}, id {$args['order']} LIMIT {$limit} OFFSET {$offset}";

        $results = $wpdb->get_results( $query, ARRAY_A );

        // Decode JSON data for each notification
        if ( ! empty( $results ) ) {
            foreach ( $results as $key => $result ) {
                if ( ! empty( $result['data'] ) ) {
                    $results[ $key ]['data'] = json_decode( $result['data'], true );
                }
            }
        }

        if ( ! is_array( $results ) ) {
            return [];
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
     * Elimina notificaciones del usuario (antes "Eliminar" solo las quitaba en el navegador
     * y reaparecían al recargar).
     *
     * @param int|array $notification_ids ID(s) de las notificaciones.
     * @param int       $user_id          ID del usuario (solo borra las suyas).
     * @return bool
     */
    public function delete_notifications( $notification_ids, $user_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'motorlan_notifications';

        $notification_ids = array_values( array_filter( array_map( 'absint', (array) $notification_ids ) ) );
        if ( empty( $notification_ids ) || ! $user_id ) {
            return false;
        }

        $ids_placeholder = implode( ',', array_fill( 0, count( $notification_ids ), '%d' ) );

        $result = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table_name} WHERE user_id = %d AND id IN ({$ids_placeholder})",
            array_merge( [ (int) $user_id ], $notification_ids )
        ) );

        return $result !== false;
    }

    /**
     * Desfase (s) entre la hora de MySQL y UTC. created_at se rellena con
     * CURRENT_TIMESTAMP, que usa la zona de MySQL (no la de WordPress): con esto se
     * convierte a UTC sin depender de cómo esté configurado el servidor.
     *
     * @return int
     */
    public static function get_db_utc_offset() {
        static $offset = null;
        if ( null === $offset ) {
            global $wpdb;
            $offset = (int) $wpdb->get_var( 'SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW())' );
        }
        return $offset;
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
     * Encola un email: se envía tras responder al cliente si el servidor lo permite
     * (PHP-FPM/LiteSpeed); si no, por WP-Cron cuando está activo y al día; y si no,
     * directamente como antes. Así el POST del chat no espera al SMTP y no se pierden
     * emails aunque WP-Cron esté desactivado (DISABLE_WP_CRON) o atascado.
     *
     * El idioma se guarda con el email: los listeners cambian de idioma antes de llamar
     * y la plantilla se renderiza más tarde, fuera de ese bloque.
     *
     * @param int    $user_id
     * @param string $type
     * @param string $title
     * @param string $message
     * @param array  $data
     */
    public function queue_email( $user_id, $type, $title, $message, $data ) {
        $payload = [
            'user_id' => (int) $user_id,
            'type'    => (string) $type,
            'title'   => (string) $title,
            'message' => (string) $message,
            'data'    => is_array( $data ) ? $data : [],
            'locale'  => function_exists( 'determine_locale' ) ? determine_locale() : get_locale(),
        ];

        // Ya en segundo plano (cron) o en CLI: enviar sin más
        $in_background = ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'WP_CLI' ) && WP_CLI );

        // 1) Tras devolver la respuesta: no depende de WP-Cron y no se pierde
        if ( ! $in_background && ( function_exists( 'fastcgi_finish_request' ) || function_exists( 'litespeed_finish_request' ) ) ) {
            self::$deferred_emails[] = $payload;
            if ( ! self::$shutdown_hooked ) {
                // Prioridad 20: después de wp_ob_end_flush_all (1), con la respuesta ya completa
                add_action( 'shutdown', [ __CLASS__, 'flush_deferred_emails' ], 20 );
                self::$shutdown_hooked = true;
            }
            return;
        }

        if ( ! $in_background ) {
            // 2) WP-Cron, solo si está activo y sin eventos atascados
            if ( self::is_wp_cron_healthy() ) {
                // uid: WP descarta eventos idénticos programados con menos de 10 min de diferencia
                $payload['uid'] = wp_generate_uuid4();
                if ( true === wp_schedule_single_event( time(), 'motorlan_send_queued_email', [ $payload ] ) ) {
                    return;
                }
                unset( $payload['uid'] );
            } else {
                // WP-Cron no funciona: rescatar los emails que se quedaron programados
                self::rescue_stuck_queued_emails();
            }
        }

        // 3) Fallback: envío directo en la misma petición
        self::send_queued_email( $payload );
    }

    /**
     * Envía los emails diferidos después de cerrar la conexión con el cliente.
     */
    public static function flush_deferred_emails() {
        if ( empty( self::$deferred_emails ) ) {
            return;
        }

        if ( function_exists( 'fastcgi_finish_request' ) ) {
            fastcgi_finish_request();
        } elseif ( function_exists( 'litespeed_finish_request' ) ) {
            litespeed_finish_request();
        }
        ignore_user_abort( true );

        $pending               = self::$deferred_emails;
        self::$deferred_emails = [];
        foreach ( $pending as $payload ) {
            self::send_queued_email( $payload );
        }
    }

    /**
     * Envío final de un email encolado (también callback de WP-Cron).
     *
     * @param array $payload
     */
    public static function send_queued_email( $payload ) {
        if ( ! is_array( $payload ) || empty( $payload['type'] ) ) {
            return;
        }

        $switched = false;
        if ( ! empty( $payload['locale'] ) && function_exists( 'switch_to_locale' ) && $payload['locale'] !== determine_locale() ) {
            $switched = switch_to_locale( $payload['locale'] );
        }

        try {
            ( new self() )->send_email_notification_direct(
                (int) ( $payload['user_id'] ?? 0 ),
                (string) $payload['type'],
                (string) ( $payload['title'] ?? '' ),
                (string) ( $payload['message'] ?? '' ),
                is_array( $payload['data'] ?? null ) ? $payload['data'] : []
            );
        } finally {
            if ( $switched ) {
                restore_previous_locale();
            }
        }
    }

    /**
     * ¿WP-Cron está activo y al día? Desactivado (DISABLE_WP_CRON: aunque haya un cron
     * de sistema no lo podemos saber) o con eventos de más de 10 min de retraso → no se
     * le confían emails.
     *
     * @return bool
     */
    private static function is_wp_cron_healthy() {
        if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
            return false;
        }
        if ( ! function_exists( '_get_cron_array' ) ) {
            return false;
        }
        $crons = _get_cron_array();
        if ( empty( $crons ) || ! is_array( $crons ) ) {
            return true;
        }
        $first = (int) min( array_keys( $crons ) );

        return $first >= time() - 10 * MINUTE_IN_SECONDS;
    }

    /**
     * Envía directamente los emails encolados en WP-Cron con más de 10 min de retraso
     * (WP-Cron dejó de funcionar después de programarlos). Como mucho 10 por petición.
     */
    private static function rescue_stuck_queued_emails() {
        if ( ! function_exists( '_get_cron_array' ) ) {
            return;
        }
        $crons = _get_cron_array();
        if ( empty( $crons ) || ! is_array( $crons ) ) {
            return;
        }
        $limit   = time() - 10 * MINUTE_IN_SECONDS;
        $rescued = 0;
        foreach ( $crons as $timestamp => $hooks ) {
            if ( $timestamp > $limit || empty( $hooks['motorlan_send_queued_email'] ) ) {
                continue;
            }
            foreach ( $hooks['motorlan_send_queued_email'] as $event ) {
                $args = isset( $event['args'] ) ? $event['args'] : [];
                // Desprogramar antes de enviar para no duplicarlo si WP-Cron revive
                if ( wp_unschedule_event( $timestamp, 'motorlan_send_queued_email', $args ) && isset( $args[0] ) ) {
                    self::send_queued_email( $args[0] );
                    $rescued++;
                }
                if ( $rescued >= 10 ) {
                    return;
                }
            }
        }
    }

    /**
     * Envía una notificación por correo electrónico (Método directo para uso por Cron/Async).
     *
     * @param int    $user_id
     * @param string $type
     * @param string $title
     * @param string $message
     * @param array  $data
     */
    public function send_email_notification_direct( $user_id, $type, $title, $message, $data ) {
        $to = '';
        $user = null;

        if ( $user_id ) {
            $user = get_userdata( $user_id );
            if ( $user ) {
                $to = $user->user_email;
            }
        } elseif ( ! empty( $data['direct_email'] ) ) {
            $to = $data['direct_email'];
        }

        if ( empty( $to ) ) {
            return;
        }

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

        $sent = wp_mail( $to, $subject, $body, $headers );

        if ( ! $sent ) {
            error_log( sprintf( '[Motorlan] wp_mail FAILED type=%s to=%s subject=%s', $type, $to, $subject ) );
            if ( $type === 'verify_email' && ! empty( $data['url'] ) ) {
                error_log( sprintf( '[Motorlan] Verify URL fallback for %s: %s', $to, $data['url'] ) );
            }
        }
    }

    /**
     * Carga y renderiza una plantilla de correo electrónico.
     *
     * @param string $template_name Nombre de la plantilla (corresponde al 'type').
     * @param array  $args          Argumentos para pasar a la plantilla.
     * @return string Contenido del correo renderizado.
     */
    public function get_email_template( $template_name, $args = [] ) {
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

        ob_start();
        include $base_template_path;
        $base_html = ob_get_clean();

        // Replace placeholders
        $subject = $args['title'] ?? 'Notificación de Motorlan';
        $full_html = str_replace( '{{subject}}', $subject, $base_html );
        $full_html = str_replace( '{{content}}', $content, $full_html );

        return $full_html;
    }
}

// Envío de los emails encolados en WP-Cron (ver Motorlan_Notification_Manager::queue_email())
add_action( 'motorlan_send_queued_email', [ 'Motorlan_Notification_Manager', 'send_queued_email' ], 10, 1 );
