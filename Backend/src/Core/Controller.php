<?php
namespace App\Core;

use App\Utils\Auth;
use App\Utils\Response;

abstract class Controller
{
    private ?array $body = null;

    protected function json($data, $statusCode = 200)
    {
        Response::json($data, $statusCode);
    }

    protected function ok($data = null, string $message = 'OK', int $status = 200)
    {
        Response::success($data, $message, $status);
    }

    /** Cuerpo JSON de la petición (se lee una sola vez). */
    protected function input(?string $key = null, $default = null)
    {
        if ($this->body === null) {
            $raw = file_get_contents('php://input');
            $this->body = $raw ? (json_decode($raw, true) ?? []) : [];
            if (!$this->body && !empty($_POST)) $this->body = $_POST;
        }
        if ($key === null) return $this->body;
        return $this->body[$key] ?? $default;
    }

    protected function query(string $key, $default = null)
    {
        $v = $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    /** Usuario autenticado (payload del JWT + datos frescos). */
    protected function user(): ?array
    {
        return Auth::user();
    }
}
