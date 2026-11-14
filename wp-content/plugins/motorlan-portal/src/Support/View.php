<?php

namespace MotorlanPortal\Support;

/**
 * Render Twig (Timber) con el contexto común. Degrada con aviso si falta Timber.
 *
 * Timber 2 tiene autoescape DESACTIVADO: en las vistas, todo dato del usuario o de la
 * API se escapa a mano — {{ x|e }} en texto, {{ x|e('html_attr') }} en atributos,
 * {{ ml_json(x)|e('html_attr') }} para JSON dentro de x-data.
 */
class View
{
    public static function render(string $template, array $context = []): void
    {
        $context = array_merge(self::base_context(), $context);
        if (class_exists('Timber\\Timber')) {
            \Timber\Timber::render($template, $context);
            return;
        }
        self::fallback($template);
    }

    /** Parcial a string (respuestas HTMX). Sin el contexto común salvo que se pida. */
    public static function compile(string $template, array $context = [], bool $with_base = false): string
    {
        if (!class_exists('Timber\\Timber')) {
            return '';
        }
        $out = \Timber\Timber::compile($template, $with_base ? array_merge(self::base_context(), $context) : $context);
        return is_string($out) ? $out : '';
    }

    /**
     * HTML crudo desde un callback REST (parciales HTMX). WP_REST_Server serializa siempre
     * el cuerpo a JSON, así que se sale del pipeline antes (echo + exit).
     */
    public static function html_response(string $html, int $status = 200, array $headers = []): void
    {
        status_header($status);
        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $html;
        exit;
    }

    public static function error(int $status): void
    {
        status_header($status);
        nocache_headers();
        self::render('pages/error.twig', ['status' => $status, 'page_title' => (string) $status]);
    }

    public static function base_context(): array
    {
        return [
            'config' => Config::data(),
            'user'   => self::current_user(),
            'nav'    => [
                'vertical' => Nav::vertical(),
                'mobile'   => Nav::mobile(),
                'user'     => Nav::user_menu(),
                'languages' => Nav::languages(),
            ],
            'urls'   => [
                'home'      => home_url('/'),
                'store'     => Urls::screen('tienda', '', '', 'marketplace-motorlan'),
                // /mi-cuenta/dashboard lleva a Mis compras (sección panel; la SPA igual).
                'dashboard' => Urls::spa('dashboard/purchases/purchases'),
                'account'   => Urls::spa('dashboard/user/account'),
                'logout'    => Urls::logout(),
                'login'     => Urls::login(Urls::current()),
            ],
            'assets' => [
                'css'        => Assets::url('css/app.css'),
                'public_css' => Assets::url('css/public.css'),
                'js'         => Assets::url('js/app.js'),
                // Logo del tema (Apariencia › Personalizar); sin él, el nombre en texto como la SPA.
                'logo'       => self::logo_url(),
            ],
            'site_name' => get_bloginfo('name'),
        ];
    }

    protected static function logo_url(): ?string
    {
        $id = (int) get_theme_mod('custom_logo');
        $url = $id ? wp_get_attachment_image_url($id, 'full') : false;
        return $url ? (string) $url : null;
    }

    protected static function current_user(): ?array
    {
        if (!is_user_logged_in()) {
            return null;
        }
        $u = wp_get_current_user();
        return [
            'id'       => (int) $u->ID,
            'name'     => $u->display_name !== '' ? $u->display_name : $u->user_login,
            'email'    => $u->user_email,
            'is_admin' => current_user_can('manage_options'),
            'avatar'   => self::avatar_url((int) $u->ID),
        ];
    }

    /**
     * Avatar subido en Mi perfil (meta avatar_attachment_id de motorlan-api-vue,
     * profile-routes.php); sin él, null = icono (no el Gravatar genérico).
     */
    protected static function avatar_url(int $user_id): ?string
    {
        $id = (int) get_user_meta($user_id, 'avatar_attachment_id', true);
        $url = $id ? wp_get_attachment_image_url($id, 'thumbnail') : false;
        return $url ? (string) $url : null;
    }

    /** Funciones y filtros propios en Twig (prefijo ml_). */
    public static function twig_extend($twig)
    {
        $functions = [
            '__'          => '__',
            '_n'          => '_n',
            'ml_icon'     => [Icons::class, 'svg'],
            'ml_json'     => [self::class, 'json'],
            'ml_url'      => [Urls::class, 'portal'],
            'ml_spa'      => [Urls::class, 'spa'],
            'ml_screen'   => [Urls::class, 'screen'],
            'ml_asset'    => [Assets::class, 'url'],
            'ml_enabled'  => ['MotorlanPortal\\Sections', 'is_enabled'],
            'ml_nonce_field' => function ($action = 'wp_rest') {
                return wp_nonce_field($action, '_wpnonce', true, false);
            },
        ];
        foreach ($functions as $name => $callable) {
            try {
                $twig->addFunction(new \Twig\TwigFunction($name, $callable, ['is_safe' => ['html']]));
            } catch (\LogicException $e) {
                // Otra extensión de Timber ya la registró (Twig compartido).
            }
        }
        return $twig;
    }

    /** JSON seguro dentro de <script> o de un atributo (con |e('html_attr')). */
    public static function json($value): string
    {
        return (string) wp_json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    }

    protected static function fallback(string $template): void
    {
        status_header(200);
        nocache_headers();
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Motorlan Portal</title></head>';
        echo '<body style="font-family:system-ui,sans-serif;background:#F8F7FA;color:#2F2B3D"><div style="max-width:640px;margin:4rem auto;padding:2rem;background:#fff;border-radius:16px;box-shadow:0 4px 6px -1px rgb(0 0 0/.1)">';
        echo '<h1 style="margin:0 0 1rem;font-size:1.25rem">Motorlan Portal</h1><p>Plugin activo, pero falta <strong>Timber</strong>. Ejecuta <code>composer install</code> en el directorio del plugin.</p>';
        echo '<p style="color:#808390;font-size:.875rem">Plantilla: <code>' . esc_html($template) . '</code></p></div></body></html>';
    }
}
