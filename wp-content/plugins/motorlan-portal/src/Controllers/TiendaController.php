<?php

namespace MotorlanPortal\Controllers;

use MotorlanPortal\Support\Api;
use MotorlanPortal\Support\Chat;
use MotorlanPortal\Support\Seo;
use MotorlanPortal\Support\SpaPages;
use MotorlanPortal\Support\Store;
use MotorlanPortal\Support\Urls;
use MotorlanPortal\Support\View;

/**
 * Tienda pública: /marketplace-motorlan/ (listado) y /marketplace-motorlan/{slug}/ (ficha).
 * También en /ml-portal/tienda/ y /ml-portal/tienda/{slug}/.
 *
 * Listado con HTMX sobre la MISMA URL: el formulario de filtros hace GET a la página con
 * HX-Request y el servidor responde solo el bloque de resultados (sin cabecera del tema),
 * con HX-Push-Url = URL normalizada. Sin JS, el mismo formulario recarga la página entera.
 * Los datos salen de motorlan/v1 (Support\Api): nada de lógica de negocio aquí.
 */
class TiendaController extends Controller
{
    public function handle(string $param): void
    {
        if ($param === '') {
            $this->listing();
            return;
        }
        $this->product(sanitize_title(urldecode($param)));
    }

    protected function listing(): void
    {
        $f = Store::filters();
        $result = Store::search($f);

        $context = [
            'f'        => $f,
            'result'   => $result,
            'active'   => Store::active_count($f),
            'store_url' => SpaPages::store_url(),
            'page_url' => Store::url($f),
            'page_pattern' => str_replace('__PAGE__', '%d', self::page_pattern($f)),
            'options_sort' => Store::options()['sort'],
        ];

        // Parcial para HTMX (filtros, búsqueda, orden, paginación). Un "volver" del
        // navegador sin caché de HTMX pide la página entera (HX-History-Restore-Request).
        if ($this->is_htmx() && empty($_SERVER['HTTP_HX_HISTORY_RESTORE_REQUEST'])) {
            $html = View::compile('partials/tienda-results.twig', $context, true);
            View::html_response($html, 200, [
                'Vary'         => 'HX-Request',
                'HX-Push-Url'  => Store::url($f),
            ]);
        }

        header('Vary: HX-Request');
        Seo::store_listing($f, $result);
        $this->render('pages/tienda.twig', $context + [
            'options'  => Store::options(),
            'brands'   => Store::brands(),
            'mnav'     => $this->mobile_nav(),
        ]);
    }

    protected function product(string $slug): void
    {
        $r = $slug !== '' ? Api::call('GET', Api::route('publicaciones/' . rawurlencode($slug))) : ['ok' => false];
        $p = $r['ok'] && isset($r['data']['data']) && is_array($r['data']['data']) ? $r['data']['data'] : null;

        if (!$p) {
            status_header(404);
            Seo::noindex();
            $this->render('pages/ficha-404.twig', ['store_url' => SpaPages::store_url()]);
            return;
        }

        // Slug antiguo (_wp_old_slug, la API ya lo resuelve): 301 a la URL actual con la query.
        if (!empty($p['slug']) && $p['slug'] !== $slug) {
            $qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . wp_unslash($_SERVER['QUERY_STRING']) : '';
            wp_safe_redirect(SpaPages::product_url((string) $p['slug']) . $qs, 301);
            exit;
        }

        $uid = get_current_user_id();
        $product = Store::product($p, $uid);
        $description = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags(preg_replace('#<(/?(?:p|div|li|ul|ol|br|h[1-6]|tr|td|th|table|blockquote)\b)#i', ' <$1', $product['description']))));
        Seo::product($product, $description);

        $this->render('pages/ficha.twig', [
            'product'   => $product,
            'favorite'  => $this->favorite_state($product, $uid),
            'related'   => Store::related($product),
            'store_url' => SpaPages::store_url(),
            'chat'      => $this->chat_config($product, $uid),
        ]);
    }

    /** null = no se muestra (invitado o dueño); bool = estado actual. */
    protected function favorite_state(array $product, int $uid): ?bool
    {
        if (!$uid || $product['owner']) {
            return null;
        }
        $fav = Api::get(Api::route('favorites'), ['fields' => 'ids']);
        $ids = is_array($fav) && isset($fav['data']) && is_array($fav['data']) ? $fav['data'] : [];
        foreach ($ids as $row) {
            if ((int) (is_array($row) ? ($row['id'] ?? 0) : $row) === $product['id']) {
                return true;
            }
        }
        return false;
    }

    /** Datos del chat de la ficha para Alpine (assets/js/product-chat.js). */
    protected function chat_config(array $product, int $uid): array
    {
        $user = $uid ? wp_get_current_user() : null;
        $name = $user ? trim($user->first_name . ' ' . $user->last_name) : '';
        return [
            'productId'  => $product['id'],
            'title'      => $product['title'],
            'sellerName' => $product['seller']['name'],
            'userId'     => $uid,
            'userName'   => $user ? ($name !== '' ? $name : $user->display_name) : '',
            'image'      => $product['images'][0]['thumb'] ?? '',
            'privacyUrl' => (string) (get_privacy_policy_url() ?: home_url('/politica-de-privacidad/')),
            'registerUrl' => SpaPages::page_url(SpaPages::ACCOUNT, 'register'),
            'i18n'       => ['defaultMessage' => __('Hola, me interesa saber el precio de la publicación', 'motorlan-portal')] + Chat::i18n(),
        ];
    }

    /** Barra inferior en móvil (StoreMobileNav.vue): invitado 2 ítems, con sesión 4. */
    protected function mobile_nav(): array
    {
        if (!is_user_logged_in()) {
            return ['logged' => false, 'login' => Urls::login(Urls::current())];
        }
        return [
            'logged'    => true,
            'dashboard' => Urls::spa('dashboard/purchases/purchases'),
            'publish'   => Urls::spa('dashboard/publications/publication/add'),
            'profile'   => Urls::spa('dashboard/user/account'),
        ];
    }

    /** Patrón de la paginación (%d = página) con los filtros actuales. */
    protected static function page_pattern(array $f): string
    {
        $url = Store::url($f, 2);
        // Store::url omite page=1; se construye con un marcador y se sustituye en Twig.
        return preg_replace('/([?&])page=2(&|$)/', '$1page=__PAGE__$2', $url);
    }
}
