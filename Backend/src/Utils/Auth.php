<?php
namespace App\Utils;

use App\Core\HttpException;

/**
 * Contexto de la sesión actual. El JWT dice quién es; la base de datos
 * confirma que todavía tiene acceso (si el admin le quitó el acceso o lo
 * dio de baja, el token deja de servir de inmediato, sin esperar a que expire).
 */
class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function token(): string
    {
        $h = '';
        if (function_exists('getallheaders')) {
            $all = array_change_key_case(getallheaders() ?: [], CASE_LOWER);
            $h = $all['authorization'] ?? '';
        }
        if ($h === '') $h = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        return trim(preg_replace('/^\s*Bearer\s+/i', '', $h));
    }

    public static function tryLoad(): ?array
    {
        if (self::$loaded) return self::$user;
        self::$loaded = true;

        $token = self::token();
        if ($token === '') return null;
        $payload = JwtUtils::validate($token);
        if (!$payload || empty($payload['sub'])) return null;

        $stmt = Database::getInstance()->getConnection()->prepare(
            "SELECT id, nombre, apellido, rol, puesto, foto, estado, acceso_activo
               FROM empleados WHERE id = ?"
        );
        $stmt->execute([(int)$payload['sub']]);
        $u = $stmt->fetch();
        if (!$u || !(int)$u['acceso_activo'] || $u['estado'] === 'baja' || $u['estado'] === 'suspendido' || !$u['rol']) {
            return null;
        }
        self::$user = [
            'id'       => (int)$u['id'],
            'nombre'   => trim($u['nombre'] . ' ' . $u['apellido']),
            'rol'      => $u['rol'],
            'puesto'   => $u['puesto'],
            'foto'     => Url::asset($u['foto']),
        ];
        return self::$user;
    }

    public static function user(): ?array
    {
        return self::tryLoad();
    }

    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['rol'] ?? null) === 'Admin';
    }

    public static function requireRoles(array $roles = []): array
    {
        $u = self::tryLoad();
        if (!$u) {
            throw new HttpException('Tu sesión expiró. Inicia sesión de nuevo.', 401);
        }
        if (!empty($roles) && !in_array($u['rol'], $roles, true)) {
            throw HttpException::forbidden();
        }
        return $u;
    }

    public static function requirePrivilege(string $privilege): void
    {
        $u = self::requireRoles();
        // Privilegios por rol (simple y suficiente para 3 roles)
        $map = [
            'Admin'    => ['*'],
            'Mesero'   => ['salon', 'pedidos', 'cobrar', 'perfil'],
            'Cocinero' => ['cocina', 'disponibilidad', 'perfil'],
        ];
        $privs = $map[$u['rol']] ?? [];
        if (!in_array('*', $privs, true) && !in_array($privilege, $privs, true)) {
            throw HttpException::forbidden("Tu rol no tiene acceso a: $privilege.");
        }
    }

    public static function issue(array $empleado): string
    {
        return JwtUtils::generate([
            'sub'  => (int)$empleado['id'],
            'name' => trim($empleado['nombre'] . ' ' . ($empleado['apellido'] ?? '')),
            'role' => $empleado['rol'],
            'iat'  => time(),
            'exp'  => time() + (int)Config::get('jwt_ttl', 43200),
        ]);
    }

    public static function ip(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) return trim(explode(',', $_SERVER[$k])[0]);
        }
        return '0.0.0.0';
    }

    public static function pinHash(string $pin): string
    {
        return hash_hmac('sha256', $pin, Config::get('pin_secret'));
    }
}
