<?php
/**
 * Agrega índices optimizados para el chat al activar el plugin.
 *
 * @package motorlan-api-vue
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function motorlan_add_chat_indexes() {
    global $wpdb;

    $product_messages_table = $wpdb->prefix . 'motorlan_product_messages';
    $purchase_messages_table = $wpdb->prefix . 'motorlan_purchase_messages';
    $room_reads_table = $wpdb->prefix . 'motorlan_product_room_reads';

    // Helper para verificar si existe un índice
    $index_exists = function( $table, $index_name ) use ( $wpdb ) {
        $indices = $wpdb->get_results( "SHOW INDEX FROM $table WHERE Key_name = '$index_name'" );
        return ! empty( $indices );
    };

    // 1. Índices para Product Messages
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$product_messages_table'" ) === $product_messages_table ) {
        if ( ! $index_exists( $product_messages_table, 'idx_polling_product' ) ) {
            $wpdb->query( "ALTER TABLE $product_messages_table ADD INDEX idx_polling_product (product_id, room_key, created_at)" );
        }
        if ( ! $index_exists( $product_messages_table, 'idx_unread_check' ) ) {
            $wpdb->query( "ALTER TABLE $product_messages_table ADD INDEX idx_unread_check (product_id, room_key, user_id, created_at)" );
        }
    }

    // 2. Índices para Purchase Messages
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$purchase_messages_table'" ) === $purchase_messages_table ) {
        if ( ! $index_exists( $purchase_messages_table, 'idx_polling_purchase' ) ) {
            $wpdb->query( "ALTER TABLE $purchase_messages_table ADD INDEX idx_polling_purchase (purchase_uuid, created_at)" );
        }
        if ( ! $index_exists( $purchase_messages_table, 'idx_purchase_id' ) ) {
            $wpdb->query( "ALTER TABLE $purchase_messages_table ADD INDEX idx_purchase_id (purchase_id, created_at)" );
        }
    }

    // 3. Índices para Room Reads
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$room_reads_table'" ) === $room_reads_table ) {
        if ( ! $index_exists( $room_reads_table, 'idx_user_room_reads' ) ) {
            $wpdb->query( "ALTER TABLE $room_reads_table ADD INDEX idx_user_room_reads (user_id, product_id, room_key)" );
        }
    }
}
