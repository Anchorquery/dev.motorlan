<?php
/**
 * Vista previa del catálogo SIN WordPress (ni base de datos): Twig puro con las funciones
 * de WordPress que usan las vistas sustituidas por equivalentes mínimos.
 *
 * Sirve para revisar los componentes y sacar capturas sin activar el plugin (el WordPress
 * local usa la base de datos de producción). Es un router de `php -S`:
 *
 *   php -S 127.0.0.1:8765 scripts/preview-ui-kit.php     (desde la carpeta del plugin)
 *   → http://127.0.0.1:8765/             catálogo (layout app-shell)
 *     http://127.0.0.1:8765/auth/        layout de acceso
 *     http://127.0.0.1:8765/public/      layout público (con cabecera/pie de tema simulados)
 *
 * La tabla funciona con HTMX contra /__table (mismo parcial que el endpoint REST).
 * Requiere `composer install` y `npm run build`.
 */

if (PHP_SAPI === 'cli-server') {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (preg_match('#^/dist/#', $uri)) {
        return false; // archivos estáticos del build
    }
}

define('ABSPATH', __DIR__ . '/');
define('MOTORLAN_PORTAL_DIR', dirname(__DIR__) . '/');
define('MOTORLAN_PORTAL_URL', '/');
define('MOTORLAN_PORTAL_VERSION', 'preview');

require MOTORLAN_PORTAL_DIR . 'vendor/autoload.php';

// ── Equivalentes mínimos de WordPress ──
function __($t, $d = null) { return $t; }
function esc_attr($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function esc_html($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function wp_json_encode($v, $f = 0) { return json_encode($v, $f); }
function sanitize_text_field($v) { return trim(strip_tags((string) $v)); }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $v)); }
function wp_unslash($v) { return $v; }

spl_autoload_register(function ($class) {
    $prefix = 'MotorlanPortal\\';
    if (strpos($class, $prefix) === 0) {
        $file = MOTORLAN_PORTAL_DIR . 'src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (file_exists($file)) require_once $file;
    }
});

$uri  = PHP_SAPI === 'cli-server' ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : ($argv[1] ?? '/');
$base = 'http://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8765');

$loader = new \Twig\Loader\FilesystemLoader(MOTORLAN_PORTAL_DIR . 'views');
$twig   = new \Twig\Environment($loader, ['autoescape' => false, 'strict_variables' => false, 'cache' => false]);
$ver    = function ($rel) { $p = MOTORLAN_PORTAL_DIR . 'dist/' . $rel; return '/dist/' . $rel . '?v=' . (file_exists($p) ? filemtime($p) : 0); };
$portal = function ($section, $path = '') { return '/' . trim($path, '/') . ($path ? '/' : ''); };
$fns = [
    '__'         => '__',
    'ml_icon'    => ['MotorlanPortal\\Support\\Icons', 'svg'],
    'ml_json'    => ['MotorlanPortal\\Support\\View', 'json'],
    'ml_url'     => $portal,
    'ml_spa'     => function ($p = '') { return 'https://www.motorlan.es/mi-cuenta/' . ltrim($p, '/'); },
    'ml_screen'  => function () { return '#'; },
    'ml_asset'   => $ver,
    'ml_enabled' => function () { return false; },
    // Timber: {{ function('get_header') }} en layout/public.twig
    'function'   => function ($name) {
        if ($name === 'get_header') {
            return '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Tienda · Motorlan</title>'
                . '<style>body{margin:0;font-family:"Open Sans",sans-serif;color:#222;background:#f7f8f9}.site-header{background:#fff;border-bottom:1px solid #e5e5e5;padding:18px 40px;display:flex;gap:32px;align-items:center}.site-header b{color:#DA291C;font-size:22px}.site-header a{color:#222;text-decoration:none}input[type=text],input[type=search],select{padding:10px 15px;border:1px solid #ccc;background:#fafafa;border-radius:0}ul{margin:0 0 1.5em 3em}button:hover{background:#55555e;color:#fff}.site-footer{background:#222;color:#bbb;padding:40px;margin-top:40px}</style></head><body>'
                . '<header class="site-header"><b>MOTORLAN</b><a href="#">Tienda</a><a href="#">Vender</a><a href="#">Contacto</a><span style="margin-left:auto">Cabecera del tema (simulada)</span></header>';
        }
        if ($name === 'get_footer') {
            return '<footer class="site-footer">Pie del tema (simulado)</footer></body></html>';
        }
        return '';
    },
];
foreach ($fns as $name => $fn) {
    $twig->addFunction(new \Twig\TwigFunction($name, $fn, ['is_safe' => ['html']]));
}

$nav = [
    ['heading' => 'Gestión de Ventas'],
    ['title' => 'Mis Publicaciones', 'icon' => 'list', 'url' => '#', 'active' => false],
    ['title' => 'Ventas', 'icon' => 'currency-euro', 'url' => '#', 'active' => false],
    ['title' => 'Ofertas Recibidas', 'icon' => 'tag', 'url' => '#', 'active' => false],
    ['title' => 'Interesados', 'icon' => 'users', 'url' => '#', 'active' => false],
    ['heading' => 'Gestión de Compras'],
    ['title' => 'Mis Compras', 'icon' => 'shopping-bag', 'url' => '#', 'active' => false],
    ['title' => 'Consultas Enviadas', 'icon' => 'message-share', 'url' => '#', 'active' => false],
    ['title' => 'Ofertas de Precio', 'icon' => 'send', 'url' => '#', 'active' => false],
    ['title' => 'Favoritos', 'icon' => 'heart', 'url' => '#', 'active' => false],
    ['heading' => 'Administración'],
    ['title' => 'Publicaciones', 'icon' => 'list', 'url' => '#', 'active' => false],
    ['title' => 'Aprobaciones', 'icon' => 'check', 'url' => '#', 'active' => false],
    ['title' => 'Componentes (UI kit)', 'icon' => 'components', 'url' => '/', 'active' => true],
];
$mobile = [
    ['title' => 'Anuncios', 'icon' => 'list-check', 'url' => '#', 'active' => false],
    ['title' => 'Aprobar', 'icon' => 'shield-check', 'url' => '#', 'active' => false],
    ['title' => 'Publicar', 'icon' => 'circle-plus', 'url' => '#', 'active' => false, 'primary' => true],
    ['title' => 'Ventas', 'icon' => 'currency-euro', 'url' => '#', 'active' => true],
    ['title' => 'Perfil', 'icon' => 'user-circle', 'url' => '#', 'active' => false],
];

$table_url = $base . '/__table';
$context = [
    'config'    => ['lang' => 'es', 'nonce' => '', 'nonceUrl' => '', 'isLogged' => true, 'isAdmin' => true, 'i18n' => []],
    'user'      => ['id' => 1, 'name' => 'Daniel H', 'email' => 'admin@motorlan.es', 'is_admin' => true, 'unread' => 3],
    'nav'       => ['vertical' => $nav, 'mobile' => $mobile, 'user' => [
        ['title' => 'Mi perfil', 'icon' => 'user', 'url' => '#'],
        ['title' => 'Notificaciones', 'icon' => 'bell', 'url' => '#'],
    ]],
    'urls'      => ['home' => '/', 'store' => '#', 'dashboard' => '#', 'logout' => '#', 'login' => '#'],
    'assets'    => ['css' => $ver('css/app.css'), 'public_css' => $ver('css/public.css'), 'js' => $ver('js/app.js'), 'logo' => null],
    'site_name' => 'Motorlan',
    'page_title' => 'Componentes',
    'table_url' => $table_url,
    'api_check' => [['route' => 'GET /motorlan/v1/session', 'ok' => true, 'detail' => 'vista previa sin WordPress']],
];

$params = $_GET;
if ($uri === '/__table') {
    header('Content-Type: text/html; charset=utf-8');
    echo $twig->render('partials/ui-kit-table.twig', ['table' => \MotorlanPortal\Rest\UiKitApi::table_context($params), 'table_url' => $table_url]);
    return true;
}

$pages = ['/' => 'pages/ui-kit.twig', '/auth/' => 'pages/ui-kit-auth.twig', '/public/' => 'pages/ui-kit-public.twig'];
$tpl = $pages[$uri] ?? $pages[rtrim($uri, '/') . '/'] ?? null;
if (!$tpl) {
    http_response_code(404);
    echo 'No encontrado';
    return true;
}
$context['table'] = \MotorlanPortal\Rest\UiKitApi::table_context($params);
header('Content-Type: text/html; charset=utf-8');
echo $twig->render($tpl, $context);
return true;
