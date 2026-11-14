<?php

namespace MotorlanPortal\Controllers;

use MotorlanPortal\Support\Api;
use MotorlanPortal\Support\AuthMessages;
use MotorlanPortal\Support\Seo;
use MotorlanPortal\Support\Urls;
use MotorlanPortal\Support\View;

/**
 * Mi cuenta: /mi-cuenta/dashboard/user/account (UserProfile.vue).
 *
 * En la SPA, `user/account` y `user/profile` pintaban el mismo componente; aquí hay una
 * sola pantalla y `user/profile` redirige a `user/account` (los avisos, verify-email y la
 * barra de la tienda enlazan a profile). `user/company` lo redirige la sección `panel`.
 *
 * - Datos personales y de empresa: formulario con POST a esta URL (HTMX responde solo
 *   partials/cuenta-form.twig). Lo valida y guarda POST /motorlan/v1/profile.
 * - Cambio de email: la API exige la contraseña actual y deja el email pendiente hasta
 *   confirmarlo por email; aquí se pide en un diálogo (y se reintenta si es incorrecta).
 * - Avatar y contraseña: en el navegador (assets/js/cuenta.js), contra la misma API: el
 *   avatar se redimensiona antes de subirlo y el cambio de contraseña es en dos pasos
 *   (código por email) y vuelve a emitir la cookie, así que hay que renovar el nonce.
 */
class CuentaController extends Controller
{
    const COMPANY_KEYS = ['nombre', 'cif_nif', 'direccion', 'cp', 'persona_contacto', 'email_contacto', 'tel_contacto'];

    public function handle(string $param): void
    {
        if ($param === 'user/profile') {
            $this->redirect(Urls::spa('dashboard/user/account'), 302, true);
        }
        if ($param !== 'user/account' && $param !== '') {
            $this->not_found();
            return;
        }

        $state = [];
        $headers = [];
        if ($this->is_post()) {
            if (!$this->valid_nonce()) {
                $state = ['error' => __('La página ha caducado. Recárgala e inténtalo de nuevo.', 'motorlan-portal')];
            } elseif ($this->field('action') === 'cancel_email') {
                [$state, $headers] = $this->cancel_email_change();
            } else {
                [$state, $headers] = $this->save();
            }
        }

        $profile = Api::get(Api::route('profile'));
        if (!is_array($profile)) {
            $state['error'] = $state['error'] ?? __('Error al cargar el perfil', 'motorlan-portal');
            $profile = ['personal_data' => [], 'company_data' => [], 'pending_email' => ''];
        }
        $context = $this->context($profile, $state);

        if ($this->is_post() && $this->is_htmx()) {
            View::html_response(View::compile('partials/cuenta-form.twig', $context, true), 200, $headers);
        }

        Seo::private_page($context['page_title']);
        $this->render('pages/cuenta.twig', $context);
    }

    /** @return array{0: array, 1: array} [estado, cabeceras] */
    protected function save(): array
    {
        $personal = [
            'nombre'    => $this->field('nombre'),
            'apellidos' => $this->field('apellidos'),
            'email'     => sanitize_email($this->field('email')),
            'telefono'  => $this->field('telefono'),
        ];
        $is_company = !empty($_POST['is_company']);
        $company = [];
        foreach (self::COMPANY_KEYS as $k) {
            $company[$k] = $this->field('company_' . $k);
        }
        $values = ['personal' => $personal, 'company' => $company, 'is_company' => $is_company];

        // Mismas reglas que el formulario de la SPA (requiredValidator / emailValidator).
        $errors = [];
        $required = __('Este campo es obligatorio', 'motorlan-portal');
        foreach (['nombre', 'apellidos', 'telefono'] as $k) {
            if ($personal[$k] === '') {
                $errors[$k] = $required;
            }
        }
        if ($personal['email'] === '') {
            $errors['email'] = $required;
        } elseif (!is_email($personal['email'])) {
            $errors['email'] = __('El email no es válido.', 'motorlan-portal');
        }
        if ($is_company) {
            foreach (self::COMPANY_KEYS as $k) {
                if ($company[$k] === '') {
                    $errors['company_' . $k] = $required;
                }
            }
            if ($company['email_contacto'] !== '' && !is_email($company['email_contacto'])) {
                $errors['company_email_contacto'] = __('El email no es válido.', 'motorlan-portal');
            }
        }
        if ($errors) {
            return [['values' => $values, 'errors' => $errors], []];
        }

        $body = ['personal_data' => $personal, 'company_data' => $is_company ? $company : (object) []];
        $password = $this->field('current_password', false);
        if ($password !== '') {
            $body['current_password'] = $password;
        }
        $r = Api::call('POST', Api::route('profile'), [], $body);

        if (!$r['ok']) {
            $code = $r['error']['code'] ?? '';
            $message = AuthMessages::from($r['data'], __('Error al guardar los datos', 'motorlan-portal'));
            if ($code === 'password_required' || $code === 'incorrect_password') {
                // Se queda en el diálogo de la contraseña para reintentar.
                return [['values' => $values, 'email_dialog' => true, 'email_error' => $message], []];
            }
            $field_errors = $code === 'invalid_email' || $code === 'email_exists' ? ['email' => $message] : [];
            return [['values' => $values, 'errors' => $field_errors, 'error' => $field_errors ? '' : $message], self::toast_header($message, 'error')];
        }

        $pending = !empty($r['data']['email_change_pending']);
        $text = $pending
            ? (string) ($r['data']['message'] ?? __('Te hemos enviado un enlace para confirmar tu nuevo email.', 'motorlan-portal'))
            : __('Datos guardados correctamente', 'motorlan-portal');
        return [['saved' => true], self::toast_header($text, 'success')];
    }

    /** @return array{0: array, 1: array} */
    protected function cancel_email_change(): array
    {
        $r = Api::call('POST', Api::route('profile/cancel-email-change'), [], []);
        if (!$r['ok']) {
            $message = __('Error al guardar los datos', 'motorlan-portal');
            return [['error' => $message], self::toast_header($message, 'error')];
        }
        return [[], self::toast_header(__('Cambio de email cancelado.', 'motorlan-portal'))];
    }

    protected function context(array $profile, array $state): array
    {
        $personal = (array) ($profile['personal_data'] ?? []);
        $company = (array) ($profile['company_data'] ?? []);
        $values = $state['values'] ?? [
            'personal'   => $personal,
            'company'    => $company,
            'is_company' => !empty($company['nombre']),
        ];

        return [
            'page_title' => __('Mi perfil', 'motorlan-portal'),
            'profile'    => [
                'nombre'        => (string) ($personal['nombre'] ?? ''),
                'apellidos'     => (string) ($personal['apellidos'] ?? ''),
                'email'         => (string) ($personal['email'] ?? ''),
                'avatar'        => (string) ($personal['avatar'] ?? ''),
                'pending_email' => (string) ($profile['pending_email'] ?? ''),
            ],
            'values'     => $values,
            'state'      => $state + ['errors' => [], 'error' => '', 'email_dialog' => false, 'email_error' => ''],
            'action'     => Urls::spa('dashboard/user/account'),
            'nonce'      => wp_create_nonce(self::PANEL_NONCE),
            'account_js' => [
                'apiBase' => esc_url_raw(rest_url('motorlan/v1/')),
                'i18n'    => [
                    'avatarUpdated' => __('Avatar actualizado correctamente', 'motorlan-portal'),
                    'avatarError'   => __('Error al actualizar el avatar', 'motorlan-portal'),
                    'imageError'    => __('Ha ocurrido un error al procesar la imagen.', 'motorlan-portal'),
                    'notImage'      => __('Elige una imagen (JPG, PNG, WebP o GIF).', 'motorlan-portal'),
                    'sendCodeError' => __('Error al enviar el código de verificación.', 'motorlan-portal'),
                    'invalidCode'   => __('El código de verificación es incorrecto o ha expirado.', 'motorlan-portal'),
                    'mismatch'      => __('Las contraseñas no coinciden', 'motorlan-portal'),
                    'weak'          => __('La contraseña no cumple los requisitos', 'motorlan-portal'),
                    'required'      => __('Este campo es obligatorio', 'motorlan-portal'),
                    'passwordRequired' => __('Introduce tu contraseña actual para cambiar el email.', 'motorlan-portal'),
                ],
            ],
        ];
    }
}
