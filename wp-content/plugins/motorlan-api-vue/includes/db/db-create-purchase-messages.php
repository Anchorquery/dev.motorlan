<?php

/**
 * Crea la tabla de mensajes del chat de compras al activar el plugin.
 */
function motorlan_create_purchase_messages_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'motorlan_purchase_messages';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        message_key VARCHAR(100) NOT NULL,
        purchase_id BIGINT(20) UNSIGNED NOT NULL,
        purchase_uuid VARCHAR(64) NOT NULL,
        user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        sender_role VARCHAR(20) NOT NULL,
        message LONGTEXT NOT NULL,
        display_name VARCHAR(191) DEFAULT '',
        avatar VARCHAR(255) DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY message_key (message_key),
        KEY purchase_lookup (purchase_uuid, created_at),
        KEY purchase_id_lookup (purchase_id),
        KEY user_lookup (user_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}