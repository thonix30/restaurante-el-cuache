<?php
namespace App\Repositories;

class TurnoRepository extends Repository
{
    public function rango(string $desde, string $hasta, ?int $empleadoId = null, bool $soloPublicados = false): array
    {
        $sql = "SELECT t.*, CONCAT(e.nombre,' ',e.apellido) AS empleado, e.puesto
                  FROM turnos t JOIN empleados e ON e.id = t.empleado_id
                 WHERE t.fecha BETWEEN ? AND ?";
        $p = [$desde, $hasta];
        if ($empleadoId) { $sql .= " AND t.empleado_id = ?"; $p[] = $empleadoId; }
        if ($soloPublicados) $sql .= " AND t.publicado = 1";
        $rows = $this->all($sql . " ORDER BY t.fecha, t.hora_inicio", $p);
        foreach ($rows as &$r) {
            $r['id'] = (int)$r['id'];
            $r['empleado_id'] = (int)$r['empleado_id'];
            $r['publicado'] = (bool)$r['publicado'];
            $r['hora_inicio'] = substr($r['hora_inicio'], 0, 5);
            $r['hora_fin'] = substr($r['hora_fin'], 0, 5);
        }
        return $rows;
    }

    public function find(int $id): ?array
    {
        return $this->one("SELECT * FROM turnos WHERE id = ?", [$id]);
    }

    public function create(array $d): int
    {
        return $this->insert('turnos', $d);
    }

    public function save(int $id, array $d): void
    {
        $this->update('turnos', $id, $d);
    }

    public function delete(int $id): void
    {
        $this->exec("DELETE FROM turnos WHERE id = ?", [$id]);
    }

    public function deleteRango(string $desde, string $hasta, ?int $empleadoId = null): int
    {
        $sql = "DELETE FROM turnos WHERE fecha BETWEEN ? AND ?";
        $p = [$desde, $hasta];
        if ($empleadoId) { $sql .= " AND empleado_id = ?"; $p[] = $empleadoId; }
        return $this->exec($sql, $p);
    }

    public function publicar(string $desde, string $hasta): int
    {
        return $this->exec("UPDATE turnos SET publicado = 1 WHERE fecha BETWEEN ? AND ?", [$desde, $hasta]);
    }

    public function proximo(int $empleadoId): ?array
    {
        return $this->one(
            "SELECT * FROM turnos WHERE empleado_id = ? AND publicado = 1
                AND TIMESTAMP(fecha, hora_fin) + INTERVAL IF(hora_fin <= hora_inicio, 1, 0) DAY > NOW()
              ORDER BY fecha, hora_inicio LIMIT 1",
            [$empleadoId]
        );
    }

    // ── Plantillas ──────────────────────────────────────────────────────
    public function plantillas(): array
    {
        $rows = $this->all("SELECT * FROM plantillas_turno ORDER BY nombre");
        foreach ($rows as &$r) {
            $r['patron'] = json_decode($r['patron'], true) ?: [];
            $r['rotativa'] = (bool)$r['rotativa'];
        }
        return $rows;
    }

    public function plantilla(int $id): ?array
    {
        $r = $this->one("SELECT * FROM plantillas_turno WHERE id = ?", [$id]);
        if ($r) { $r['patron'] = json_decode($r['patron'], true) ?: []; $r['rotativa'] = (bool)$r['rotativa']; }
        return $r;
    }

    public function crearPlantilla(string $nombre, array $patron, bool $rotativa): int
    {
        return $this->insert('plantillas_turno', [
            'nombre' => $nombre, 'patron' => json_encode($patron), 'rotativa' => $rotativa ? 1 : 0,
        ]);
    }

    public function borrarPlantilla(int $id): void
    {
        $this->exec("DELETE FROM plantillas_turno WHERE id = ?", [$id]);
    }
}
