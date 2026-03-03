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
     * Verificación genérica adaptable
     */
    public static function check($action, $identifier = null, $max_attempts = 5, $period = 900) {
        if (!$identifier) {
            $identifier = self::get_user_identifier();
        }
        
        $key = 'motorlan_rate_limit_' . md5($action . '_' . $identifier);
        
        $attempts = get_transient($key);
        
        if ($attempts === false) {
            set_transient($key, 1, $period);
            return true;
        }
        
        if ($attempts >= $max_attempts) {
            return new WP_Error('too_many_requests', 'Has excedido el límite de solicitudes.', ['status' => 429, 'retry_after' => $period]);
        }
        
        // Incrementar contador
        set_transient($key, $attempts + 1, $period);
        return true;
    }
    
    /**
     * Obtiene los intentos restantes
     */
    public static function get_remaining_attempts($action, $max_attempts = 5) {
        $ip = self::get_client_ip();
        $key = 'motorlan_rate_limit_' . md5($action . '_' . $ip);
        $attempts = get_transient($key) ?: 0;
        
        return max(0, $max_attempts - $attempts);
    }
    
    /**
     * Resetea el contador para una acción
     */
    public static function reset_limit($action) {
        $ip = self::get_client_ip();
        $key = 'motorlan_rate_limit_' . md5($action . '_' . $ip);
        delete_transient($key);
    }
    
    /**
     * Obtiene la IP del cliente (considerando proxies)
     */
    private static function get_client_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        // Verificar headers de proxy (solo si confías en tu proxy)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
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
