<?php
/**
 * Helper functions for the Publicaciones REST API endpoints.
 *
 * @package motorlan-api-vue
 */

if (!defined('WPINC')) {
    die;
}

/**
 * Return the cache version for public store publicaciones responses.
 *
 * Bumping this version invalidates all cached variants without needing to
 * enumerate transient keys.
 *
 * @return int
 */
function motorlan_get_store_publicaciones_cache_version() {
    return max(1, (int) get_option('motorlan_store_publicaciones_cache_version', 1));
}

/**
 * Bump the cache version for public store publicaciones responses.
 *
 * @return void
 */
function motorlan_bump_store_publicaciones_cache_version() {
    $version = motorlan_get_store_publicaciones_cache_version() + 1;
    update_option('motorlan_store_publicaciones_cache_version', $version, false);
}

/**
 * Invalidate the cached store publicaciones responses.
 *
 * Uses a per-request guard so a single save operation that touches multiple
 * hooks only bumps the cache version once.
 *
 * @return void
 */
function motorlan_invalidate_store_publicaciones_cache() {
    static $invalidated = false;

    if ($invalidated) {
        return;
    }

    $invalidated = true;
    motorlan_bump_store_publicaciones_cache_version();
    // Segundo bump al terminar la petición: los guardados van en varios pasos
    // (estado → ACF → stock) y una visita que consultara la tienda entre medias
    // dejaba cacheada (10 min) una respuesta sin la publicación recién aprobada.
    add_action('shutdown', 'motorlan_bump_store_publicaciones_cache_version', 0);
}

/**
 * Determine whether a request is eligible for store response caching.
 *
 * Cache all public store pages. The cache key already includes the page number
 * and all user-visible filters, so each combination is isolated.
 *
 * @param array $params Normalized request params.
 * @return bool
 */
function motorlan_should_cache_store_publicaciones_request($params) {
    $cache_options = get_option( 'motorlan_store_cache_options', [] );
    if ( ! is_array( $cache_options ) ) {
        $cache_options = [];
    }

    $enabled = ! empty( $cache_options['enabled'] );
    if ( ! $enabled ) {
        return false;
    }

    $cache_all_pages = ! empty( $cache_options['cache_all_pages'] );
    if ( $cache_all_pages ) {
        return true;
    }

    $page = isset( $params['page'] ) ? absint( $params['page'] ) : 1;

    return $page === 1;
}

/**
 * Normalize request params for cache key generation.
 *
 * @param array $params Raw request params.
 * @return array
 */
function motorlan_normalize_store_publicaciones_cache_params($params) {
    $normalized = [
        'per_page' => isset($params['per_page']) ? absint($params['per_page']) : 12,
        'page' => isset($params['page']) ? absint($params['page']) : 1,
        'status' => !empty($params['status']) ? sanitize_text_field($params['status']) : 'publish',
        'orderby' => !empty($params['orderby']) ? sanitize_text_field($params['orderby']) : 'date',
        'order' => !empty($params['order']) ? strtoupper(sanitize_text_field($params['order'])) : 'DESC',
    ];

    $filter_keys = [
        'search',
        'tipo',
        'marca',
        'estado_del_articulo',
        'potencia',
        'velocidad',
        'par_nominal',
        'tipo_de_alimentacion',
        'tipo_o_referencia',
    ];

    foreach ($filter_keys as $key) {
        if (isset($params[$key]) && $params[$key] !== '') {
            $normalized[$key] = is_array($params[$key])
                ? array_map('sanitize_text_field', $params[$key])
                : sanitize_text_field($params[$key]);
        }
    }

    ksort($normalized);

    return $normalized;
}

/**
 * Build a transient key for the public store publicaciones response.
 *
 * @param array $params Normalized request params.
 * @return string
 */
function motorlan_build_store_publicaciones_cache_key($params) {
    // 4: autor sin email en name/nickname, author.acf (reputación) y listado sin galería
    $schema_version = 4;
    $version = motorlan_get_store_publicaciones_cache_version();
    $site_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 1;
    $signature = md5(wp_json_encode($params));

    return "motorlan_store_publicaciones_s{$schema_version}_v{$version}_b{$site_id}_{$signature}";
}

/**
 * Return the transient TTL for public store responses.
 *
 * @return int
 */
function motorlan_get_store_publicaciones_cache_ttl() {
    $cache_options = get_option( 'motorlan_store_cache_options', [] );
    if ( ! is_array( $cache_options ) ) {
        $cache_options = [];
    }

    $ttl_minutes = isset( $cache_options['ttl_minutes'] ) ? absint( $cache_options['ttl_minutes'] ) : 10;
    $ttl_minutes = max( 1, min( 1440, $ttl_minutes ) );

    return $ttl_minutes * MINUTE_IN_SECONDS;
}

/**
 * Clear cached storefront publications responses.
 *
 * This is intentionally broad. Any mutation that affects public listings should
 * invalidate every cached variant.
 *
 * @param int    $post_id Post ID, if available.
 * @param string $post_type Post type, if available.
 * @return void
 */
function motorlan_flush_store_publicaciones_cache($post_id = 0, $post_type = '', $post = null) {
    if ($post_id && wp_is_post_revision($post_id)) {
        return;
    }

    if ($post_id && wp_is_post_autosave($post_id)) {
        return;
    }

    if ($post instanceof WP_Post && empty($post_type)) {
        $post_type = $post->post_type;
    }

    if ($post_id && !$post_type) {
        $post_type = get_post_type($post_id);
    }

    if ($post_type && $post_type !== 'publicacion') {
        return;
    }

    motorlan_invalidate_store_publicaciones_cache();
}

/**
 * Flush cache when post meta changes for a publicacion.
 *
 * This catches direct meta writes from admin helpers or REST callbacks that do
 * not always rely on the main save_post flow.
 *
 * @param int    $meta_id Meta row ID.
 * @param int    $object_id Post ID.
 * @param string $meta_key Meta key.
 * @param mixed  $_meta_value Meta value.
 * @return void
 */
function motorlan_flush_store_publicaciones_cache_from_meta($meta_id = 0, $object_id = 0, $meta_key = '', $_meta_value = null) {
    // Metas internas de wp-admin: _edit_lock se reescribe en cada heartbeat mientras
    // alguien tiene la publicación abierta y anulaba la caché todo el rato.
    if (in_array($meta_key, ['_edit_lock', '_edit_last', '_wp_old_slug'], true)) {
        return;
    }

    if (!$object_id || get_post_type($object_id) !== 'publicacion') {
        return;
    }

    motorlan_invalidate_store_publicaciones_cache();
}

/**
 * El listado público muestra el nombre del vendedor: invalidar al cambiar el perfil.
 *
 * @return void
 */
function motorlan_flush_store_publicaciones_cache_from_profile() {
    motorlan_invalidate_store_publicaciones_cache();
}

/**
 * Flush cache when terms change for a publicacion.
 *
 * @param int    $object_id Object ID.
 * @param array  $terms Term IDs or slugs.
 * @param array  $tt_ids Term taxonomy IDs.
 * @param string $taxonomy Taxonomy name.
 * @param bool   $append Whether terms are appended.
 * @param array  $old_tt_ids Old term taxonomy IDs.
 * @return void
 */
function motorlan_flush_store_publicaciones_cache_from_terms($object_id = 0, $terms = [], $tt_ids = [], $taxonomy = '', $append = false, $old_tt_ids = []) {
    if (!$object_id || get_post_type($object_id) !== 'publicacion') {
        return;
    }

    if (!in_array($taxonomy, ['tipo', 'marca'], true)) {
        return;
    }

    motorlan_invalidate_store_publicaciones_cache();
}

/**
 * Flush cache when taxonomy terms are created, edited or removed.
 *
 * @param int    $term_id Term ID.
 * @param int    $tt_id Term taxonomy ID.
 * @param string $taxonomy Taxonomy name.
 * @return void
 */
function motorlan_flush_store_publicaciones_cache_from_term_changes($term_id = 0, $tt_id = 0, $taxonomy = '') {
    if ( ! in_array( $taxonomy, [ 'marca', 'tipo' ], true ) ) {
        return;
    }

    motorlan_invalidate_store_publicaciones_cache();
}

/**
 * Hook cache invalidation to publication mutations.
 *
 * @return void
 */
function motorlan_register_store_publicaciones_cache_hooks() {
    add_action('save_post_publicacion', 'motorlan_flush_store_publicaciones_cache', 20, 3);
    add_action('before_delete_post', 'motorlan_flush_store_publicaciones_cache', 20, 1);
    add_action('trashed_post', 'motorlan_flush_store_publicaciones_cache', 20, 1);
    add_action('added_post_meta', 'motorlan_flush_store_publicaciones_cache_from_meta', 20, 4);
    add_action('updated_post_meta', 'motorlan_flush_store_publicaciones_cache_from_meta', 20, 4);
    add_action('deleted_post_meta', 'motorlan_flush_store_publicaciones_cache_from_meta', 20, 4);
    add_action('set_object_terms', 'motorlan_flush_store_publicaciones_cache_from_terms', 20, 6);
    add_action('created_term', 'motorlan_flush_store_publicaciones_cache_from_term_changes', 20, 3);
    add_action('edited_term', 'motorlan_flush_store_publicaciones_cache_from_term_changes', 20, 3);
    add_action('delete_term', 'motorlan_flush_store_publicaciones_cache_from_term_changes', 20, 3);
    add_action('profile_update', 'motorlan_flush_store_publicaciones_cache_from_profile', 20, 0);
}

add_action('init', 'motorlan_register_store_publicaciones_cache_hooks', 20);

/**
 * Build WP_Query arguments from request parameters for publicaciones.
 *
 * @param array $params Request parameters.
 * @return array Arguments for WP_Query.
 */
function motorlan_build_publicaciones_query_args($params) {
    // Parámetros públicos: ?tipo[]=x llegaba como array y explode()/strtoupper() lanzaban
    // TypeError (500 con HTML). Se aplanan a "a,b" (formato que ya aceptan los filtros).
    foreach ((array) $params as $param_key => $param_value) {
        if (is_array($param_value)) {
            $params[$param_key] = implode(',', array_map('strval', array_filter($param_value, 'is_scalar')));
        }
    }

    // SEGURIDAD: este builder lo usan endpoints públicos (/publicaciones, /store, IA).
    // Solo un admin puede pedir otros estados (borradores, pendientes...) o cambiar el
    // umbral de stock; antes ?post_status=any exponía publicaciones no publicadas.
    $can_see_unpublished = current_user_can('manage_options');
    if (!$can_see_unpublished) {
        unset($params['post_status'], $params['stock_min']);
        if (!empty($params['status']) && $params['status'] !== 'publish') {
            $params['status'] = 'publish';
        }
    }

    $requested_status = !empty($params['status']) && is_string($params['status']) ? sanitize_text_field($params['status']) : null;
    $per_page = !empty($params['per_page']) ? absint($params['per_page']) : 10;
    $per_page = max(1, min(100, $per_page));

    $args = [
        'post_type'      => 'publicacion',
        'posts_per_page' => $per_page,
        'paged'          => !empty($params['page']) ? absint($params['page']) : 1,
        'post_status'    => !empty($params['post_status']) && is_string($params['post_status']) ? sanitize_text_field($params['post_status']) : 'publish',
    ];

    $meta_query = ['relation' => 'AND'];
    $tax_query = ['relation' => 'AND'];

    $stock_filter_min = isset($params['stock_min']) ? max(0, intval($params['stock_min'])) : 1;
    $should_filter_stock = $requested_status === null || $requested_status === 'publish';
    if ($should_filter_stock) {
        $meta_query[] = [
            'key'     => 'stock',
            'value'   => $stock_filter_min,
            'compare' => '>=',
            'type'    => 'NUMERIC',
        ];
    }

    $filterable_fields = [
        'marca', 'tipo_o_referencia', 'potencia', 'velocidad', 'par_nominal', 'voltaje', 'intensidad',
        'pais', 'provincia', 'posibilidad_de_alquiler',
        'servomotores', 'regulacion_electronica_drivers', 'precio_negociable', 'uuid','estado_del_articulo'
    ];
    // SEGURIDAD: filtrar u ordenar por precio_de_venta permitía averiguar los precios
    // ocultos ("Consultar precio", ~97 % del catálogo) con ?precio_de_venta=250 u
    // ?orderby=price. Solo administradores (ninguna pantalla pública lo usa).
    if ($can_see_unpublished) {
        $filterable_fields[] = 'precio_de_venta';
    }

    // tipo_de_alimentacion: accept both new values ('ac'/'dc') and legacy ACF radio values
    if (!empty($params['tipo_de_alimentacion'])) {
        $val = sanitize_text_field($params['tipo_de_alimentacion']);
        $legacy_map = ['ac' => 'Alterna (C.A.)', 'dc' => 'Continua (C.C.)'];
        if (isset($legacy_map[$val])) {
            $meta_query[] = [
                'relation' => 'OR',
                ['key' => 'tipo_de_alimentacion', 'value' => $val, 'compare' => '='],
                ['key' => 'tipo_de_alimentacion', 'value' => $legacy_map[$val], 'compare' => '='],
            ];
        } else {
            $meta_query[] = ['key' => 'tipo_de_alimentacion', 'value' => $val, 'compare' => '='];
        }
    }

    // Helper to parse range strings like "10-50"
    $parse_range = function($value) {
        if (!is_string($value)) return null;
        $parts = array_map('trim', explode('-', $value));
        if (count($parts) !== 2) return null;
        $min = is_numeric($parts[0]) ? (float)$parts[0] : null;
        $max = is_numeric($parts[1]) ? (float)$parts[1] : null;
        if ($min === null || $max === null) return null;
        return [$min, $max];
    };

    foreach ($filterable_fields as $field) {
        if (!empty($params[$field])) {
            $raw_value = $params[$field];

            // Support numeric range filtering for specific fields
            if (in_array($field, ['par_nominal', 'velocidad', 'potencia'], true)) {
                $range = $parse_range($raw_value);
                if ($range) {
                    // - Sin dato ('') no es 0: con NUMERIC '' contaba como 0 y "0-100 kW"
                    //   devolvía 292 resultados en vez de 135 (reguladores, repuestos...).
                    // - DECIMAL: NUMERIC es CAST AS SIGNED y truncaba 5.1 → 5.
                    // - Intervalo semiabierto [min, max): con BETWEEN los extremos
                    //   compartidos (100 kW) salían en dos rangos del filtro de la tienda.
                    $range_clause = [
                        'relation' => 'AND',
                        ['key' => $field, 'value' => '', 'compare' => '!='],
                        ['key' => $field, 'value' => $range[0], 'compare' => '>=', 'type' => 'DECIMAL(12,3)'],
                    ];
                    if ($range[1] > $range[0]) {
                        $range_clause[] = ['key' => $field, 'value' => $range[1], 'compare' => '<', 'type' => 'DECIMAL(12,3)'];
                    } else {
                        $range_clause[] = ['key' => $field, 'value' => $range[1], 'compare' => '<=', 'type' => 'DECIMAL(12,3)'];
                    }
                    $meta_query[] = $range_clause;
                    continue;
                }
            }

            if ($field === 'tipo_o_referencia') {
                // Custom handling for 'tipo_o_referencia' to ignore special characters (., -, /, spaces)
                // We strip these characters from the search term here.
                // The actual SQL modification will be handled in the query execution via posts_join/posts_where filters
                // relying on $args['motorlan_custom_search_tipo'].
                $clean_value = preg_replace('/[\.\-\/\s]/', '', $raw_value);
                if (!empty($clean_value)) {
                    $args['motorlan_custom_search_tipo'] = $clean_value;
                }
            } else {
                 $meta_query[] = [
                    'key'     => $field,
                    'value'   => sanitize_text_field($raw_value),
                    'compare' => '=',
                ];
            }
        }
    }

    if (!empty($params['status'])) {
        $meta_query[] = [
            'key'     => 'publicar_acf',
            'value'   => sanitize_text_field($params['status']),
            'compare' => '=',
        ];
    }

    // Filtro por "categoria" DESACTIVADO: la web filtra por "tipo".
    /*
    if (!empty($params['category'])) {
        $tax_query[] = [
            'taxonomy' => 'categoria',
            'field'    => 'slug',
            'terms'    => array_map('sanitize_text_field', explode(',', $params['category'])),
        ];
    }
    */

    if (!empty($params['tipo'])) {
        $tipo_terms = array_map('sanitize_text_field', explode(',', $params['tipo']));
        // Sinónimos de "Otros" por si el slug real del término difiere.
        if (in_array('otro-repuesto', $tipo_terms, true)) {
            $tipo_terms = array_values(array_unique(array_merge($tipo_terms, ['repuesto', 'otros'])));
        }
        $tax_query[] = [
            'taxonomy' => 'tipo',
            'field'    => 'slug',
            'terms'    => $tipo_terms,
        ];
    }

    if (count($meta_query) > 1) $args['meta_query'] = $meta_query;
    if (count($tax_query) > 1) $args['tax_query'] = $tax_query;

    if (!empty($params['search'])) {
        $args['s'] = sanitize_text_field($params['search']);
    }

    if (!empty($params['orderby'])) {
        $orderby = sanitize_text_field($params['orderby']);
        // Ordenar por precio acota los precios ocultos: solo administradores.
        if ($orderby === 'price' && $can_see_unpublished) {
            $args['meta_key'] = 'precio_de_venta';
            $args['orderby'] = 'meta_value_num';
        } elseif (in_array($orderby, ['date', 'title', 'modified'], true)) {
            $args['orderby'] = $orderby;
        }
    }

    if (!empty($params['order']) && in_array(strtoupper($params['order']), ['ASC', 'DESC'])) {
        $args['order'] = strtoupper(sanitize_text_field($params['order']));
    }

    return $args;
}

/**
 * Get all data for a single publicacion.
 *
 * @param int $post_id The post ID.
 * @return array The publicacion data.
 */
function motorlan_get_publicacion_data($post_id, $include_sensitive = false, $include_author_contact = false, $list_mode = false) {
    $post = get_post($post_id);
    if (!$post) return [];

    // Email y login del autor solo con datos sensibles (dueño/admin vía by-uuid) o
    // contactos autorizados (participantes de una compra). Antes se exponían en la
    // tienda pública, favoritos, IA, etc. No depender del usuario actual: la respuesta
    // de la tienda se cachea y se serviría a todos los visitantes.
    $show_author_private = $include_sensitive || $include_author_contact;

    $author_id = (int) $post->post_author;
    $user = get_userdata($author_id);

    // RENDIMIENTO (listado de la tienda): las tarjetas solo usan imagen_destacada. La
    // galería y los documentos eran ~14 KB de los ~16 KB de cada item y ACF formateaba
    // cada imagen (get_post + metadatos). Se cortocircuita su formateo y se quitan.
    $list_skip_fields = ['motor_gallery', 'motor_image', 'informe_de_reparacion', 'documentacion_adjunta', 'documentacion_adicional'];
    $skip_format = null;
    if ($list_mode) {
        $skip_format = function ($check, $value, $hook_post_id, $field) use ($list_skip_fields) {
            if (is_array($field) && isset($field['name']) && in_array($field['name'], $list_skip_fields, true)) {
                return false;
            }
            return $check;
        };
        add_filter('acf/pre_format_value', $skip_format, 10, 4);
    }
    $acf_fields = get_fields($post_id) ?: [];
    if ($skip_format) {
        remove_filter('acf/pre_format_value', $skip_format, 10);
        foreach ($list_skip_fields as $skip_field) {
            unset($acf_fields[$skip_field]);
        }
    }

    $publicacion_item = [
        'id'           => $post_id,
        'uuid'         => get_post_meta($post_id, 'uuid', true),
        'title'        => get_the_title($post_id),
        'slug'         => $post->post_name,
        'link'         => function_exists('motorlan_get_store_url') ? motorlan_get_store_url($post_id) : get_permalink($post_id),
        'status'       => get_field('publicar_acf', $post_id),
        'author_id'    => $author_id,
        'author'       => $user ? [
            'id' => $author_id,
            // display_name y nickname son el email en la mayoría de cuentas (registro con
            // email como login): solo se envían tal cual a dueño/admin/participantes.
            'name' => $show_author_private ? $user->display_name : motorlan_public_author_name($user),
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'nickname' => $show_author_private ? $user->nickname : null,
            'user_login' => $show_author_private ? $user->user_login : null,
            'email' => $show_author_private ? $user->user_email : null,
            'avatar' => get_avatar_url($author_id),
            // Reputación agregada por Motorlan_Reviews_Controller (antes nunca se devolvía
            // y la ficha mostraba siempre "0 valoraciones").
            'acf' => motorlan_get_seller_reputation($author_id),
        ] : null,
        // 'categories' DESACTIVADO (taxonomía categoria sustituida por tipo).
        'categories'   => array(),
        'tipo'         => motorlan_get_post_taxonomy_details($post_id, 'tipo'),
        'acf'          => $acf_fields,
        'imagen_destacada' => motorlan_format_image_for_frontend(get_field('motor_image', $post_id, true)),
    ];

    // Ocultar precio si "Consultar precio" está marcado y el usuario NO es el owner.
    // El owner siempre ve el precio como referencia ($include_sensitive = true).
    if (!$include_sensitive) {
        $consultar_precio = isset($publicacion_item['acf']['precio_negociable']) ? $publicacion_item['acf']['precio_negociable'] : 'no';
        // Normalizar valores legacy: 'Sí', 'si', 'yes', true → ocultar precio
        $is_consult = in_array($consultar_precio, ['yes', 'Sí', 'si', 'true', true], true);
        if ($is_consult && isset($publicacion_item['acf']['precio_de_venta'])) {
            unset($publicacion_item['acf']['precio_de_venta']);
        }
    }

    // Forzar la obtención de la marca si get_fields() no la incluye.
    if (empty($publicacion_item['acf']['marca'])) {
        $marca_id = get_field('marca', $post_id);
        if ($marca_id) {
            $publicacion_item['acf']['marca'] = $marca_id;
        }
    }
    
    // Enrich marca with name if it is an ID
    if (!empty($publicacion_item['acf']['marca'])) {
        $marca_val = $publicacion_item['acf']['marca'];
        if (is_numeric($marca_val) && (int) $marca_val === -1) {
            // Marca pendiente de aprobación
            $pending_brand = get_post_meta($post_id, '_pending_brand_name', true);
            if ($pending_brand) {
                $publicacion_item['marca_name'] = $pending_brand;
                $publicacion_item['pending_brand'] = $pending_brand;
            }
        } elseif (is_numeric($marca_val)) {
            $term = get_term($marca_val);
            if ($term && !is_wp_error($term)) {
                $publicacion_item['marca_name'] = $term->name;
            }
        } elseif (is_object($marca_val) && isset($marca_val->name)) {
            $publicacion_item['marca_name'] = $marca_val->name;
        } elseif (is_array($marca_val) && isset($marca_val['name'])) {
            $publicacion_item['marca_name'] = $marca_val['name'];
        } elseif (is_string($marca_val) && !is_numeric($marca_val)) {
            $publicacion_item['marca_name'] = $marca_val;
        }
    }

    // Marca pendiente de aprobación: leer siempre la meta. Con marca = -1 no hay término
    // asociado y ACF (load_terms) devuelve false, así que la rama "-1" de arriba nunca se
    // ejecutaba y ni la vista previa de aprobación ni el editor mostraban la marca nueva.
    if (empty($publicacion_item['pending_brand'])) {
        $pending_brand = get_post_meta($post_id, '_pending_brand_name', true);
        if ($pending_brand) {
            $publicacion_item['pending_brand'] = $pending_brand;
            $publicacion_item['marca_name'] = $pending_brand;
        }
    }

    return $publicacion_item;
}

/**
 * Nombre público del vendedor sin datos de contacto.
 *
 * display_name y nickname son el email en la mayoría de cuentas (registro con el email
 * como login): se usa nombre + apellidos o el display_name solo si no parece un email
 * ni el login. Si no hay nada seguro devuelve '' (el frontend muestra "Vendedor").
 *
 * @param WP_User $user Usuario.
 * @return string
 */
function motorlan_public_author_name($user) {
    if (!$user instanceof WP_User) {
        return '';
    }

    $looks_private = function ($value) use ($user) {
        $value = trim((string) $value);
        return $value === ''
            || strpos($value, '@') !== false
            || strcasecmp($value, (string) $user->user_login) === 0;
    };

    $full = trim($user->first_name . ' ' . $user->last_name);
    if (!$looks_private($full)) {
        return $full;
    }
    if (!$looks_private($user->display_name)) {
        return $user->display_name;
    }

    return '';
}

/**
 * Reputación pública del vendedor (valoraciones recibidas como vendedor).
 *
 * Lee las metas que agrega Motorlan_Reviews_Controller::aggregate_user_ratings().
 * Con ventas solo si se pide (consulta extra: únicamente en la ficha).
 *
 * @param int  $user_id     Vendedor.
 * @param bool $with_sales  Incluir el número de ventas completadas.
 * @return array{calificacion: float|null, valoraciones: int, ventas?: int}
 */
function motorlan_get_seller_reputation($user_id, $with_sales = false) {
    $user_id = (int) $user_id;
    $rating  = $user_id ? get_user_meta($user_id, 'motorlan_seller_rating', true) : '';
    $count   = $user_id ? (int) get_user_meta($user_id, 'motorlan_seller_count', true) : 0;

    $reputation = [
        'calificacion' => ($rating !== '' && is_numeric($rating) && $count > 0) ? (float) $rating : null,
        'valoraciones' => $count,
    ];

    if ($with_sales) {
        $reputation['ventas'] = motorlan_count_seller_completed_sales($user_id);
    }

    return $reputation;
}

/**
 * Número de ventas completadas de un vendedor (compras con estado completed).
 *
 * @param int $user_id Vendedor.
 * @return int
 */
function motorlan_count_seller_completed_sales($user_id) {
    global $wpdb;

    $user_id = (int) $user_id;
    if (!$user_id) {
        return 0;
    }

    $cached = wp_cache_get('seller_sales_' . $user_id, 'motorlan');
    if (false !== $cached) {
        return (int) $cached;
    }

    $count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT p.ID)
           FROM {$wpdb->posts} p
           INNER JOIN {$wpdb->postmeta} v ON v.post_id = p.ID AND v.meta_key = 'vendedor' AND v.meta_value = %s
           INNER JOIN {$wpdb->postmeta} e ON e.post_id = p.ID AND e.meta_key = 'estado' AND e.meta_value = 'completed'
          WHERE p.post_type = 'compra' AND p.post_status = 'publish'",
        (string) $user_id
    ));

    wp_cache_set('seller_sales_' . $user_id, $count, 'motorlan', 5 * MINUTE_IN_SECONDS);

    return $count;
}

/**
 * Get post taxonomy details.
 *
 * @param int    $post_id  The post ID.
 * @param string $taxonomy The taxonomy slug.
 * @return array
 */
function motorlan_get_post_taxonomy_details($post_id, $taxonomy) {
    $terms_details = [];
    // RENDIMIENTO: get_the_terms usa la caché de términos que WP_Query ya precargó
    // (wp_get_post_terms consultaba siempre la BD, una vez por item del listado).
    $terms = get_the_terms($post_id, $taxonomy);
    if (!is_wp_error($terms) && !empty($terms)) {
        foreach ($terms as $term) {
            $terms_details[] = [
                'id'   => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            ];
        }
    }
    return $terms_details;
}

/**
 * Get post ID by UUID.
 *
 * @param string $uuid The UUID.
 * @return int|null The post ID or null if not found.
 */
function motorlan_get_post_id_by_uuid($uuid) {
    if (empty($uuid)) {
        return null;
    }
    $args = [
        'post_type'      => 'publicacion',
        'meta_key'       => 'uuid',
        'meta_value'     => $uuid,
        'posts_per_page' => 1,
        // Lista explícita: 'any' excluye 'trash' y cualquier estado no
        // registrado ('paused', 'sold'), lo que provocaba 404 en
        // publicaciones visibles en los listados de admin.
        'post_status'    => ['publish', 'pending', 'draft', 'future', 'private', 'trash', 'paused', 'sold'],
        'fields'         => 'ids',
    ];
    $query = new WP_Query($args);
    return $query->have_posts() ? $query->posts[0] : null;
}

/**
 * Normalize checkbox field values that store arrays of choices.
 *
 * When the UI sends plain booleans (true/false or 0/1) for these fields, convert
 * them into the matching choice arrays that ACF expects.
 *
 * @param int    $post_id    Post ID where the field lives.
 * @param string $field_name Field name.
 * @param mixed  $value      Incoming value.
 * @return mixed
 */
function motorlan_normalize_checkbox_acf_value($post_id, $field_name, $value) {
    if (!function_exists('get_field_object')) {
        return $value;
    }

    if (is_array($value)) {
        return $value;
    }

    if ($value === null) {
        return [];
    }

    if (is_bool($value) || in_array($value, [0, 1, '0', '1'], true)) {
        $field_object = get_field_object($field_name, $post_id);
        if (!$field_object || empty($field_object['choices'])) {
            return $value;
        }

        $choices = array_keys($field_object['choices']);
        if (empty($choices)) {
            return $value;
        }

        return (bool) $value ? [$choices[0]] : [];
    }

    return $value;
}

/**
 * Generic callback to get terms from a taxonomy.
 *
 * @param string $taxonomy The taxonomy slug.
 * @return WP_REST_Response
 */
function motorlan_get_taxonomy_terms_callback($taxonomy, $hide_empty = false) {
    $args = [
        'taxonomy'   => $taxonomy,
        'hide_empty' => $hide_empty,
    ];

    // WPML filtra automáticamente get_terms() cuando suppress_filters no está activo
    if ( has_filter( 'wpml_current_language' ) ) {
        $args['suppress_filters'] = false;
    }

    $terms = get_terms($args);
    if (is_wp_error($terms)) {
        return new WP_REST_Response(['message' => $terms->get_error_message()], 500);
    }
    return new WP_REST_Response($terms, 200);
}
/**
 * Generate a formatted slug for a publicación.
 * Format: [Nombre]-[Tipo]-[Marca]-[Referencia]-[Potencia o Par]-[Velocidad]
 *
 * @param array $data Data for the publication (title and acf).
 * @param int   $post_id Optional. Post ID to exclude from uniqueness check.
 * @return string Generated slug.
 */
/**
 * Normaliza el parámetro `tipo` (JSON string, array o escalar) a array de IDs de término.
 *
 * @param mixed $raw
 * @return int[]
 */
function motorlan_parse_tipo_param($raw) {
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $raw;
    }
    if ($raw === null || $raw === '' || $raw === false || is_wp_error($raw) || is_object($raw)) {
        return [];
    }
    $ids = array_map('intval', (array) $raw);
    return array_values(array_filter($ids, function ($id) { return $id > 0; }));
}

/**
 * ¿La publicación es un motor? Solo los motores exigen potencia/velocidad/par.
 * Sin tipo asignado se asume motor (comportamiento legacy).
 *
 * @param int[] $tipo_ids
 * @return bool
 */
function motorlan_tipo_is_motor($tipo_ids) {
    if (empty($tipo_ids)) {
        return true;
    }
    foreach ((array) $tipo_ids as $tipo_id) {
        $term = get_term((int) $tipo_id, 'tipo');
        if ($term && !is_wp_error($term) && $term->slug === 'motor') {
            return true;
        }
    }
    return false;
}

function motorlan_generate_publicacion_slug($data, $post_id = 0) {
    $title = $data['title'] ?? '';
    $acf = $data['acf'] ?? [];
    $parts = [];

    // 1. Tipo (Taxonomy 'tipo')
    $tipo_ids = motorlan_parse_tipo_param($data['tipo'] ?? null);
    $tipo_id = $tipo_ids ? reset($tipo_ids) : null;
    if ($tipo_id) {
        $term = get_term($tipo_id, 'tipo');
        if ($term && !is_wp_error($term)) {
            $parts[] = $term->slug;
        }
    }

    // 2. Marca (ACF 'marca')
    $marca_id = $acf['marca'] ?? null;
    if ($marca_id) {
        $term = get_term($marca_id, 'marca');
        if ($term && !is_wp_error($term)) {
            $parts[] = $term->slug;
        } elseif (is_string($marca_id)) {
            $parts[] = $marca_id;
        }
    }

    // 3. Referencia (ACF 'tipo_o_referencia')
    if (!empty($acf['tipo_o_referencia'])) {
        $parts[] = $acf['tipo_o_referencia'];
    }

    // 4. Potencia o Par (ACF 'potencia' or 'par_nominal')
    if (!empty($acf['potencia'])) {
        $parts[] = $acf['potencia'] . '-kw';
    } elseif (!empty($acf['par_nominal'])) {
        $parts[] = $acf['par_nominal'] . '-nm';
    }

    // Ensure we have at least something, fallback to title if empty parts
    if (empty($parts)) {
        $parts[] = $title;
    }

    $slug = implode('-', $parts);
    $slug = sanitize_title($slug);

    // Ensure uniqueness
    return motorlan_make_slug_unique($slug, $post_id);
}

/**
 * Check if a slug already exists and return a unique version if it does.
 *
 * @param string $slug Original slug.
 * @param int    $post_id Post ID to exclude.
 * @return string Unique slug.
 */
function motorlan_make_slug_unique($slug, $post_id = 0) {
    global $wpdb;

    $check_sql = "SELECT post_name FROM $wpdb->posts WHERE post_name = %s AND post_type = 'publicacion' AND ID != %d LIMIT 1";
    $exists = $wpdb->get_var($wpdb->prepare($check_sql, $slug, $post_id));

    if (!$exists) {
        return $slug;
    }

    // If it exists, append numerical suffix
    $suffix = 2;
    while (true) {
        $alt_slug = $slug . '-' . $suffix;
        $exists = $wpdb->get_var($wpdb->prepare($check_sql, $alt_slug, $post_id));
        if (!$exists) {
            return $alt_slug;
        }
        $suffix++;
    }
}
/**
 * Generate a formatted slug based on a post ID.
 * Useful for refreshing existing posts.
 *
 * @param int $post_id The post ID.
 * @return string Generated unique slug.
 */
function motorlan_generate_slug_by_post_id($post_id) {
    if (!$post_id) return '';
    
    $post = get_post($post_id);
    if (!$post) return '';

    $data = [
        'title' => $post->post_title,
        'acf'   => get_fields($post_id) ?: [],
        'tipo'  => wp_get_post_terms($post_id, 'tipo', ['fields' => 'ids'])
    ];

    return motorlan_generate_publicacion_slug($data, $post_id);
}
