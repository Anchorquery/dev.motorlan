<?php

namespace MotorlanPortal\Support;

/**
 * Aviso que sobrevive a una redirección (alta de publicación → listado, duplicar → editor).
 * La SPA lo resolvía con un toast antes del router.push; aquí el servidor redirige, así que
 * el aviso se guarda un minuto por usuario y lo pinta la página siguiente
 * (components/flash.twig → $store.toast). Se consume al leerlo.
 */
class Flash
{
    const PREFIX = 'ml_portal_flash_';

    public static function set(string $text, string $color = 'success'): void
    {
        $uid = get_current_user_id();
        if ($uid && $text !== '') {
            set_transient(self::PREFIX . $uid, ['text' => $text, 'color' => $color], MINUTE_IN_SECONDS);
        }
    }

    /** @return array{text: string, color: string}|null */
    public static function pull(): ?array
    {
        $uid = get_current_user_id();
        if (!$uid) {
            return null;
        }
        $v = get_transient(self::PREFIX . $uid);
        if (!is_array($v) || empty($v['text'])) {
            return null;
        }
        delete_transient(self::PREFIX . $uid);
        return ['text' => (string) $v['text'], 'color' => (string) ($v['color'] ?? 'success')];
    }
}
