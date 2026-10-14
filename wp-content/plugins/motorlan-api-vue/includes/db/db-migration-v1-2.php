<?php
/**
 * Migration v1.2: Add missing columns to chat tables.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ensures that the product messages table has all required columns.
 */
function motorlan_migrate_product_messages_v1_2() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'motorlan_product_messages';

    // Check if table exists
    $found = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
    if ( $found !== $table_name ) {
        if ( function_exists( 'motorlan_create_product_messages_table' ) ) {
            motorlan_create_product_messages_table();
        }
        return;
    }

    // Check for guest_email column
    $column = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table_name} LIKE %s", 'guest_email' ) );
    if ( empty( $column ) ) {
        $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN guest_email VARCHAR(191) NULL AFTER message" );
    }

    // Índice para reclamar los chats de invitado por email (antes: recorrido completo de la tabla)
    $index = $wpdb->get_results( "SHOW INDEX FROM {$table_name} WHERE Key_name = 'idx_guest_email'" );
    if ( empty( $index ) ) {
        $wpdb->query( "ALTER TABLE {$table_name} ADD INDEX idx_guest_email (guest_email)" );
    }

    motorlan_migrate_product_room_reads_last_read_id();
}

/**
 * Añade last_read_id a la tabla de lecturas por sala: con solo last_read_at (resolución
 * de 1 s) un mensaje insertado en el mismo segundo de la lectura no contaba como no leído.
 * El controlador solo usa la columna cuando la opción motorlan_room_reads_last_read_id = 1.
 */
function motorlan_migrate_product_room_reads_last_read_id() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'motorlan_product_room_reads';

    $found = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
    if ( $found !== $table_name ) {
        return;
    }

    $column = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table_name} LIKE %s", 'last_read_id' ) );
    if ( empty( $column ) ) {
        $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN last_read_id BIGINT(20) UNSIGNED NULL AFTER last_read_at" );
        $column = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table_name} LIKE %s", 'last_read_id' ) );
    }

    update_option( 'motorlan_room_reads_last_read_id', empty( $column ) ? '0' : '1', true );
}

// Support for purchase messages if needed
function motorlan_migrate_purchase_messages_v1_2() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'motorlan_purchase_messages';

    // Check if table exists
    $found = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );
    if ( $found !== $table_name ) {
        return; // Handled by its own creation logic if it exists
    }

    // Check for guest_email column (just in case)
    $column = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM {$table_name} LIKE %s", 'guest_email' ) );
    if ( empty( $column ) ) {
        $wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN guest_email VARCHAR(191) NULL AFTER message" );
    }
}
