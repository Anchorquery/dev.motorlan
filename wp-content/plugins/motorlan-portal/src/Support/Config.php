<?php

namespace MotorlanPortal\Support;

/**
 * Datos para el JavaScript: window.motorlanPortal (layout/base.twig).
 */
class Config
{
    public static function data(): array
    {
        $lang = apply_filters('wpml_current_language', null);

        return [
            'siteUrl'  => site_url(),
            'restRoot' => esc_url_raw(rest_url()),
            'apiBase'  => esc_url_raw(rest_url('motorlan/v1/')),
            'portalApi' => esc_url_raw(rest_url('motorlan-portal/v1/')),
            // Nonce REST de la sesión de WordPress (cookie). HTMX y fetchRest lo envían en
            // X-WP-Nonce; si caduca se pide otro (assets/js/nonce.js).
            'nonce'    => wp_create_nonce('wp_rest'),
            // Renovación: la acción de motorlan-api-vue (también para invitados, devuelve
            // logged_in); si no existe, la de WordPress (solo con sesión).
            'nonceUrl' => admin_url('admin-ajax.php?action=' . (has_action('wp_ajax_nopriv_motorlan_rest_nonce') ? 'motorlan_rest_nonce' : 'rest-nonce')),
            'lang'     => $lang ? $lang : substr(get_locale(), 0, 2),
            'isLogged' => is_user_logged_in(),
            'isAdmin'  => current_user_can('manage_options'),
            'i18n'     => [
                'error'   => __('Ha ocurrido un error', 'motorlan-portal'),
                'network' => __('Error de red. Revisa tu conexión.', 'motorlan-portal'),
                'expired' => __('Tu sesión ha caducado. Vuelve a iniciar sesión.', 'motorlan-portal'),
            ],
        ];
    }
}
