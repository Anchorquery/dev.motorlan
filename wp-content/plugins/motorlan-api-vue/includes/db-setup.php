<?php

function motorlan_offers_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'motorlan_offers';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        publication_id bigint(20) NOT NULL,
        user_id bigint(20) NOT NULL,
        offer_amount float NOT NULL,
        offer_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        justification TEXT,
        status ENUM('pending', 'rejected', 'accepted') DEFAULT 'pending' NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
