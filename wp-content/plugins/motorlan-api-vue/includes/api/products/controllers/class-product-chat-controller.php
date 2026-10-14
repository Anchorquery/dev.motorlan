<?php
/**
 * Product chat (pre-purchase) REST controller.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Motorlan_Product_Chat_Controller' ) ) {
    class Motorlan_Product_Chat_Controller extends WP_REST_Controller {
        protected $namespace = 'motorlan/v1';

        public function register_routes() {
            register_rest_route(
                $this->namespace,
                '/products/(?P<id>\\d+)/messages',
                array(
                    array(
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => array( $this, 'get_messages' ),
                        'permission_callback' => '__return_true',
                        'args'                => array(
                            'room_key' => array(
                                'type'        => 'string',
                                'required'    => false,
                                'description' => __( 'Room identifier for the conversation.', 'motorlan-api-vue' ),
                            ),
                            'since_timestamp' => array(
                                'type'              => 'string',
                                'required'          => false,
                                'validate_callback' => array( $this, 'validate_timestamp' ),
                                'description'       => __( 'UTC timestamp for incremental polling.', 'motorlan-api-vue' ),
                            ),
                            // Token firmado de la sala de invitado (ver room_token())
                            'room_token' => array(
                                'type'     => 'string',
                                'required' => false,
                            ),
                        ),
                    ),
                    array(
                        'methods'             => WP_REST_Server::CREATABLE,
                        'callback'            => array( $this, 'create_message' ),
                        'permission_callback' => '__return_true',
                        'args'                => array(
                            'message' => array(
                                'type'              => 'string',
                                'required'          => true,
                                'sanitize_callback' => 'sanitize_textarea_field',
                            ),
                            'room_key' => array(
                                'type'     => 'string',
                                'required' => false,
                            ),
                            'viewer_name' => array(
                                'type'     => 'string',
                                'required' => false,
                            ),
                            'guest_email' => array(
                                'type'     => 'string',
                                'required' => false,
                                'sanitize_callback' => 'sanitize_email',
                            ),
                            'room_token' => array(
                                'type'     => 'string',
                                'required' => false,
                            ),
                            // Honeypot del formulario de invitado: un humano nunca lo rellena
                            'website' => array(
                                'type'     => 'string',
                                'required' => false,
                            ),
                        ),
                    ),
                )
            );

            // Reclama los chats de invitado (mismo email VERIFICADO) para el usuario actual.
            // Cubre los inicios de sesión que no pasan por wp_login (p. ej. wp_set_auth_cookie).
            register_rest_route(
                $this->namespace,
                '/buyer/claim-guest-chats',
                array(
                    array(
                        'methods'             => WP_REST_Server::CREATABLE,
                        'callback'            => array( $this, 'claim_guest_chats' ),
                        'permission_callback' => 'motorlan_is_user_authenticated',
                    ),
                )
            );

            // List rooms for a product (seller only)
            register_rest_route(
                $this->namespace,
                '/products/(?P<id>\\d+)/rooms',
                array(
                    array(
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => array( $this, 'get_rooms_by_product' ),
                        'permission_callback' => 'motorlan_is_user_authenticated',
                    ),
                    array(
                        'methods'             => WP_REST_Server::CREATABLE,
                        'callback'            => array( $this, 'mark_room_read' ),
                        'permission_callback' => 'motorlan_is_user_authenticated',
                        'args'                => array(
                            'room_key' => array(
                                'type'     => 'string',
                                'required' => true,
                            ),
                        ),
                    ),
                )
            );

            // List inquiries across seller products
            register_rest_route(
                $this->namespace,
                '/seller/inquiries',
                array(
                    array(
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => array( $this, 'get_seller_inquiries' ),
                        'permission_callback' => 'motorlan_is_user_authenticated',
                    ),
                )
            );

            // List inquiries for a buyer
            register_rest_route(
                $this->namespace,
                '/buyer/inquiries',
                array(
                    array(
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => array( $this, 'get_buyer_inquiries' ),
                        'permission_callback' => 'motorlan_is_user_authenticated',
                    ),
                )
            );

            // Guest chat token exchange
            register_rest_route(
                $this->namespace,
                '/chat-tokens/(?P<token>[A-Za-z0-9_\-\.]+)',
                array(
                    array(
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => array( $this, 'exchange_chat_token' ),
                        'permission_callback' => '__return_true',
                    ),
                )
            );
        }

        public static function generate_chat_token( $room_key, $guest_email, $product_id, $ttl_seconds = 604800 ) {
            $payload = array(
                'rk'  => (string) $room_key,
                'ge'  => (string) $guest_email,
                'pid' => (int) $product_id,
                'exp' => time() + (int) $ttl_seconds,
            );
            $body = self::base64url_encode( wp_json_encode( $payload ) );
            $sig  = self::base64url_encode( hash_hmac( 'sha256', $body, wp_salt( 'auth' ), true ) );
            return $body . '.' . $sig;
        }

        public static function verify_chat_token( $token ) {
            if ( empty( $token ) || strpos( $token, '.' ) === false ) {
                return null;
            }
            list( $body, $sig ) = explode( '.', $token, 2 );
            $expected = self::base64url_encode( hash_hmac( 'sha256', $body, wp_salt( 'auth' ), true ) );
            if ( ! hash_equals( $expected, $sig ) ) {
                return null;
            }
            $json = self::base64url_decode( $body );
            $data = json_decode( $json, true );
            if ( ! is_array( $data ) ) return null;
            if ( empty( $data['exp'] ) || (int) $data['exp'] < time() ) return null;
            return $data;
        }

        protected static function base64url_encode( $data ) {
            return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
        }

        protected static function base64url_decode( $data ) {
            $remainder = strlen( $data ) % 4;
            if ( $remainder ) $data .= str_repeat( '=', 4 - $remainder );
            return base64_decode( strtr( $data, '-_', '+/' ) );
        }

        public function exchange_chat_token( WP_REST_Request $request ) {
            $token = (string) $request['token'];
            $data  = self::verify_chat_token( $token );
            if ( ! $data ) {
                return new WP_Error( 'invalid_token', __( 'El enlace ha caducado o no es válido.', 'motorlan-api-vue' ), array( 'status' => 410 ) );
            }
            return new WP_REST_Response( array(
                'room_key'    => $data['rk'],
                'guest_email' => $data['ge'],
                'product_id'  => (int) $data['pid'],
                // El enlace del email es la prueba de identidad del invitado: con él
                // se entrega el token de la sala (sin él, la sala ya no es accesible)
                'room_token'  => self::is_guest_room( $data['rk'] ) ? self::room_token( $data['rk'] ) : null,
            ), 200 );
        }

        /**
         * Token firmado de una sala de invitado.
         *
         * Antes bastaba con conocer la clave de la sala; el vendedor la ve en su bandeja
         * y, con el id de invitado global por navegador, podía deducir las salas del mismo
         * invitado con OTROS vendedores (leerlas y escribir suplantándolo).
         *
         * @param string $room_key Clave de la sala.
         * @return string
         */
        public static function room_token( $room_key ) {
            return substr( hash_hmac( 'sha256', 'room|' . (string) $room_key, wp_salt( 'auth' ) ), 0, 32 );
        }

        /**
         * ¿La sala es de invitado (id de visitante no numérico)?
         *
         * @param string $room_key Clave de la sala.
         * @return bool
         */
        public static function is_guest_room( $room_key ) {
            return (bool) preg_match( '/^pub-\d+-viewer-(.+)$/', (string) $room_key, $m ) && ! is_numeric( $m[1] );
        }

        /**
         * Formato válido de clave de sala (también limita la longitud: la columna es VARCHAR(191)).
         *
         * @param string $room_key Clave de la sala.
         * @return bool
         */
        protected function is_valid_room_key( $room_key ) {
            return (bool) preg_match( '/^pub-\d{1,20}-viewer-[A-Za-z0-9_-]{1,100}$/', (string) $room_key );
        }

        /**
         * ¿Tiene la sala algún mensaje?
         *
         * @param string $room_key Clave de la sala.
         * @return bool
         */
        protected function room_has_messages( $room_key ) {
            global $wpdb;
            if ( ! $this->ensure_table_exists() ) return false;
            $table = $this->get_table_name();
            return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$table} WHERE room_key = %s LIMIT 1", $room_key ) );
        }

        /**
         * ¿El usuario participa como comprador en la sala (sala de invitado reclamada)?
         *
         * @param string $room_key Clave de la sala.
         * @param int    $user_id  Usuario.
         * @return bool
         */
        protected function user_is_room_viewer( $room_key, $user_id ) {
            if ( ! $user_id ) return false;
            global $wpdb;
            if ( ! $this->ensure_table_exists() ) return false;
            $table = $this->get_table_name();
            return (bool) $wpdb->get_var( $wpdb->prepare(
                "SELECT 1 FROM {$table} WHERE room_key = %s AND user_id = %d AND sender_role = 'viewer' LIMIT 1",
                $room_key,
                $user_id
            ) );
        }

        /**
         * Usuario registrado que reclamó una sala de invitado (0 si nadie).
         *
         * @param string $room_key Clave de la sala.
         * @return int
         */
        protected function get_room_claimed_user_id( $room_key ) {
            global $wpdb;
            $table = $this->get_table_name();
            return (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT user_id FROM {$table} WHERE room_key = %s AND sender_role = 'viewer' AND user_id > 0 ORDER BY id DESC LIMIT 1",
                $room_key
            ) );
        }

        /**
         * Reclama para el usuario actual los mensajes de invitado enviados con su email,
         * solo si ese email está verificado (o es un usuario antiguo sin la marca).
         *
         * @param WP_REST_Request $request Petición.
         * @return WP_REST_Response|WP_Error
         */
        public function claim_guest_chats( WP_REST_Request $request ) {
            $user = wp_get_current_user();
            if ( ! $user || ! $user->ID ) {
                return new WP_Error( 'rest_not_logged_in', __( 'Debes iniciar sesión.', 'motorlan-api-vue' ), array( 'status' => 401 ) );
            }

            $claimed = $this->ensure_table_exists() ? self::claim_for_user( $user ) : 0;

            return new WP_REST_Response( array( 'claimed' => $claimed ), 200 );
        }

        /**
         * Asigna al usuario las salas de invitado iniciadas con su email.
         *
         * Solo con el email verificado ('1') o en usuarios antiguos sin la marca
         * _motorlan_email_verified: si no, registrarse con el email de otro permitiría
         * quedarse con sus conversaciones. Reclama la SALA entera (los mensajes
         * siguientes del invitado se guardaban sin guest_email y quedaban huérfanos).
         * También lo puede usar motorlan_claim_guest_chats_on_login().
         *
         * @param WP_User $user Usuario.
         * @return int Filas reclamadas.
         */
        public static function claim_for_user( $user ) {
            if ( ! $user instanceof WP_User || ! $user->ID || empty( $user->user_email ) ) return 0;

            $has_flag = metadata_exists( 'user', $user->ID, '_motorlan_email_verified' );
            if ( $has_flag && '1' !== (string) get_user_meta( $user->ID, '_motorlan_email_verified', true ) ) return 0;

            global $wpdb;
            $table = $wpdb->prefix . 'motorlan_product_messages';

            // Se llama en wp_login: sin la tabla (instalación nueva) el error de wpdb se
            // imprimía delante del JSON de /login con WP_DEBUG_DISPLAY
            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) !== $table ) return 0;

            // SELECT barato primero (índice guest_email): casi nunca hay nada que reclamar
            $rooms = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT room_key FROM {$table} WHERE user_id = 0 AND guest_email = %s LIMIT 200",
                $user->user_email
            ) );
            if ( empty( $rooms ) ) return 0;

            $placeholders = implode( ',', array_fill( 0, count( $rooms ), '%s' ) );
            $sql = $wpdb->prepare(
                "UPDATE {$table} SET user_id = %d WHERE user_id = 0 AND sender_role = 'viewer' AND room_key IN ({$placeholders})",
                array_merge( array( (int) $user->ID ), $rooms )
            );

            return (int) $wpdb->query( $sql );
        }

        protected function get_table_name() {
            global $wpdb;
            return $wpdb->prefix . 'motorlan_product_messages';
        }

        protected function get_reads_table_name() {
            global $wpdb;
            return $wpdb->prefix . 'motorlan_product_room_reads';
        }

        protected function table_exists() {
            global $wpdb;
            $table = $this->get_table_name();
            $like  = str_replace( array( '_', '%' ), array( '\\_', '\\%' ), $table );
            $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
            return ( $found === $table );
        }

        protected function ensure_table_exists() {
            static $checked = null;
            if ( null !== $checked ) return $checked;

            // Migraciones (SHOW TABLES/COLUMNS) como mucho cada 12 h, no en cada poll del chat
            if ( function_exists( 'motorlan_run_migrations' ) && ! get_transient( 'motorlan_chat_migrations_checked_v2' ) ) {
                motorlan_run_migrations();
                set_transient( 'motorlan_chat_migrations_checked_v2', 1, 12 * HOUR_IN_SECONDS );
            }
            if ( $this->table_exists() ) return $checked = true;
            if ( function_exists( 'motorlan_create_product_messages_table' ) ) {
                motorlan_create_product_messages_table();
                return $checked = $this->table_exists();
            }
            return $checked = false;
        }

        protected function ensure_reads_table_exists() {
            global $wpdb;
            $table = $this->get_reads_table_name();
            $like  = str_replace( array( '_', '%' ), array( '\\_', '\\%' ), $table );
            $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
            if ( $found === $table ) return true;
            if ( function_exists( 'motorlan_create_product_room_reads_table' ) ) {
                motorlan_create_product_room_reads_table();
                $found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
                return ( $found === $table );
            }
            return false;
        }

        public function validate_timestamp( $value ) {
            if ( empty( $value ) ) return true;
            $value = trim( (string) $value );
            // Accept ISO string with Z or offset
            $parsed = strtotime( $value );
            return $parsed !== false;
        }

        protected function normalize_datetime_for_storage( $value ) {
            if ( empty( $value ) ) return gmdate( 'Y-m-d H:i:s' );
            $clean = trim( (string) $value );
            if ( false !== strpos( $clean, 'T' ) ) $clean = str_replace( 'T', ' ', $clean );
            if ( false !== strpos( $clean, 'Z' ) ) $clean = str_replace( 'Z', '', $clean );
            $timestamp = strtotime( $clean );
            if ( false === $timestamp ) return gmdate( 'Y-m-d H:i:s' );
            return gmdate( 'Y-m-d H:i:s', $timestamp );
        }

        protected function format_datetime_for_response( $value ) {
            if ( empty( $value ) ) return gmdate( 'Y-m-d H:i:s' );
            $timestamp = strtotime( $value );
            if ( false === $timestamp ) return gmdate( 'Y-m-d H:i:s' );
            return gmdate( 'Y-m-d H:i:s', $timestamp );
        }

        protected function get_product( $product_id ) {
            $post = get_post( (int) $product_id );
            if ( ! $post || 'publicacion' !== $post->post_type ) {
                return new WP_Error( 'not_found', __( 'Publicación no encontrada.', 'motorlan-api-vue' ), array( 'status' => 404 ) );
            }
            return $post;
        }

        protected function parse_room_key( $room_key ) {
            // Expected: pub-{productId}-viewer-{viewerId}
            $pattern = '/^pub-(\d+)-viewer-(.+)$/';
            if ( preg_match( $pattern, (string) $room_key, $m ) ) {
                return array( (int) $m[1], (string) $m[2] );
            }
            return array( null, null );
        }

        /**
         * Invalida la caché de una sala de chat específica.
         * 
         * @param int $product_id
         * @param string $room_key
         */
        protected function invalidate_room_cache( $product_id, $room_key ) {
            // La clave de caché depende también del usuario y el timestamp, 
            // lo cual hace difícil invalidar una clave específica sin conocer todos los parámetros.
            // Una estrategia mejor sería usar un "version key" para la sala.
            
            // Por simplicidad en este parche, intentaremos invalidar las variantes más comunes
            // O mejor aún, la validación de caché en get_messages debería chequear un "last_updated" global de la sala.
            
            // Implementación simple: Borrar transitorios que coincidan con el patrón (requiere soporte de la DB, WP options no es optimo para wildcards)
            // Alternativa: Guardar timestamp de última actualización de la sala.
            
            $update_key = 'motorlan_chat_room_updated_' . md5($room_key);
            set_transient($update_key, time(), 3600); // 1 hora
        }

        protected function current_user_info() {
            $id    = get_current_user_id();
            $name  = '';
            $avatar= '';
            if ( $id ) {
                $user = get_user_by( 'id', $id );
                if ( $user ) $name = $user->display_name;
                $avatar = get_avatar_url( $id );
            }
            return array( $id, $name, $avatar );
        }

        protected function is_user_product_author( $product_id, $user_id ) {
            if ( ! $user_id ) return false;
            $post = get_post( $product_id );
            return $post && (int) $post->post_author === (int) $user_id;
        }

        protected function user_can_access_room( $product_id, $room_key, $user_id, $room_token = '' ) {
            list( $key_pid, $viewer_id ) = $this->parse_room_key( $room_key );
            if ( empty( $room_key ) ) return ( $this->is_user_product_author( $product_id, $user_id ) );
            if ( (int) $key_pid !== (int) $product_id ) return false;
            if ( $this->is_user_product_author( $product_id, $user_id ) ) return true;
            if ( ! empty( $viewer_id ) && ! is_numeric( $viewer_id ) ) {
                // Sala de invitado: ya NO basta con conocer la clave (el vendedor la ve y,
                // con el id global antiguo, podía deducir las salas del invitado con otros
                // vendedores). Hace falta el token firmado que se entrega al crear la sala
                // y en el enlace del email, o haber reclamado la sala con el email verificado.
                $room_token = (string) $room_token;
                if ( '' !== $room_token && hash_equals( self::room_token( $room_key ), $room_token ) ) return true;
                if ( $user_id && $this->user_is_room_viewer( $room_key, $user_id ) ) return true;
                // Sala nueva (aún sin mensajes): se permite el primer envío; el POST devuelve el token
                return ! $this->room_has_messages( $room_key );
            }
            if ( $user_id && (string) $user_id === (string) $viewer_id ) return true;

            return false;
        }

        protected function compute_sender_role( $product_id, $user_id, $room_key ) {
            if ( $this->is_user_product_author( $product_id, $user_id ) ) return 'seller';
            return 'viewer';
        }

        protected function get_messages_from_table( $product_id, $room_key, $since_timestamp, $current_user_id ) {
            global $wpdb;
            $table = $this->get_table_name();
            $where = 'WHERE product_id = %d AND room_key = %s';
            $params = array( $product_id, $room_key );
            if ( $since_timestamp ) {
                // >= (no >): created_at tiene resolución de 1 s y un mensaje insertado en el
                // mismo segundo que el poll anterior se perdía. Además, margen de 5 s: el
                // created_at se fija en PHP antes del INSERT y un mensaje confirmado después
                // del poll quedaba por detrás del cursor. El frontend deduplica por id.
                $where .= ' AND created_at >= %s';
                $params[] = gmdate( 'Y-m-d H:i:s', strtotime( $this->normalize_datetime_for_storage( $since_timestamp ) . ' UTC' ) - 5 );
            }
            $sql = $wpdb->prepare( "SELECT * FROM {$table} {$where} ORDER BY created_at ASC, id ASC", $params );
            $rows = $wpdb->get_results( $sql );
            $messages = array();
            if ( $rows ) {
                foreach ( $rows as $row ) {
                    $messages[] = $this->format_message( $row, $current_user_id );
                }
            }
            return $messages;
        }

        protected function get_last_read_at( $user_id, $product_id, $room_key ) {
            if ( ! $user_id ) return null;
            global $wpdb;
            $table = $this->get_reads_table_name();
            if ( ! $this->ensure_reads_table_exists() ) return null;
            $row = $wpdb->get_row( $wpdb->prepare(
                "SELECT last_read_at FROM {$table} WHERE user_id = %d AND product_id = %d AND room_key = %s",
                $user_id, $product_id, $room_key
            ) );
            return $row ? ( $row->last_read_at ?: null ) : null;
        }

        protected function set_last_read_now( $user_id, $product_id, $room_key ) {
            if ( ! $user_id ) return false;
            global $wpdb;
            $table = $this->get_reads_table_name();
            if ( ! $this->ensure_reads_table_exists() ) return false;
            $now = gmdate( 'Y-m-d H:i:s' );
            $fields  = array( 'last_read_at' => $now, 'updated_at' => $now );
            $formats = array( '%s', '%s' );
            // Último id leído: con solo last_read_at (resolución 1 s) un mensaje insertado en
            // el mismo segundo en que se marcaba la sala como leída no contaba nunca como no leído
            if ( $this->reads_have_last_read_id() ) {
                $fields['last_read_id'] = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT MAX(id) FROM {$this->get_table_name()} WHERE product_id = %d AND room_key = %s",
                    $product_id, $room_key
                ) );
                $formats[] = '%d';
            }
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND product_id = %d AND room_key = %s",
                $user_id, $product_id, $room_key
            ) );
            if ( $exists ) {
                $wpdb->update( $table, $fields, array( 'user_id' => $user_id, 'product_id' => $product_id, 'room_key' => $room_key ), $formats, array( '%d', '%d', '%s' ) );
            } else {
                $wpdb->insert(
                    $table,
                    array_merge( array( 'user_id' => $user_id, 'product_id' => $product_id, 'room_key' => $room_key ), $fields ),
                    array_merge( array( '%d', '%d', '%s' ), $formats )
                );
            }
            return true;
        }

        /**
         * ¿Existe ya la columna last_read_id? (la añade motorlan_migrate_product_messages_v1_2;
         * hasta entonces se sigue usando solo last_read_at).
         *
         * @return bool
         */
        protected function reads_have_last_read_id() {
            return '1' === (string) get_option( 'motorlan_room_reads_last_read_id', '' );
        }

        protected function get_unread_count( $product_id, $room_key, $user_id ) {
            if ( ! $user_id ) return 0;
            global $wpdb;
            $messages_table = $this->get_table_name();
            $reads_table    = $this->get_reads_table_name();
            if ( ! $this->ensure_table_exists() ) return 0;
            if ( ! $this->ensure_reads_table_exists() ) return 0;
            $last_read = $this->get_last_read_at( $user_id, $product_id, $room_key );
            if ( ! $last_read ) {
                $sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$messages_table} WHERE product_id = %d AND room_key = %s AND user_id != %d", $product_id, $room_key, $user_id );
                return (int) $wpdb->get_var( $sql );
            }
            $sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$messages_table} WHERE product_id = %d AND room_key = %s AND created_at > %s AND user_id != %d", $product_id, $room_key, $last_read, $user_id );
            return (int) $wpdb->get_var( $sql );
        }

        protected function persist_message( $product_id, $room_key, $message, $user_id, $sender_role, $display_name, $avatar, $guest_email = null ) {
            global $wpdb;
            $table = $this->get_table_name();
            $key   = uniqid( 'msg_', true );
            $created = gmdate( 'Y-m-d H:i:s' );
            $inserted = $wpdb->insert( $table, array(
                'message_key' => $key,
                'product_id'  => (int) $product_id,
                'room_key'    => (string) $room_key,
                'user_id'     => (int) $user_id,
                'sender_role' => (string) $sender_role,
                'display_name'=> (string) $display_name,
                'avatar'      => (string) $avatar,
                'message'     => (string) $message,
                'guest_email' => $guest_email,
                'created_at'  => $created,
            ), array( '%s','%d','%s','%d','%s','%s','%s','%s','%s','%s' ) );

            if ( false === $inserted ) {
                error_log( 'Motorlan Chat DB Error: ' . $wpdb->last_error );
                return new WP_Error( 'db_error', __( 'No se pudo guardar el mensaje.', 'motorlan-api-vue' ), array( 'status' => 500 ) );
            }
            // (Sin error_log por mensaje: llenaba el log con las claves de sala)

            $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE message_key = %s", $key ) );
            return $row;
        }

        protected function format_message( $row, $current_user_id ) {
            $user_id = (int) ( is_object( $row ) ? $row->user_id : ( $row['user_id'] ?? 0 ) );
            $display = is_object( $row ) ? ( $row->display_name ?? '' ) : ( $row['display_name'] ?? '' );
            $avatar  = is_object( $row ) ? ( $row->avatar ?? '' ) : ( $row['avatar'] ?? '' );
            $created = is_object( $row ) ? ( $row->created_at ?? '' ) : ( $row['created_at'] ?? '' );
            $message = is_object( $row ) ? ( $row->message ?? '' ) : ( $row['message'] ?? '' );
            $id      = is_object( $row ) ? ( $row->message_key ?? '' ) : ( $row['message_key'] ?? '' );
            $role    = is_object( $row ) ? ( $row->sender_role ?? 'viewer' ) : ( $row['sender_role'] ?? 'viewer' );

            if ( ! $display && $user_id ) {
                $user = get_user_by( 'id', $user_id );
                if ( $user ) $display = $user->display_name;
            }
            if ( 'viewer' === $role ) {
                // Sin Gravatar del comprador/invitado: la URL es el hash MD5 de su email y
                // permitía cotejar emails conocidos. El front pinta sus iniciales.
                $avatar = '';
            } elseif ( ! $avatar && $user_id ) {
                $avatar = get_avatar_url( $user_id );
            }

            return array(
                'id'              => $id ?: uniqid( 'msg_', true ),
                'message'         => (string) $message,
                'created_at'      => $this->format_datetime_for_response( $created ),
                'sender_role'     => (string) $role,
                'user_id'         => $user_id,
                'display_name'    => (string) $display,
                'avatar'          => (string) $avatar,
                'is_current_user' => ( $user_id === (int) $current_user_id ),
            );
        }

    public function get_messages( WP_REST_Request $request ) {
        $product_id = absint( $request['id'] );
        $product    = $this->get_product( $product_id );
        if ( is_wp_error( $product ) ) return $product;

        $room_key = sanitize_text_field( $request->get_param( 'room_key' ) );
        list( $key_pid, $viewer_id ) = $this->parse_room_key( $room_key );
        if ( $room_key && (int) $key_pid !== (int) $product_id ) {
            return new WP_Error( 'invalid_room', __( 'La sala no coincide con el producto.', 'motorlan-api-vue' ), array( 'status' => 400 ) );
        }

        list( $current_user_id, $display_name, $avatar ) = $this->current_user_info();
        $guest_em        = sanitize_email( (string) $request->get_param( 'guest_email' ) );
        $is_real_guest   = ! is_user_logged_in();
        // SEGURIDAD: no se amplía el acceso por un guest_email sin verificar (permitía leer
        // y escribir en salas ajenas, o listar todas las salas con el email del vendedor/admin).
        // Los invitados usan salas con id no numérico (secreto); las numéricas exigen login.
        $access_user_id  = $current_user_id;

        $room_token = sanitize_text_field( (string) $request->get_param( 'room_token' ) );
        if ( ! $this->user_can_access_room( $product_id, $room_key, $access_user_id, $room_token ) ) {
            return new WP_Error( 'forbidden', __( 'No tienes permisos para ver estos mensajes.', 'motorlan-api-vue' ), array( 'status' => 403 ) );
        }

        $since = $request->get_param( 'since_timestamp' );
        $since = $since ? $this->normalize_datetime_for_storage( $since ) : null;

        // Sin caché por transient: la clave dependía del since de cada poll (nunca se
        // reutilizaba), escribía 2 filas en wp_options por poll y la carga inicial podía
        // servir mensajes con hasta 10 s de retraso. La consulta usa índice y es barata.

        // Rate limiting (Missing Cache, protect DB)
        if ( class_exists( 'Motorlan_Rate_Limiter' ) ) {
            $user_identifier = Motorlan_Rate_Limiter::get_user_identifier();
            // 200 solicitudes cada 60 segundos
            // Por usuario/IP y sala: con IP compartida (NAT) varios invitados no se bloquean entre sí
            $rate_check = Motorlan_Rate_Limiter::check( 'product_messages_get', $user_identifier . '_' . md5( (string) $room_key ), 200, 60 );
            
            if ( is_wp_error( $rate_check ) ) {
                Motorlan_Rate_Limiter::send_rate_limit_headers( $rate_check );
                return $rate_check;
            }
        }

        // Marca de tiempo ANTES de consultar: si se toma después, un mensaje insertado
        // entre la consulta y la marca quedaba fuera del siguiente poll para siempre.
        $server_ts = gmdate( 'Y-m-d H:i:s' );

        $table_ready = $this->ensure_table_exists();
        $messages = array();
        if ( $table_ready ) {
            $messages = $this->get_messages_from_table( $product_id, $room_key, $since, $current_user_id );
        }

        $response_data = array(
            'data' => $messages,
            'meta' => array(
                'current_user_id' => (int) $current_user_id,
                'viewer_role'     => $this->compute_sender_role( $product_id, $current_user_id, $room_key ),
                'server_timestamp'=> $server_ts,
            ),
        );

        return new WP_REST_Response( $response_data, 200 );
    }

    public function create_message( WP_REST_Request $request ) {
        // Rate limiting para creación de mensajes (20 por minuto para evitar spam)
        if ( class_exists( 'Motorlan_Rate_Limiter' ) ) {
            $user_identifier = Motorlan_Rate_Limiter::get_user_identifier();
            $rate_check = Motorlan_Rate_Limiter::check( 'product_messages_post', $user_identifier, 20, 60 );
            
            if ( is_wp_error( $rate_check ) ) {
                Motorlan_Rate_Limiter::send_rate_limit_headers( $rate_check );
                return $rate_check;
            }
        }

        // Validate Content-Type
        if ( function_exists( 'motorlan_validate_json_content_type' ) ) {
            $valid_type = motorlan_validate_json_content_type( $request );
            if ( is_wp_error( $valid_type ) ) {
                return $valid_type;
            }
        }
        
        $product_id = absint( $request['id'] );
        $product    = $this->get_product( $product_id );
        if ( is_wp_error( $product ) ) return $product;

        $message    = sanitize_textarea_field( (string) $request->get_param( 'message' ) );
        $room_key   = sanitize_text_field( (string) $request->get_param( 'room_key' ) );
        // display_name es VARCHAR(191): un nombre más largo hacía fallar el INSERT (500)
        $viewer_nm  = mb_substr( sanitize_text_field( (string) $request->get_param( 'viewer_name' ) ), 0, 100 );
        $guest_em   = sanitize_email( (string) $request->get_param( 'guest_email' ) );
        $room_token = sanitize_text_field( (string) $request->get_param( 'room_token' ) );

        if ( '' === $message ) {
            return new WP_Error( 'no_message', __( 'Debes escribir un mensaje.', 'motorlan-api-vue' ), array( 'status' => 400 ) );
        }

        // Sin tope, un mensaje de decenas de KB se guardaba, se enviaba entero por email y
        // se pintaba en la bandeja (el chat de compra ya limitaba a 1000)
        if ( mb_strlen( $message ) > 2000 ) {
            return new WP_Error( 'message_too_long', __( 'El mensaje es demasiado largo.', 'motorlan-api-vue' ), array( 'status' => 400 ) );
        }

        // Clave de sala con formato y longitud válidos (room_key es VARCHAR(191): una clave
        // más larga fallaba o se truncaba y el mensaje acababa en otra sala)
        if ( ! $this->is_valid_room_key( $room_key ) ) {
            return new WP_Error( 'invalid_room', __( 'La sala no es válida.', 'motorlan-api-vue' ), array( 'status' => 400 ) );
        }

        // Honeypot del formulario de invitado
        if ( '' !== trim( (string) $request->get_param( 'website' ) ) ) {
            return new WP_Error( 'invalid_request', __( 'No se pudo enviar el mensaje.', 'motorlan-api-vue' ), array( 'status' => 400 ) );
        }

            $was_guest_request = ! is_user_logged_in();
            list( $current_user_id, $display_name, $avatar ) = $this->current_user_info();
            // SEGURIDAD: no se amplía el acceso por un guest_email sin verificar (permitía leer
            // y escribir en salas ajenas, o listar todas las salas con el email del vendedor/admin).
            // Los invitados usan salas con id no numérico (secreto); las numéricas exigen login.
            $access_user_id = $current_user_id;

            if ( ! $display_name && $viewer_nm ) $display_name = $viewer_nm;

            if ( ! $this->user_can_access_room( $product_id, $room_key, $access_user_id, $room_token ) ) {
                return new WP_Error( 'forbidden', __( 'No tienes permisos para enviar mensajes.', 'motorlan-api-vue' ), array( 'status' => 403 ) );
            }

            // Use $current_user_id (0 for guest) so unauthenticated requests never become 'seller' even if email matches author
            $sender_role = $this->compute_sender_role( $product_id, $current_user_id, $room_key );

            // Publicación en la papelera o borrador automático: solo su autor puede escribir
            if ( 'seller' !== $sender_role && in_array( $product->post_status, array( 'trash', 'auto-draft' ), true ) ) {
                return new WP_Error( 'not_found', __( 'Publicación no encontrada.', 'motorlan-api-vue' ), array( 'status' => 404 ) );
            }

            $table_ready = $this->ensure_table_exists();
            if ( ! $table_ready ) {
                return new WP_Error( 'server_error', __( 'El chat no está disponible.', 'motorlan-api-vue' ), array( 'status' => 500 ) );
            }

            // Sala nueva de invitado: límite por producto y por IP. Cada room_key nueva era una
            // conversación nueva y un script anónimo podía inundar de avisos al vendedor.
            $is_new_room = ! $this->room_has_messages( $room_key );
            if ( $was_guest_request && $is_new_room && class_exists( 'Motorlan_Rate_Limiter' ) ) {
                $room_limits = array(
                    Motorlan_Rate_Limiter::check( 'guest_room_create', 'p' . $product_id, 30, HOUR_IN_SECONDS ),
                    // 30/h por IP: con 10 un comprador que consultaba 11 motores, o una oficina con NAT, recibía 429
                    Motorlan_Rate_Limiter::check( 'guest_room_create_ip', Motorlan_Rate_Limiter::get_user_identifier(), 30, HOUR_IN_SECONDS ),
                );
                foreach ( $room_limits as $room_limit ) {
                    if ( is_wp_error( $room_limit ) ) {
                        Motorlan_Rate_Limiter::send_rate_limit_headers( $room_limit );
                        return $room_limit;
                    }
                }
            }

            // ¿Primer mensaje de este email en la sala? (la copia por email solo se manda una vez)
            $is_first_guest_message = false;
            if ( $was_guest_request && ! empty( $guest_em ) ) {
                global $wpdb;
                $msg_table = $this->get_table_name();
                $is_first_guest_message = 0 === (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$msg_table} WHERE room_key = %s AND guest_email = %s",
                    $room_key,
                    $guest_em
                ) );
            }

            $row = $this->persist_message( $product_id, $room_key, $message, $current_user_id, $sender_role, $display_name, $avatar, $guest_em );
            if ( is_wp_error( $row ) ) return $row;

            // Idioma del invitado: los emails de respuesta del vendedor salían en el idioma
            // de la petición del vendedor, no en el del invitado
            if ( $was_guest_request && 'seller' !== $sender_role && ! empty( $guest_em ) ) {
                set_transient( 'motorlan_guest_locale_' . md5( $room_key ), determine_locale(), 90 * DAY_IN_SECONDS );
            }

            // Mark as read immediately for the sender so it doesn't count as unread for themselves
            $this->set_last_read_now( $current_user_id, $product_id, $room_key );

            // Send copy to guest sender (if request came in unauthenticated with email)
            // Solo en el primer mensaje de la sala y como mucho 3 copias por email y hora:
            // antes se enviaba en cada mensaje a cualquier email indicado por un anónimo, y
            // rotando room_key se podía seguir usando como relay de spam.
            $copy_allowed = $is_first_guest_message;
            if ( $copy_allowed && class_exists( 'Motorlan_Rate_Limiter' ) ) {
                $copy_allowed = ! is_wp_error( Motorlan_Rate_Limiter::check( 'guest_message_copy', 'email_' . md5( strtolower( $guest_em ) ), 3, HOUR_IN_SECONDS ) );
            }
            if ( $copy_allowed && $sender_role !== 'seller' ) {
                $product_title = function_exists( 'motorlan_format_motor_name' ) ? motorlan_format_motor_name( $product_id ) : get_the_title( $product_id );
                $post_for_copy = get_post( $product_id );
                $seller_name   = '';
                if ( $post_for_copy ) {
                    $seller = get_userdata( $post_for_copy->post_author );
                    if ( $seller ) {
                        $fn   = trim( (string) get_user_meta( $seller->ID, 'first_name', true ) );
                        $ln   = trim( (string) get_user_meta( $seller->ID, 'last_name', true ) );
                        $full = trim( $fn . ' ' . $ln );
                        $seller_name = $full !== '' ? $full : $seller->display_name;
                    }
                }
                do_action( 'motorlan_send_guest_message_copy', $guest_em, $message, $product_title, $seller_name, $product_id, $room_key );
            }

            // Notification Logic
            if ( $sender_role !== 'seller' ) {
                $post = get_post( $product_id );
                $author_id = (int) $post->post_author;

                if ( $author_id ) {
                    do_action( 'motorlan_new_chat_message', $author_id, $row, $display_name, $product_id );
                }
            } else {
                // Seller replied. Notify the buyer/viewer.
                list( $key_pid, $viewer_id ) = $this->parse_room_key( $room_key );
                
                $buyer_id = is_numeric( $viewer_id ) ? (int) $viewer_id : 0;
                $buyer_email = '';

                if ( ! $buyer_id ) {
                    // Sala de invitado ya reclamada por un usuario (claim con email verificado):
                    // avisarle en la web y no solo por email con el enlace de invitado
                    $buyer_id = $this->get_room_claimed_user_id( $room_key );
                }

                if ( ! $buyer_id ) {
                    // It's a guest. Find their email from the FIRST message of the room if not already in $row
                    global $wpdb;
                    $table = $this->get_table_name();
                    $buyer_email = $wpdb->get_var( $wpdb->prepare(
                        "SELECT guest_email FROM {$table} WHERE room_key = %s AND guest_email IS NOT NULL AND guest_email != '' ORDER BY created_at ASC LIMIT 1",
                        $room_key
                    ) );
                }
                
                do_action( 'motorlan_seller_reply', $buyer_id, $buyer_email, $row, $display_name, $product_id );
            }

            $response_message                    = $this->format_message( $row, $current_user_id );
            $response_message['is_current_user'] = true;

            $meta = array(
                'current_user_id' => $current_user_id ?: null,
                'viewer_role'     => $sender_role,
                'server_timestamp'=> gmdate( 'Y-m-d H:i:s' ),
            );
            // El invitado guarda este token para volver a leer/escribir en su sala
            if ( 'seller' !== $sender_role && self::is_guest_room( $room_key ) ) {
                $meta['room_token'] = self::room_token( $room_key );
            }

            return new WP_REST_Response( array(
                'data' => $response_message,
                'meta' => $meta,
            ), 201 );
        }

        /**
         * Idioma con el que escribió el invitado de una sala (vacío si no se conoce).
         *
         * @param string $room_key Clave de la sala.
         * @return string
         */
        public static function get_guest_locale( $room_key ) {
            $locale = get_transient( 'motorlan_guest_locale_' . md5( (string) $room_key ) );
            return is_string( $locale ) ? $locale : '';
        }

        protected function get_rooms_for_products( $product_ids ) {
            global $wpdb;
            if ( empty( $product_ids ) ) return array();
            $table = $this->get_table_name();
            $ids_placeholders = implode( ',', array_fill( 0, count( $product_ids ), '%d' ) );
            $sql = $wpdb->prepare(
                "SELECT product_id, room_key, MAX(created_at) AS last_at
                 FROM {$table}
                 WHERE product_id IN ($ids_placeholders)
                 GROUP BY product_id, room_key
                 ORDER BY last_at DESC",
                $product_ids
            );
            $rows = $wpdb->get_results( $sql );
            if ( ! $rows ) return array();

            $result = array();
            foreach ( $rows as $row ) {
                $result[] = array(
                    'product_id' => (int) $row->product_id,
                    'room_key'   => (string) $row->room_key,
                    'last_at'    => $this->format_datetime_for_response( $row->last_at ),
                );
            }

            return $result;
        }

        public function get_rooms_by_product( WP_REST_Request $request ) {
            $product_id = absint( $request['id'] );
            $room_key   = $request->get_param( 'room_key' );
            $guest_em   = sanitize_email( (string) $request->get_param( 'guest_email' ) );

            if ( is_wp_error( $this->get_product( $product_id ) ) ) {
                return new WP_Error( 'invalid_product', __( 'El producto no existe.', 'motorlan-api-vue' ), array( 'status' => 404 ) );
            }

            list( $current_user_id ) = $this->current_user_info();
            // SEGURIDAD: sin promoción por guest_email (con el email del vendedor o de un
            // admin se listaban todas las salas sin iniciar sesión).
            if ( ! $current_user_id ) {
                return new WP_Error( 'rest_not_logged_in', __( 'Debes iniciar sesión.', 'motorlan-api-vue' ), array( 'status' => 401 ) );
            }

            $post = get_post( $product_id );
            if ( ! $post || 'publicacion' !== $post->post_type ) {
                return new WP_Error( 'not_found', __( 'Publicación no encontrada.', 'motorlan-api-vue' ), array( 'status' => 404 ) );
            }

            if ( (int) $post->post_author !== (int) $current_user_id && ! user_can( $current_user_id, 'manage_options' ) ) {
                return new WP_Error( 'forbidden', __( 'No tienes permisos para ver estos chats.', 'motorlan-api-vue' ), array( 'status' => 403 ) );
            }

            $table_ready = $this->ensure_table_exists();
            $rooms = $table_ready ? $this->get_rooms_for_products( array( $product_id ) ) : array();

            return new WP_REST_Response( array( 'data' => $rooms ), 200 );
        }

        public function get_seller_inquiries( WP_REST_Request $request ) {
            $current_user = get_current_user_id();
            if ( ! $current_user )
                return new WP_Error( 'unauthenticated', __( 'Debes iniciar sesión.', 'motorlan-api-vue' ), array( 'status' => 401 ) );

            // Get all product IDs authored by the current user
            $posts = get_posts( array(
                'post_type'      => 'publicacion',
                // Sin esto solo 'publish': los chats de publicaciones pausadas, vendidas,
                // pendientes o en borrador desaparecían de la bandeja aunque el comprador
                // siguiera escribiendo (y el enlace ?room_key= no abría nada)
                'post_status'    => array( 'publish', 'paused', 'sold', 'pending', 'draft', 'private', 'future' ),
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'author'         => $current_user,
                'no_found_rows'  => true,
            ) );

            if ( empty( $posts ) )
                return new WP_REST_Response( array( 'data' => array() ), 200 );

            $table_ready = $this->ensure_table_exists();
            $rooms = $table_ready ? $this->get_rooms_for_products( $posts ) : array();

            if ( empty( $rooms ) ) {
                return new WP_REST_Response( array( 'data' => array() ), 200 );
            }

            // --- EAGER LOADING START ---
            $product_ids = array_unique( array_column( $rooms, 'product_id' ) );
            $user_ids_to_load = array();

            // 1. Preload Post Meta (ACF fields)
            update_meta_cache( 'post', $product_ids );

            // 2. Identify Users to load
            foreach ( $rooms as $r ) {
                list( $key_pid, $viewer_id ) = $this->parse_room_key( $r['room_key'] );
                if ( is_numeric( $viewer_id ) ) {
                    $user_ids_to_load[] = (int) $viewer_id;
                }
            }
            if ( ! empty( $user_ids_to_load ) ) {
                $user_ids_to_load = array_unique( $user_ids_to_load );
                cache_users( $user_ids_to_load ); // WP function to prime user cache
            }

            // 3. Batch Unread Counts
            $unread_counts = $this->get_batch_unread_counts( $rooms, $current_user );
            // --- EAGER LOADING END ---

            // Attach minimal product info
            $items = array();
            foreach ( $rooms as $r ) {
                $pid = (int) $r['product_id'];
                
                // Construct full title with ACF fields (Now cached)
                $base_title = get_the_title( $pid );
                // Use get_post_meta directly or ACF get_field (which uses meta cache)
                $ref   = get_post_meta( $pid, 'tipo_o_referencia', true );
                $power = get_post_meta( $pid, 'potencia', true );
                $speed = get_post_meta( $pid, 'velocidad', true );
                $image = get_post_meta( $pid, 'motor_image', true ); 
                // Note: get_field might trigger extra processing, raw meta is faster if format not needed.
                // If ACF formatting is crucial, keep get_field but it's cached now.

                $full_title_parts = array( $base_title );
                if ( $ref ) $full_title_parts[] = $ref;
                if ( $power ) $full_title_parts[] = $power;
                if ( $speed ) $full_title_parts[] = $speed;
                
                $full_title = implode( ' ', $full_title_parts );

                // Get Viewer Info
                $viewer_name = '';
                $viewer_avatar = '';
                
                list( $key_pid, $viewer_id ) = $this->parse_room_key( $r['room_key'] );
                
                // Try to get from User Cache
                if ( is_numeric( $viewer_id ) ) {
                    $user_data = get_userdata( $viewer_id ); // Uses cache
                    if ( $user_data ) {
                        $viewer_name = $user_data->display_name;
                        // Sin Gravatar del comprador (hash MD5 de su email): iniciales en el front
                    }
                }
                
                // Fallback: Get from messages table (Optimization: Only if name empty)
                if ( empty( $viewer_name ) ) {
                     // This is still N+1 but rare (only for guests/deleted users). 
                     // Could be optimized further but low priority if most are registered.
                     global $wpdb;
                     $table = $this->get_table_name();
                     $sql = $wpdb->prepare(
                         "SELECT display_name, avatar FROM {$table} 
                          WHERE product_id = %d AND room_key = %s AND sender_role = 'viewer' 
                          ORDER BY created_at DESC LIMIT 1",
                         $pid, $r['room_key']
                     );
                     $viewer_row = $wpdb->get_row( $sql );
                     if ( $viewer_row ) {
                         $viewer_name = $viewer_row->display_name;
                     }
                }
                
                if ( empty( $viewer_name ) ) {
                    $viewer_name = __( 'Usuario', 'motorlan-api-vue' );
                }

                // Get unread from batch array
                $unread = isset( $unread_counts[ $r['room_key'] ] ) ? $unread_counts[ $r['room_key'] ] : 0;

                $item = array(
                    'product_id'    => $pid,
                    'product_title' => $full_title,
                    'product_slug'  => get_post_field( 'post_name', $pid ),
                    'product_link'  => function_exists('motorlan_get_store_url') ? motorlan_get_store_url($pid) : get_permalink($pid),
                    'product_image' => motorlan_format_image_for_frontend( $image ),
                    'room_key'      => $r['room_key'],
                    'last_at'       => $r['last_at'],
                    'user_name'     => $viewer_name,
                    'user_avatar'   => $viewer_avatar,
                    'unread'        => $unread
                );
                $items[] = $item;
            }

            return new WP_REST_Response( array( 'data' => $items ), 200 );
        }

        /**
         * Get unread counts for multiple rooms in one query.
         */
        protected function get_batch_unread_counts( $rooms, $user_id ) {
            global $wpdb;
            if ( empty( $rooms ) || ! $user_id ) return array();

            $messages_table = $this->get_table_name();
            $reads_table    = $this->get_reads_table_name();

            // 1. Get last read timestamps for all rooms
            // Composite key match is tricky in SQL IN clause generally, but useful here if constructed.
            // Simplified: Get all reads for this user and these products.
            $product_ids = array_unique( array_column( $rooms, 'product_id' ) );
            $pids_in = implode( ',', array_map( 'intval', $product_ids ) );
            
            $reads_sql = $wpdb->prepare( 
                "SELECT room_key, last_read_at FROM {$reads_table} WHERE user_id = %d AND product_id IN ($pids_in)", 
                $user_id 
            );
            $reads = $wpdb->get_results( $reads_sql, OBJECT_K ); // Key by room_key (first column unique constraint allows this?) 
            // Warning: room_key implies product, but theoretically unique_user_room idx handles it. 
            // Actually wait, unique index is (user_id, product_id, room_key). 
            // If room_key is globally unique (it contains product_id), we can key by it.
            // YES: room_key format is pub-{id}-viewer-{id}, so it is unique per product.

            $counts = array();
            
            // Build a complex query or iterate?
            // "SELECT room_key, COUNT(*) as cnt FROM msgs WHERE (conditions) GROUP BY room_key"
            // The condition "created_at > last_read" is dynamic per room.
            // SQL Case: SUM(CASE WHEN created_at > ... THEN 1 ELSE 0 END)
            // But we need to join reads.
            
            // OPTIMIZED QUERY:
            // JOIN messages and reads, filter by user.
            // But reads might not exist (never read).
            
            // Let's create a map of room_key -> last_read dates
            $last_reads_map = array();
            foreach( $rooms as $r ) $last_reads_map[$r['room_key']] = '0000-00-00 00:00:00';
            if ( $reads ) {
                foreach( $reads as $rk => $obj ) $last_reads_map[$rk] = $obj->last_read_at;
            }

            // We need to query messages. To avoid scanning WHOLE table, we filter by Product IDs.
            // And grouping by room_key.
            // PROBLEM: We can't apply different WHERE created_at > X for each group easily in one simple query without a JOIN.
            
            // Approach: Get ALL counts per room for these products, 
            // AND Get counts of messages older than read? No.
            
            // Approach: LEFT JOIN reads ON ... AND created_at > reads.last_read_at
            
            // Con last_read_id se compara por id (exacto); las filas antiguas sin él, por fecha
            $read_condition = $this->reads_have_last_read_id()
                ? "( ( r.last_read_id IS NOT NULL AND r.last_read_id > 0 AND m.id > r.last_read_id )
                     OR ( ( r.last_read_id IS NULL OR r.last_read_id = 0 ) AND m.created_at > COALESCE(r.last_read_at, '0000-00-00 00:00:00') ) )"
                : "m.created_at > COALESCE(r.last_read_at, '0000-00-00 00:00:00')";

            $sql = "SELECT m.room_key, COUNT(m.id) as unread_count
                    FROM {$messages_table} m
                    LEFT JOIN {$reads_table} r ON m.product_id = r.product_id AND m.room_key = r.room_key AND r.user_id = %d
                    WHERE m.product_id IN ($pids_in)
                    AND {$read_condition}
                    AND m.user_id != %d
                    GROUP BY m.room_key";
            
            $results = $wpdb->get_results( $wpdb->prepare( $sql, $user_id, $user_id ) );
            
            foreach ( $results as $row ) {
                $counts[ $row->room_key ] = (int) $row->unread_count;
            }
            
            return $counts;
        }

        // Mark messages of a room as read now (seller only or viewer if logged)
        public function mark_room_read( WP_REST_Request $request ) {
            // Validate Content-Type
            if ( function_exists( 'motorlan_validate_json_content_type' ) ) {
                $valid_type = motorlan_validate_json_content_type( $request );
                if ( is_wp_error( $valid_type ) ) {
                    return $valid_type;
                }
            }

            $current_user = get_current_user_id();
            if ( ! $current_user )
                return new WP_Error( 'unauthenticated', __( 'Debes iniciar sesión.', 'motorlan-api-vue' ), array( 'status' => 401 ) );

            $product_id = absint( $request['id'] );
            $room_key   = sanitize_text_field( (string) $request->get_param( 'room_key' ) );
            if ( empty( $room_key ) )
                return new WP_Error( 'no_room', __( 'room_key es requerido.', 'motorlan-api-vue' ), array( 'status' => 400 ) );

            if ( ! $this->user_can_access_room( $product_id, $room_key, $current_user ) )
                return new WP_Error( 'forbidden', __( 'No tienes permisos para esta sala.', 'motorlan-api-vue' ), array( 'status' => 403 ) );

            $this->set_last_read_now( $current_user, $product_id, $room_key );
            return new WP_REST_Response( array( 'success' => true ), 200 );
        }

        public function get_buyer_inquiries( WP_REST_Request $request ) {
            $current_user = get_current_user_id();
            if ( ! $current_user )
                return new WP_Error( 'unauthenticated', __( 'Debes iniciar sesión.', 'motorlan-api-vue' ), array( 'status' => 401 ) );

            global $wpdb;
            $table = $this->get_table_name();
            if ( ! $this->ensure_table_exists() )
                return new WP_REST_Response( array( 'data' => array() ), 200 );

            // Get all rooms where the current user is recorded as a sender
            $sql = $wpdb->prepare(
                "SELECT product_id, room_key, MAX(created_at) AS last_at
                 FROM {$table}
                 WHERE user_id = %d AND sender_role = 'viewer'
                 GROUP BY product_id, room_key
                 ORDER BY last_at DESC",
                $current_user
            );
            $rooms = $wpdb->get_results( $sql );

            if ( empty( $rooms ) ) {
                return new WP_REST_Response( array( 'data' => array() ), 200 );
            }

            // --- EAGER LOADING START ---
            // Recast object results to array for consistency with our helper if needed, or query returns objects.
            // Our helper expects array with ['product_id'], ['room_key']. Logic adapts below.
            
            $product_ids = array();
            $rooms_array = array(); // Normalizing to array for batch helper
            foreach( $rooms as $r ) {
                $product_ids[] = (int) $r->product_id;
                $rooms_array[] = array( 'product_id' => $r->product_id, 'room_key' => $r->room_key );
            }
            $product_ids = array_unique( $product_ids );
            
            // 1. Preload Post Meta
            update_meta_cache( 'post', $product_ids );
            
            // 2. Preload Authors (Sellers)
            $user_ids_to_load = array();
            foreach( $product_ids as $pid ) {
                $post_author = get_post_field( 'post_author', $pid ); // Cached by WP core usually with post, but...
                if ( $post_author ) $user_ids_to_load[] = (int) $post_author;
            }
            if ( ! empty( $user_ids_to_load ) ) {
                $user_ids_to_load = array_unique( $user_ids_to_load );
                cache_users( $user_ids_to_load );
            }

            // 3. Batch Unread Counts
            $unread_counts = $this->get_batch_unread_counts( $rooms_array, $current_user );
            // --- EAGER LOADING END ---

            $items = array();
            foreach ( $rooms as $r ) {
                $pid = (int) $r->product_id;
                
                $base_title = get_the_title( $pid );
                $ref   = get_post_meta( $pid, 'tipo_o_referencia', true );
                $power = get_post_meta( $pid, 'potencia', true );
                $speed = get_post_meta( $pid, 'velocidad', true );
                $image = get_post_meta( $pid, 'motor_image', true );
                
                $full_title_parts = array( $base_title );
                if ( $ref ) $full_title_parts[] = $ref;
                if ( $power ) $full_title_parts[] = $power;
                if ( $speed ) $full_title_parts[] = $speed;
                
                $full_title = implode( ' ', $full_title_parts );

                // Seller info
                // get_post_field uses cache if post is loaded
                $seller_id = (int) get_post_field( 'post_author', $pid );
                $seller_name = __( 'Vendedor', 'motorlan-api-vue' );
                $seller_avatar = '';

                if ( $seller_id ) {
                    $user = get_userdata( $seller_id ); // Uses cache
                    if ( $user ) {
                        $seller_name = $user->display_name;
                        $seller_avatar = get_avatar_url( $seller_id );
                    }
                }

                $unread = isset( $unread_counts[ $r->room_key ] ) ? $unread_counts[ $r->room_key ] : 0;

                $item = array(
                    'product_id'    => $pid,
                    'product_title' => $full_title,
                    'product_slug'  => get_post_field( 'post_name', $pid ),
                    'product_link'  => function_exists('motorlan_get_store_url') ? motorlan_get_store_url($pid) : get_permalink($pid),
                    'product_image' => motorlan_format_image_for_frontend( $image ),
                    'room_key'      => $r->room_key,
                    'last_at'       => $this->format_datetime_for_response( $r->last_at ),
                    'user_name'     => $seller_name, // Map to user_name for frontend consistency
                    'user_avatar'   => $seller_avatar,
                    'seller_id'     => $seller_id,
                    'unread'        => $unread
                );
                $items[] = $item;
            }

            return new WP_REST_Response( array( 'data' => $items ), 200 );
        }
    }
}
