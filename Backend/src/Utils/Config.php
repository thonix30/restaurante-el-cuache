<?php
namespace App\Utils;

/**
 * Acceso a config/app.php y a la tabla `configuracion` (ajustes editables
 * desde el sistema: URL del menú, horas máximas por semana, etc.).
 */
class Config
{
    private static ?array $app = null;
    private static ?array $db = null;

    public static function get(string $key, $default = null)
    {
        if (self::$app === null) {
            self::$app = require __DIR__ . '/../../config/app.php';
            if (empty(self::$app['jwt_secret'])) self::$app['jwt_secret'] = self::crearSecretoJwt();
        }
        return self::$app[$key] ?? $default;
    }

    /**
     * Instalación nueva (clonada del repositorio): no hay jwt_secret porque no se
     * versiona. Se genera uno aleatorio y se guarda en config/app.local.php.
     */
    private static function crearSecretoJwt(): string
    {
        $secreto = bin2hex(random_bytes(32));
        $archivo = __DIR__ . '/../../config/app.local.php';
        $actual = is_file($archivo) ? (require $archivo) : [];
        $actual = is_array($actual) ? $actual : [];
        $actual['jwt_secret'] = $secreto;
        $ok = @file_put_contents($archivo, "<?php\n// Secretos de esta instalación (NO se sube a git).\nreturn " . var_export($actual, true) . ";\n");
        if ($ok === false) {
            error_log('[config] No se pudo escribir config/app.local.php; el jwt_secret durará solo esta petición.');
        }
        return $secreto;
    }

    /** Ajuste de negocio guardado en la base de datos. */
    public static function setting(string $key, $default = null)
    {
        if (self::$db === null) {
            try {
                $rows = Database::getInstance()->getConnection()
                    ->query("SELECT clave, valor FROM configuracion")->fetchAll(\PDO::FETCH_KEY_PAIR);
                self::$db = $rows ?: [];
            } catch (\Throwable $e) {
                self::$db = [];
            }
        }
        return self::$db[$key] ?? $default;
    }

    public static function settings(): array
    {
        self::setting('_');
        return self::$db;
    }

    public static function setSetting(string $key, string $value): void
    {
        Database::getInstance()->getConnection()
            ->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)")
            ->execute([$key, $value]);
        self::$db = null;
    }
}
