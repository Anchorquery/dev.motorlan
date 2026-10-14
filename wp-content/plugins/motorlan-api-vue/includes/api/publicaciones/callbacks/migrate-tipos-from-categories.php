<?php
/**
 * Sync tipo taxonomy from legacy categoria relationships.
 *
 * @package motorlan-api-vue
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * REST callback that compares legacy categoria terms against current tipo data
 * and updates publicaciones when categoria is missing or diverges from tipo.
 *
 * Dry-run is enabled by default. Pass dry_run=0 or dry_run=false to apply changes.
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response|WP_Error
 */
function motorlan_sync_tipos_from_categories_callback( WP_REST_Request $request ) {
    global $wpdb;

    $page     = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
    $per_page = min( 200, max( 1, absint( $request->get_param( 'per_page' ) ?: 100 ) ) );
    $dry_run  = $request->get_param( 'dry_run' );
    $dry_run  = null === $dry_run ? true : filter_var( $dry_run, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
    $dry_run  = null === $dry_run ? true : $dry_run;
    $offset   = ( $page - 1 ) * $per_page;

    $query = new WP_Query(
        [
            'post_type'      => 'publicacion',
            'post_status'    => 'any',
            'fields'         => 'ids',
            'posts_per_page' => $per_page,
            'offset'         => $offset,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'no_found_rows'  => false,
        ]
    );

    $items = [];
    $stats = [
        'scanned'            => 0,
        'updated'            => 0,
        'matched'            => 0,
        'skipped_no_category' => 0,
        'skipped_unresolved' => 0,
        'cleared_supply'     => 0,
    ];

    foreach ( $query->posts as $post_id ) {
        $stats['scanned']++;

        $legacy_categories = motorlan_sync_get_legacy_categoria_terms( (int) $post_id );
        $current_tipo       = motorlan_sync_get_current_tipo_signature( (int) $post_id );
        $target             = motorlan_sync_resolve_target_from_categories( $legacy_categories, $current_tipo );

        if ( empty( $legacy_categories ) ) {
            $stats['skipped_no_category']++;
            $items[] = motorlan_sync_build_migration_item( $post_id, $legacy_categories, $current_tipo, null, 'no_category' );
            continue;
        }

        if ( empty( $target['family'] ) ) {
            $stats['skipped_unresolved']++;
            $items[] = motorlan_sync_build_migration_item( $post_id, $legacy_categories, $current_tipo, null, 'unresolved_category' );
            continue;
        }

        $needs_update = motorlan_sync_needs_tipo_update( $current_tipo, $target );

        if ( ! $needs_update ) {
            $stats['matched']++;
            $items[] = motorlan_sync_build_migration_item( $post_id, $legacy_categories, $current_tipo, $target, 'already_matching' );
            continue;
        }

        if ( ! $dry_run ) {
            motorlan_sync_apply_tipo_migration( (int) $post_id, $target, $current_tipo );
        }

        if ( 'motor' !== $target['family'] && ! empty( $current_tipo['alimentacion'] ) ) {
            $stats['cleared_supply']++;
        }

        $stats['updated']++;
        $items[] = motorlan_sync_build_migration_item( $post_id, $legacy_categories, $current_tipo, $target, $dry_run ? 'would_update' : 'updated' );
    }

    return new WP_REST_Response(
        [
            'success'    => true,
            'dry_run'    => $dry_run,
            'page'       => $page,
            'per_page'   => $per_page,
            'total'      => (int) $query->found_posts,
            'totalPages' => (int) $query->max_num_pages,
            'stats'      => $stats,
            'items'      => $items,
        ],
        200
    );
}

/**
 * Fetch legacy categoria terms directly from the DB, even if the taxonomy is not
 * registered anymore.
 *
 * @param int $post_id Post ID.
 * @return array<int, array<string, mixed>>
 */
function motorlan_sync_get_legacy_categoria_terms( $post_id ) {
    global $wpdb;

    $sql = "
        SELECT
            t.term_id,
            t.name,
            t.slug,
            tt.parent
        FROM {$wpdb->terms} t
        INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
        INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
        WHERE tt.taxonomy = %s
          AND tr.object_id = %d
        ORDER BY tt.parent ASC, t.name ASC
    ";

    $rows = $wpdb->get_results( $wpdb->prepare( $sql, 'categoria', $post_id ), ARRAY_A );
    if ( empty( $rows ) ) {
        return [];
    }

    $map = [];
    foreach ( $rows as $row ) {
        $row['term_id'] = (int) $row['term_id'];
        $row['parent']  = (int) $row['parent'];
        $map[ $row['term_id'] ] = $row;
    }

    $expanded = [];
    foreach ( $map as $term ) {
        motorlan_sync_append_term_with_ancestors( $expanded, $map, $term );
    }

    return array_values( $expanded );
}

/**
 * Append a term and its ancestors to a collection.
 *
 * @param array<int, array<string, mixed>> $expanded Collection passed by reference.
 * @param array<int, array<string, mixed>> $map Term map indexed by term_id.
 * @param array<string, mixed> $term Current term.
 * @return void
 */
function motorlan_sync_append_term_with_ancestors( array &$expanded, array $map, array $term ) {
    $term_id = (int) $term['term_id'];
    if ( isset( $expanded[ $term_id ] ) ) {
        return;
    }

    $expanded[ $term_id ] = $term;

    $parent = (int) ( $term['parent'] ?? 0 );
    while ( $parent > 0 && isset( $map[ $parent ] ) ) {
        if ( isset( $expanded[ $parent ] ) ) {
            break;
        }
        $expanded[ $parent ] = $map[ $parent ];
        $parent              = (int) ( $map[ $parent ]['parent'] ?? 0 );
    }
}

/**
 * Read the current tipo family and tipo_de_alimentacion value.
 *
 * @param int $post_id Post ID.
 * @return array{family:?string,alimentacion:?string,raw_terms:array<int,string>}
 */
function motorlan_sync_get_current_tipo_signature( $post_id ) {
    $raw_terms = wp_get_post_terms( $post_id, 'tipo', [ 'fields' => 'slugs' ] );
    if ( is_wp_error( $raw_terms ) ) {
        $raw_terms = [];
    }

    $family = null;
    foreach ( $raw_terms as $slug ) {
        $family = motorlan_sync_normalize_tipo_family( (string) $slug );
        if ( $family ) {
            break;
        }
    }

    $alimentacion = function_exists( 'get_field' )
        ? get_field( 'tipo_de_alimentacion', $post_id )
        : get_post_meta( $post_id, 'tipo_de_alimentacion', true );

    return [
        'family'        => $family,
        'alimentacion'  => motorlan_sync_normalize_alimentacion( $alimentacion ),
        'raw_terms'     => array_values( array_filter( array_map( 'strval', $raw_terms ) ) ),
    ];
}

/**
 * Resolve the target type from legacy categoria terms.
 *
 * @param array<int, array<string, mixed>> $legacy_categories Legacy category terms.
 * @param array<string, mixed> $current_tipo Current tipo signature.
 * @return array{family:?string,alimentacion:?string,source:?string}
 */
function motorlan_sync_resolve_target_from_categories( array $legacy_categories, array $current_tipo ) {
    $haystack = motorlan_sync_term_text_haystack( $legacy_categories );

    if ( '' === $haystack ) {
        return [
            'family'       => null,
            'alimentacion' => null,
            'source'       => null,
        ];
    }

    if ( preg_match( '/\bmotor\b/u', $haystack ) ) {
        $alimentacion = null;
        if ( preg_match( '/\b(dc|continua|directa|corriente\s+directa)\b/u', $haystack ) ) {
            $alimentacion = 'dc';
        } elseif ( preg_match( '/\b(ac|alterna|corriente\s+alterna)\b/u', $haystack ) ) {
            $alimentacion = 'ac';
        }

        return [
            'family'       => 'motor',
            'alimentacion' => $alimentacion,
            'source'       => 'categoria',
        ];
    }

    if ( preg_match( '/\bregulador\b/u', $haystack ) ) {
        return [
            'family'       => 'regulador',
            'alimentacion' => null,
            'source'       => 'categoria',
        ];
    }

    if ( preg_match( '/\b(repuesto|otros?|recambio|spare)\b/u', $haystack ) ) {
        return [
            'family'       => 'otro-repuesto',
            'alimentacion' => null,
            'source'       => 'categoria',
        ];
    }

    $direct_family = motorlan_sync_normalize_tipo_family( $haystack );
    if ( $direct_family ) {
        return [
            'family'       => $direct_family,
            'alimentacion' => 'motor' === $direct_family ? ( $current_tipo['alimentacion'] ?? 'ac' ) : null,
            'source'       => 'categoria',
        ];
    }

    return [
        'family'       => null,
        'alimentacion' => null,
        'source'       => null,
    ];
}

/**
 * Build a searchable string from categoria terms and ancestors.
 *
 * @param array<int, array<string, mixed>> $legacy_categories Legacy terms.
 * @return string
 */
function motorlan_sync_term_text_haystack( array $legacy_categories ) {
    $parts = [];

    foreach ( $legacy_categories as $term ) {
        $parts[] = (string) ( $term['name'] ?? '' );
        $parts[] = (string) ( $term['slug'] ?? '' );
    }

    $text = trim( strtolower( remove_accents( implode( ' ', $parts ) ) ) );
    $text = preg_replace( '/\s+/', ' ', $text );

    return is_string( $text ) ? $text : '';
}

/**
 * Normalize a tipo family slug from a raw string.
 *
 * @param string $value Raw value.
 * @return string|null
 */
function motorlan_sync_normalize_tipo_family( $value ) {
    $value = strtolower( remove_accents( trim( (string) $value ) ) );

    if ( '' === $value ) {
        return null;
    }

    if ( preg_match( '/\bmotor\b/', $value ) ) {
        return 'motor';
    }

    if ( preg_match( '/\bregulador\b/', $value ) ) {
        return 'regulador';
    }

    if ( preg_match( '/\b(repuesto|otros?)\b/', $value ) ) {
        return 'otro-repuesto';
    }

    if ( in_array( $value, [ 'otro-repuesto', 'repuesto', 'otros' ], true ) ) {
        return 'otro-repuesto';
    }

    return null;
}

/**
 * Normalize ACF alimentarion values to ac/dc.
 *
 * @param mixed $value Raw value.
 * @return string|null
 */
function motorlan_sync_normalize_alimentacion( $value ) {
    $value = strtolower( remove_accents( trim( (string) $value ) ) );

    if ( '' === $value ) {
        return null;
    }

    if ( in_array( $value, [ 'ac', 'alterna', 'alterna (c.a.)', 'alterna (ac)', 'corriente alterna' ], true ) ) {
        return 'ac';
    }

    if ( in_array( $value, [ 'dc', 'continua', 'continua (c.c.)', 'continua (dc)', 'corriente directa' ], true ) ) {
        return 'dc';
    }

    if ( preg_match( '/\bac\b/', $value ) ) {
        return 'ac';
    }

    if ( preg_match( '/\bdc\b/', $value ) ) {
        return 'dc';
    }

    return null;
}

/**
 * Check whether the current tipo needs to be replaced by categoria.
 *
 * @param array<string, mixed> $current Current tipo signature.
 * @param array<string, mixed> $target Target tipo signature.
 * @return bool
 */
function motorlan_sync_needs_tipo_update( array $current, array $target ) {
    $current_family = $current['family'] ?? null;
    $current_alim   = $current['alimentacion'] ?? null;
    $target_family  = $target['family'] ?? null;
    $target_alim    = $target['alimentacion'] ?? null;

    if ( ! $target_family ) {
        return false;
    }

    if ( ! $current_family ) {
        return true;
    }

    if ( $current_family !== $target_family ) {
        return true;
    }

    if ( 'motor' === $target_family && $target_alim && $current_alim !== $target_alim ) {
        return true;
    }

    if ( 'motor' !== $target_family && ! empty( $current_alim ) ) {
        return true;
    }

    return false;
}

/**
 * Apply the update to the post.
 *
 * @param int $post_id Post ID.
 * @param array<string, mixed> $target Target signature.
 * @param array<string, mixed> $current Current signature.
 * @return void
 */
function motorlan_sync_apply_tipo_migration( $post_id, array $target, array $current ) {
    $term_id = motorlan_sync_resolve_tipo_term_id( $target['family'] ?? null );
    if ( $term_id ) {
        wp_set_post_terms( $post_id, [ $term_id ], 'tipo', false );
    }

    if ( 'motor' === ( $target['family'] ?? null ) ) {
        $alimentacion = $target['alimentacion'] ?? null;
        if ( ! $alimentacion ) {
            $alimentacion = $current['alimentacion'] ?? 'ac';
        }

        if ( function_exists( 'update_field' ) ) {
            update_field( 'tipo_de_alimentacion', $alimentacion, $post_id );
        } else {
            update_post_meta( $post_id, 'tipo_de_alimentacion', $alimentacion );
        }

        return;
    }

    if ( function_exists( 'delete_field' ) ) {
        delete_field( 'tipo_de_alimentacion', $post_id );
    } else {
        delete_post_meta( $post_id, 'tipo_de_alimentacion' );
        delete_post_meta( $post_id, '_tipo_de_alimentacion' );
    }
}

/**
 * Resolve a tipo taxonomy term ID by canonical family slug.
 *
 * @param string|null $family Canonical family slug.
 * @return int|null
 */
function motorlan_sync_resolve_tipo_term_id( $family ) {
    $family = motorlan_sync_normalize_tipo_family( (string) $family );
    if ( ! $family ) {
        return null;
    }

    $term = get_term_by( 'slug', $family, 'tipo' );
    if ( $term && ! is_wp_error( $term ) ) {
        return (int) $term->term_id;
    }

    $term = get_term_by( 'name', ucfirst( str_replace( '-', ' ', $family ) ), 'tipo' );
    if ( $term && ! is_wp_error( $term ) ) {
        return (int) $term->term_id;
    }

    return null;
}

/**
 * Build a compact migration item for the response.
 *
 * @param int $post_id Post ID.
 * @param array<int, array<string, mixed>> $legacy_categories Legacy categories.
 * @param array<string, mixed> $current Current tipo signature.
 * @param array<string, mixed>|null $target Target signature.
 * @param string $action Action label.
 * @return array<string, mixed>
 */
function motorlan_sync_build_migration_item( $post_id, array $legacy_categories, array $current, ?array $target, $action ) {
    return [
        'post_id'   => (int) $post_id,
        'title'     => get_the_title( $post_id ),
        'action'    => $action,
        'category'  => array_map(
            static function ( $term ) {
                return [
                    'term_id' => (int) ( $term['term_id'] ?? 0 ),
                    'name'    => (string) ( $term['name'] ?? '' ),
                    'slug'    => (string) ( $term['slug'] ?? '' ),
                ];
            },
            $legacy_categories
        ),
        'current'   => $current,
        'target'    => $target,
    ];
}
