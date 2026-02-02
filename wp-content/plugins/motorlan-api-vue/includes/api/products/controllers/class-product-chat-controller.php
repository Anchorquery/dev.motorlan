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
                        ),
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
            if ( $this->table_exists() ) return true;
            if ( function_exists( 'motorlan_create_product_messages_table' ) ) {
                motorlan_create_product_messages_table();
                return $this->table_exists();
            }
            return false;
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

        protected function user_can_access_room( $product_id, $room_key, $user_id ) {
            list( $key_pid, $viewer_id ) = $this->parse_room_key( $room_key );
            if ( empty( $room_key ) ) return ( $this->is_user_product_author( $product_id, $user_id ) );
            if ( (int) $key_pid !== (int) $product_id ) return false;
            if ( $this->is_user_product_author( $product_id, $user_id ) ) return true;
            if ( $user_id && (string) $user_id === (string) $viewer_id ) return true;
            // Guest: allow by possession of the room_key
            if ( ! $user_id && ! empty( $viewer_id ) ) return true;
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
                $where .= ' AND created_at > %s';
                $params[] = $this->normalize_datetime_for_storage( $since_timestamp );
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
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND product_id = %d AND room_key = %s",
                $user_id, $product_id, $room_key
            ) );
            if ( $exists ) {
                $wpdb->update( $table, array( 'last_read_at' => $now, 'updated_at' => $now ), array( 'user_id' => $user_id, 'product_id' => $product_id, 'room_key' => $room_key ), array( '%s', '%s' ), array( '%d', '%d', '%s' ) );
            } else {
                $wpdb->insert( $table, array( 'user_id' => $user_id, 'product_id' => $product_id, 'room_key' => $room_key, 'last_read_at' => $now, 'updated_at' => $now ), array( '%d','%d','%s','%s','%s' ) );
            }
            return true;
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
                $sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$messages_table} WHERE product_id = %d AND room_key = %s", $product_id, $room_key );
                return (int) $wpdb->get_var( $sql );
            }
            $sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$messages_table} WHERE product_id = %d AND room_key = %s AND created_at > %s", $product_id, $room_key, $last_read );
            return (int) $wpdb->get_var( $sql );
        }

        protected function persist_message( $product_id, $room_key, $message, $user_id, $sender_role, $display_name, $avatar ) {
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
                'created_at'  => $created,
            ), array( '%s','%d','%s','%d','%s','%s','%s','%s','%s' ) );

            if ( false === $inserted ) {
                return new WP_Error( 'db_error', __( 'No se pudo guardar el mensaje.', 'motorlan-api-vue' ), array( 'status' => 500 ) );
            }

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
            if ( ! $avatar && $user_id ) $avatar = get_avatar_url( $user_id );

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

            list( $current_user_id ) = $this->current_user_info();
            if ( ! $this->user_can_access_room( $product_id, $room_key, $current_user_id ) ) {
                return new WP_Error( 'forbidden', __( 'No tienes permisos para ver estos mensajes.', 'motorlan-api-vue' ), array( 'status' => 403 ) );
            }

            $since = $request->get_param( 'since_timestamp' );
            $since = $since ? $this->normalize_datetime_for_storage( $since ) : null;

            $table_ready = $this->ensure_table_exists();
            $messages = array();
            if ( $table_ready ) {
                $messages = $this->get_messages_from_table( $product_id, $room_key, $since, $current_user_id );
            }

            $server_ts = gmdate( 'Y-m-d H:i:s' );

            return new WP_REST_Response( array(
                'data' => $messages,
                'meta' => array(
                    'current_user_id' => $current_user_id ?: null,
                    'viewer_role'     => $this->compute_sender_role( $product_id, $current_user_id, $room_key ),
                    'server_timestamp'=> $server_ts,
                ),
            ), 200 );
        }

        public function create_message( WP_REST_Request $request ) {
            $product_id = absint( $request['id'] );
            $product    = $this->get_product( $product_id );
            if ( is_wp_error( $product ) ) return $product;

            $message   = sanitize_textarea_field( (string) $request->get_param( 'message' ) );
            $room_key  = sanitize_text_field( (string) $request->get_param( 'room_key' ) );
            $viewer_nm = sanitize_text_field( (string) $request->get_param( 'viewer_name' ) );

            if ( '' === $message ) {
                return new WP_Error( 'no_message', __( 'Debes escribir un mensaje.', 'motorlan-api-vue' ), array( 'status' => 400 ) );
            }

            list( $current_user_id, $display_name, $avatar ) = $this->current_user_info();
            if ( ! $display_name && $viewer_nm ) $display_name = $viewer_nm;

            if ( ! $this->user_can_access_room( $product_id, $room_key, $current_user_id ) ) {
                return new WP_Error( 'forbidden', __( 'No tienes permisos para enviar mensajes.', 'motorlan-api-vue' ), array( 'status' => 403 ) );
            }

            $sender_role = $this->compute_sender_role( $product_id, $current_user_id, $room_key );

            $table_ready = $this->ensure_table_exists();
            if ( ! $table_ready ) {
                return new WP_Error( 'server_error', __( 'El chat no está disponible.', 'motorlan-api-vue' ), array( 'status' => 500 ) );
            }

            $row = $this->persist_message( $product_id, $room_key, $message, $current_user_id, $sender_role, $display_name, $avatar );
            if ( is_wp_error( $row ) ) return $row;

            // Notification Logic
            if ( $sender_role !== 'seller' ) {
                $post = get_post( $product_id );
                $author_id = (int) $post->post_author;
                
                if ( $author_id && class_exists( 'Motorlan_Notification_Manager' ) ) {
                    $notif_manager = new Motorlan_Notification_Manager();
                    $notif_title = __( 'Nuevo mensaje en tu publicación', 'motorlan-api-vue' );
                    $notif_data = [
                        'url' => '/dashboard/inquiries?room_key=' . $room_key,
                        'room_key' => $room_key,
                        'product_id' => $product_id,
                        'product_title' => get_the_title( $product_id ),
                        'sender_name' => $display_name
                    ];
                    // Also send email
                    $notif_manager->create_notification( $author_id, 'new_message', $notif_title, $message, $notif_data, ['web', 'email'] );
                }
            }

            $response_message                    = $this->format_message( $row, $current_user_id );
            $response_message['is_current_user'] = true;

            return new WP_REST_Response( array(
                'data' => $response_message,
                'meta' => array(
                    'current_user_id' => $current_user_id ?: null,
                    'viewer_role'     => $sender_role,
                    'server_timestamp'=> gmdate( 'Y-m-d H:i:s' ),
                ),
            ), 201 );
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
            $current_user = get_current_user_id();
            $product_id = absint( $request['id'] );

            $post = get_post( $product_id );
            if ( ! $post || 'publicacion' !== $post->post_type ) {
                return new WP_Error( 'not_found', __( 'Publicación no encontrada.', 'motorlan-api-vue' ), array( 'status' => 404 ) );
            }

            if ( (int) $post->post_author !== (int) $current_user && ! user_can( $current_user, 'manage_options' ) ) {
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
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'author'         => $current_user,
                'no_found_rows'  => true,
            ) );

            if ( empty( $posts ) )
                return new WP_REST_Response( array( 'data' => array() ), 200 );

            $table_ready = $this->ensure_table_exists();
            $rooms = $table_ready ? $this->get_rooms_for_products( $posts ) : array();

            // Attach minimal product info
            $items = array();
            foreach ( $rooms as $r ) {
                $pid = (int) $r['product_id'];
                
                // Construct full title with ACF fields
                $base_title = get_the_title( $pid );
                $ref   = get_field( 'tipo_o_referencia', $pid );
                $power = get_field( 'potencia', $pid );
                $speed = get_field( 'velocidad', $pid );
                
                $full_title_parts = array( $base_title );
                if ( $ref ) $full_title_parts[] = $ref;
                if ( $power ) $full_title_parts[] = $power; // Assuming it includes unit or is just number
                if ( $speed ) $full_title_parts[] = $speed;
                
                $full_title = implode( ' ', $full_title_parts );

                // Get Image
                $image = get_field( 'motor_image', $pid );

                // Get Viewer Info
                $viewer_name = '';
                $viewer_avatar = '';
                
                list( $key_pid, $viewer_id ) = $this->parse_room_key( $r['room_key'] );
                
                // Try to get from User ID first
                if ( is_numeric( $viewer_id ) ) {
                    $user = get_user_by( 'id', $viewer_id );
                    if ( $user ) {
                        $viewer_name = $user->display_name;
                        $viewer_avatar = get_avatar_url( $user->ID );
                    }
                }
                
                // Fallback: Get from messages table (useful for guests or if user deleted/not found)
                if ( empty( $viewer_name ) ) {
                     global $wpdb;
                     $table = $this->get_table_name();
                     // Get the most recent message from a viewer in this room
                     $sql = $wpdb->prepare(
                         "SELECT display_name, avatar FROM {$table} 
                          WHERE product_id = %d AND room_key = %s AND sender_role = 'viewer' 
                          ORDER BY created_at DESC LIMIT 1",
                         $pid, $r['room_key']
                     );
                     $viewer_row = $wpdb->get_row( $sql );
                     if ( $viewer_row ) {
                         $viewer_name = $viewer_row->display_name;
                         $viewer_avatar = $viewer_row->avatar;
                     }
                }
                
                // Final fallback
                if ( empty( $viewer_name ) ) {
                    $viewer_name = __( 'Usuario', 'motorlan-api-vue' );
                }

                $item = array(
                    'product_id'    => $pid,
                    'product_title' => $full_title,
                    'product_slug'  => get_post_field( 'post_name', $pid ),
                    'product_image' => $image,
                    'room_key'      => $r['room_key'],
                    'last_at'       => $r['last_at'],
                    'user_name'     => $viewer_name,
                    'user_avatar'   => $viewer_avatar,
                );
                $item['unread'] = $this->get_unread_count( $pid, $r['room_key'], $current_user );
                $items[] = $item;
            }

            return new WP_REST_Response( array( 'data' => $items ), 200 );
        }

        // Mark messages of a room as read now (seller only or viewer if logged)
        public function mark_room_read( WP_REST_Request $request ) {
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
    }
}
