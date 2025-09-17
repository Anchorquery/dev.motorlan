<?php

// Hook para registrar el evento cron
add_action('init', 'motorlan_schedule_daily_offer_reset');

function motorlan_schedule_daily_offer_reset() {
    if (!wp_next_scheduled('motorlan_reset_daily_offers')) {
        wp_schedule_event(strtotime('00:00:00'), 'daily', 'motorlan_reset_daily_offers');
    }
}

// Hook para la acción del cron
add_action('motorlan_reset_daily_offers', 'motorlan_do_reset_daily_offers');

function motorlan_do_reset_daily_offers() {
    global $wpdb;
    $meta_key = 'offers_today';

    // Elimina el user meta 'offers_today' de todos los usuarios.
    // La próxima vez que un usuario haga una oferta, se creará de nuevo con el contador a 1.
    $wpdb->delete($wpdb->usermeta, array('meta_key' => $meta_key));
}

// Hook para desprogramar el evento al desactivar el plugin
register_deactivation_hook(__FILE__, 'motorlan_unschedule_daily_offer_reset');

function motorlan_unschedule_daily_offer_reset() {
    $timestamp = wp_next_scheduled('motorlan_reset_daily_offers');
    wp_unschedule_event($timestamp, 'motorlan_reset_daily_offers');
}