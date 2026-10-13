<?php
namespace App\DTOs;

use App\Core\HttpException;

/**
 * Base para los DTOs de entrada: lectura tipada del request y validación
 * con mensajes por campo (el frontend los muestra junto a cada input).
 */
abstract class DTO
{
    protected array $errors = [];

    public function __construct(protected array $data)
    {
    }

    protected function str(string $key, int $max = 255, ?string $default = null): ?string
    {
        $v = $this->data[$key] ?? null;
        if ($v === null) return $default;
        $v = trim((string)$v);
        return $v === '' ? $default : mb_substr($v, 0, $max);
    }

    protected function num(string $key, ?float $default = null): ?float
    {
        $v = $this->data[$key] ?? null;
        if ($v === null || $v === '') return $default;
        if (!is_numeric($v)) { $this->errors[$key] = 'Debe ser un número.'; return $default; }
        return round((float)$v, 2);
    }

    protected function int(string $key, ?int $default = null): ?int
    {
        $v = $this->data[$key] ?? null;
        if ($v === null || $v === '') return $default;
        if (!is_numeric($v)) { $this->errors[$key] = 'Debe ser un número entero.'; return $default; }
        return (int)$v;
    }

    protected function bool(string $key, bool $default = false): bool
    {
        if (!array_key_exists($key, $this->data)) return $default;
        return filter_var($this->data[$key], FILTER_VALIDATE_BOOLEAN);
    }

    protected function date(string $key): ?string
    {
        $v = $this->str($key, 10);
        if ($v === null) return null;
        $d = \DateTime::createFromFormat('Y-m-d', $v);
        if (!$d || $d->format('Y-m-d') !== $v) { $this->errors[$key] = 'Fecha inválida.'; return null; }
        return $v;
    }

    protected function time(string $key): ?string
    {
        $v = $this->str($key, 8);
        if ($v === null) return null;
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $v)) { $this->errors[$key] = 'Hora inválida (HH:MM).'; return null; }
        return strlen($v) === 5 ? "$v:00" : $v;
    }

    protected function oneOf(string $key, array $options, ?string $default = null): ?string
    {
        $v = $this->str($key, 60, $default);
        if ($v !== null && !in_array($v, $options, true)) {
            $this->errors[$key] = 'Valor no válido.';
            return $default;
        }
        return $v;
    }

    protected function required(string $field, $value, string $msg = 'Este campo es obligatorio.'): void
    {
        if ($value === null || $value === '' || $value === []) $this->errors[$field] = $msg;
    }

    protected function fail(string $field, string $msg): void
    {
        $this->errors[$field] = $msg;
    }

    /** Lanza 422 con los errores por campo si hay alguno. */
    protected function check(): void
    {
        if ($this->errors) {
            $first = reset($this->errors);
            throw HttpException::invalid($first, ['errors' => $this->errors]);
        }
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }
}
