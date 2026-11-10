<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Versión del esquema de tablas propias. Súbela al cambiar cualquier CREATE TABLE o
 * migración de includes/db: motorlan_maybe_upgrade_db() lo aplicará en el siguiente
 * request (los despliegues por FTP no disparan register_activation_hook).
 */
if ( ! defined( 'MOTORLAN_DB_VERSION' ) ) {
    define( 'MOTORLAN_DB_VERSION', '1.3' );
}

function motorlan_offers_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'motorlan_offers';
    $charset_collate = $wpdb->get_charset_collate();

    // offer_date sin DEFAULT '0000-00-00 00:00:00': con sql_mode estricto (NO_ZERO_DATE,
    // por defecto en MySQL 5.7+/8) el CREATE TABLE fallaba y la tabla no se creaba. El
    // INSERT de offers-routes.php siempre envía offer_date.
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        publication_id bigint(20) NOT NULL,
        user_id bigint(20) NOT NULL,
        offer_amount float NOT NULL,
        offer_date datetime NOT NULL,
        justification TEXT,
        status ENUM('pending', 'rejected', 'accepted', 'accepted_pending_confirmation', 'confirmed', 'expired') DEFAULT 'pending' NOT NULL,
        accepted_at datetime DEFAULT NULL,
        expires_at datetime DEFAULT NULL,
        confirmed_at datetime DEFAULT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
require_once MOTORLAN_API_VUE_PATH . 'includes/db/db-migration-v1-2.php';

function motorlan_run_migrations() {
    motorlan_migrate_product_messages_v1_2();
    motorlan_migrate_purchase_messages_v1_2();
}

/**
 * Crea/actualiza todas las tablas propias en el orden correcto.
 *
 * Antes cada tabla era un register_activation_hook suelto y las migraciones corrían
 * ANTES de crear motorlan_product_room_reads, así que en una instalación nueva la
 * opción motorlan_room_reads_last_read_id nunca quedaba a '1'.
 */
function motorlan_install_db() {
    $steps = [
        'motorlan_create_notifications_table',
        'motorlan_offers_create_table',
        'motorlan_create_purchase_messages_table',
        'motorlan_create_product_messages_table',
        'motorlan_create_product_room_reads_table',
        'motorlan_create_security_logs_table',
        'motorlan_run_migrations',
        'motorlan_add_chat_indexes',
    ];
    foreach ( $steps as $step ) {
        if ( function_exists( $step ) ) {
            call_user_func( $step );
        }
    }
    update_option( 'motorlan_db_version', MOTORLAN_DB_VERSION, true );
}

/**
 * Aplica el esquema cuando cambia MOTORLAN_DB_VERSION. El plugin se despliega por FTP
 * (sin reactivarlo), así que sin esto una tabla o columna nueva nunca llegaba a crearse.
 */
function motorlan_maybe_upgrade_db() {
    if ( get_option( 'motorlan_db_version' ) === MOTORLAN_DB_VERSION ) {
        return;
    }
    // Evita que varias peticiones simultáneas lancen el dbDelta a la vez.
    if ( get_transient( 'motorlan_db_upgrading' ) ) {
        return;
    }
    set_transient( 'motorlan_db_upgrading', 1, 5 * MINUTE_IN_SECONDS );
    motorlan_install_db();
    delete_transient( 'motorlan_db_upgrading' );
}
add_action( 'plugins_loaded', 'motorlan_maybe_upgrade_db' );
