<?php

namespace MotorlanPortal\Controllers;

use MotorlanPortal\Support\Api;
use MotorlanPortal\Support\AuthMessages;
use MotorlanPortal\Support\Seo;
use MotorlanPortal\Support\SpaPages;
use MotorlanPortal\Support\Urls;
use MotorlanPortal\Support\View;

/**
 * Acceso: /mi-cuenta/{login|register|register-success|forgot-password|reset-password|verify-email}
 * (también bajo la página de la tienda, como la SPA, y en /ml-portal/acceso/{pantalla}/).
 *
 * Cada pantalla es un formulario que hace POST a su propia URL. Con HTMX (hx-post) el
 * servidor responde solo el bloque del formulario (#ml-auth-body) o, si todo fue bien,
 * HX-Redirect; sin JS responde la página entera o una redirección 303. La lógica (sesión
 * de WordPress, límites de intentos, emails, validación de contraseña) es la de los
 * endpoints motorlan/v1 (Support\Api): aquí solo se traducen sus respuestas a vistas.
 */
class AccesoController extends Controller
{
    const NONCE = 'motorlan_portal_auth';

    /** Cookie (HttpOnly) que enlaza "registro" -> "registro completado" sin poner el email en la URL. */
    const REG_COOKIE = 'ml_portal_reg';

    /** Pantallas que un usuario con sesión no necesita: se le manda al panel. */
    const GUEST_ONLY = ['login', 'register', 'register-success', 'forgot-password', 'reset-password'];

    /**
     * Con la sección encendida, los "inicia sesión" del portal (Urls::login, p. ej. una
     * sección privada sin sesión) van a esta pantalla en vez de a wp-login.php.
     */
    public static function boot(): void
    {
        add_filter('motorlan_portal_login_url', function ($url, $back) {
            $login = SpaPages::page_url(SpaPages::ACCOUNT, 'login');
            return $back !== '' ? add_query_arg('to', rawurlencode($back), $login) : $login;
        }, 10, 2);
    }

    public function handle(string $param): void
    {
        $screen = strtolower($param);
        if (!in_array($screen, SpaPages::AUTH_SCREENS, true)) {
            $this->not_found();
            return;
        }

        if (is_user_logged_in() && in_array($screen, self::GUEST_ONLY, true)) {
            wp_safe_redirect($this->after_login_url(), 302);
            exit;
        }

        $method = str_replace('-', '_', $screen);
        $state  = [];
        $is_post = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';

        if ($is_post) {
            if (!$this->valid_nonce()) {
                $state = ['error' => __('La página ha caducado. Recárgala e inténtalo de nuevo.', 'motorlan-portal')];
            } else {
                $state = $this->{'post_' . $method}();
            }
        } elseif (method_exists($this, 'get_' . $method)) {
            $state = $this->{'get_' . $method}();
        }

        // Éxito con cambio de página: HX-Redirect (HTMX) o 303 (sin JS).
        if (!empty($state['redirect'])) {
            if ($this->is_htmx()) {
                View::html_response('', 200, ['HX-Redirect' => $state['redirect']]);
            }
            wp_safe_redirect($state['redirect'], 303);
            exit;
        }

        $context = $this->context($screen, $state);

        if ($is_post && $this->is_htmx()) {
            View::html_response(View::compile('partials/acceso/' . $screen . '.twig', $context, true));
        }

        Seo::private_page($context['title']);
        $this->render('pages/acceso.twig', $context);
    }

    // ── Pantallas ───────────────────────────────────────────────────────────────────────

    protected function post_login(): array
    {
        $email = $this->field('username');
        $r = Api::call('POST', Api::route('login'), [], [
            'username' => $email,
            'password' => $this->field('password', false),
            'remember' => true,
        ]);

        if (!$r['ok'] || empty($r['data']['success'])) {
            return [
                'error'  => $this->error_text($r, __('Se produjo un error desconocido. Inténtalo de nuevo.', 'motorlan-portal')),
                'values' => ['username' => $email],
            ];
        }

        // Perfil sin nombre/apellidos: a completarlo (como la SPA).
        $personal = $r['data']['profile']['personal_data'] ?? [];
        if (empty($personal['nombre']) || empty($personal['apellidos'])) {
            return ['redirect' => Urls::spa('dashboard/user/account')];
        }
        return ['redirect' => $this->after_login_url()];
    }

    protected function post_register(): array
    {
        $values = [
            'first_name' => $this->field('first_name'),
            'last_name'  => $this->field('last_name'),
            'email'      => $this->field('email'),
        ];

        if (empty($_POST['privacy'])) {
            return ['error' => __('Debes aceptar la política de privacidad y los términos.', 'motorlan-portal'), 'values' => $values, 'step' => 3];
        }

        $r = Api::call('POST', Api::route('register'), [], [
            'first_name' => $values['first_name'],
            'last_name'  => $values['last_name'],
            'username'   => $values['email'],
            'email'      => $values['email'],
            'password'   => $this->field('password', false),
            'lang'       => apply_filters('wpml_current_language', null) ?: 'es',
        ]);

        if (!$r['ok']) {
            $msg   = $this->error_text($r, __('Ocurrió un error al registrar la cuenta. Inténtalo de nuevo.', 'motorlan-portal'));
            $field = is_array($r['data']) ? (string) ($r['data']['field'] ?? '') : '';
            if ($field === 'username') {
                $field = 'email';
            }
            if (in_array($field, ['email', 'first_name', 'last_name', 'password'], true)) {
                return ['errors' => [$field => $msg], 'values' => $values, 'step' => $field === 'password' ? 2 : 1];
            }
            return ['error' => $msg, 'values' => $values, 'step' => 3];
        }

        // Email (y el enlace de verificación en desarrollo) para "registro completado".
        $key = wp_generate_password(32, false);
        set_transient('ml_portal_reg_' . $key, [
            'email'   => $values['email'],
            'dev_url' => is_array($r['data']) ? (string) ($r['data']['dev_verify_url'] ?? '') : '',
        ], HOUR_IN_SECONDS);
        setcookie(self::REG_COOKIE, $key, [
            'expires'  => time() + HOUR_IN_SECONDS,
            'path'     => COOKIEPATH ?: '/',
            'domain'   => COOKIE_DOMAIN ?: '',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        return ['redirect' => SpaPages::page_url(SpaPages::ACCOUNT, 'register-success')];
    }

    protected function get_register_success(): array
    {
        $reg = $this->registration();
        return ['values' => ['email' => $reg['email'] ?? ''], 'dev_url' => $reg['dev_url'] ?? ''];
    }

    protected function post_register_success(): array
    {
        $email = $this->field('email');
        $reg   = $this->registration();
        $state = ['values' => ['email' => $email], 'dev_url' => $reg['dev_url'] ?? '', 'show_resend' => true];

        $r = Api::call('POST', Api::route('resend-verification'), [], ['email' => $email]);
        if (!$r['ok']) {
            return $state + ['error' => $this->error_text($r, __('No se pudo reenviar el enlace.', 'motorlan-portal'))];
        }
        return ['values' => ['email' => $email], 'dev_url' => $state['dev_url'],
            'notice' => AuthMessages::from($r['data'], __('Si la cuenta existe y no está activada, se ha enviado un nuevo enlace.', 'motorlan-portal'))];
    }

    protected function post_forgot_password(): array
    {
        $email = $this->field('email');
        $r = Api::call('POST', Api::route('forgot-password'), [], ['email' => $email]);
        if (!$r['ok'] || empty($r['data']['success'])) {
            return ['error' => $this->error_text($r, __('No se pudo enviar el correo. Intenta de nuevo.', 'motorlan-portal')), 'values' => ['email' => $email]];
        }
        return ['done' => true];
    }

    protected function post_reset_password(): array
    {
        $key   = $this->field('key', false);
        $login = $this->field('login', false);
        if ($key === '' || $login === '') {
            return ['invalid' => true, 'error' => __('Enlace de restablecimiento inválido. Por favor, solicita uno nuevo.', 'motorlan-portal')];
        }

        $r = Api::call('POST', Api::route('reset-password'), [], [
            'key'      => $key,
            'login'    => $login,
            'password' => $this->field('password', false),
        ]);
        if ($r['ok'] && !empty($r['data']['success'])) {
            return ['done' => true];
        }
        $code = is_array($r['data']) ? (string) ($r['data']['code'] ?? '') : '';
        if (in_array($code, ['invalid_key', 'expired_key'], true)) {
            return ['invalid' => true, 'error' => $this->error_text($r, __('Enlace de restablecimiento inválido. Por favor, solicita uno nuevo.', 'motorlan-portal'))];
        }
        // El token sigue en el formulario (campos ocultos) para reintentar.
        return ['error' => $this->error_text($r, __('No se pudo restablecer la contraseña.', 'motorlan-portal')), 'key' => $key, 'login' => $login];
    }

    protected function get_verify_email(): array
    {
        return ['status' => 'verifying'];
    }

    /**
     * Verificación de la cuenta (#token=…), confirmación de cambio de email
     * (#email_change=…) y "solicitar nuevo enlace". El token llega en el fragmento: lo lee
     * mlAuthToken en el navegador y envía este formulario solo.
     */
    protected function post_verify_email(): array
    {
        $token  = $this->field('token', false);
        $mode   = $this->field('mode') === 'email_change' ? 'email_change' : 'verify';
        $action = $this->field('do');
        $base   = ['mode' => $mode, 'token' => $token];

        if ($token === '') {
            return $base + ['status' => 'error', 'error' => __('Token de verificación no encontrado.', 'motorlan-portal')];
        }

        if ($action === 'resend' && $mode === 'verify') {
            $r = Api::call('POST', Api::route('resend-verification'), [], ['token' => $token]);
            $code = is_array($r['data']) ? (string) ($r['data']['code'] ?? '') : '';
            if ($r['ok'] && !empty($r['data']['success'])) {
                return $base + ['status' => $code === 'already_verified' ? 'already_verified' : 'resent'];
            }
            return $base + ['status' => 'error', 'code' => $code, 'error' => $this->error_text($r, __('No se pudo reenviar el enlace.', 'motorlan-portal'))];
        }

        $r = $mode === 'email_change'
            ? Api::call('POST', Api::route('profile/confirm-email-change'), [], ['token' => $token])
            : Api::call('POST', Api::route('verify-email'), [], ['token' => $token]);
        $code = is_array($r['data']) ? (string) ($r['data']['code'] ?? '') : '';

        if ($r['ok'] && !empty($r['data']['success'])) {
            if ($mode === 'email_change') {
                return $base + ['status' => 'email_changed'];
            }
            return $base + ['status' => $code === 'already_verified' ? 'already_verified' : 'verified'];
        }

        $fallback = $mode === 'email_change'
            ? __('El enlace para confirmar el email no es válido o ya se ha usado.', 'motorlan-portal')
            : __('Token de verificación inválido o expirado.', 'motorlan-portal');
        return $base + ['status' => 'error', 'code' => $code, 'error' => $this->error_text($r, $fallback)];
    }

    // ── Apoyo ───────────────────────────────────────────────────────────────────────────

    protected function context(string $screen, array $state): array
    {
        $titles = [
            'login'            => __('Iniciar sesión', 'motorlan-portal'),
            'register'         => __('Crea tu cuenta', 'motorlan-portal'),
            'register-success' => __('Registro completado', 'motorlan-portal'),
            'forgot-password'  => __('¿Olvidaste tu contraseña?', 'motorlan-portal'),
            'reset-password'   => __('Restablecer contraseña', 'motorlan-portal'),
            'verify-email'     => __('Verificación de email', 'motorlan-portal'),
        ];

        return [
            'screen'  => $screen,
            'title'   => $titles[$screen],
            'state'   => $state + ['values' => [], 'errors' => []],
            'action'  => Urls::current(),
            'nonce'   => wp_create_nonce(self::NONCE),
            'logged_in' => is_user_logged_in(),
            'links'   => [
                'login'    => SpaPages::page_url(SpaPages::ACCOUNT, 'login'),
                'register' => SpaPages::page_url(SpaPages::ACCOUNT, 'register'),
                'forgot'   => SpaPages::page_url(SpaPages::ACCOUNT, 'forgot-password'),
                'profile'  => Urls::spa('dashboard/user/profile'),
                'privacy'  => function_exists('get_privacy_policy_url') && get_privacy_policy_url() ? get_privacy_policy_url() : home_url('/politica-de-privacidad/'),
            ],
        ];
    }

    /**
     * Destino tras iniciar sesión: ?to= (si es del mismo sitio) o el panel.
     * `to` puede ser una ruta del router de la SPA (/dashboard/…, la pone su guard) o una
     * ruta completa de la web (/mi-cuenta/…, /marketplace-motorlan/…): como en login.vue.
     */
    protected function after_login_url(): string
    {
        $fallback = Urls::spa('dashboard/purchases/purchases');
        $to = isset($_GET['to']) && is_string($_GET['to']) ? trim(wp_unslash($_GET['to'])) : '';
        if ($to === '') {
            return $fallback;
        }

        $is_full = preg_match('#^https?://#i', $to) === 1;
        if (!$is_full) {
            $path  = '/' . ltrim($to, '/');
            $bases = array_unique(array_merge(SpaPages::account_slugs(), SpaPages::store_slugs()));
            $known = false;
            foreach ($bases as $b) {
                if (preg_match('#^(?:/[a-z]{2}(?:-[a-z]{2})?)?/' . preg_quote($b, '#') . '(?:/|$)#i', $path)) {
                    $known = true;
                    break;
                }
            }
            $to = $known ? home_url($path) : Urls::spa(ltrim($path, '/'));
        }

        // Solo mismo sitio (evita ?to=https://otro-dominio).
        return wp_validate_redirect($to, $fallback);
    }

    protected function registration(): array
    {
        $key = isset($_COOKIE[self::REG_COOKIE]) ? sanitize_key(wp_unslash($_COOKIE[self::REG_COOKIE])) : '';
        $reg = $key !== '' ? get_transient('ml_portal_reg_' . $key) : false;
        return is_array($reg) ? $reg : [];
    }

    protected function valid_nonce(): bool
    {
        $nonce = isset($_POST['_mlnonce']) ? sanitize_text_field(wp_unslash($_POST['_mlnonce'])) : '';
        return (bool) wp_verify_nonce($nonce, self::NONCE);
    }

    /** Campo del POST. $clean = false para contraseñas y tokens (sin sanitize_text_field). */
    protected function field(string $name, bool $clean = true): string
    {
        $v = isset($_POST[$name]) && is_string($_POST[$name]) ? wp_unslash($_POST[$name]) : '';
        return $clean ? trim(sanitize_text_field($v)) : $v;
    }

    protected function error_text(array $r, string $fallback): string
    {
        if ((int) $r['status'] >= 500 && !is_array($r['data'])) {
            return AuthMessages::connection();
        }
        return AuthMessages::from($r['data'], $fallback);
    }
}
