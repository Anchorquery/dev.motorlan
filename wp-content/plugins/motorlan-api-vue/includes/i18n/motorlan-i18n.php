<?php
/**
 * Shared i18n helpers for WordPress and the Vue app.
 *
 * @package motorlan-api-vue
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Return the supported locale map used by the Vue app.
 *
 * @return array<string, array{label:string,locale:string,isRTL:bool}>
 */
function motorlan_get_supported_locale_map() {
    return [
        'es' => [
            'label'  => 'Español',
            'locale' => 'es-ES',
            'isRTL'  => false,
        ],
        'en' => [
            'label'  => 'English',
            'locale' => 'en-US',
            'isRTL'  => false,
        ],
        'eu' => [
            'label'  => 'Euskera',
            'locale' => 'eu-ES',
            'isRTL'  => false,
        ],
        'fr' => [
            'label'  => 'Français',
            'locale' => 'fr-FR',
            'isRTL'  => false,
        ],
        'ar' => [
            'label'  => 'العربية',
            'locale' => 'ar',
            'isRTL'  => true,
        ],
    ];
}

/**
 * Check whether WPML string translation is available.
 *
 * @return bool
 */
function motorlan_is_wpml_active() {
    return defined( 'ICL_SITEPRESS_VERSION' ) || function_exists( 'icl_register_string' ) || has_filter( 'wpml_translate_single_string' );
}

/**
 * Register a single string with WPML when available.
 *
 * Forces the string's source language to the Vue i18n source locale (es by
 * default) so WPML String Translation marks Spanish as the original and
 * other locales as translations — not the other way around.
 *
 * @param string $context String context.
 * @param string $name String name.
 * @param string $value String value.
 * @return void
 */
function motorlan_wpml_register_string( $context, $name, $value ) {
    if ( ! motorlan_is_wpml_active() ) {
        return;
    }

    $source_lang = motorlan_get_vue_i18n_source_locale();

    // Call both APIs for maximum compatibility across WPML versions.
    // `icl_register_string` is the legacy helper, `wpml_register_single_string`
    // is the modern action. Either one alone fails on some setups.
    if ( function_exists( 'icl_register_string' ) ) {
        icl_register_string( $context, $name, $value, false, $source_lang );
    }
    do_action( 'wpml_register_single_string', $context, $name, $value, false, $source_lang );

    // Older WPML builds ignore the language argument on register, and
    // `icl_register_string` does not update the `value` of an existing row.
    // Realign both language and value to the JSON source.
    motorlan_wpml_force_string_language( $context, $name, $source_lang, $value );
}

/**
 * Make sure a registered string's source language AND source value match what
 * the Vue source locale expects.
 *
 * Useful when previous code paths registered strings under the WordPress site
 * language (e.g. English) with the wrong value, or when `icl_register_string`
 * — being idempotent on existing rows — left an obsolete `value` behind.
 *
 * @param string $context String context.
 * @param string $name    String name.
 * @param string $locale  Desired source language code (e.g. "es").
 * @param string $value   Desired source value from the JSON.
 * @return void
 */
function motorlan_wpml_force_string_language( $context, $name, $locale, $value = null ) {
    global $wpdb;

    $table = $wpdb->prefix . 'icl_strings';
    if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
        return;
    }

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, language, value FROM {$table} WHERE context = %s AND name = %s LIMIT 1",
            $context,
            $name
        )
    );

    if ( ! $row ) {
        return;
    }

    $updates = [];
    if ( $row->language !== $locale ) {
        $updates['language'] = $locale;
    }
    if ( null !== $value && $row->value !== (string) $value ) {
        $updates['value'] = (string) $value;
    }

    if ( empty( $updates ) ) {
        return;
    }

    $wpdb->update( $table, $updates, [ 'id' => (int) $row->id ] );
}

/**
 * Translate a single string through WPML when available.
 *
 * @param string $context String context.
 * @param string $name String name.
 * @param string $value Original string value.
 * @param string $locale Target locale.
 * @return string
 */
function motorlan_wpml_translate_string( $context, $name, $value, $locale ) {
    if ( ! motorlan_is_wpml_active() ) {
        return $value;
    }

    $translated = apply_filters( 'wpml_translate_single_string', $value, $context, $name, $locale );

    return is_string( $translated ) && '' !== trim( $translated ) ? $translated : $value;
}

/**
 * Recursively register a translation tree in WPML.
 *
 * @param string               $context Context prefix.
 * @param array<string, mixed> $messages Translation tree.
 * @param string               $prefix   Current key prefix.
 * @return void
 */
function motorlan_register_wpml_translation_tree( $context, array $messages, $prefix = '' ) {
    foreach ( $messages as $key => $value ) {
        $path = '' === $prefix ? (string) $key : $prefix . '.' . $key;

        if ( is_array( $value ) ) {
            motorlan_register_wpml_translation_tree( $context, $value, $path );
            continue;
        }

        motorlan_wpml_register_string( $context, $path, (string) $value );
    }
}

/**
 * Recursively translate a tree using WPML.
 *
 * @param string               $context Context prefix.
 * @param array<string, mixed> $messages Translation tree.
 * @param string               $locale   Locale code.
 * @param string               $prefix   Current key prefix.
 * @return array<string, mixed>
 */
function motorlan_translate_wpml_translation_tree( $context, array $messages, $locale, $prefix = '' ) {
    $translated = [];

    foreach ( $messages as $key => $value ) {
        $path = '' === $prefix ? (string) $key : $prefix . '.' . $key;

        if ( is_array( $value ) ) {
            $translated[ $key ] = motorlan_translate_wpml_translation_tree( $context, $value, $locale, $path );
            continue;
        }

        $translated[ $key ] = motorlan_wpml_translate_string( $context, $path, (string) $value, $locale );
    }

    return $translated;
}

/**
 * Normalize a locale string to its language code.
 *
 * @param string|null $locale Locale or language code.
 * @return string
 */
function motorlan_normalize_locale_code( $locale ) {
    $supported = array_keys( motorlan_get_supported_locale_map() );
    $default    = 'es';

    if ( ! is_string( $locale ) || '' === trim( $locale ) ) {
        return $default;
    }

    $parts = preg_split( '/[-_]/', strtolower( trim( $locale ) ) );
    $code  = $parts[0] ?? '';

    return in_array( $code, $supported, true ) ? $code : $default;
}

/**
 * Check whether a locale should be treated as RTL.
 *
 * @param string|null $locale Locale or language code.
 * @return bool
 */
function motorlan_is_rtl_locale( $locale ) {
    $code = motorlan_normalize_locale_code( $locale );
    return in_array( $code, [ 'ar' ], true );
}

/**
 * Resolve the current language code from WordPress.
 *
 * @return string
 */
function motorlan_get_current_language_code() {
    // Documented WPML / Polylang filter — returns the language that should be
    // used to render the current request. Honors per-URL language detection
    // and WPML's runtime overrides better than reading ICL_LANGUAGE_CODE,
    // which can lag behind in some hook contexts.
    $filtered = apply_filters( 'wpml_current_language', null );
    if ( is_string( $filtered ) && '' !== $filtered ) {
        return motorlan_normalize_locale_code( $filtered );
    }

    if ( function_exists( 'pll_current_language' ) ) {
        $pll_locale = pll_current_language( 'slug' );
        if ( $pll_locale ) {
            return motorlan_normalize_locale_code( $pll_locale );
        }
    }

    if ( defined( 'ICL_LANGUAGE_CODE' ) && ICL_LANGUAGE_CODE ) {
        return motorlan_normalize_locale_code( ICL_LANGUAGE_CODE );
    }

    return motorlan_normalize_locale_code( get_locale() );
}

/**
 * Convert supported locale map into a list consumable by Vue language switchers.
 *
 * @return array<int, array<string, mixed>>
 */
function motorlan_get_supported_languages() {
    $supported = motorlan_get_supported_locale_map();
    $languages  = [];

    foreach ( $supported as $code => $meta ) {
        $languages[] = [
            'code'            => $code,
            'i18nLang'        => $code,
            'label'           => $meta['label'],
            'locale'          => $meta['locale'],
            'isRTL'           => $meta['isRTL'],
            'active'          => $code === motorlan_get_current_language_code(),
            'native_name'     => $meta['label'],
            'translated_name' => $meta['label'],
        ];
    }

    if ( function_exists( 'icl_get_languages' ) ) {
        $languages_data = icl_get_languages( 'skip_missing=0&orderby=code' );

        if ( is_array( $languages_data ) && ! empty( $languages_data ) ) {
            $languages = [];

            foreach ( $languages_data as $lang ) {
                $code = motorlan_normalize_locale_code( $lang['language_code'] ?? ( $lang['code'] ?? '' ) );

                $label = $lang['native_name'] ?? $lang['translated_name'] ?? ( $supported[ $code ]['label'] ?? strtoupper( $code ) );

                $languages[] = [
                    'code'            => $code,
                    'i18nLang'        => $code,
                    'label'           => $label,
                    'locale'          => $lang['default_locale'] ?? ( $supported[ $code ]['locale'] ?? '' ),
                    'isRTL'           => motorlan_is_rtl_locale( $code ),
                    'active'          => ! empty( $lang['active'] ),
                    'native_name'     => $lang['native_name'] ?? $label,
                    'translated_name' => $lang['translated_name'] ?? $label,
                    'url'             => $lang['url'] ?? '',
                ];
            }
        }
    } elseif ( function_exists( 'pll_the_languages' ) ) {
        $pll_languages = pll_the_languages(
            [
                'raw'           => 1,
                'hide_if_empty' => 0,
            ]
        );

        if ( is_array( $pll_languages ) && ! empty( $pll_languages ) ) {
            $languages = [];

            foreach ( $pll_languages as $lang ) {
                $code  = motorlan_normalize_locale_code( $lang['slug'] ?? '' );
                $label = $lang['name'] ?? ( $supported[ $code ]['label'] ?? strtoupper( $code ) );

                $languages[] = [
                    'code'            => $code,
                    'i18nLang'        => $code,
                    'label'           => $label,
                    'locale'          => $lang['locale'] ?? ( $supported[ $code ]['locale'] ?? '' ),
                    'isRTL'           => motorlan_is_rtl_locale( $code ),
                    'active'          => ! empty( $lang['current_lang'] ),
                    'native_name'     => $label,
                    'translated_name' => $label,
                    'url'             => $lang['url'] ?? '',
                ];
            }
        }
    }

    return $languages;
}

/**
 * Read the bundled Vue i18n JSON files as the default translation source.
 *
 * @return array<string, array<string, mixed>>
 */
function motorlan_get_base_vue_i18n_messages() {
    $base_dir = MOTORLAN_API_VUE_PATH . 'app/src/plugins/i18n/locales/';
    $messages = [];

    foreach ( glob( $base_dir . '*.json' ) as $file ) {
        $locale = basename( $file, '.json' );
        $raw    = file_get_contents( $file );

        if ( false === $raw ) {
            continue;
        }

        $decoded = json_decode( $raw, true );
        if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
            continue;
        }

        $messages[ $locale ] = $decoded;
    }

    return $messages;
}

/**
 * Source locale used when registering strings in WPML String Translation.
 *
 * All strings are registered once in this locale; WPML provides per-language
 * overrides via its translation table.
 *
 * @return string
 */
function motorlan_get_vue_i18n_source_locale() {
    return apply_filters( 'motorlan_vue_i18n_source_locale', 'es' );
}

/**
 * WPML String Translation context shared by every Vue string.
 *
 * @return string
 */
function motorlan_get_vue_i18n_wpml_context() {
    return apply_filters( 'motorlan_vue_i18n_wpml_context', 'motorlan-vue' );
}

/**
 * Register bundled strings (source locale only) with WPML String Translation.
 *
 * Translations for the other locales are managed entirely from WPML once
 * the source strings are registered. On first sync (and when JSON values
 * change) the bundled translations for other locales are also seeded into
 * WPML so existing translators see the work already done.
 *
 * @return void
 */
function motorlan_sync_vue_i18n_strings_to_wpml() {
    if ( ! motorlan_is_wpml_active() ) {
        return;
    }

    // Bump this whenever the seeder logic changes to force a one-shot
    // re-import on the next request after a deploy.
    $seeder_version = 3;

    // RENDIMIENTO: salida rápida en cada petición. Antes se leían y decodificaban los
    // ~160 KB de JSON y se consultaban 3 opciones sin autoload solo para comprobar que
    // nada había cambiado. La huella de mtime+tamaño (opción con autoload) lo evita.
    $files_stamp = motorlan_get_vue_i18n_files_stamp( $seeder_version );
    if ( get_option( 'motorlan_vue_i18n_files_stamp' ) === $files_stamp ) {
        return;
    }

    $messages = motorlan_get_base_vue_i18n_messages();
    $source   = motorlan_get_vue_i18n_source_locale();
    $tree     = $messages[ $source ] ?? [];

    if ( empty( $tree ) ) {
        return;
    }

    $context = motorlan_get_vue_i18n_wpml_context();

    if ( (int) get_option( 'motorlan_vue_i18n_seeder_version', 0 ) < $seeder_version ) {
        delete_option( 'motorlan_vue_i18n_seed_hash' );
        update_option( 'motorlan_vue_i18n_seeder_version', $seeder_version, false );
    }

    // RENDIMIENTO: registrar ~1000 cadenas + realign + migrate en CADA petición
    // (también REST/AJAX) suponía miles de consultas por guardado. Solo se repite
    // cuando cambian los JSON o la versión del seeder.
    $sync_fingerprint = md5( wp_json_encode( $messages ) . '|' . $seeder_version );
    if ( get_option( 'motorlan_vue_i18n_sync_hash' ) === $sync_fingerprint
        && get_option( 'motorlan_vue_i18n_seed_hash' ) === md5( wp_json_encode( $messages ) ) ) {
        update_option( 'motorlan_vue_i18n_files_stamp', $files_stamp, true );
        return;
    }

    // Register the source strings (idempotent).
    motorlan_register_wpml_translation_tree( $context, $tree );

    // One-shot fix: any string in this context that was previously stored
    // under a different source language (e.g. English because the WP site
    // default was en when the plugin was first activated) is realigned to
    // the Vue source locale so WPML shows the Spanish flag as origin and
    // English / Basque / French / Arabic as targets.
    motorlan_wpml_realign_context_source_language( $context, $source );

    // Migrate translations stored under legacy `motorlan-vue-<locale>`
    // contexts into the new single `motorlan-vue` context. Idempotent: only
    // copies translations that aren't already present on the target string.
    motorlan_migrate_legacy_wpml_contexts();

    // Seed translations from the bundled JSONs into WPML String Translation.
    // Only runs when the JSON fingerprint changes so we don't hammer WPML on
    // every request.
    update_option( 'motorlan_vue_i18n_sync_hash', $sync_fingerprint, false );

    $fingerprint = md5( wp_json_encode( $messages ) );
    if ( get_option( 'motorlan_vue_i18n_seed_hash' ) === $fingerprint ) {
        update_option( 'motorlan_vue_i18n_files_stamp', $files_stamp, true );
        return;
    }

    foreach ( $messages as $locale => $locale_tree ) {
        if ( $locale === $source ) {
            continue;
        }
        motorlan_seed_wpml_translations_from_tree( $context, $tree, $locale_tree, $locale );
    }

    update_option( 'motorlan_vue_i18n_seed_hash', $fingerprint, false );
    update_option( 'motorlan_vue_i18n_files_stamp', $files_stamp, true );
}

/**
 * Cheap fingerprint of the bundled Vue i18n JSON files (path + mtime + size),
 * used to skip the WPML sync without reading the files.
 *
 * @param int $seeder_version Seeder logic version.
 * @return string
 */
function motorlan_get_vue_i18n_files_stamp( $seeder_version ) {
    $parts = [ (string) $seeder_version ];

    foreach ( glob( MOTORLAN_API_VUE_PATH . 'app/src/plugins/i18n/locales/*.json' ) ?: [] as $file ) {
        $parts[] = basename( $file ) . ':' . (int) @filemtime( $file ) . ':' . (int) @filesize( $file );
    }

    return md5( implode( '|', $parts ) );
}

add_action( 'init', 'motorlan_sync_vue_i18n_strings_to_wpml', 20 );

/**
 * Realign every string in the given WPML context to use $source_lang as the
 * source language. Also moves any translation that lived under the new
 * source language out of `wp_icl_string_translations` (since the source
 * value is now authoritative).
 *
 * Safe to call repeatedly: only touches rows that disagree with $source_lang.
 *
 * @param string $context     WPML context.
 * @param string $source_lang Desired source language code (e.g. "es").
 * @return void
 */
function motorlan_wpml_realign_context_source_language( $context, $source_lang ) {
    global $wpdb;

    $strings_table       = $wpdb->prefix . 'icl_strings';
    $translations_table  = $wpdb->prefix . 'icl_string_translations';

    if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $strings_table ) ) ) {
        return;
    }

    $misaligned = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT id FROM {$strings_table} WHERE context = %s AND language != %s",
            $context,
            $source_lang
        )
    );

    if ( empty( $misaligned ) ) {
        return;
    }

    foreach ( $misaligned as $string_id ) {
        $string_id = (int) $string_id;

        $wpdb->update(
            $strings_table,
            [ 'language' => $source_lang ],
            [ 'id' => $string_id ]
        );

        // Remove any translation row that points at the (new) source language —
        // the source value is now the authoritative one for that locale.
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $translations_table ) ) ) {
            $wpdb->delete(
                $translations_table,
                [
                    'string_id' => $string_id,
                    'language'  => $source_lang,
                ]
            );
        }
    }
}

/**
 * Look up the WPML string id for a given context/name pair.
 *
 * @param string $context String context.
 * @param string $name    String name (dotted path).
 * @return int|null
 */
function motorlan_wpml_get_string_id( $context, $name ) {
    global $wpdb;

    $table = $wpdb->prefix . 'icl_strings';
    if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
        return null;
    }

    $id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$table} WHERE context = %s AND name = %s LIMIT 1",
            $context,
            $name
        )
    );

    return $id ? (int) $id : null;
}

/**
 * Persist a translation in WPML String Translation tables.
 *
 * Uses the documented `icl_add_string_translation` helper when available and
 * falls back to a direct insert/update against `wp_icl_string_translations`.
 *
 * @param int    $string_id WPML string id.
 * @param string $locale    Target locale code.
 * @param string $value     Translation value.
 * @return void
 */
function motorlan_wpml_add_string_translation( $string_id, $locale, $value ) {
    $status = defined( 'ICL_TM_COMPLETE' ) ? ICL_TM_COMPLETE : 10;

    if ( function_exists( 'icl_add_string_translation' ) ) {
        icl_add_string_translation( $string_id, $locale, $value, $status );
        return;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'icl_string_translations';
    if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
        return;
    }

    $existing_id = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT id FROM {$table} WHERE string_id = %d AND language = %s LIMIT 1",
            $string_id,
            $locale
        )
    );

    if ( $existing_id ) {
        $wpdb->update(
            $table,
            [
                'value'  => $value,
                'status' => $status,
            ],
            [ 'id' => (int) $existing_id ]
        );
        return;
    }

    $wpdb->insert(
        $table,
        [
            'string_id'        => $string_id,
            'language'         => $locale,
            'status'           => $status,
            'value'            => $value,
            'translation_date' => current_time( 'mysql' ),
        ]
    );
}

/**
 * Seed WPML String Translation with the bundled translations from a target locale.
 *
 * Walks the source tree path-by-path and pushes the matching value from the
 * target tree into WPML. Existing user translations are NOT overwritten
 * unless they are empty / equal to the source value.
 *
 * @param string               $context     WPML context.
 * @param array<string, mixed> $source_tree Source locale tree (es).
 * @param array<string, mixed> $target_tree Bundled target locale tree.
 * @param string               $locale      Target locale code.
 * @param string               $prefix      Internal path accumulator.
 * @return void
 */
function motorlan_seed_wpml_translations_from_tree( $context, array $source_tree, array $target_tree, $locale, $prefix = '' ) {
    foreach ( $source_tree as $key => $source_value ) {
        $path = '' === $prefix ? (string) $key : $prefix . '.' . $key;

        if ( is_array( $source_value ) ) {
            $child_target = ( isset( $target_tree[ $key ] ) && is_array( $target_tree[ $key ] ) )
                ? $target_tree[ $key ]
                : [];
            motorlan_seed_wpml_translations_from_tree( $context, $source_value, $child_target, $locale, $path );
            continue;
        }

        if ( ! array_key_exists( $key, $target_tree ) || is_array( $target_tree[ $key ] ) ) {
            continue;
        }

        $target_value = (string) $target_tree[ $key ];
        if ( '' === $target_value || $target_value === (string) $source_value ) {
            continue;
        }

        // Skip when WPML already has a non-source translation for this locale.
        $existing = apply_filters( 'wpml_translate_single_string', (string) $source_value, $context, $path, $locale );
        if ( is_string( $existing ) && '' !== $existing && $existing !== (string) $source_value ) {
            continue;
        }

        $string_id = motorlan_wpml_get_string_id( $context, $path );
        if ( ! $string_id ) {
            continue;
        }

        motorlan_wpml_add_string_translation( $string_id, $locale, $target_value );
    }
}

/**
 * Force the seeder to re-run on the next request. Useful from WP-CLI or a
 * one-off button in the admin Translations page.
 *
 * @return void
 */
function motorlan_vue_i18n_force_reseed() {
    delete_option( 'motorlan_vue_i18n_seed_hash' );
    delete_option( 'motorlan_vue_i18n_files_stamp' );
}

/**
 * Migrate user-edited translations from legacy `motorlan-vue-<locale>` contexts
 * into the single `motorlan-vue` context.
 *
 * For each legacy string, look up the matching string id in the target
 * context. Copy every translation row whose language is NOT already present
 * for the target string. Source values are not touched.
 *
 * @return array{migrated_strings:int,migrated_translations:int}
 */
function motorlan_migrate_legacy_wpml_contexts() {
    global $wpdb;

    $strings_table     = $wpdb->prefix . 'icl_strings';
    $translations_table = $wpdb->prefix . 'icl_string_translations';
    $target_context    = motorlan_get_vue_i18n_wpml_context();

    $result = [ 'migrated_strings' => 0, 'migrated_translations' => 0 ];

    if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $strings_table ) ) ) {
        return $result;
    }
    if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $translations_table ) ) ) {
        return $result;
    }

    // Pull every legacy row.
    $legacy_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, name, value, language, context FROM {$strings_table}
              WHERE context LIKE %s AND context != %s",
            $wpdb->esc_like( $target_context ) . '-%',
            $target_context
        )
    );

    if ( empty( $legacy_rows ) ) {
        return $result;
    }

    $supported   = array_keys( motorlan_get_supported_locale_map() );
    $source_lang = motorlan_get_vue_i18n_source_locale();
    $prefix_len  = strlen( $target_context . '-' );

    foreach ( $legacy_rows as $legacy ) {
        // Find the target row (registered by the current sync run).
        $target_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$strings_table} WHERE context = %s AND name = %s LIMIT 1",
                $target_context,
                $legacy->name
            )
        );

        if ( ! $target_id ) {
            continue;
        }

        $target_id = (int) $target_id;
        $result['migrated_strings']++;

        // Pull every translation that lived under the legacy string id.
        $legacy_translations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT language, value, status FROM {$translations_table} WHERE string_id = %d",
                (int) $legacy->id
            )
        );

        // The actual content language of a legacy string is encoded in its
        // context (`motorlan-vue-<locale>`), not in the `language` column —
        // that column holds whatever the WP site default was when the row
        // was registered. Derive the real locale from the context name.
        $context_locale = substr( $legacy->context, $prefix_len );

        if (
            $context_locale
            && in_array( $context_locale, $supported, true )
            && $context_locale !== $source_lang
            && '' !== (string) $legacy->value
        ) {
            $legacy_translations[] = (object) [
                'language' => $context_locale,
                'value'    => $legacy->value,
                'status'   => defined( 'ICL_TM_COMPLETE' ) ? ICL_TM_COMPLETE : 10,
            ];
        }

        foreach ( $legacy_translations as $trans ) {
            if ( ! $trans->language || $trans->language === $source_lang ) {
                continue;
            }

            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$translations_table} WHERE string_id = %d AND language = %s LIMIT 1",
                    $target_id,
                    $trans->language
                )
            );

            if ( $exists ) {
                continue;
            }

            $wpdb->insert(
                $translations_table,
                [
                    'string_id'        => $target_id,
                    'language'         => $trans->language,
                    'value'            => $trans->value,
                    'status'           => (int) $trans->status,
                    'translation_date' => current_time( 'mysql' ),
                ]
            );
            $result['migrated_translations']++;
        }
    }

    return $result;
}

/**
 * Delete every legacy `motorlan-vue-<locale>` context together with its
 * translations. Call only after `motorlan_migrate_legacy_wpml_contexts()`
 * has copied the translations into the modern context.
 *
 * @return int Number of legacy strings removed.
 */
function motorlan_purge_legacy_wpml_contexts() {
    global $wpdb;

    $strings_table     = $wpdb->prefix . 'icl_strings';
    $translations_table = $wpdb->prefix . 'icl_string_translations';
    $target_context    = motorlan_get_vue_i18n_wpml_context();

    if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $strings_table ) ) ) {
        return 0;
    }

    $legacy_ids = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT id FROM {$strings_table} WHERE context LIKE %s AND context != %s",
            $wpdb->esc_like( $target_context ) . '-%',
            $target_context
        )
    );

    if ( empty( $legacy_ids ) ) {
        return 0;
    }

    $placeholders = implode( ',', array_fill( 0, count( $legacy_ids ), '%d' ) );

    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $translations_table ) ) ) {
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$translations_table} WHERE string_id IN ({$placeholders})",
                $legacy_ids
            )
        );
    }

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$strings_table} WHERE id IN ({$placeholders})",
            $legacy_ids
        )
    );

    return count( $legacy_ids );
}

/**
 * Return a per-context count of strings registered in WPML String Translation.
 *
 * @return array<string,int>
 */
function motorlan_wpml_context_counts() {
    global $wpdb;

    $strings_table = $wpdb->prefix . 'icl_strings';
    $target_prefix = motorlan_get_vue_i18n_wpml_context();

    if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $strings_table ) ) ) {
        return [];
    }

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT context, COUNT(*) AS total FROM {$strings_table}
              WHERE context = %s OR context LIKE %s
              GROUP BY context
              ORDER BY context",
            $target_prefix,
            $wpdb->esc_like( $target_prefix ) . '-%'
        ),
        ARRAY_A
    );

    $out = [];
    foreach ( $rows as $row ) {
        $out[ $row['context'] ] = (int) $row['total'];
    }
    return $out;
}

/**
 * Walk the source tree asking WPML for translations in $locale, falling back
 * to the bundled JSON value for $locale when WPML has no translation yet.
 *
 * @param array<string, mixed> $source_tree     Source locale tree.
 * @param array<string, mixed> $base_target_tree Bundled target-locale tree.
 * @param string               $locale          Target locale code.
 * @param string               $prefix          Current key prefix.
 * @return array<string, mixed>
 */
function motorlan_translate_tree_with_fallback( array $source_tree, array $base_target_tree, $locale, $prefix = '' ) {
    $out     = [];
    $context = motorlan_get_vue_i18n_wpml_context();

    foreach ( $source_tree as $key => $value ) {
        $path = '' === $prefix ? (string) $key : $prefix . '.' . $key;

        if ( is_array( $value ) ) {
            $child_base = ( isset( $base_target_tree[ $key ] ) && is_array( $base_target_tree[ $key ] ) )
                ? $base_target_tree[ $key ]
                : [];
            $out[ $key ] = motorlan_translate_tree_with_fallback( $value, $child_base, $locale, $path );
            continue;
        }

        $source_value = (string) $value;
        $translated   = motorlan_wpml_translate_string( $context, $path, $source_value, $locale );

        // WPML returns the source value when no translation exists. Fall back
        // to the bundled JSON value for the target locale in that case.
        if ( $translated === $source_value && array_key_exists( $key, $base_target_tree ) && ! is_array( $base_target_tree[ $key ] ) ) {
            $out[ $key ] = (string) $base_target_tree[ $key ];
            continue;
        }

        $out[ $key ] = $translated;
    }

    return $out;
}

/**
 * Build the effective translation tree exposed to Vue.
 *
 * Source locale comes straight from the JSON. Every other locale walks the
 * source tree through WPML, falling back to bundled values when untranslated.
 *
 * @param string[]|null $locales Locales to build (null = all supported).
 * @return array<string, array<string, mixed>>
 */
function motorlan_get_effective_vue_i18n_messages( $locales = null ) {
    $base_messages = motorlan_get_base_vue_i18n_messages();
    $source        = motorlan_get_vue_i18n_source_locale();
    $source_tree   = $base_messages[ $source ] ?? [];
    $effective     = [];

    $codes = array_keys( motorlan_get_supported_locale_map() );
    if ( is_array( $locales ) ) {
        $codes = array_values( array_intersect( $codes, $locales ) );
    }

    foreach ( $codes as $code ) {
        if ( $code === $source ) {
            $effective[ $code ] = $source_tree;
            continue;
        }

        $base_target = is_array( $base_messages[ $code ] ?? null ) ? $base_messages[ $code ] : [];

        if ( motorlan_is_wpml_active() ) {
            $effective[ $code ] = motorlan_translate_tree_with_fallback( $source_tree, $base_target, $code );
        } else {
            $effective[ $code ] = $base_target;
        }
    }

    return $effective;
}

/**
 * Prepare the payload exposed to the Vue app.
 *
 * @return array<string, mixed>
 */
function motorlan_get_vue_i18n_payload() {
    $current_locale = motorlan_get_current_language_code();
    // RENDIMIENTO: solo el idioma de la página. Con los 5 idiomas cada vista HTML hacía
    // ~4000 consultas de traducción a WPML e incrustaba ~200 KB de JSON. El resto de
    // idiomas ya va en el bundle (locales/*.json) y cambiar de idioma recarga la página
    // en su URL de WPML, que trae su propio payload.
    $messages       = motorlan_get_effective_vue_i18n_messages( [ $current_locale ] );

    return [
        'current_locale'   => $current_locale,
        'language'         => $current_locale,
        'language_locale'   => motorlan_get_supported_locale_map()[ $current_locale ]['locale'] ?? $current_locale,
        'languages'        => motorlan_get_supported_languages(),
        'supported_locales' => array_keys( motorlan_get_supported_locale_map() ),
        'rtl_locales'      => [ 'ar' ],
        'i18n_messages'    => $messages,
    ];
}
