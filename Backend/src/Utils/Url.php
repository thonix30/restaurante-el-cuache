<?php
namespace App\Utils;

class Url
{
    /** URL pública de la carpeta Backend (ej. http://localhost/Restaurante%20el%20cuache/Backend). */
    public static function backendBase(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // SCRIPT_NAME = .../Backend/api/v1/index.php
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/', 3));
        $dir = $dir === '/' ? '' : $dir;
        $encoded = implode('/', array_map('rawurlencode', explode('/', $dir)));
        return "$scheme://$host$encoded";
    }

    /**
     * Convierte una ruta guardada en BD en URL utilizable:
     *  - http(s)://...  → tal cual
     *  - uploads/...    → servida por el backend
     *  - img/...        → asset del frontend (relativo a la app)
     */
    public static function asset(?string $path): ?string
    {
        if ($path === null || $path === '') return null;
        if (preg_match('@^(https?:)?//@', $path) || str_starts_with($path, 'data:')) return $path;
        if (str_starts_with($path, 'uploads/')) return self::backendBase() . '/' . $path;
        return $path;
    }
}
