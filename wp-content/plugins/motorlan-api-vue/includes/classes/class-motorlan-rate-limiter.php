<?php
/**
 * Motorlan Rate Limiter
 * Protección contra brute force y spam
 * 
 * Archivo: includes/classes/class-motorlan-rate-limiter.php
 */

if (!defined('WPINC')) {
    die;
}

class Motorlan_Rate_Limiter {
    
    /**
     * Verifica si el request está dentro del límite
     * 
     * @param string $action Acción a limitar (login, register, etc)
     * @param int $max_attempts Máximo de intentos permitidos
     * @param int $period Período en segundos
     * @return bool True si está dentro del límite, false si excedió
     */
    public static function check_limit($action, $max_attempts = 5, $period = 900) {
        $check = self::check($action, null, $max_attempts, $period);
        return !is_wp_error($check);
    }

    /**
     * Clave del contador. Única fuente para check/reset/remaining (antes check() y
     * reset_limit() calculaban claves distintas y el reset nunca funcionaba).
     */
    private static function key($action, $identifier = null) {
        if (!$identifier) {
            $identifier = self::get_user_identifier();
        }
        return 'motorlan_rate_limit_' . md5($action . '_' . $identifier);
    }

    /**
     * Verificación genérica adaptable.
     *
     * Ventana FIJA: el contador caduca $period segundos después del PRIMER intento.
     * Antes cada intento renovaba el TTL (ventana deslizante), así que el polling del
     * chat (cada 3 s) nunca dejaba caducar el contador y acababa en 429 permanente.
     */
    public static function check($action, $identifier = null, $max_attempts = 5, $period = 900) {
        $key   = self::key($action, $identifier);
        $now   = time();
        $state = get_transient($key);

        if (!is_array($state) || !isset($state['count'], $state['start']) || ($now - (int) $state['start']) >= $period) {
            set_transient($key, ['count' => 1, 'start' => $now], $period);
            return true;
        }

        $retry_after = max(1, $period - ($now - (int) $state['start']));

        if ((int) $state['count'] >= $max_attempts) {
            return new WP_Error('too_many_requests', __('Has excedido el límite de solicitudes.', 'motorlan-api-vue'), ['status' => 429, 'retry_after' => $retry_after]);
        }

        $state['count'] = (int) $state['count'] + 1;
        set_transient($key, $state, $retry_after);
        return true;
    }

    /**
     * Segundos hasta que se libera el límite (0 si no hay bloqueo activo).
     */
    public static function get_retry_after($action, $period = 900, $identifier = null) {
        $state = get_transient(self::key($action, $identifier));
        if (!is_array($state) || !isset($state['start'])) {
            return 0;
        }
        return max(0, $period - (time() - (int) $state['start']));
    }

    /**
     * Obtiene los intentos restantes
     */
    public static function get_remaining_attempts($action, $max_attempts = 5, $identifier = null) {
        $state = get_transient(self::key($action, $identifier));
        $attempts = is_array($state) && isset($state['count']) ? (int) $state['count'] : 0;

        return max(0, $max_attempts - $attempts);
    }

    /**
     * Resetea el contador para una acción
     */
    public static function reset_limit($action, $identifier = null) {
        delete_transient(self::key($action, $identifier));
        // Tras un login correcto el identificador pasa de IP a usuario: limpiar ambos.
        delete_transient('motorlan_rate_limit_' . md5($action . '_ip_' . md5(self::get_client_ip())));
    }

    /**
     * Obtiene la IP del cliente (considerando proxies)
     */
    private static function get_client_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Only trust proxy headers when explicitly configured. Otherwise an
        // attacker can spoof X-Forwarded-For to bypass rate limits.
        $trust_proxy = defined( 'MOTORLAN_TRUST_PROXY_HEADERS' ) && MOTORLAN_TRUST_PROXY_HEADERS;

        if ( $trust_proxy ) {
            if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
                $ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
                $ip = trim( $ips[0] );
            } elseif ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            }
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
    
    /**
     * Registra un intento fallido
     */
    public static function log_failed_attempt($action, $details = []) {
        if (class_exists('Motorlan_Security_Logger')) {
            Motorlan_Security_Logger::log('rate_limit_violation', [
                'action' => $action,
                'ip' => self::get_client_ip(),
                'details' => $details,
            ]);
        }
    }

    /**
     * Obtiene un identificador único para el usuario (IP o ID de usuario)
     */
    public static function get_user_identifier() {
        $user_id = get_current_user_id();
        if ($user_id) {
            return 'user_' . $user_id;
        }
        return 'ip_' . md5(self::get_client_ip());
    }

    /**
     * Envía cabeceras HTTP de rate limit (si aplicara)
     */
    public static function send_rate_limit_headers($result) {
        if (is_wp_error($result)) {
            $data = $result->get_error_data();
            if (isset($data['retry_after'])) {
                header('Retry-After: ' . $data['retry_after']);
            }
        }
    }
}
