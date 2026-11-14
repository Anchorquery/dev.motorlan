<?php

namespace MotorlanPortal\Support;

/**
 * SEO de las pantallas públicas que se pintan dentro del tema (wp_head del tema).
 *
 * Ficha: la cubre motorlan-api-vue desde antes de la migración y sigue activa con la
 * sección encendida, porque actúa sobre la URL y no sobre el HTML de Vue
 * (motorlan_store_seo_setup en 'wp' y motorlan_store_seo_template_redirect): title,
 * description, canonical, og:*, robots, hreflang con el slug, JSON-LD Product (sin precio
 * con "Consultar precio"), 301 de slugs antiguos y 404 real. self::product() solo la
 * completa cuando esa capa no está (p. ej. al retirar motorlan-api-vue en la fase 7).
 *
 * Listado: la página de WordPress lleva su title/description (Rank Math en producción);
 * aquí se añade canonical con ?page=N, noindex,follow para búsquedas y filtros (variantes
 * infinitas sin contenido propio) y el JSON-LD ItemList de la página de resultados.
 *
 * Los hooks se añaden desde el controlador, antes de pintar la cabecera del tema.
 */
class Seo
{
    public static function rank_math(): bool
    {
        return defined('RANK_MATH_VERSION') || class_exists('RankMath');
    }

    /**
     * @param array $f      Filtros normalizados (Store::filters)
     * @param array $result Resultado de Store::search
     */
    public static function store_listing(array $f, array $result): void
    {
        $filtered  = Store::active_count($f) > 0 || $f['sort'] !== 'recent';
        $canonical = Store::url(['q' => '', 'type' => '', 'tipo' => '', 'tech' => '', 'brand' => 0, 'state' => '', 'par' => '', 'pot' => '', 'vel' => '', 'sort' => 'recent', 'page' => 1], $f['page']);
        $page_sfx  = $f['page'] > 1 ? ' - ' . sprintf(__('Página %d', 'motorlan-portal'), $f['page']) : '';

        if (self::rank_math()) {
            add_filter('rank_math/frontend/canonical', function () use ($canonical) { return $canonical; }, 99);
            add_filter('rank_math/opengraph/url', function () use ($canonical) { return $canonical; }, 99);
            if ($page_sfx !== '') {
                add_filter('rank_math/frontend/title', function ($title) use ($page_sfx) { return $title . $page_sfx; }, 99);
            }
            if ($filtered) {
                add_filter('rank_math/frontend/robots', function ($robots) {
                    $robots = is_array($robots) ? $robots : [];
                    $robots['index'] = 'noindex';
                    $robots['follow'] = 'follow';
                    return $robots;
                }, 99);
            }
        } else {
            remove_action('wp_head', 'rel_canonical');
            if ($page_sfx !== '') {
                add_filter('document_title_parts', function ($parts) use ($page_sfx) {
                    $parts['title'] = ($parts['title'] ?? '') . $page_sfx;
                    return $parts;
                }, 99);
            }
            $description = __('Compra y venta de motores industriales: motores, reguladores, repuestos y más.', 'motorlan-portal');
            add_action('wp_head', function () use ($canonical, $description) {
                echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
                echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
                echo '<meta property="og:type" content="website">' . "\n";
                echo '<meta property="og:url" content="' . esc_url($canonical) . '">' . "\n";
            }, 1);
            if ($filtered) {
                add_filter('wp_robots', function ($robots) {
                    unset($robots['index']);
                    $robots['noindex'] = true;
                    $robots['follow'] = true;
                    return $robots;
                }, 99);
            }
        }

        if ($result['pages'] > 1) {
            add_action('wp_head', function () use ($f, $result) {
                if ($f['page'] > 1) {
                    echo '<link rel="prev" href="' . esc_url(Store::url($f, $f['page'] - 1)) . '">' . "\n";
                }
                if ($f['page'] < $result['pages']) {
                    echo '<link rel="next" href="' . esc_url(Store::url($f, $f['page'] + 1)) . '">' . "\n";
                }
            }, 2);
        }

        if (!$filtered && $result['items']) {
            $items = [];
            foreach ($result['items'] as $i => $item) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => ($f['page'] - 1) * Store::PER_PAGE + $i + 1,
                    'url'      => $item['url'],
                    'name'     => $item['title'],
                ];
            }
            self::json_ld(['@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => $items]);
        }
    }

    /**
     * Ficha sin la capa SEO de motorlan-api-vue: mismos datos que motorlan_store_seo_setup.
     */
    public static function product(array $product, string $description_text): void
    {
        if (function_exists('motorlan_store_seo_setup')) {
            return;
        }
        $title = wp_strip_all_tags($product['title'] . ($product['brand'] !== '-' && stripos($product['title'], $product['brand']) === false ? ' ' . $product['brand'] : '') . ' | Motorlan');
        $desc  = wp_html_excerpt($description_text !== '' ? $description_text : $product['title'], 155, '…');
        $image = $product['images'][0]['src'] ?? '';
        $canonical = $product['url'];

        add_filter('pre_get_document_title', function () use ($title) { return $title; }, 99);
        remove_action('wp_head', 'rel_canonical');
        add_action('wp_head', function () use ($title, $desc, $canonical, $image) {
            echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
            echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
            echo '<meta property="og:type" content="product">' . "\n";
            echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
            echo '<meta property="og:description" content="' . esc_attr($desc) . '">' . "\n";
            echo '<meta property="og:url" content="' . esc_url($canonical) . '">' . "\n";
            if ($image) {
                echo '<meta property="og:image" content="' . esc_url($image) . '">' . "\n";
            }
            echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        }, 1);
        if (!$product['available']) {
            add_filter('wp_robots', function ($robots) {
                unset($robots['index']);
                $robots['noindex'] = true;
                $robots['follow'] = true;
                return $robots;
            }, 99);
        }

        $schema = ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product['title'], 'description' => $desc, 'url' => $canonical];
        if ($image) {
            $schema['image'] = [$image];
        }
        if ($product['brand'] !== '-') {
            $schema['brand'] = ['@type' => 'Brand', 'name' => $product['brand']];
        }
        if ($product['show_price'] && !$product['consult'] && $product['price'] !== null) {
            $schema['offers'] = [
                '@type'         => 'Offer',
                'url'           => $canonical,
                'price'         => number_format($product['price'], 2, '.', ''),
                'priceCurrency' => 'EUR',
                'availability'  => $product['available'] ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            ];
        }
        self::json_ld($schema);
    }

    /** Página no encontrada dentro del tema: noindex. */
    public static function noindex(): void
    {
        add_filter('wp_robots', 'wp_robots_no_robots', 99);
        add_filter('rank_math/frontend/robots', function () {
            return ['index' => 'noindex', 'follow' => 'nofollow'];
        }, 99);
    }

    /** Pantallas de acceso: título propio y fuera del índice. */
    public static function private_page(string $title): void
    {
        self::noindex();
        add_filter('pre_get_document_title', function () use ($title) {
            return $title . ' | ' . get_bloginfo('name');
        }, 99);
        add_filter('rank_math/frontend/title', function () use ($title) {
            return $title . ' | ' . get_bloginfo('name');
        }, 99);
    }

    public static function json_ld(array $schema): void
    {
        add_action('wp_head', function () use ($schema) {
            echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>' . "\n";
        }, 20);
    }
}
