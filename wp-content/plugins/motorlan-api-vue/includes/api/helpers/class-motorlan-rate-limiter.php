<?php
/**
 * Rate Limiter para endpoints de Motorlan.
 *
 * Previene abuso de APIs mediante límites de peticiones por minuto
 * basados en tipo de usuario (autenticado vs invitado).
 *
 * @package motorlan-api-vue
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Motorlan_Rate_Limiter' ) ) {
	/**
	 * Gestiona rate limiting usando WordPress Transients API.
	 */
	class Motorlan_Rate_Limiter {

		/**
		 * Límites de peticiones por minuto según tipo de usuario.
		 *
		 * @var array
		 */
		private static $limits = array(
			'authenticated' => 30,  // 30 peticiones/minuto para usuarios registrados.
			'guest'         => 20,  // 20 peticiones/minuto para invitados.
		);

		/**
		 * TTL del contador en segundos (1 minuto).
		 *
		 * @var int
		 */
		private static $window = 60;

		/**
		 * Verifica si una petición está dentro del límite permitido.
		 *
		 * @param string $endpoint        Identificador del endpoint (ej: 'product_messages').
		 * @param string $user_identifier Identificador único del usuario (user_id, IP, etc).
		 *
		 * @return bool|WP_Error True si está permitido, WP_Error si excede el límite.
		 */
		public static function check( $endpoint, $user_identifier ) {
			// Determinar tipo de usuario y límite aplicable.
			$user_type = get_current_user_id() ? 'authenticated' : 'guest';
			$limit     = self::$limits[ $user_type ];

			// Generar clave única para el transient.
			$transient_key = self::get_transient_key( $endpoint, $user_identifier );

			// Obtener contador actual.
			$count = get_transient( $transient_key );

			// Primera petición en esta ventana de tiempo.
			if ( false === $count ) {
				set_transient( $transient_key, 1, self::$window );
				return true;
			}

			// Verificar si se excedió el límite.
			if ( $count >= $limit ) {
				return new WP_Error(
					'rate_limit_exceeded',
					sprintf(
						/* translators: 1: Límite de peticiones, 2: Ventana de tiempo en segundos */
						__( 'Has excedido el límite de %1$d peticiones por %2$d segundos. Intenta de nuevo en un momento.', 'motorlan-api-vue' ),
						$limit,
						self::$window
					),
					array(
						'status'      => 429,
						'retry_after' => self::$window,
					)
				);
			}

			// Incrementar contador.
			set_transient( $transient_key, $count + 1, self::$window );

			return true;
		}

		/**
		 * Genera clave única para el transient de rate limiting.
		 *
		 * @param string $endpoint        Identificador del endpoint.
		 * @param string $user_identifier Identificador del usuario.
		 *
		 * @return string Clave del transient.
		 */
		private static function get_transient_key( $endpoint, $user_identifier ) {
			return 'motorlan_rl_' . $endpoint . '_' . md5( $user_identifier );
		}

		/**
		 * Obtiene el identificador único del usuario actual.
		 *
		 * Para usuarios autenticados usa user_id, para invitados usa IP.
		 *
		 * @return string Identificador único.
		 */
		public static function get_user_identifier() {
			$user_id = get_current_user_id();

			if ( $user_id ) {
				return 'user_' . $user_id;
			}

			// Para invitados, usar IP + User-Agent para mayor precisión.
			$ip         = self::get_client_ip();
			$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : 'unknown';

			return 'guest_' . md5( $ip . $user_agent );
		}

		/**
		 * Obtiene la IP real del cliente, considerando proxies.
		 *
		 * @return string IP del cliente.
		 */
		private static function get_client_ip() {
			// Headers de proxy comunes.
			$headers = array(
				'HTTP_CF_CONNECTING_IP', // Cloudflare.
				'HTTP_X_FORWARDED_FOR',
				'HTTP_X_REAL_IP',
				'REMOTE_ADDR',
			);

			foreach ( $headers as $header ) {
				if ( ! empty( $_SERVER[ $header ] ) ) {
					$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
					// Si es una lista separada por comas (proxies múltiples), tomar la primera.
					if ( strpos( $ip, ',' ) !== false ) {
						$ip_list = explode( ',', $ip );
						$ip      = trim( $ip_list[0] );
					}
					// Validar que sea una IP válida.
					if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
						return $ip;
					}
				}
			}

			return '0.0.0.0';
		}

		/**
		 * Envía headers HTTP apropiados para respuesta 429.
		 *
		 * @param WP_Error $error Error de rate limit.
		 *
		 * @return void
		 */
		public static function send_rate_limit_headers( $error ) {
			if ( ! is_wp_error( $error ) ) {
				return;
			}

			$data = $error->get_error_data();

			if ( isset( $data['retry_after'] ) ) {
				header( 'Retry-After: ' . absint( $data['retry_after'] ) );
			}

			header( 'X-RateLimit-Limit: ' . self::$limits['authenticated'] );
		}

		/**
		 * Configura límites personalizados (útil para entornos de desarrollo/testing).
		 *
		 * @param array $custom_limits Array con keys 'authenticated' y 'guest'.
		 *
		 * @return void
		 */
		public static function set_limits( $custom_limits ) {
			if ( isset( $custom_limits['authenticated'] ) ) {
				self::$limits['authenticated'] = absint( $custom_limits['authenticated'] );
			}
			if ( isset( $custom_limits['guest'] ) ) {
				self::$limits['guest'] = absint( $custom_limits['guest'] );
			}
		}
	}
}
