<?php
namespace App\Utils;

/**
 * Bitácora de acciones sensibles. Nunca interrumpe la operación:
 * si falla el registro, solo se anota en el log del servidor.
 */
class Audit
{
    public static function log(
        string $accion,
        string $categoria,
        ?string $entidad = null,
        $entidadId = null,
        ?string $descripcion = null,
        $detalle = null,
        ?array $user = null
    ): void {
        try {
            $user = $user ?? Auth::user();
            Database::getInstance()->getConnection()->prepare(
                "INSERT INTO auditoria (usuario_id, usuario_nombre, accion, categoria, entidad, entidad_id, descripcion, detalle, ip)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $user['id'] ?? null,
                $user['nombre'] ?? 'Sistema',
                $accion,
                $categoria,
                $entidad,
                $entidadId !== null ? (string)$entidadId : null,
                $descripcion !== null ? mb_substr($descripcion, 0, 255) : null,
                $detalle === null ? null : (is_string($detalle) ? $detalle : json_encode($detalle, JSON_UNESCAPED_UNICODE)),
                Auth::ip(),
            ]);
        } catch (\Throwable $e) {
            error_log('[audit] ' . $e->getMessage());
        }
    }
}
