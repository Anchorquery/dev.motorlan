<?php
/**
 * Motorlan Security Logger
 * Handles logging of security events to database.
 * 
 * Archivo: includes/classes/class-motorlan-security-logger.php
 */

if (!defined('WPINC')) {
    die;
}

class Motorlan_Security_Logger {
    
    /**
     * Log a security event
     * 
     * @param string $event_type Type of event (e.g., 'login_failed', 'rate_limit')
     * @param array $data Additional data (details, user_id, severity)
     */
    public static function log($event_type, $data = []) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'motorlan_security_logs';
        
        // Check if table exists (cache this check ideally, but for now simple)
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return; // Fail silently if table doesn't exist yet
        }
        
        $user_id = isset($data['user_id']) ? intval($data['user_id']) : get_current_user_id();
        $severity = isset($data['severity']) ? sanitize_text_field($data['severity']) : 'info';
        $details = isset($data['details']) ? $data['details'] : [];
        
        // Serialize details if it's an array/object
        if (is_array($details) || is_object($details)) {
            $details = json_encode($details);
        }
        
        $ip = self::get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT']), 0, 255) : '';
        
        $wpdb->insert(
            $table_name,
            array(
                'event_type' => sanitize_text_field($event_type),
                'user_id' => $user_id > 0 ? $user_id : null,
                'severity' => $severity,
                'ip_address' => $ip,
                'user_agent' => $user_agent,
                'details' => $details,
                'created_at' => current_time('mysql')
            ),
            array(
                '%s', // event_type
                '%d', // user_id
                '%s', // severity
                '%s', // ip_address
                '%s', // user_agent
                '%s', // details
                '%s'  // created_at
            )
        );
    }
    
    /**
     * Get client IP
     */
    private static function get_client_ip() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }
    
    /**
     * Prune old logs
     * Can be called by CRON
     */
    public static function prune_logs($days = 30) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'motorlan_security_logs';
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }
}
