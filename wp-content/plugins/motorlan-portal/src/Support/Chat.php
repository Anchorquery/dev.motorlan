<?php

namespace MotorlanPortal\Support;

/**
 * Configuración del chat del panel (kit: components/chat-dialog.twig + mlChat en
 * assets/js/chat.js). Las pantallas de compras, ventas, consultas y administración la
 * construyen aquí y la pasan a la plantilla:
 *
 *   'chat' => Chat::purchase($uuid, ['title' => $nombre, 'image' => $img, 'otherName' => $vendedor])
 *   'chat' => Chat::product($product_id, $room_key, ['title' => …, 'otherName' => $comprador])
 *
 *   {% include 'components/chat-dialog.twig' with { chat: chat } %}
 *
 * Opciones: dialogId (def. ml-chat; uno distinto por chat si hay varios en la página),
 * title, image, otherName, autoOpen (abrirlo al cargar: ?openChat=true de la SPA),
 * markRead (consultas: marcar la sala leída al abrir; def. true), emptyHint.
 * El servidor no comprueba aquí el acceso: lo hace la API en cada petición (403/404 →
 * el diálogo muestra el error y deja de sondear).
 */
class Chat
{
    public static function purchase(string $uuid, array $opts = []): array
    {
        return self::base($opts) + ['kind' => 'purchase', 'uuid' => $uuid];
    }

    public static function product(int $product_id, string $room_key, array $opts = []): array
    {
        return self::base($opts) + ['kind' => 'product', 'productId' => $product_id, 'roomKey' => $room_key];
    }

    protected static function base(array $opts): array
    {
        $id = isset($opts['dialogId']) ? sanitize_html_class((string) $opts['dialogId']) : '';
        return [
            'dialogId'  => $id !== '' ? $id : 'ml-chat',
            'title'     => (string) ($opts['title'] ?? ''),
            'image'     => (string) ($opts['image'] ?? ''),
            'otherName' => (string) ($opts['otherName'] ?? ''),
            'autoOpen'  => !empty($opts['autoOpen']),
            'markRead'  => !array_key_exists('markRead', $opts) || !empty($opts['markRead']),
            'emptyHint' => isset($opts['emptyHint']) ? (string) $opts['emptyHint'] : null,
            'i18n'      => self::i18n(),
        ];
    }

    public static function i18n(): array
    {
        return [
            'withBuyer'     => __('Chat con el comprador', 'motorlan-portal'),
            'withSeller'    => __('Chat con el vendedor', 'motorlan-portal'),
            'forbiddenView' => __('No tienes permisos para ver estos mensajes.', 'motorlan-portal'),
            'forbiddenSend' => __('No tienes permisos para enviar mensajes en este chat.', 'motorlan-portal'),
            'notFound'      => __('No pudimos encontrar este chat.', 'motorlan-portal'),
            'loadError'     => __('No se pudieron cargar los mensajes.', 'motorlan-portal'),
            'sendError'     => __('No se pudo enviar el mensaje.', 'motorlan-portal'),
        ];
    }
}
