<?php
namespace App\Core;

/**
 * Error de negocio con código HTTP. El Router lo convierte en
 * { status:'error', message, ...extra } sin exponer trazas.
 */
class HttpException extends \RuntimeException
{
    public function __construct(
        string $message,
        public int $status = 400,
        public array $extra = []
    ) {
        parent::__construct($message, $status);
    }

    public static function notFound(string $msg = 'No encontrado.'): self { return new self($msg, 404); }
    public static function forbidden(string $msg = 'No tienes permiso para esta acción.'): self { return new self($msg, 403); }
    public static function conflict(string $msg, array $extra = []): self { return new self($msg, 409, $extra); }
    public static function invalid(string $msg, array $extra = []): self { return new self($msg, 422, $extra); }
}
