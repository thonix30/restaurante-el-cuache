<?php
namespace App\Repositories;

class AsistenciaRepository extends Repository
{
    /** Marcaciones entre dos instantes (para uno o todos los empleados). */
    public function marcaciones(string $desde, string $hasta, ?int $empleadoId = null): array
    {
        $sql = "SELECT id, empleado_id, tipo, marcado_en, origen, foto IS NOT NULL AS tiene_foto, motivo
                  FROM marcaciones WHERE marcado_en BETWEEN ? AND ?";
        $p = [$desde, $hasta];
        if ($empleadoId) { $sql .= " AND empleado_id = ?"; $p[] = $empleadoId; }
        return $this->all($sql . " ORDER BY empleado_id, marcado_en, id", $p);
    }

    public function ultima(int $empleadoId): ?array
    {
        return $this->one(
            "SELECT * FROM marcaciones WHERE empleado_id = ? AND marcado_en >= DATE_SUB(NOW(), INTERVAL 20 HOUR)
              ORDER BY marcado_en DESC, id DESC LIMIT 1",
            [$empleadoId]
        );
    }

    public function find(int $id): ?array
    {
        return $this->one("SELECT * FROM marcaciones WHERE id = ?", [$id]);
    }

    public function create(array $d): int
    {
        return $this->insert('marcaciones', $d);
    }

    public function deleteIds(array $ids): void
    {
        if (!$ids) return;
        $this->exec("DELETE FROM marcaciones WHERE id IN (" . $this->in($ids) . ")", array_values($ids));
    }

    /** Entradas sin salida de días anteriores (alerta "no marcó salida"). */
    public function sinSalida(): array
    {
        return $this->all(
            "SELECT m.empleado_id, CONCAT(e.nombre,' ',e.apellido) AS empleado, m.marcado_en
               FROM marcaciones m JOIN empleados e ON e.id = m.empleado_id
              WHERE m.tipo = 'entrada'
                AND m.marcado_en BETWEEN DATE_SUB(NOW(), INTERVAL 3 DAY) AND DATE_SUB(NOW(), INTERVAL 12 HOUR)
                AND NOT EXISTS (SELECT 1 FROM marcaciones s WHERE s.empleado_id = m.empleado_id
                                   AND s.tipo IN ('salida','entrada') AND s.marcado_en > m.marcado_en)
              ORDER BY m.marcado_en DESC"
        );
    }

    // ── Justificaciones ─────────────────────────────────────────────────
    public function justificaciones(string $desde, string $hasta, ?int $empleadoId = null): array
    {
        $sql = "SELECT id, empleado_id, fecha, tipo, motivo, archivo IS NOT NULL AS tiene_archivo, creado_en
                  FROM justificaciones WHERE fecha BETWEEN ? AND ?";
        $p = [$desde, $hasta];
        if ($empleadoId) { $sql .= " AND empleado_id = ?"; $p[] = $empleadoId; }
        return $this->all($sql, $p);
    }

    public function justificacion(int $id): ?array
    {
        return $this->one("SELECT * FROM justificaciones WHERE id = ?", [$id]);
    }

    public function justificar(array $d): int
    {
        $this->exec(
            "INSERT INTO justificaciones (empleado_id, fecha, tipo, motivo, archivo, registrado_por)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE motivo = VALUES(motivo), archivo = COALESCE(VALUES(archivo), archivo), registrado_por = VALUES(registrado_por)",
            [$d['empleado_id'], $d['fecha'], $d['tipo'], $d['motivo'], $d['archivo'] ?? null, $d['registrado_por'] ?? null]
        );
        return (int)$this->value("SELECT id FROM justificaciones WHERE empleado_id = ? AND fecha = ? AND tipo = ?", [$d['empleado_id'], $d['fecha'], $d['tipo']]);
    }
}
