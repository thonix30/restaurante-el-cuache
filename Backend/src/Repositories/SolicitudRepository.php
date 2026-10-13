<?php
namespace App\Repositories;

class SolicitudRepository extends Repository
{
    private const SELECT = "
        SELECT s.*, CONCAT(e.nombre,' ',e.apellido) AS empleado, e.foto, e.puesto,
               CONCAT(c.nombre,' ',c.apellido) AS companero,
               CONCAT(r.nombre,' ',r.apellido) AS resuelto_por_nombre,
               t.fecha AS turno_fecha, t.hora_inicio AS turno_inicio, t.hora_fin AS turno_fin,
               tc.fecha AS turno_comp_fecha, tc.hora_inicio AS turno_comp_inicio, tc.hora_fin AS turno_comp_fin
          FROM solicitudes s
          JOIN empleados e ON e.id = s.empleado_id
          LEFT JOIN empleados c ON c.id = s.companero_id
          LEFT JOIN empleados r ON r.id = s.resuelto_por
          LEFT JOIN turnos t ON t.id = s.turno_id
          LEFT JOIN turnos tc ON tc.id = s.turno_companero_id";

    public function list(?string $estado = null, ?int $empleadoId = null): array
    {
        $w = ['1=1'];
        $p = [];
        if ($estado) { $w[] = 's.estado = ?'; $p[] = $estado; }
        if ($empleadoId) { $w[] = 's.empleado_id = ?'; $p[] = $empleadoId; }
        return $this->all(self::SELECT . " WHERE " . implode(' AND ', $w) . " ORDER BY s.estado = 'pendiente' DESC, s.creado_en DESC LIMIT 200", $p);
    }

    public function find(int $id): ?array
    {
        return $this->one(self::SELECT . " WHERE s.id = ?", [$id]);
    }

    public function create(array $d): int
    {
        return $this->insert('solicitudes', $d);
    }

    public function save(int $id, array $d): void
    {
        $this->update('solicitudes', $id, $d);
    }

    /** Días de vacaciones aprobados en el año (cuenta días calendario dentro del año). */
    public function diasVacacionesUsados(int $empleadoId, int $anio): int
    {
        $rows = $this->all(
            "SELECT fecha_inicio, fecha_fin FROM solicitudes
              WHERE empleado_id = ? AND tipo = 'vacaciones' AND estado IN ('aprobada','pendiente')
                AND YEAR(fecha_inicio) = ?",
            [$empleadoId, $anio]
        );
        $n = 0;
        foreach ($rows as $r) $n += \App\Utils\Time::daysBetween($r['fecha_inicio'], $r['fecha_fin']);
        return $n;
    }

    public function diasVacacionesAprobados(int $empleadoId, int $anio): int
    {
        $rows = $this->all(
            "SELECT fecha_inicio, fecha_fin FROM solicitudes
              WHERE empleado_id = ? AND tipo = 'vacaciones' AND estado = 'aprobada' AND YEAR(fecha_inicio) = ?",
            [$empleadoId, $anio]
        );
        $n = 0;
        foreach ($rows as $r) $n += \App\Utils\Time::daysBetween($r['fecha_inicio'], $r['fecha_fin']);
        return $n;
    }

    /** Ausencias aprobadas (vacaciones/permisos) que tocan el rango: [empleado_id][fecha] = tipo */
    public function ausencias(string $desde, string $hasta): array
    {
        $rows = $this->all(
            "SELECT empleado_id, tipo, fecha_inicio, fecha_fin FROM solicitudes
              WHERE estado = 'aprobada' AND tipo IN ('vacaciones','permiso')
                AND fecha_inicio <= ? AND fecha_fin >= ?",
            [$hasta, $desde]
        );
        $map = [];
        foreach ($rows as $r) {
            foreach (\App\Utils\Time::dates(max($r['fecha_inicio'], $desde), min($r['fecha_fin'], $hasta)) as $d) {
                $map[(int)$r['empleado_id']][$d] = $r['tipo'];
            }
        }
        return $map;
    }

    public function pendientesCount(): int
    {
        return (int)$this->value("SELECT COUNT(*) FROM solicitudes WHERE estado = 'pendiente'");
    }
}
