<?php

namespace MotorlanPortal\Support;

/**
 * Llamadas internas (sin HTTP) a la REST de motorlan-api-vue (namespace motorlan/v1).
 *
 * rest_do_request() ejecuta el endpoint con el usuario ACTUAL de la petición (la cookie
 * ya lo ha autenticado) y sin pasar por rest_authentication_errors: no hace falta nonce
 * ni JWT. Así el portal reutiliza la lógica, los permisos y la validación de la API en
 * vez de duplicarla. Ver docs/migracion/api-endpoints.md para el catálogo de rutas.
 *
 *   $session = Api::get('/motorlan/v1/session');
 *   $list    = Api::get('/motorlan/v1/store/publicaciones', ['page' => 2, 'per_page' => 12]);
 *   $r       = Api::post('/motorlan/v1/favorites', ['publicacion_id' => 123]);
 *   if ($r === null) { $err = Api::last_error(); }   // ['status' => 403, 'code' => …, 'message' => …]
 *
 * get()/post() devuelven los datos (array) o null si la API respondió error; call()
 * devuelve siempre el detalle completo.
 */
class Api
{
    const NS = '/motorlan/v1';

    /** @var array|null */
    protected static $last_error = null;

    public static function get(string $route, array $query = [])
    {
        return self::data(self::call('GET', $route, $query));
    }

    /** POST con cuerpo JSON (los endpoints de motorlan/v1 leen get_json_params()). */
    public static function post(string $route, array $body = [])
    {
        return self::data(self::call('POST', $route, [], $body));
    }

    public static function put(string $route, array $body = [])
    {
        return self::data(self::call('PUT', $route, [], $body));
    }

    public static function delete(string $route, array $query = [])
    {
        return self::data(self::call('DELETE', $route, $query));
    }

    /**
     * @return array{ok: bool, status: int, data: mixed, headers: array, error: array|null}
     */
    public static function call(string $method, string $route, array $query = [], ?array $body = null): array
    {
        if (!function_exists('rest_do_request')) {
            return self::fail(500, 'rest_unavailable', 'REST API no disponible');
        }
        $request = new \WP_REST_Request(strtoupper($method), '/' . ltrim($route, '/'));
        if ($query) {
            $request->set_query_params($query);
        }
        if ($body !== null) {
            $request->set_header('Content-Type', 'application/json');
            $request->set_body(wp_json_encode($body));
        }

        $response = rest_do_request($request);
        $status   = (int) $response->get_status();
        $data     = self::normalize(rest_get_server()->response_to_data($response, false));

        if ($response->is_error() || $status >= 400) {
            $code = is_array($data) && isset($data['code']) ? (string) $data['code'] : 'rest_error';
            $msg  = is_array($data) && isset($data['message']) ? (string) $data['message'] : '';
            return self::fail($status ?: 500, $code, $msg, $data);
        }

        self::$last_error = null;
        return ['ok' => true, 'status' => $status, 'data' => $data, 'headers' => $response->get_headers(), 'error' => null];
    }

    /** Último error de get()/post()/put()/delete(): ['status', 'code', 'message', 'data'] o null. */
    public static function last_error(): ?array
    {
        return self::$last_error;
    }

    /** Ruta del namespace de la API: Api::route('store/publicaciones') -> /motorlan/v1/store/publicaciones. */
    public static function route(string $path): string
    {
        return self::NS . '/' . ltrim($path, '/');
    }

    /** stdClass -> array en cualquier profundidad (Twig y el código asumen arrays). */
    public static function normalize($data)
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = self::normalize($v);
            }
            return $data;
        }
        if ($data instanceof \WP_REST_Response) {
            return self::normalize($data->get_data());
        }
        if (is_object($data)) {
            $arr = json_decode(wp_json_encode($data), true);
            return is_array($arr) ? $arr : $data;
        }
        return $data;
    }

    /**
     * POST multipart (alta y edición de publicaciones) reenviando los archivos de ESTA
     * petición. El navegador envía el FormData a la URL de la pantalla del portal; aquí se
     * pasa a la API tal cual: campos con set_body_params() y archivos con set_file_params().
     *
     * rest_do_request() no lee $_FILES por sí mismo (eso lo hace WP_REST_Server::serve_request
     * en una petición HTTP a /wp-json), por eso hay que pasárselos. Los callbacks de la API
     * usan get_file_params() para validar y media_handle_upload('motor_image'), que lee el
     * $_FILES global: funciona porque los archivos llegaron en esta misma petición con los
     * mismos nombres de campo (motor_image, motor_gallery[], documentacion_adicional_archivos[]).
     * Sin duplicar nada de la lógica de la API (validación, idempotencia, subida, estado).
     *
     * @param array $fields Campos de texto ya sin barras (wp_unslash($_POST)).
     * @param array $files  $_FILES de la petición.
     */
    public static function multipart(string $route, array $fields, array $files): array
    {
        if (!function_exists('rest_do_request')) {
            return self::fail(500, 'rest_unavailable', 'REST API no disponible');
        }
        $request = new \WP_REST_Request('POST', '/' . ltrim($route, '/'));
        $request->set_header('Content-Type', 'multipart/form-data');
        $request->set_body_params($fields);
        $request->set_file_params($files);

        $response = rest_do_request($request);
        $status   = (int) $response->get_status();
        $data     = self::normalize(rest_get_server()->response_to_data($response, false));
        if ($response->is_error() || $status >= 400) {
            $code = is_array($data) && isset($data['code']) ? (string) $data['code'] : 'rest_error';
            $msg  = is_array($data) && isset($data['message']) ? (string) $data['message'] : '';
            return self::fail($status ?: 500, $code, $msg, $data);
        }
        self::$last_error = null;
        return ['ok' => true, 'status' => $status, 'data' => $data, 'headers' => $response->get_headers(), 'error' => null];
    }

    protected static function data(array $result)
    {
        return $result['ok'] ? $result['data'] : null;
    }

    protected static function fail(int $status, string $code, string $message, $data = null): array
    {
        self::$last_error = ['status' => $status, 'code' => $code, 'message' => $message, 'data' => $data];
        return ['ok' => false, 'status' => $status, 'data' => $data, 'headers' => [], 'error' => self::$last_error];
    }
}
