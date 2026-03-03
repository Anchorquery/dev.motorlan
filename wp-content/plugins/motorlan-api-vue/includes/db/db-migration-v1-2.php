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
