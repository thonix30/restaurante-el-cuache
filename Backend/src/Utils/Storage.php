<?php
namespace App\Utils;

use App\Core\HttpException;

/**
 * Guardado de archivos subidos.
 *  - public  → Backend/uploads   (fotos de platillos y de perfil)
 *  - private → Backend/storage   (documentos del personal, fotos de asistencia)
 * La extensión siempre la decide el servidor según el contenido real.
 */
class Storage
{
    private const IMAGE_TYPES = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif',
    ];
    private const DOC_TYPES = self::IMAGE_TYPES + ['application/pdf' => 'pdf'];

    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function saveUploadedImage(array $file, string $folder = 'uploads'): string
    {
        return self::saveUploaded($file, self::IMAGE_TYPES, $folder, 8);
    }

    public static function saveUploadedDocument(array $file, string $subfolder): array
    {
        $rel = self::saveUploaded($file, self::DOC_TYPES, 'storage/' . $subfolder, 10);
        return ['path' => $rel, 'mime' => mime_content_type(self::root() . '/' . $rel) ?: null];
    }

    /** Imagen en base64 (data URL), usada por la cámara del kiosco. */
    public static function saveDataUrlImage(string $dataUrl, string $folder): ?string
    {
        if (!preg_match('@^data:image/(jpeg|png|webp);base64,(.+)$@', $dataUrl, $m)) return null;
        $bin = base64_decode($m[2], true);
        if ($bin === false || strlen($bin) > 3 * 1024 * 1024) return null;
        $info = @getimagesizefromstring($bin);
        if (!$info || !isset(self::IMAGE_TYPES[$info['mime']])) return null;
        $dir = self::root() . '/' . $folder;
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $name = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . self::IMAGE_TYPES[$info['mime']];
        file_put_contents("$dir/$name", $bin);
        return "$folder/$name";
    }

    private static function saveUploaded(array $file, array $allowed, string $folder, int $maxMb): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw HttpException::invalid('No se recibió un archivo válido.');
        }
        if ($file['size'] > $maxMb * 1024 * 1024) {
            throw new HttpException("El archivo supera el máximo de {$maxMb} MB.", 413);
        }
        $mime = mime_content_type($file['tmp_name']) ?: '';
        if (!isset($allowed[$mime])) {
            throw HttpException::invalid('Tipo de archivo no permitido (' . implode(', ', array_unique(array_values($allowed))) . ').');
        }
        if (str_starts_with($mime, 'image/') && @getimagesize($file['tmp_name']) === false) {
            throw HttpException::invalid('La imagen está dañada o no es válida.');
        }
        $dir = self::root() . '/' . $folder;
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new HttpException('No se pudo guardar el archivo.', 500);
        }
        $name = 'f_' . bin2hex(random_bytes(10)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) {
            throw new HttpException('No se pudo guardar el archivo.', 500);
        }
        @chmod("$dir/$name", 0644);
        return "$folder/$name";
    }

    public static function absolute(string $relative): string
    {
        $path = realpath(self::root() . '/' . $relative);
        // Evita salir de la carpeta del backend con rutas manipuladas
        if (!$path || !str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', self::root()))) {
            throw HttpException::notFound('Archivo no encontrado.');
        }
        return $path;
    }

    public static function delete(?string $relative): void
    {
        if (!$relative || preg_match('@^(https?:|img/|demo/)@', $relative)) return;
        $p = self::root() . '/' . $relative;
        if (is_file($p)) @unlink($p);
    }
}
