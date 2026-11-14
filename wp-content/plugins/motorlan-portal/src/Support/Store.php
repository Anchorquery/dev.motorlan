<?php

namespace MotorlanPortal\Support;

/**
 * Tienda: filtros de la URL, consulta a la API y datos de presentación de las publicaciones.
 *
 * Los parámetros de la URL son los de la SPA (enlaces compartidos y marcadores siguen
 * valiendo): q, type (modelo), tipo + tech (tipo de producto), brand, state, par, pot, vel,
 * page. Nuevos: ptype (radio del tipo de producto, se traduce a tipo + tech) y sort.
 * Todo se valida contra listas cerradas: lo desconocido se descarta.
 */
class Store
{
    const PER_PAGE = 12;

    /** Opciones de los selects (valor => etiqueta). Rangos [min, max) como la API. */
    public static function options(): array
    {
        return [
            'ptype' => [
                ''          => __('Todos', 'motorlan-portal'),
                'motor-ac'  => __('Motor AC', 'motorlan-portal'),
                'motor-dc'  => __('Motor DC', 'motorlan-portal'),
                'regulador' => __('Regulador', 'motorlan-portal'),
                'otros'     => __('Otros', 'motorlan-portal'),
            ],
            'pot' => [
                ''           => __('Seleccionar potencia', 'motorlan-portal'),
                '0-100'      => __('0-100 kW / 0-136 CV', 'motorlan-portal'),
                '100-300'    => __('100-300 kW / 136-408 CV', 'motorlan-portal'),
                '300-999999' => __('> 300 kW / > 408 CV', 'motorlan-portal'),
            ],
            'par' => [
                ''          => __('Seleccionar PAR (Nm)', 'motorlan-portal'),
                '0-5'       => '0-5',
                '5-20'      => '5-20',
                '20-50'     => '20-50',
                '50-999999' => '>50',
            ],
            'vel' => [
                ''            => __('Seleccionar velocidad', 'motorlan-portal'),
                '0-1500'      => __('0-1.500 rpm', 'motorlan-portal'),
                '1500-3000'   => __('1.500-3.000 rpm', 'motorlan-portal'),
                '3000-5000'   => __('3.000-5.000 rpm', 'motorlan-portal'),
                '5000-999999' => __('> 5.000 rpm', 'motorlan-portal'),
            ],
            'state' => [
                ''         => __('Seleccionar estado', 'motorlan-portal'),
                'new'      => __('Nuevo', 'motorlan-portal'),
                'used'     => __('Usado', 'motorlan-portal'),
                'restored' => __('Reacondicionado', 'motorlan-portal'),
            ],
            'sort' => [
                'recent' => __('Más recientes', 'motorlan-portal'),
                'oldest' => __('Más antiguos', 'motorlan-portal'),
                'title'  => __('Nombre (A-Z)', 'motorlan-portal'),
            ],
        ];
    }

    /**
     * Filtros normalizados desde $_GET (o el array dado).
     *
     * @return array{q:string,type:string,ptype:string,tipo:string,tech:string,brand:int,state:string,par:string,pot:string,vel:string,sort:string,page:int}
     */
    public static function filters(?array $src = null): array
    {
        $src = $src ?? wp_unslash($_GET);
        $opt = self::options();
        $str = function ($key, $max = 100) use ($src) {
            $v = isset($src[$key]) && is_scalar($src[$key]) ? trim(sanitize_text_field((string) $src[$key])) : '';
            return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
        };
        $pick = function ($key) use ($str, $opt) {
            $v = $str($key, 20);
            return $v !== '' && isset($opt[$key][$v]) ? $v : '';
        };

        // Tipo de producto: el radio (ptype) manda; si no, tipo + tech de la SPA.
        $ptype = $pick('ptype');
        $tipo  = '';
        $tech  = '';
        if ($ptype === '' && !array_key_exists('ptype', $src)) {
            $tipo = $str('tipo', 40);
            $tech = in_array($str('tech', 4), ['ac', 'dc'], true) ? $str('tech', 4) : '';
            $ptype = self::ptype_for($tipo, $tech);
        }
        [$tipo, $tech] = self::tipo_for($ptype);

        $f = [
            'q'     => $str('q'),
            'type'  => $str('type', 60),
            'ptype' => $ptype,
            'tipo'  => $tipo,
            'tech'  => $tech,
            'brand' => isset($src['brand']) && is_numeric($src['brand']) ? max(0, (int) $src['brand']) : 0,
            'state' => $pick('state'),
            'par'   => $pick('par'),
            'pot'   => $pick('pot'),
            'vel'   => $pick('vel'),
            'sort'  => $pick('sort') ?: 'recent',
            'page'  => isset($src['page']) && is_numeric($src['page']) ? max(1, min(500, (int) $src['page'])) : 1,
        ];

        // Como la SPA: el PAR no aplica a motores AC, y modelo y filtros avanzados se
        // excluyen (el formulario deshabilita unos cuando hay otros). Gana el modelo.
        if ($f['tech'] === 'ac') {
            $f['par'] = '';
        }
        if ($f['type'] !== '') {
            foreach (['ptype', 'tipo', 'tech', 'state', 'par', 'pot', 'vel'] as $k) {
                $f[$k] = '';
            }
            $f['brand'] = 0;
        }
        return $f;
    }

    /** Filtros activos (como el contador de la SPA: búsqueda incluida; orden y página no). */
    public static function active_count(array $f): int
    {
        $n = 0;
        foreach (['q', 'type', 'tipo', 'tech', 'state', 'par', 'pot', 'vel'] as $k) {
            $n += $f[$k] !== '' ? 1 : 0;
        }
        return $n + ($f['brand'] ? 1 : 0);
    }

    /** Parámetros de la URL pública (sin vacíos ni valores por defecto). */
    public static function query_args(array $f, ?int $page = null): array
    {
        $page = $page ?? $f['page'];
        $args = [
            'q'     => $f['q'],
            'type'  => $f['type'],
            'tipo'  => $f['tipo'],
            'tech'  => $f['tech'],
            'brand' => $f['brand'] ?: '',
            'state' => $f['state'],
            'par'   => $f['par'],
            'pot'   => $f['pot'],
            'vel'   => $f['vel'],
            'sort'  => $f['sort'] !== 'recent' ? $f['sort'] : '',
            'page'  => $page > 1 ? $page : '',
        ];
        return array_filter($args, function ($v) { return $v !== '' && $v !== null; });
    }

    public static function url(array $f, ?int $page = null): string
    {
        $args = self::query_args($f, $page);
        $base = SpaPages::store_url();
        return $args ? $base . '?' . http_build_query($args, '', '&', PHP_QUERY_RFC3986) : $base;
    }

    /** Consulta a /store/publicaciones (mismos parámetros que manda la SPA). */
    public static function search(array $f): array
    {
        $sort = ['recent' => ['date', 'desc'], 'oldest' => ['date', 'asc'], 'title' => ['title', 'asc']][$f['sort']] ?? ['date', 'desc'];
        $query = array_filter([
            'per_page'             => self::PER_PAGE,
            'page'                 => $f['page'],
            'status'               => 'publish',
            'search'               => $f['q'],
            'tipo'                 => $f['tipo'],
            'marca'                => $f['brand'] ?: '',
            'estado_del_articulo'  => $f['state'],
            'potencia'             => $f['pot'],
            'velocidad'            => $f['vel'],
            'par_nominal'          => $f['par'],
            'tipo_de_alimentacion' => $f['tech'],
            // La SPA quita puntos, guiones, barras y espacios antes de buscar la referencia.
            'tipo_o_referencia'    => preg_replace('/[.\-\/\s]/', '', $f['type']),
            'orderby'              => $sort[0],
            'order'                => $sort[1],
        ], function ($v) { return $v !== '' && $v !== null; });

        $res  = Api::get(Api::route('store/publicaciones'), $query);
        $ok   = is_array($res);
        $data = $ok && isset($res['data']) && is_array($res['data']) ? $res['data'] : [];
        $pg   = $ok && isset($res['pagination']) && is_array($res['pagination']) ? $res['pagination'] : [];

        return [
            'ok'    => $ok,
            'items' => array_map([self::class, 'card'], $data),
            'total' => (int) ($pg['total'] ?? 0),
            'pages' => max(1, (int) ($pg['totalPages'] ?? 1)),
            'page'  => (int) ($pg['currentPage'] ?? $f['page']),
        ];
    }

    /** Marcas para el select (term_id => nombre). */
    public static function brands(): array
    {
        $out = ['' => __('Seleccionar marcas', 'motorlan-portal')];
        $terms = Api::get(Api::route('marcas'));
        $terms = is_array($terms) && isset($terms['data']) && is_array($terms['data']) ? $terms['data'] : (array) $terms;
        foreach ($terms as $t) {
            if (is_array($t) && isset($t['term_id'], $t['name'])) {
                $out[(int) $t['term_id']] = html_entity_decode((string) $t['name'], ENT_QUOTES, 'UTF-8');
            }
        }
        return $out;
    }

    // ── Presentación ──────────────────────────────────────────────────────────────────

    /** Datos mínimos de una tarjeta del listado. */
    public static function card(array $p): array
    {
        $slug = (string) ($p['slug'] ?? '');
        return [
            'id'    => (int) ($p['id'] ?? 0),
            'title' => html_entity_decode((string) ($p['title'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'slug'  => $slug,
            'url'   => SpaPages::product_url($slug),
            'image' => self::image_url($p['imagen_destacada'] ?? null, 'medium_large'),
        ];
    }

    /** URL de una imagen de la API (imagen_destacada o campo imagen de ACF) en un tamaño. */
    public static function image_url($img, string $size = 'large'): string
    {
        if (!is_array($img)) {
            return '';
        }
        if (!empty($img['sizes'][$size]) && is_string($img['sizes'][$size])) {
            return $img['sizes'][$size];
        }
        return isset($img['url']) && is_string($img['url']) ? $img['url'] : '';
    }

    /** "Consultar precio" (precio_negociable = sí; valores antiguos incluidos, como la SPA). */
    public static function is_consult_price(array $acf): bool
    {
        $v = $acf['precio_negociable'] ?? null;
        if (is_array($v)) {
            $v = $v[0] ?? null;
        }
        if (is_bool($v)) {
            return $v;
        }
        return in_array(strtolower(trim((string) $v)), ['si', 'sí', 'yes', 'true', '1'], true);
    }

    /** Valor de UI del tipo de producto a partir del término y la alimentación. */
    public static function ptype_for(string $tipo_slug, string $tech): string
    {
        if ($tipo_slug === '') {
            return '';
        }
        if ($tipo_slug === 'motor') {
            return $tech === 'dc' ? 'motor-dc' : ($tech === 'ac' ? 'motor-ac' : '');
        }
        if (preg_match('/regulador/i', $tipo_slug)) {
            return 'regulador';
        }
        return 'otros';
    }

    /** [tipo, tech] para la API a partir del valor de UI. */
    public static function tipo_for(string $ptype): array
    {
        switch ($ptype) {
            case 'motor-ac': return ['motor', 'ac'];
            case 'motor-dc': return ['motor', 'dc'];
            case 'regulador': return ['regulador', ''];
            case 'otros': return ['otro-repuesto', ''];
        }
        return ['', ''];
    }

    /** Nombre del país por código (lista de /countries en el idioma actual). */
    public static function country_name(string $code): string
    {
        if ($code === '') {
            return '';
        }
        static $map = null;
        if ($map === null) {
            $map = [];
            $list = Api::get(Api::route('countries'), ['lang' => substr((string) (apply_filters('wpml_current_language', null) ?: get_locale()), 0, 2)]);
            foreach ((array) $list as $c) {
                if (is_array($c) && isset($c['value'], $c['title'])) {
                    $map[strtolower((string) $c['value'])] = (string) $c['title'];
                }
            }
        }
        return $map[strtolower($code)] ?? $code;
    }

    /**
     * Todo lo que pinta la ficha a partir de la publicación de la API.
     * $viewer_id: usuario actual (0 = invitado) para saber si es el dueño.
     */
    public static function product(array $p, int $viewer_id = 0): array
    {
        $acf    = isset($p['acf']) && is_array($p['acf']) ? $p['acf'] : [];
        $author = isset($p['author']) && is_array($p['author']) ? $p['author'] : [];
        $title  = html_entity_decode((string) ($p['title'] ?? ''), ENT_QUOTES, 'UTF-8');
        $owner  = $viewer_id > 0 && (int) ($author['id'] ?? $p['author_id'] ?? 0) === $viewer_id;

        // Galería: destacada + galería + imagen del motor, sin repetir (ProductImage.vue).
        $images = [];
        $push = function ($img) use (&$images, $title) {
            $full  = self::image_url($img, 'large');
            $thumb = self::image_url($img, 'thumbnail') ?: $full;
            $id    = is_array($img) ? (int) ($img['id'] ?? $img['ID'] ?? 0) : 0;
            if ($full === '') {
                return;
            }
            foreach ($images as $i) {
                if ($i['src'] === $full || ($id && $i['id'] === $id)) {
                    return;
                }
            }
            $alt = is_array($img) && !empty($img['alt']) ? (string) $img['alt'] : $title;
            $images[] = ['id' => $id, 'src' => $full, 'thumb' => $thumb, 'alt' => $alt];
        };
        $push($p['imagen_destacada'] ?? null);
        foreach ((array) ($acf['motor_gallery'] ?? []) as $img) {
            $push($img);
        }
        $push($acf['motor_image'] ?? null);

        // Precio: la API ya lo quita con "Consultar precio" (salvo al dueño, que lo ve como referencia).
        $consult = self::is_consult_price($acf);
        $price   = isset($acf['precio_de_venta']) && is_numeric($acf['precio_de_venta']) && (float) $acf['precio_de_venta'] > 0 ? (float) $acf['precio_de_venta'] : null;
        $show_price = $price !== null && (!$consult || $owner);

        $tipo_slug = (string) ($p['tipo'][0]['slug'] ?? '');
        $ptype = self::ptype_for($tipo_slug, (string) ($acf['tipo_de_alimentacion'] ?? ''));
        if ($tipo_slug === 'motor' && $ptype === '') {
            $ptype = 'motor-ac'; // Vue: motor sin alimentación = AC
        }
        $tipo_label = $ptype !== '' ? (self::options()['ptype'][$ptype] ?? '') : (string) ($p['tipo'][0]['name'] ?? '');

        $country  = self::country_name(is_scalar($acf['pais'] ?? null) ? (string) $acf['pais'] : '');
        $province = is_scalar($acf['provincia'] ?? null) ? (string) $acf['provincia'] : '';
        $location = $country !== '' && $province !== '' ? $country . ' / ' . $province : ($country ?: $province);

        $seller = trim(((string) ($author['first_name'] ?? '')) . ' ' . ((string) ($author['last_name'] ?? '')));
        $rating = $author['acf']['calificacion'] ?? null;
        $rating = is_numeric($rating) && (float) $rating > 0 ? (float) $rating : null;
        $sales  = $author['acf']['ventas'] ?? null;

        $stock = $acf['stock'] ?? null;
        $status = (string) ($p['status'] ?? 'publish');
        $acf_status = (string) ($acf['publicar_acf'] ?? '');
        $available = $status === 'publish' && !in_array($acf_status, ['paused', 'sold'], true)
            && !($stock !== null && $stock !== '' && (int) $stock <= 0);

        $num = function ($key, $unit) use ($acf) {
            $v = $acf[$key] ?? '';
            return is_scalar($v) && trim((string) $v) !== '' ? trim((string) $v) . $unit : '';
        };
        $specs = array_filter([
            __('Marca', 'motorlan-portal')      => html_entity_decode((string) ($p['marca_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
            __('Modelo', 'motorlan-portal')     => is_scalar($acf['tipo_o_referencia'] ?? null) ? (string) $acf['tipo_o_referencia'] : '',
            __('Potencia', 'motorlan-portal')   => $num('potencia', ' kW'),
            __('Velocidad', 'motorlan-portal')  => $num('velocidad', ' rpm'),
            __('Par nominal', 'motorlan-portal') => $num('par_nominal', ' Nm'),
            __('Voltaje', 'motorlan-portal')    => $num('voltaje', ' V'),
            __('Intensidad', 'motorlan-portal') => $num('intensidad', ' A'),
        ], function ($v) { return $v !== ''; });

        $docs = [];
        foreach ((array) ($acf['documentacion_adicional'] ?? []) as $doc) {
            $file = is_array($doc) ? ($doc['archivo'] ?? $doc['file'] ?? $doc) : null;
            $url  = is_array($file) ? (string) ($file['url'] ?? '') : (is_string($file) ? $file : '');
            if ($url === '' || !wp_http_validate_url($url)) {
                continue;
            }
            $name = is_array($doc) && !empty($doc['nombre']) ? (string) $doc['nombre']
                : (is_array($file) && !empty($file['title']) ? (string) $file['title'] : basename((string) wp_parse_url($url, PHP_URL_PATH)));
            $docs[] = ['name' => $name, 'url' => $url];
        }

        return [
            'id'          => (int) ($p['id'] ?? 0),
            'uuid'        => (string) ($p['uuid'] ?? ''),
            'slug'        => (string) ($p['slug'] ?? ''),
            'title'       => $title,
            'url'         => SpaPages::product_url((string) ($p['slug'] ?? '')),
            'images'      => $images,
            'show_price'  => $show_price,
            'price'       => $price,
            'price_label' => $price !== null ? number_format_i18n($price, floor($price) == $price ? 0 : 2) . ' €' : '',
            'consult'     => $consult,
            'tipo_slug'   => $tipo_slug,
            'tipo_label'  => $tipo_label,
            'brand'       => html_entity_decode((string) ($p['marca_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?: '-',
            'location'    => $location,
            'seller'      => [
                'id'       => (int) ($author['id'] ?? 0),
                'name'     => $seller !== '' ? $seller : __('Vendedor', 'motorlan-portal'),
                'initials' => self::initials($seller),
                'avatar'   => isset($author['avatar']) && is_string($author['avatar']) && strpos($author['avatar'], 'gravatar.com') === false ? $author['avatar'] : '',
                'rating'   => $rating,
                'reviews'  => (int) ($author['acf']['valoraciones'] ?? 0),
                'sales'    => is_numeric($sales) ? (int) $sales : null,
            ],
            'stock'       => $stock === null || $stock === '' ? null : (int) $stock,
            'warranty'    => !empty($acf['garantia_motorlan']),
            'description' => is_string($acf['descripcion'] ?? null) ? wp_kses_post((string) $acf['descripcion']) : '',
            'specs'       => $specs,
            'docs'        => $docs,
            'owner'       => $owner,
            'available'   => $available,
            // Acciones (consultar precio, favorito): ni el dueño ni con el artículo retirado.
            'actions'     => !$owner && $available,
        ];
    }

    /** Relacionados: 4 del mismo tipo, sin la publicación actual (RelatedProducts.vue). */
    public static function related(array $product): array
    {
        if ($product['tipo_slug'] === '') {
            return [];
        }
        $res = Api::get(Api::route('store/publicaciones'), ['per_page' => 5, 'page' => 1, 'status' => 'publish', 'tipo' => $product['tipo_slug'], 'orderby' => 'date', 'order' => 'desc']);
        $items = is_array($res) && isset($res['data']) && is_array($res['data']) ? $res['data'] : [];
        $out = [];
        foreach ($items as $it) {
            if ((int) ($it['id'] ?? 0) !== $product['id']) {
                $out[] = self::card($it);
            }
        }
        return array_slice($out, 0, 4);
    }

    protected static function initials(string $name): string
    {
        $parts = array_values(array_filter(preg_split('/\s+/u', trim($name)) ?: []));
        if (!$parts) {
            return 'V';
        }
        $out = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $out .= function_exists('mb_substr') ? mb_strtoupper(mb_substr($part, 0, 1)) : strtoupper(substr($part, 0, 1));
        }
        return $out;
    }
}
