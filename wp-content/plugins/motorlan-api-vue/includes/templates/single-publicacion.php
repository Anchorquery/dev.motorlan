<?php
/**
 * Plantilla del CPT "publicacion" (single).
 *
 * La ficha real es la SPA en /marketplace-motorlan/{slug}/ (con sus metadatos SEO, ver
 * vue-app-setup.php). Antes esta plantilla pintaba un skeleton con meta tags propios que
 * nunca llegaba a verse (la URL /{slug}/ daba 404 y leía un campo acf.resumen que no
 * existe). Ahora cualquier acceso al single del CPT (?publicacion=slug, enlaces antiguos)
 * redirige a la ficha: 301 si está publicada; 302 si no (la SPA solo la muestra al dueño
 * o a un admin y devuelve "no encontrada" al resto).
 *
 * @package motorlan-api-vue
 */

if (!defined('ABSPATH')) {
    exit;
}

$motorlan_post_id = get_queried_object_id() ?: get_the_ID();

if ($motorlan_post_id && function_exists('motorlan_get_store_url')) {
    $motorlan_status = get_post_status($motorlan_post_id);
    wp_safe_redirect(motorlan_get_store_url($motorlan_post_id), 'publish' === $motorlan_status ? 301 : 302);
    exit;
}

// Sin destino posible: 404 del tema
global $wp_query;
if ($wp_query) {
    $wp_query->set_404();
}
status_header(404);
nocache_headers();
$motorlan_404_template = get_404_template();
if ($motorlan_404_template) {
    include $motorlan_404_template;
}
