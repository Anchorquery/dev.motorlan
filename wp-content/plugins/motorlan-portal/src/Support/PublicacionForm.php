<?php

namespace MotorlanPortal\Support;

/**
 * Formulario de publicación del vendedor: alta (asistente) y edición (pestañas) comparten
 * campos, valores y envío. Port de usePublicationForm.ts, utils/tipoMapping.ts y los
 * Step*.vue de la SPA, SIN la lógica de negocio: la validación definitiva, la subida de
 * archivos, la idempotencia y el estado (no admin → en revisión) siguen en la API
 * (POST /publicaciones y POST /publicaciones/uuid/{uuid}).
 *
 * Nombres de campo del formulario (sirven también sin JS):
 *   tipo_ui (motor-ac|motor-dc|regulador|otro-repuesto) · acf[marca] (-1 = «Otros») · marca_custom
 *   acf[tipo_o_referencia] · acf[potencia|velocidad|par_nominal|voltaje|intensidad]
 *   acf[descripcion|estado_del_articulo|pais|provincia|precio_de_venta|precio_negociable|stock]
 *   motor_image · motor_gallery[] · documentacion_adicional_archivos[] + doc_nombre_nuevo[]
 *   Edición: motor_image_id · gallery_keep[] · doc_keep_id[] + doc_keep_nombre[]
 */
class PublicacionForm
{
    /** Máximo de imágenes de galería y de documentos (ACF y API). */
    const MAX_GALLERY = 5;
    const MAX_DOCS = 5;
    const OTHER_BRAND = -1;
    const MOTOR_SPECS = ['potencia', 'velocidad', 'par_nominal', 'voltaje', 'intensidad'];
    const TEXT_ACF = ['tipo_o_referencia', 'potencia', 'velocidad', 'par_nominal', 'voltaje', 'intensidad',
        'descripcion', 'estado_del_articulo', 'pais', 'provincia', 'precio_de_venta', 'stock'];

    /** Tarjetas del paso «tipo» (StepTypeSelection.vue). Iconos literales para el build. */
    public static function ui_types(): array
    {
        return [
            'motor-ac' => ['title' => __('Motor AC', 'motorlan-portal'), 'icon' => 'engine',
                'description' => __('Motores de corriente alterna: asíncronos, síncronos, servomotores…', 'motorlan-portal')],
            'motor-dc' => ['title' => __('Motor DC', 'motorlan-portal'), 'icon' => 'engine',
                'description' => __('Motores de corriente continua y sus repuestos.', 'motorlan-portal')],
            'regulador' => ['title' => __('Regulador', 'motorlan-portal'), 'icon' => 'settings',
                'description' => __('Variadores, reguladores y drives.', 'motorlan-portal')],
            'otro-repuesto' => ['title' => __('Otros repuestos', 'motorlan-portal'), 'icon' => 'settings-cog',
                'description' => __('Encoders, frenos, ventiladores y cualquier otro repuesto.', 'motorlan-portal')],
        ];
    }

    public static function conditions(): array
    {
        return [
            'new'      => __('Nuevo', 'motorlan-portal'),
            'used'     => __('Usado', 'motorlan-portal'),
            'restored' => __('Restaurado', 'motorlan-portal'),
        ];
    }

    /** Marcas, tipos y países de la API (GET /marcas, /tipos, /countries). */
    public static function catalogs(): array
    {
        $marcas = [];
        foreach ((array) Api::get(Api::route('marcas')) as $m) {
            if (is_array($m) && isset($m['term_id'], $m['name'])) {
                $marcas[(int) $m['term_id']] = html_entity_decode((string) $m['name'], ENT_QUOTES, 'UTF-8');
            }
        }
        natcasesort($marcas);
        $tipos = [];
        foreach ((array) Api::get(Api::route('tipos')) as $t) {
            if (is_array($t) && isset($t['term_id'], $t['slug'])) {
                $tipos[] = ['id' => (int) $t['term_id'], 'slug' => (string) $t['slug'], 'name' => (string) ($t['name'] ?? $t['slug'])];
            }
        }
        $countries = [];
        $lang = substr((string) (apply_filters('wpml_current_language', null) ?: get_locale()), 0, 2);
        foreach ((array) Api::get(Api::route('countries'), ['lang' => $lang]) as $c) {
            if (is_array($c) && isset($c['value'], $c['title'])) {
                $countries[strtolower((string) $c['value'])] = (string) $c['title'];
            }
        }
        if (!$countries) {
            // StepLocationCondition.vue: lista mínima si /countries falla.
            $countries = ['es' => __('España', 'motorlan-portal'), 'pt' => __('Portugal', 'motorlan-portal'), 'fr' => __('Francia', 'motorlan-portal')];
        }
        return ['marcas' => $marcas, 'tipos' => $tipos, 'countries' => $countries];
    }

    /** Término `tipo` para la opción de la interfaz (findTipoForUiSlug). */
    public static function tipo_for_ui(array $tipos, string $ui): int
    {
        $by = function ($slug) use ($tipos) {
            foreach ($tipos as $t) {
                if ($t['slug'] === $slug) {
                    return $t['id'];
                }
            }
            return 0;
        };
        if ($ui === 'motor-ac' || $ui === 'motor-dc') {
            return $by('motor');
        }
        if ($ui === 'regulador') {
            if ($id = $by('regulador')) {
                return $id;
            }
            foreach ($tipos as $t) {
                if (preg_match('/regulador/i', $t['slug'] . ' ' . $t['name'])) {
                    return $t['id'];
                }
            }
            return 0;
        }
        if ($ui === 'otro-repuesto') {
            foreach (['otro-repuesto', 'repuesto', 'otros'] as $slug) {
                if ($id = $by($slug)) {
                    return $id;
                }
            }
            foreach ($tipos as $t) {
                if ($t['slug'] !== 'motor' && !preg_match('/regulador/i', $t['slug'])) {
                    return $t['id'];
                }
            }
        }
        return 0;
    }

    /** Opción de la interfaz para el término guardado (uiSlugForTipo). */
    public static function ui_for_tipo(string $slug, string $alimentacion): string
    {
        if ($slug === '') {
            return '';
        }
        if ($slug === 'motor') {
            return $alimentacion === 'dc' ? 'motor-dc' : 'motor-ac';
        }
        return preg_match('/regulador/i', $slug) ? 'regulador' : 'otro-repuesto';
    }

    /** Valores vacíos del alta (defaultState de usePublicationForm). */
    public static function defaults(): array
    {
        return [
            'tipo_ui' => '', 'marca' => '', 'marca_custom' => '', 'tipo_o_referencia' => '',
            'potencia' => '', 'velocidad' => '', 'par_nominal' => '', 'voltaje' => '', 'intensidad' => '',
            'descripcion' => '', 'estado_del_articulo' => 'new', 'pais' => '', 'provincia' => '',
            'precio_de_venta' => '', 'consultar' => false, 'stock' => '1', 'status' => '',
            'image' => null, 'gallery' => [], 'docs' => [],
        ];
    }

    /** Valores de una publicación de GET /publicaciones/uuid/{uuid} (fetchPublication del editor). */
    public static function from_publication(array $p): array
    {
        $acf = isset($p['acf']) && is_array($p['acf']) ? $p['acf'] : [];
        $v = self::defaults();
        foreach (self::TEXT_ACF as $k) {
            $v[$k] = isset($acf[$k]) && is_scalar($acf[$k]) ? (string) $acf[$k] : '';
        }
        // La descripción puede traer HTML de altas antiguas; la API la guarda como texto
        // (sanitize_textarea_field), así que se edita ya como texto con sus saltos de línea.
        $v['descripcion'] = self::plain_text($v['descripcion']);
        $tipo_slug = '';
        foreach ((array) ($p['tipo'] ?? []) as $t) {
            if (is_array($t) && !empty($t['slug'])) {
                $tipo_slug = (string) $t['slug'];
                break;
            }
        }
        $v['tipo_ui'] = self::ui_for_tipo($tipo_slug, (string) ($acf['tipo_de_alimentacion'] ?? 'ac'));
        $marca = $acf['marca'] ?? '';
        $v['marca'] = is_array($marca) ? (string) (int) ($marca['id'] ?? $marca['term_id'] ?? 0) : (is_numeric($marca) ? (string) (int) $marca : '');
        if (!empty($p['pending_brand']) && is_string($p['pending_brand'])) {
            $v['marca'] = (string) self::OTHER_BRAND;
            $v['marca_custom'] = $p['pending_brand'];
        }
        // Valores antiguos: países y estado en texto (el editor de la SPA ya los convertía).
        $countries = ['españa' => 'es', 'spain' => 'es', 'portugal' => 'pt', 'francia' => 'fr', 'france' => 'fr'];
        $v['pais'] = $countries[mb_strtolower($v['pais'])] ?? strtolower($v['pais']);
        $conditions = ['nuevo' => 'new', 'usado' => 'used', 'restaurado' => 'restored'];
        $v['estado_del_articulo'] = $conditions[mb_strtolower($v['estado_del_articulo'])] ?? $v['estado_del_articulo'];
        // «Consultar precio»: la SPA solo marcaba la casilla con 'yes' y con 'si' (valor
        // habitual de ACF) la dejaba desmarcada: al guardar se perdía sin que el usuario lo tocara.
        $v['consultar'] = Store::is_consult_price($acf);
        if ($v['stock'] === '') {
            $v['stock'] = '1';
        }
        $v['status'] = (string) ($p['status'] ?? '');
        $v['image'] = self::file_item($acf['motor_image'] ?? null);
        foreach ((array) ($acf['motor_gallery'] ?? []) as $img) {
            if ($item = self::file_item($img)) {
                $v['gallery'][] = $item;
            }
        }
        foreach ((array) ($acf['documentacion_adicional'] ?? []) as $doc) {
            if (!is_array($doc)) {
                continue;
            }
            $file = self::file_item($doc['archivo'] ?? null);
            if ($file) {
                $v['docs'][] = ['nombre' => (string) ($doc['nombre'] ?? ''), 'id' => $file['id'], 'url' => $file['url'], 'filename' => $file['filename']];
            }
        }
        return $v;
    }

    protected static function plain_text(string $html): string
    {
        if (strpos($html, '<') === false) {
            return $html;
        }
        $text = preg_replace(['#<br\s*/?>#i', '#</(p|div|li|h[1-6])>#i'], "\n", $html);
        $text = html_entity_decode(wp_strip_all_tags((string) $text), ENT_QUOTES, 'UTF-8');
        return trim((string) preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', $text)));
    }
    /** Adjunto de ACF (array u id) → {id, url, thumb, filename}. */
    protected static function file_item($f): ?array
    {
        if (is_numeric($f) && (int) $f > 0) {
            $id = (int) $f;
            $url = (string) wp_get_attachment_url($id);
            return $url !== '' ? ['id' => $id, 'url' => $url, 'thumb' => (string) (wp_get_attachment_image_url($id, 'thumbnail') ?: $url), 'filename' => wp_basename($url)] : null;
        }
        if (!is_array($f)) {
            return null;
        }
        $id = (int) ($f['id'] ?? $f['ID'] ?? 0);
        $url = isset($f['url']) && is_string($f['url']) ? $f['url'] : '';
        if (!$id || $url === '') {
            return null;
        }
        $thumb = isset($f['sizes']['thumbnail']) && is_string($f['sizes']['thumbnail']) ? $f['sizes']['thumbnail'] : $url;
        return ['id' => $id, 'url' => $url, 'thumb' => $thumb, 'filename' => (string) ($f['filename'] ?? $f['title'] ?? wp_basename($url))];
    }

    /**
     * Valores enviados (para volver a pintar el formulario sin JS tras un error). Los
     * archivos nuevos no se pueden conservar; los existentes (edición) sí.
     */
    public static function from_post(array $post, array $base): array
    {
        $acf = isset($post['acf']) && is_array($post['acf']) ? $post['acf'] : [];
        $v = $base;
        $str = function ($x) { return is_scalar($x) ? trim(sanitize_text_field((string) $x)) : ''; };
        foreach (self::TEXT_ACF as $k) {
            if (array_key_exists($k, $acf)) {
                $v[$k] = $k === 'descripcion' ? sanitize_textarea_field((string) $acf[$k]) : $str($acf[$k]);
            }
        }
        if (isset($post['tipo_ui']) && isset(self::ui_types()[(string) $post['tipo_ui']])) {
            $v['tipo_ui'] = (string) $post['tipo_ui'];
        }
        $v['marca'] = $str($acf['marca'] ?? $v['marca']);
        $v['marca_custom'] = mb_strtoupper($str($post['marca_custom'] ?? ''));
        $v['consultar'] = !empty($acf['precio_negociable']) && $acf['precio_negociable'] === 'yes';
        if (array_key_exists('motor_image_id', $post)) {
            $keep = (int) $post['motor_image_id'];
            $v['image'] = $base['image'] && (int) $base['image']['id'] === $keep ? $base['image'] : null;
        }
        if (array_key_exists('gallery_keep', $post) || array_key_exists('gallery_sent', $post)) {
            $keep = array_map('intval', (array) ($post['gallery_keep'] ?? []));
            $v['gallery'] = array_values(array_filter($base['gallery'], function ($g) use ($keep) { return in_array((int) $g['id'], $keep, true); }));
        }
        if (array_key_exists('docs_sent', $post)) {
            $ids = array_map('intval', (array) ($post['doc_keep_id'] ?? []));
            $names = (array) ($post['doc_keep_nombre'] ?? []);
            $docs = [];
            foreach ($base['docs'] as $d) {
                $i = array_search((int) $d['id'], $ids, true);
                if ($i !== false) {
                    $docs[] = ['nombre' => $str($names[$i] ?? $d['nombre'])] + $d;
                }
            }
            $v['docs'] = $docs;
        }
        return $v;
    }

    /** Nombre «MARCA REFERENCIA 2 KW» (getFormattedPreview); es el título que envía la SPA. */
    public static function title(array $v, array $marcas): string
    {
        $parts = [];
        if ((int) $v['marca'] === self::OTHER_BRAND && $v['marca_custom'] !== '') {
            $parts[] = mb_strtoupper($v['marca_custom']);
        } elseif (isset($marcas[(int) $v['marca']])) {
            $parts[] = mb_strtoupper($marcas[(int) $v['marca']]);
        }
        if ($v['tipo_o_referencia'] !== '') {
            $parts[] = mb_strtoupper($v['tipo_o_referencia']);
        }
        if ($v['potencia'] !== '') {
            $parts[] = $v['potencia'] . ' KW';
        } elseif ($v['par_nominal'] !== '') {
            $parts[] = $v['par_nominal'] . ' NM';
        }
        return implode(' ', $parts);
    }

    /**
     * Campos de la API a partir del POST (submitForm del alta / updatePublication del editor).
     * $mode: 'add' | 'edit'. Devuelve [campos, archivos] listos para Api::multipart().
     */
    public static function api_request(array $post, array $files, string $mode, array $catalogs, string $status): array
    {
        $v = self::from_post($post, self::defaults());
        $ui = $v['tipo_ui'];
        $is_motor = $ui === 'motor-ac' || $ui === 'motor-dc';
        $acf = [];
        foreach (self::TEXT_ACF as $k) {
            $acf[$k] = $v[$k] === '' ? null : $v[$k];
        }
        $acf['tipo_o_referencia'] = $v['tipo_o_referencia'];
        $acf['descripcion'] = $v['descripcion'];
        $acf['provincia'] = $v['provincia'];
        $acf['marca'] = $v['marca'] === '' ? null : (int) $v['marca'];
        $acf['precio_negociable'] = $v['consultar'] ? 'yes' : 'no';
        $acf['posibilidad_de_alquiler'] = 'No';
        if ($is_motor) {
            $acf['tipo_de_alimentacion'] = $ui === 'motor-dc' ? 'dc' : 'ac';
        } else {
            // Regulador/otros: las specs de motor no se guardan (handleTypeSelect de la SPA).
            foreach (self::MOTOR_SPECS as $k) {
                $acf[$k] = null;
            }
        }
        $fields = [
            'title'  => self::title($v, $catalogs['marcas']),
            'status' => $status,
        ];
        $tipo = $ui !== '' ? self::tipo_for_ui($catalogs['tipos'], $ui) : 0;
        if ($tipo || $mode === 'add') {
            $fields['tipo'] = wp_json_encode($tipo ? [$tipo] : []);
        }
        if ($acf['marca'] === self::OTHER_BRAND) {
            $acf['marca'] = null;
            if ($v['marca_custom'] !== '') {
                $fields['marca_custom'] = $v['marca_custom'];
            }
        }
        if ($mode === 'add') {
            // El alta no admite posibilidad_de_alquiler distinta de No y la deja fuera si no se toca.
            $fields['client_request_id'] = isset($post['client_request_id']) ? sanitize_key((string) $post['client_request_id']) : '';
        }
        $fields['acf'] = wp_json_encode($acf);

        // Archivos: solo los que traen fichero (los huecos vacíos de un formulario sin JS
        // fuera, para que los nombres de los documentos casen con su archivo).
        $out_files = [];
        $main = self::files_list($files['motor_image'] ?? null);
        if ($main) {
            $out_files['motor_image'] = $main[0];
        }
        $gallery = self::files_list($files['motor_gallery'] ?? null);
        if ($gallery) {
            $out_files['motor_gallery'] = self::files_pack($gallery);
        }
        $docs = [];
        $names = [];
        $doc_names = (array) ($post['doc_nombre_nuevo'] ?? []);
        foreach (self::files_list($files['documentacion_adicional_archivos'] ?? null, true) as $i => $f) {
            if ($f === null) {
                continue;
            }
            $docs[] = $f;
            $names[] = ['nombre' => isset($doc_names[$i]) && is_scalar($doc_names[$i]) ? sanitize_text_field((string) $doc_names[$i]) : ''];
        }
        if ($docs) {
            $out_files['documentacion_adicional_archivos'] = self::files_pack($docs);
            $fields['documentacion_adicional_nombres'] = wp_json_encode($names);
        }

        if ($mode === 'edit') {
            // Lo que no se envía no se toca (update-publicacion-by-uuid): se mandan siempre
            // las listas completas de lo que se conserva.
            if (!$main) {
                $fields['motor_image_id'] = isset($post['motor_image_id']) ? (string) absint($post['motor_image_id']) : '';
                if ($fields['motor_image_id'] === '0') {
                    $fields['motor_image_id'] = '';
                }
            }
            $fields['motor_gallery_ids'] = implode(',', array_filter(array_map('absint', (array) ($post['gallery_keep'] ?? []))));
            $keep = [];
            $keep_names = (array) ($post['doc_keep_nombre'] ?? []);
            foreach ((array) ($post['doc_keep_id'] ?? []) as $i => $id) {
                if (absint($id)) {
                    $keep[] = ['nombre' => isset($keep_names[$i]) && is_scalar($keep_names[$i]) ? sanitize_text_field((string) $keep_names[$i]) : '', 'archivo' => absint($id)];
                }
            }
            $fields['documentacion_adicional_ids'] = wp_json_encode($keep);
            if (!isset($fields['documentacion_adicional_nombres'])) {
                $fields['documentacion_adicional_nombres'] = '[]';
            }
        }
        return [$fields, $out_files];
    }

    /**
     * Entrada de $_FILES (simple o "campo[]") → lista de archivos con contenido.
     * $keep_index: conserva las posiciones (null en los huecos) para casar nombres.
     */
    protected static function files_list($entry, bool $keep_index = false): array
    {
        if (!is_array($entry) || !isset($entry['name'])) {
            return [];
        }
        $out = [];
        if (!is_array($entry['name'])) {
            $entry = ['name' => [$entry['name']], 'type' => [$entry['type'] ?? ''], 'tmp_name' => [$entry['tmp_name'] ?? ''],
                'error' => [$entry['error'] ?? UPLOAD_ERR_NO_FILE], 'size' => [$entry['size'] ?? 0]];
        }
        foreach ($entry['name'] as $i => $name) {
            $f = ['name' => $name, 'type' => $entry['type'][$i] ?? '', 'tmp_name' => $entry['tmp_name'][$i] ?? '',
                'error' => (int) ($entry['error'][$i] ?? UPLOAD_ERR_NO_FILE), 'size' => (int) ($entry['size'][$i] ?? 0)];
            if ($f['error'] === UPLOAD_ERR_NO_FILE) {
                if ($keep_index) {
                    $out[$i] = null;
                }
                continue;
            }
            // Otros errores (tamaño, subida parcial) se pasan: la API los explica.
            $out[$i] = $f;
        }
        return $keep_index ? $out : array_values($out);
    }

    /** Lista de archivos → formato de $_FILES con "campo[]" (lo que espera la API). */
    protected static function files_pack(array $list): array
    {
        $pack = ['name' => [], 'type' => [], 'tmp_name' => [], 'error' => [], 'size' => []];
        foreach (array_values($list) as $f) {
            foreach ($pack as $k => $_) {
                $pack[$k][] = $f[$k];
            }
        }
        return $pack;
    }

    /** Paso del asistente / pestaña del editor donde se corrige cada error de la API. */
    public static function step_for_error(string $code): int
    {
        $map = [
            'missing_tipo' => 0, 'invalid_tipo' => 0,
            'missing_title' => 1, 'missing_brand' => 1, 'missing_reference' => 1, 'brand_create_error' => 1,
            'missing_speed' => 2, 'missing_power' => 2, 'missing_power_or_torque' => 2, 'invalid_number' => 2,
            'missing_image' => 3, 'invalid_image' => 3, 'gallery_limit' => 3,
            'invalid_stock_publish' => 4, 'invalid_stock' => 4, 'invalid_price' => 4,
            'invalid_document' => 5, 'documents_limit' => 5,
        ];
        return $map[$code] ?? -1;
    }

    /** Límites de subida (wpData.max_upload_size / post_max_size de la SPA). */
    public static function upload_limits(): array
    {
        return [
            'maxFile' => (int) wp_max_upload_size(),
            'maxPost' => (int) wp_convert_hr_to_bytes((string) ini_get('post_max_size')),
        ];
    }

    /**
     * ¿El cuerpo superó post_max_size? PHP vacía $_POST y $_FILES y ni siquiera llega el
     * nonce: se detecta antes para dar el mismo aviso que la API (413).
     */
    public static function payload_truncated(): bool
    {
        $len = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
        $type = isset($_SERVER['CONTENT_TYPE']) ? (string) $_SERVER['CONTENT_TYPE'] : '';
        return $len > 0 && strpos($type, 'multipart/form-data') !== false && empty($_POST) && empty($_FILES);
    }
}
