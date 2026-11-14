<?php

namespace MotorlanPortal\Support;

/**
 * Mensajes de error de los endpoints de acceso (motorlan/v1/login, /register…).
 *
 * La API responde {code, message}: como la SPA (apiErrorMessage + auth_errors.* de
 * es.json), se muestra el texto propio del código y, si no hay, el `message` de la API
 * (ya traducido con __()). Si la API manda `errors` (detalle de la contraseña débil), se
 * añaden detrás.
 */
class AuthMessages
{
    /** Texto por código de error/éxito (mismo contenido que auth_errors en la SPA). */
    public static function map(): array
    {
        return [
            'invalid_credentials'    => __('Email o contraseña incorrectos.', 'motorlan-portal'),
            'missing_credentials'    => __('Introduce tu email y tu contraseña.', 'motorlan-portal'),
            /* translators: %s: minutos */
            'too_many_attempts'      => __('Demasiados intentos. Inténtalo de nuevo en %s minutos.', 'motorlan-portal'),
            'email_not_verified'     => __('Tu cuenta aún no está activada. Revisa tu email: te hemos enviado el enlace de activación.', 'motorlan-portal'),
            'too_many_registrations' => __('Demasiados registros desde esta conexión. Inténtalo más tarde.', 'motorlan-portal'),
            'missing_fields'         => __('Faltan datos obligatorios.', 'motorlan-portal'),
            'invalid_email'          => __('El email no es válido.', 'motorlan-portal'),
            'first_name_required'    => __('El nombre es obligatorio.', 'motorlan-portal'),
            'last_name_required'     => __('El apellido es obligatorio.', 'motorlan-portal'),
            'email_exists'           => __('Este correo ya está registrado. Inicia sesión o, si no recuerdas la contraseña, restablécela. Si la cuenta no estaba activada, recibirás de nuevo el enlace de activación.', 'motorlan-portal'),
            'weak_password'          => __('La contraseña no cumple los requisitos de seguridad.', 'motorlan-portal'),
            'registration_failed'    => __('No se pudo crear la cuenta. Inténtalo de nuevo.', 'motorlan-portal'),
            'registered'             => __('Usuario registrado con éxito. Revisa tu email para activar tu cuenta.', 'motorlan-portal'),
            'token_required'         => __('Falta el token.', 'motorlan-portal'),
            'invalid_token'          => __('El enlace no es válido o ya se ha usado.', 'motorlan-portal'),
            'token_expired'          => __('El enlace ha caducado. Solicita uno nuevo.', 'motorlan-portal'),
            'already_verified'       => __('Tu cuenta ya está activa. Inicia sesión.', 'motorlan-portal'),
            'verified'               => __('Email verificado. Ya puedes iniciar sesión.', 'motorlan-portal'),
            'resend_neutral'         => __('Si la cuenta existe y no está activada, se ha enviado un nuevo enlace.', 'motorlan-portal'),
            'resent'                 => __('Te hemos enviado un nuevo enlace de activación.', 'motorlan-portal'),
            'too_many_requests'      => __('Demasiadas solicitudes. Inténtalo más tarde.', 'motorlan-portal'),
            'email_required'         => __('Indica tu email.', 'motorlan-portal'),
            'reset_sent'             => __('Si el email existe, se ha enviado un enlace para restablecer la contraseña.', 'motorlan-portal'),
            'reset_key_failed'       => __('No se pudo generar el enlace. Inténtalo de nuevo.', 'motorlan-portal'),
            'expired_key'            => __('El enlace para restablecer la contraseña ha caducado. Solicita uno nuevo.', 'motorlan-portal'),
            'invalid_key'            => __('El enlace para restablecer la contraseña no es válido o ya se ha usado. Solicita uno nuevo.', 'motorlan-portal'),
            'password_reset'         => __('La contraseña se ha restablecido. Ya puedes iniciar sesión.', 'motorlan-portal'),
        ];
    }

    /**
     * Mensaje para la respuesta de Api::call() (o su `data`).
     *
     * @param array|null $data     Cuerpo de la respuesta ({code, message, retry_minutes, errors}).
     * @param string     $fallback Texto si no hay código conocido ni mensaje.
     */
    public static function from($data, string $fallback): string
    {
        $data = is_array($data) ? $data : [];
        $code = isset($data['code']) ? (string) $data['code'] : '';
        $map  = self::map();

        if ($code !== '' && isset($map[$code])) {
            $base = $code === 'too_many_attempts'
                ? sprintf($map[$code], (string) ($data['retry_minutes'] ?? ''))
                : $map[$code];
        } else {
            $base = isset($data['message']) && is_string($data['message']) && $data['message'] !== '' ? $data['message'] : $fallback;
        }

        $details = isset($data['errors']) && is_array($data['errors']) ? array_filter($data['errors'], 'is_string') : [];
        return $details ? $base . ' ' . implode(' ', $details) : $base;
    }

    /** Errores de conexión/servidor (5xx): mismo texto que la SPA. */
    public static function connection(): string
    {
        return __('No se pudo conectar con el servidor. Revisa tu conexión.', 'motorlan-portal');
    }
}
