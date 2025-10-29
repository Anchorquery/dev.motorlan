<?php
/**
 * Crea la tabla de estados de lectura por sala de chat de productos.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function motorlan_create_product_room_reads_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'motorlan_product_room_reads';
    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        product_id BIGINT(20) UNSIGNED NOT NULL,
        room_key VARCHAR(191) NOT NULL,
        last_read_at DATETIME NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_user_room (user_id, product_id, room_key),
        KEY product_room (product_id, room_key)
    ) {$charset_collate};";

    dbDelta( $sql );
}

