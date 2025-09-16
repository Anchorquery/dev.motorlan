<?php
/**
 * Route registration for Publicaciones and related endpoints.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Register all REST API routes for publicaciones.
 */
function motorlan_register_publicaciones_rest_routes() {
    $namespace = 'motorlan/v1';

    // --- Publicaciones CRUD ---
    register_rest_route($namespace, '/publicaciones', [
        [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => 'motorlan_get_publicaciones_callback',
            'permission_callback' => '__return_true',
        ],
        [
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => 'motorlan_create_publicacion_callback',
            'permission_callback' => 'motorlan_is_user_authenticated',
        ],
    ]);

    register_rest_route($namespace, '/publicaciones/uuid/(?P<uuid>[a-zA-Z0-9-]+)', [
        [
            'methods' => 'GET',
            'callback' => 'motorlan_get_publicacion_by_uuid',
            'permission_callback' => '__return_true',
        ],
        [
            'methods' => 'POST',
            'callback' => 'motorlan_update_publicacion_by_uuid',
            'permission_callback' => 'motorlan_is_user_authenticated',
        ],
    ]);

    register_rest_route($namespace, '/publicaciones/(?P<slug>[a-zA-Z0-9-]+)', [
        'methods'  => 'GET',
        'callback' => 'motorlan_get_publicacion_by_slug',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($namespace, '/publicaciones/(?P<id>\\d+)', [
        'methods' => 'DELETE',
        'callback' => 'motorlan_delete_publicacion',
        'permission_callback' => 'motorlan_is_user_authenticated',
    ]);

    // --- Publicaciones Actions ---
    register_rest_route($namespace, '/publicaciones/duplicate/(?P<id>\\d+)', [
        'methods' => 'POST',
        'callback' => 'motorlan_duplicate_publicacion',
        'permission_callback' => 'motorlan_is_user_authenticated',
    ]);

    register_rest_route($namespace, '/publicaciones/(?P<id>\\d+)/status', [
        'methods' => 'POST',
        'callback' => 'motorlan_update_publicacion_status',
        'permission_callback' => 'motorlan_is_user_authenticated',
    ]);

    register_rest_route($namespace, '/publicaciones/bulk-delete', [
        'methods' => 'POST',
        'callback' => 'motorlan_bulk_delete_publicaciones',
        'permission_callback' => 'motorlan_is_user_authenticated',
    ]);

    // --- Store Endpoint ---
    register_rest_route($namespace, '/store/publicaciones', [
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_public_publicaciones_callback',
        'permission_callback' => '__return_true',
    ]);

    // --- Taxonomies ---
    register_rest_route($namespace, '/publicacion-categories', [
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_publicacion_categories_callback',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($namespace, '/tipos', [
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_tipos_callback',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route($namespace, '/marcas', [
        'methods'  => WP_REST_Server::READABLE,
        'callback' => 'motorlan_get_marcas_callback',
        'permission_callback' => '__return_true',
    ]);

    // --- Favorites ---
    register_rest_route($namespace, '/favorites', [
        [
            'methods'  => WP_REST_Server::READABLE,
            'callback' => 'motorlan_get_user_favorites',
            'permission_callback' => 'motorlan_is_user_authenticated',
        ],
        [
            'methods'  => WP_REST_Server::CREATABLE,
            'callback' => 'motorlan_add_user_favorite',
            'permission_callback' => 'motorlan_is_user_authenticated',
        ],
    ]);

    register_rest_route($namespace, '/favorites/(?P<id>\\d+)', [
        'methods'  => 'DELETE',
        'callback' => 'motorlan_remove_user_favorite',
        'permission_callback' => 'motorlan_is_user_authenticated',
    ]);
}