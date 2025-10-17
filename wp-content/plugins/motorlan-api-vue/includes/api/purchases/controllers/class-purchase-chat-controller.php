<?php
/**
 * Purchase chat REST controller.
 *
 * @package motorlan-api-vue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Motorlan_Purchase_Chat_Controller' ) ) {
	/**
	 * Handles purchase conversation endpoints.
	 */
	class Motorlan_Purchase_Chat_Controller extends WP_REST_Controller {

		/**
		 * REST namespace.
		 *
		 * @var string
		 */
		protected $namespace = 'motorlan/v1';

		/**
		 * Register the routes for purchase messaging.
		 *
		 * @return void
		 */
		public function register_routes() {
			register_rest_route(
				$this->namespace,
				'/purchases/(?P<uuid>[\\w-]+)/messages',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_messages' ),
						'permission_callback' => 'motorlan_is_user_authenticated',
						'args'                => array(
							'since_timestamp' => array(
								'type'              => 'string',
								'description'       => __( 'Timestamp for incremental polling in UTC.', 'motorlan-api-vue' ),
								'required'          => false,
								'validate_callback' => array( $this, 'validate_timestamp' ),
							),
						),
					),
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, 'create_message' ),
						'permission_callback' => 'motorlan_is_user_authenticated',
						'args'                => array(
							'message' => array(
								'type'              => 'string',
								'required'          => true,
								'sanitize_callback' => 'sanitize_textarea_field',
								'description'       => __( 'Message body for the conversation.', 'motorlan-api-vue' ),
							),
						),
					),
				)
			);
		}

		/**
		 * Retrieve the database table name for chat messages.
		 *
		 * @return string
		 */
		protected function get_table_name() {
			global $wpdb;
			return $wpdb->prefix . 'motorlan_purchase_messages';
		}

		/**
		 * Determine whether the chat messages table exists.
		 *
		 * @return bool
		 */
		protected function table_exists() {
			global $wpdb;

			$table = $this->get_table_name();
			$like  = str_replace(
				array( '_', '%' ),
				array( '\\_', '\\%' ),
				$table
			);

			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );

			return ( $found === $table );
		}

		/**
		 * Ensure the chat messages table is available.
		 *
		 * @return bool
		 */
		protected function ensure_table_exists() {
			if ( $this->table_exists() ) {
				return true;
			}

			if ( function_exists( 'motorlan_create_purchase_messages_table' ) ) {
				motorlan_create_purchase_messages_table();
				return $this->table_exists();
			}

			return false;
		}

		/**
		 * Normalise any datetime string to UTC storage format.
		 *
		 * @param string $value Datetime string.
		 *
		 * @return string
		 */
		protected function normalize_datetime_for_storage( $value ) {
			if ( empty( $value ) ) {
				return gmdate( 'Y-m-d H:i:s' );
			}

			$clean = trim( (string) $value );

			if ( false !== strpos( $clean, 'T' ) ) {
				$clean = str_replace( 'T', ' ', $clean );
			}

			$timestamp = strtotime( $clean );

			if ( false === $timestamp ) {
				$timestamp = time();
			}

			return gmdate( 'Y-m-d H:i:s', $timestamp );
		}

		/**
		 * Normalise stored datetime for API responses.
		 *
		 * @param string $value Stored datetime.
		 *
		 * @return string
		 */
		protected function format_datetime_for_response( $value ) {
			if ( empty( $value ) ) {
				return gmdate( 'Y-m-d H:i:s' );
			}

			$timestamp = strtotime( $value );

			if ( false === $timestamp ) {
				return gmdate( 'Y-m-d H:i:s' );
			}

			return gmdate( 'Y-m-d H:i:s', $timestamp );
		}

		/**
		 * Migrate legacy meta-based messages into the dedicated table.
		 *
		 * @param int    $purchase_id Purchase post ID.
		 * @param string $uuid        Purchase UUID.
		 *
		 * @return void
		 */
		protected function maybe_migrate_legacy_messages( $purchase_id, $uuid ) {
			global $wpdb;

			$flag = get_post_meta( $purchase_id, '_motorlan_messages_migrated', true );
			if ( $flag ) {
				return;
			}

			$legacy_messages = get_post_meta( $purchase_id, 'purchase_messages', true );
			if ( ! is_array( $legacy_messages ) || empty( $legacy_messages ) ) {
				update_post_meta( $purchase_id, '_motorlan_messages_migrated', 1 );
				return;
			}

			$table = $this->get_table_name();

			foreach ( $legacy_messages as $index => $message ) {
				$message_key = isset( $message['id'] ) ? (string) $message['id'] : uniqid( 'msg_', true );
				$created_at  = $this->normalize_datetime_for_storage( isset( $message['created_at'] ) ? $message['created_at'] : '' );

				$data = array(
					'message_key'   => $message_key,
					'purchase_id'   => $purchase_id,
					'purchase_uuid' => $uuid,
					'user_id'       => isset( $message['user_id'] ) ? (int) $message['user_id'] : 0,
					'sender_role'   => isset( $message['sender_role'] ) ? (string) $message['sender_role'] : 'buyer',
					'message'       => isset( $message['message'] ) ? (string) $message['message'] : '',
					'display_name'  => isset( $message['display_name'] ) ? (string) $message['display_name'] : '',
					'avatar'        => isset( $message['avatar'] ) ? (string) $message['avatar'] : '',
					'created_at'    => $created_at,
				);

				$format = array( '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' );

				$wpdb->replace( $table, $data, $format );
			}

			update_post_meta( $purchase_id, '_motorlan_messages_migrated', 1 );
		}

		/**
		 * Retrieve messages from the dedicated table.
		 *
		 * @param string   $uuid             Purchase UUID.
		 * @param int|null $since_timestamp  Optional timestamp filter.
		 * @param int      $current_user_id  Current user ID.
		 *
		 * @return array
		 */
		protected function get_messages_from_table( $uuid, $since_timestamp, $current_user_id ) {
			global $wpdb;

			$table = $this->get_table_name();

			$sql    = "SELECT message_key, user_id, sender_role, message, display_name, avatar, created_at FROM {$table} WHERE purchase_uuid = %s";
			$params = array( $uuid );

			if ( $since_timestamp ) {
				$sql     .= ' AND created_at > %s';
				$params[] = gmdate( 'Y-m-d H:i:s', $since_timestamp );
			}

			$sql .= ' ORDER BY created_at ASC, id ASC';

			$prepared = call_user_func_array(
				array( $wpdb, 'prepare' ),
				array_merge( array( $sql ), $params )
			);

			$rows = $wpdb->get_results( $prepared, ARRAY_A );

			if ( empty( $rows ) ) {
				return array();
			}

			$messages = array();

			foreach ( $rows as $row ) {
				$row['id']         = isset( $row['message_key'] ) ? (string) $row['message_key'] : ( isset( $row['id'] ) ? (string) $row['id'] : uniqid( 'msg_', true ) );
				$row['created_at'] = $this->format_datetime_for_response( isset( $row['created_at'] ) ? $row['created_at'] : '' );
				$messages[]        = $this->format_message( $row, $current_user_id );
			}

			return $messages;
		}

		/**
		 * Fallback to legacy meta messages.
		 *
		 * @param int      $purchase_id      Purchase post ID.
		 * @param int|null $since_timestamp  Optional timestamp filter.
		 * @param int      $current_user_id  Current user ID.
		 *
		 * @return array
		 */
		protected function get_messages_from_meta( $purchase_id, $since_timestamp, $current_user_id ) {
			$raw_messages = get_post_meta( $purchase_id, 'purchase_messages', true );
			$raw_messages = is_array( $raw_messages ) ? $raw_messages : array();

			usort(
				$raw_messages,
				function ( $a, $b ) {
					$time_a = isset( $a['created_at'] ) ? strtotime( $a['created_at'] ) : 0;
					$time_b = isset( $b['created_at'] ) ? strtotime( $b['created_at'] ) : 0;
					return $time_a <=> $time_b;
				}
			);

			$messages = array();

			foreach ( $raw_messages as $message ) {
				$created_at = isset( $message['created_at'] ) ? $message['created_at'] : gmdate( 'Y-m-d H:i:s' );
				$created_ts = strtotime( $created_at );

				if ( $since_timestamp && $created_ts && $created_ts <= $since_timestamp ) {
					continue;
				}

				$messages[] = $this->format_message( $message, $current_user_id );
			}

			return $messages;
		}

		/**
		 * Persist a new message into the dedicated table.
		 *
		 * @param int    $purchase_id Purchase post ID.
		 * @param string $uuid        Purchase UUID.
		 * @param array  $message     Message payload.
		 *
		 * @return array|WP_Error
		 */
		protected function persist_message_to_table( $purchase_id, $uuid, array $message ) {
			global $wpdb;

			$table       = $this->get_table_name();
			$message_key = isset( $message['id'] ) ? (string) $message['id'] : uniqid( 'msg_', true );
			$created_at  = $this->normalize_datetime_for_storage( isset( $message['created_at'] ) ? $message['created_at'] : '' );

			$data = array(
				'message_key'   => $message_key,
				'purchase_id'   => $purchase_id,
				'purchase_uuid' => $uuid,
				'user_id'       => isset( $message['user_id'] ) ? (int) $message['user_id'] : 0,
				'sender_role'   => isset( $message['sender_role'] ) ? (string) $message['sender_role'] : 'buyer',
				'message'       => isset( $message['message'] ) ? (string) $message['message'] : '',
				'display_name'  => isset( $message['display_name'] ) ? (string) $message['display_name'] : '',
				'avatar'        => isset( $message['avatar'] ) ? (string) $message['avatar'] : '',
				'created_at'    => $created_at,
			);

			$format = array( '%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' );

			$result = $wpdb->replace( $table, $data, $format );

			if ( false === $result ) {
				return new WP_Error(
					'db_insert_error',
					__( 'Could not persist the message.', 'motorlan-api-vue' ),
					array( 'status' => 500 )
				);
			}

			$message['id']         = $message_key;
			$message['created_at'] = $this->format_datetime_for_response( $created_at );

			return $message;
		}

		/**
		 * Validate a timestamp string.
		 *
		 * @param string $value Incoming timestamp.
		 *
		 * @return bool
		 */
		public function validate_timestamp( $value ) {
			if ( empty( $value ) ) {
				return true;
			}

			return false !== strtotime( $value );
		}

		/**
		 * Retrieve messages for a purchase conversation.
		 *
		 * @param WP_REST_Request $request Request object.
		 *
		 * @return WP_REST_Response|WP_Error
		 */
		public function get_messages( WP_REST_Request $request ) {
			$purchase = $this->get_purchase_from_request( $request );
			if ( is_wp_error( $purchase ) ) {
				return $purchase;
			}

			$purchase_id     = $purchase->ID;
			$current_user_id = get_current_user_id();
			if ( ! motorlan_user_can_access_purchase( $purchase_id, $current_user_id ) ) {
				return new WP_Error(
					'forbidden',
					__( 'You are not allowed to access these messages.', 'motorlan-api-vue' ),
					array( 'status' => 403 )
				);
			}

			$since_timestamp = $request->get_param( 'since_timestamp' );
			$since_timestamp = $since_timestamp ? strtotime( sanitize_text_field( $since_timestamp ) ) : null;

			$messages      = array();
			$table_ready   = $this->ensure_table_exists();
			$server_time   = gmdate( 'Y-m-d H:i:s' );
			$purchase_uuid = $request['uuid'];

			if ( $table_ready ) {
				$this->maybe_migrate_legacy_messages( $purchase_id, $purchase_uuid );
				$messages = $this->get_messages_from_table( $purchase_uuid, $since_timestamp, $current_user_id );
			}

			if ( ! $table_ready || empty( $messages ) ) {
				$messages = $this->get_messages_from_meta( $purchase_id, $since_timestamp, $current_user_id );
			}

			if ( ! empty( $messages ) ) {
				$last        = end( $messages );
				$server_time = isset( $last['created_at'] ) ? $this->format_datetime_for_response( $last['created_at'] ) : $server_time;
				reset( $messages );
			}

			$participants = motorlan_get_purchase_participants( $purchase_id );
			$viewer_role  = ( $participants['seller_id'] === $current_user_id ) ? 'seller' : 'buyer';

			return new WP_REST_Response(
				array(
					'data' => $messages,
					'meta' => array(
						'current_user_id'  => $current_user_id,
						'viewer_role'      => $viewer_role,
						'purchase_uuid'    => $purchase_uuid,
						'server_timestamp' => $server_time,
					),
				),
				200
			);
		}

		/**
		 * Create a new message for the conversation.
		 *
		 * @param WP_REST_Request $request Request object.
		 *
		 * @return WP_REST_Response|WP_Error
		 */
		public function create_message( WP_REST_Request $request ) {
			$purchase = $this->get_purchase_from_request( $request );
			if ( is_wp_error( $purchase ) ) {
				return $purchase;
			}

			$purchase_id     = $purchase->ID;
			$current_user_id = get_current_user_id();
			if ( ! motorlan_user_can_access_purchase( $purchase_id, $current_user_id ) ) {
				return new WP_Error(
					'forbidden',
					__( 'You are not allowed to send messages for this purchase.', 'motorlan-api-vue' ),
					array( 'status' => 403 )
				);
			}

			$raw_message = $request->get_param( 'message' );
			$message     = sanitize_textarea_field( wp_unslash( $raw_message ) );

			if ( '' === trim( $message ) ) {
				return new WP_Error(
					'empty_message',
					__( 'Message cannot be empty.', 'motorlan-api-vue' ),
					array( 'status' => 400 )
				);
			}

			if ( mb_strlen( $message ) > 1000 ) {
				return new WP_Error(
					'message_too_long',
					__( 'Message is too long.', 'motorlan-api-vue' ),
					array( 'status' => 400 )
				);
			}

			$participants = motorlan_get_purchase_participants( $purchase_id );
			$sender_role  = ( $participants['seller_id'] === $current_user_id ) ? 'seller' : 'buyer';
			$user         = get_user_by( 'id', $current_user_id );
			$display_name = $user ? $user->display_name : '';
			$avatar       = get_avatar_url( $current_user_id );

			$new_message = array(
				'id'           => uniqid( 'msg_', true ),
				'user_id'      => $current_user_id,
				'sender_role'  => $sender_role,
				'message'      => $message,
				'created_at'   => gmdate( 'Y-m-d H:i:s' ),
				'display_name' => $display_name,
				'avatar'       => $avatar,
			);

			$existing_messages   = get_post_meta( $purchase_id, 'purchase_messages', true );
			$existing_messages   = is_array( $existing_messages ) ? $existing_messages : array();
			$existing_messages[] = $new_message;

			update_post_meta( $purchase_id, 'purchase_messages', $existing_messages );

			$table_ready = $this->ensure_table_exists();
			if ( $table_ready ) {
				$persisted = $this->persist_message_to_table( $purchase_id, $request['uuid'], $new_message );

				if ( is_wp_error( $persisted ) ) {
					return $persisted;
				}

				$new_message = $persisted;
			}

			$response_message                    = $this->format_message( $new_message, $current_user_id );
			$response_message['is_current_user'] = true;

			return new WP_REST_Response(
				array(
					'data' => $response_message,
					'meta' => array(
						'current_user_id' => $current_user_id,
						'viewer_role'     => $sender_role,
						'purchase_uuid'   => $request['uuid'],
					),
				),
				201
			);
		}

		/**
		 * Locate the purchase post associated with the request.
		 *
		 * @param WP_REST_Request $request Request object.
		 *
		 * @return WP_Post|WP_Error
		 */
		protected function get_purchase_from_request( WP_REST_Request $request ) {
			$uuid = sanitize_text_field( $request['uuid'] );
			if ( empty( $uuid ) ) {
				return new WP_Error(
					'invalid_uuid',
					__( 'Purchase identifier is required.', 'motorlan-api-vue' ),
					array( 'status' => 400 )
				);
			}

			$purchase = motorlan_find_purchase_by_uuid( $uuid );
			if ( ! $purchase ) {
				return new WP_Error(
					'not_found',
					__( 'Purchase not found.', 'motorlan-api-vue' ),
					array( 'status' => 404 )
				);
			}

			return $purchase;
		}

		/**
		 * Normalize a single message for API responses.
		 *
		 * @param array $message          Raw message meta array.
		 * @param int   $current_user_id  Current user ID.
		 *
		 * @return array
		 */
		protected function format_message( $message, $current_user_id ) {
			if ( is_object( $message ) ) {
				$message = (array) $message;
			}

			$user_id      = isset( $message['user_id'] ) ? (int) $message['user_id'] : 0;
			$display_name = isset( $message['display_name'] ) ? $message['display_name'] : '';

			if ( ! $display_name && $user_id ) {
				$user         = get_user_by( 'id', $user_id );
				$display_name = $user ? $user->display_name : '';
			}

			$avatar = '';
			if ( isset( $message['avatar'] ) ) {
				$avatar = (string) $message['avatar'];
			} elseif ( $user_id ) {
				$avatar = get_avatar_url( $user_id );
			}

			$message_id = '';
			if ( isset( $message['message_key'] ) && $message['message_key'] ) {
				$message_id = (string) $message['message_key'];
			} elseif ( isset( $message['id'] ) ) {
				$message_id = (string) $message['id'];
			}

			if ( '' === $message_id ) {
				$message_id = uniqid( 'msg_', true );
			}

			$created_at = isset( $message['created_at'] ) ? $this->format_datetime_for_response( $message['created_at'] ) : gmdate( 'Y-m-d H:i:s' );

			return array(
				'id'              => $message_id,
				'message'         => isset( $message['message'] ) ? (string) $message['message'] : '',
				'created_at'      => $created_at,
				'sender_role'     => isset( $message['sender_role'] ) ? (string) $message['sender_role'] : 'buyer',
				'user_id'         => $user_id,
				'display_name'    => $display_name,
				'avatar'          => $avatar,
				'is_current_user' => ( $user_id === $current_user_id ),
			);
		}
	}
}
