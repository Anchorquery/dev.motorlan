<?php
/**
 * Crea la tabla de mensajes del chat de productos (pre-compra) al activar el plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function motorlan_create_product_messages_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'motorlan_product_messages';
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        message_key VARCHAR(64) NOT NULL,
        product_id BIGINT(20) UNSIGNED NOT NULL,
        room_key VARCHAR(191) NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        sender_role VARCHAR(20) NOT NULL DEFAULT 'viewer',
        display_name VARCHAR(191) NULL,
        avatar VARCHAR(512) NULL,
        message TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY message_key (message_key),
        KEY product_room (product_id, room_key),
        KEY created_at (created_at)
    ) {$charset_collate};";

    dbDelta( $sql );
}

