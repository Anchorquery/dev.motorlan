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
 * @param string $context String context.
 * @param string $name String name.
 * @param string $value String value.
 * @return void
 */
function motorlan_wpml_register_string( $context, $name, $value ) {
    if ( ! motorlan_is_wpml_active() ) {
        return;
    }

    if ( function_exists( 'icl_register_string' ) ) {
        icl_register_string( $context, $name, $value );
        return;
    }

    do_action( 'wpml_register_single_string', $context, $name, $value );
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
    if ( defined( 'ICL_LANGUAGE_CODE' ) && ICL_LANGUAGE_CODE ) {
        return motorlan_normalize_locale_code( ICL_LANGUAGE_CODE );
    }

    if ( function_exists( 'pll_current_language' ) ) {
        $pll_locale = pll_current_language( 'slug' );
        if ( $pll_locale ) {
            return motorlan_normalize_locale_code( $pll_locale );
        }
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
 * Register all bundled strings with WPML so they can be edited from String Translation.
 *
 * @return void
 */
function motorlan_sync_vue_i18n_strings_to_wpml() {
    $messages = motorlan_get_base_vue_i18n_messages();

    foreach ( $messages as $locale => $tree ) {
        motorlan_register_wpml_translation_tree( 'motorlan-vue-' . $locale, $tree );
    }
}

add_action( 'init', 'motorlan_sync_vue_i18n_strings_to_wpml', 20 );

/**
 * Merge bundled translations with WPML overrides.
 *
 * @return array<string, array<string, mixed>>
 */
function motorlan_get_effective_vue_i18n_messages() {
    $base_messages = motorlan_get_base_vue_i18n_messages();
    $effective     = [];

    foreach ( $base_messages as $locale => $tree ) {
        $effective[ $locale ] = motorlan_translate_wpml_translation_tree( 'motorlan-vue-' . $locale, $tree, $locale );
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
    $messages       = motorlan_get_effective_vue_i18n_messages();

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
