<?php
/**
 * Json
 * Consistent {ok: true/false, ...} JSON responses for api/v1/* endpoints.
 */
class Json
{
    public static function ok(array $data = []): never
    {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['ok' => true], $data));
        exit;
    }

    public static function error(string $message, int $status = 400): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $message]);
        exit;
    }
}