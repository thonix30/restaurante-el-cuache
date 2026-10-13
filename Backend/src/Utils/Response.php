<?php
namespace App\Utils;

class Response
{
    public static function json($data, $statusCode = 200)
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        exit;
    }

    public static function success($data = [], $message = 'Success', $statusCode = 200)
    {
        self::json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    public static function error($message = 'Error', $statusCode = 400, $errors = [])
    {
        $response = [
            'status' => 'error',
            'message' => $message
        ];

        if (!empty($errors)) {
            $response = array_merge($response, $errors);
        }

        self::json($response, $statusCode);
    }

    /** Envía un archivo (descarga privada) y termina. */
    public static function file(string $path, string $downloadName, ?string $mime = null)
    {
        if (!is_file($path)) self::error('Archivo no encontrado.', 404);
        header_remove('Content-Type');
        header('Content-Type: ' . ($mime ?: (mime_content_type($path) ?: 'application/octet-stream')));
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="' . preg_replace('/[^\w.\- ]/u', '_', $downloadName) . '"');
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }
}
