<?php

namespace MotorlanPortal\Support;

/**
 * Presentación de una publicación de la API (motorlan_get_publicacion_data) en las tablas
 * del panel: nombre como lo forma la SPA, estado con color e imagen en miniatura.
 * Lo usan favoritos y lo usarán publicaciones, compras, ventas y administración.
 */
class Publicacion
{
    /**
     * Nombre «MARCA REFERENCIA POTENCIA» (useMotorFormatter.ts → formatMotorName); sin
     * datos, el título.
     */
    public static function name(array $p): string
    {
        $acf = isset($p['acf']) && is_array($p['acf']) ? $p['acf'] : [];
        $parts = [];
        $brand = '';
        if (!empty($p['marca_name']) && is_string($p['marca_name'])) {
            $brand = $p['marca_name'];
        } elseif (isset($acf['marca']['name']) && is_string($acf['marca']['name'])) {
            $brand = $acf['marca']['name'];
        } elseif (isset($acf['marca']) && is_string($acf['marca']) && !is_numeric($acf['marca'])) {
            $brand = $acf['marca'];
        }
        if ($brand !== '') {
            $parts[] = mb_strtoupper(html_entity_decode($brand, ENT_QUOTES, 'UTF-8'));
        }
        if (!empty($acf['tipo_o_referencia']) && is_scalar($acf['tipo_o_referencia'])) {
            $parts[] = mb_strtoupper((string) $acf['tipo_o_referencia']);
        }
        if (!empty($acf['potencia']) && is_scalar($acf['potencia'])) {
            $parts[] = $acf['potencia'] . ' KW';
        } elseif (!empty($acf['par_nominal']) && is_scalar($acf['par_nominal'])) {
            $parts[] = $acf['par_nominal'] . ' NM';
        }
        return $parts ? implode(' ', $parts) : html_entity_decode((string) ($p['title'] ?? ''), ENT_QUOTES, 'UTF-8');
    }

    /** Marca en texto (columna secundaria). */
    public static function brand(array $p): string
    {
        $acf = isset($p['acf']) && is_array($p['acf']) ? $p['acf'] : [];
        $name = $acf['marca']['name'] ?? ($p['marca_name'] ?? '');
        return is_string($name) ? html_entity_decode($name, ENT_QUOTES, 'UTF-8') : '';
    }

    /**
     * Estados (publicar_acf) con su etiqueta y color de chip. `pending` faltaba en la SPA
     * (salía «Desconocido» para una publicación en revisión).
     */
    public static function statuses(): array
    {
        return [
            'publish' => ['label' => __('Publicado', 'motorlan-portal'), 'color' => 'success'],
            'pending' => ['label' => __('En revisión', 'motorlan-portal'), 'color' => 'info'],
            'draft'   => ['label' => __('Borrador', 'motorlan-portal'), 'color' => 'secondary'],
            'paused'  => ['label' => __('Pausado', 'motorlan-portal'), 'color' => 'warning'],
            'sold'    => ['label' => __('Vendido', 'motorlan-portal'), 'color' => 'error'],
        ];
    }

    public static function status(string $status): array
    {
        return self::statuses()[$status] ?? ['label' => __('Desconocido', 'motorlan-portal'), 'color' => 'info'];
    }

    /** Fila lista para una tabla del panel. */
    public static function row(array $p): array
    {
        $acf = isset($p['acf']) && is_array($p['acf']) ? $p['acf'] : [];
        $status = (string) ($p['status'] ?? '');
        return [
            'id'        => (int) ($p['id'] ?? 0),
            'uuid'      => (string) ($p['uuid'] ?? ''),
            'name'      => self::name($p),
            'brand'     => self::brand($p),
            'reference' => is_scalar($acf['tipo_o_referencia'] ?? null) ? (string) $acf['tipo_o_referencia'] : '',
            'status'    => $status,
            'chip'      => self::status($status),
            'url'       => SpaPages::product_url((string) ($p['slug'] ?? '')),
            'image'     => Store::image_url($p['imagen_destacada'] ?? null, 'thumbnail'),
        ];
    }
}
