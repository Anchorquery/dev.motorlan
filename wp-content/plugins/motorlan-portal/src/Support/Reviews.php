<?php

namespace MotorlanPortal\Support;

/**
 * Kit de valoración (RatingModal.vue): el diálogo components/rating-dialog.twig hace POST a
 * la URL de la pantalla con action=review; el controlador llama a Reviews::submit(), que
 * pasa los datos a POST /motorlan/v1/reviews (la API decide quién puede valorar a quién,
 * si la compra está anulada y si ya se valoró).
 *
 *   if ($this->field('action') === 'review') {
 *       [$ok, $msg] = Reviews::submit($_POST);   // valida, llama a la API y da el texto del aviso
 *   }
 */
class Reviews
{
    const MAX_COMMENT = 1000;

    /** @return array{0: bool, 1: string} [correcto, texto del aviso] */
    public static function submit(array $post): array
    {
        $post = wp_unslash($post);
        $uuid = isset($post['purchase_uuid']) && is_string($post['purchase_uuid']) ? sanitize_text_field($post['purchase_uuid']) : '';
        $role = isset($post['target_role']) && in_array($post['target_role'], ['buyer', 'seller'], true) ? $post['target_role'] : '';
        $rating = isset($post['rating']) ? (int) $post['rating'] : 0;
        $comment = isset($post['comment']) && is_string($post['comment']) ? trim(sanitize_textarea_field($post['comment'])) : '';

        if ($uuid === '' || $role === '' || $rating < 1 || $rating > 5) {
            return [false, __('Elige de 1 a 5 estrellas.', 'motorlan-portal')];
        }
        // La SPA exigía el comentario (la API lo admite vacío): se mantiene la regla de la SPA.
        if ($comment === '') {
            return [false, __('El comentario es obligatorio', 'motorlan-portal')];
        }
        if (mb_strlen($comment) > self::MAX_COMMENT) {
            return [false, __('Máximo 1000 caracteres', 'motorlan-portal')];
        }
        $r = Api::call('POST', Api::route('reviews'), [], [
            'purchase_uuid' => $uuid,
            'rating'        => $rating,
            'comment'       => $comment,
            'target_role'   => $role,
        ]);
        if (!$r['ok']) {
            $m = (string) ($r['error']['message'] ?? '');
            return [false, $m !== '' ? $m : __('Error al enviar la valoración', 'motorlan-portal')];
        }
        return [true, __('¡Gracias! Tu valoración se ha guardado.', 'motorlan-portal')];
    }
}
