<?php
/**
 * Create security logs table.
 *
 * @package motorlan-api-vue
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

function motorlan_create_security_logs_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'motorlan_security_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) DEFAULT NULL,
        event_type varchar(50) NOT NULL,
        severity varchar(20) DEFAULT 'info',
        ip_address varchar(45) NOT NULL,
        user_agent text,
        details longtext,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY event_type (event_type),
        KEY user_id (user_id),
        KEY created_at (created_at)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}
