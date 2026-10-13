<?php
namespace App\Repositories;

class EmpleadoRepository extends Repository
{
    public function find(int $id): ?array
    {
        return $this->one("SELECT * FROM empleados WHERE id = ?", [$id]);
    }

    public function findByLogin(string $login): ?array
    {
        return $this->one(
            "SELECT * FROM empleados WHERE (usuario = ? OR (email IS NOT NULL AND email = ?)) LIMIT 1",
            [$login, $login]
        );
    }

    public function findByPinHash(string $hash): ?array
    {
        return $this->one("SELECT * FROM empleados WHERE pin_hash = ? AND estado IN ('activo','vacaciones')", [$hash]);
    }

    /**
     * Lista con columnas calculadas (prefijo x_):
     *  x_en_turno: marcó entrada y aún no marca salida (últimas 20 h)
     *  x_en_descanso: su última marca es inicio de descanso
     */
    public function list(array $f = []): array
    {
        $where = ['1=1'];
        $p = [];
        if (!empty($f['puesto'])) { $where[] = 'e.puesto = ?'; $p[] = $f['puesto']; }
        if (!empty($f['estado'])) { $where[] = 'e.estado = ?'; $p[] = $f['estado']; }
        elseif (empty($f['incluir_baja'])) { $where[] = "e.estado <> 'baja'"; }
        if (!empty($f['q'])) {
            $where[] = "(CONCAT(e.nombre,' ',e.apellido) LIKE ? OR e.telefono LIKE ? OR e.usuario LIKE ?)";
            $q = '%' . $f['q'] . '%';
            array_push($p, $q, $q, $q);
        }
        $sql = "
            SELECT e.*,
              (SELECT m.tipo FROM marcaciones m WHERE m.empleado_id = e.id
                 AND m.marcado_en >= DATE_SUB(NOW(), INTERVAL 20 HOUR)
               ORDER BY m.marcado_en DESC, m.id DESC LIMIT 1) AS x_ultima_marca
            FROM empleados e
            WHERE " . implode(' AND ', $where) . "
            ORDER BY FIELD(e.estado,'activo','vacaciones','suspendido','baja'), e.nombre, e.apellido";
        $rows = $this->all($sql, $p);
        foreach ($rows as &$r) {
            $u = $r['x_ultima_marca'];
            $r['x_en_turno'] = in_array($u, ['entrada', 'fin_descanso', 'inicio_descanso'], true) ? 1 : 0;
            $r['x_en_descanso'] = $u === 'inicio_descanso' ? 1 : 0;
        }
        return $rows;
    }

    /** Empleados que trabajan (para planificador y asistencia). */
    public function activos(): array
    {
        return $this->all("SELECT * FROM empleados WHERE estado <> 'baja' ORDER BY FIELD(puesto,'Administrador','Cocinero','Mesero','Bartender','Cajero','Ayudante'), nombre");
    }

    public function create(array $data): int
    {
        return $this->insert('empleados', $data);
    }

    public function updateFields(int $id, array $data): void
    {
        $this->update('empleados', $id, $data);
    }

    public function usuarioEnUso(string $usuario, ?int $exceptId = null): bool
    {
        return (bool)$this->value("SELECT COUNT(*) FROM empleados WHERE usuario = ? AND id <> ?", [$usuario, $exceptId ?? 0]);
    }

    public function pinEnUso(string $hash, ?int $exceptId = null): bool
    {
        return (bool)$this->value("SELECT COUNT(*) FROM empleados WHERE pin_hash = ? AND id <> ?", [$hash, $exceptId ?? 0]);
    }

    public function adminsActivos(?int $exceptId = null): int
    {
        return (int)$this->value(
            "SELECT COUNT(*) FROM empleados WHERE rol = 'Admin' AND acceso_activo = 1 AND estado NOT IN ('baja','suspendido') AND id <> ?",
            [$exceptId ?? 0]
        );
    }

    public function touchAcceso(int $id): void
    {
        $this->exec("UPDATE empleados SET ultimo_acceso = NOW() WHERE id = ?", [$id]);
    }

    // ── Documentos ──────────────────────────────────────────────────────
    public function documentos(int $empleadoId): array
    {
        return $this->all(
            "SELECT id, empleado_id, tipo, nombre, mime, vence_en, creado_en,
                    DATEDIFF(vence_en, CURDATE()) AS dias_para_vencer
               FROM empleado_documentos WHERE empleado_id = ? ORDER BY creado_en DESC",
            [$empleadoId]
        );
    }

    public function documento(int $id): ?array
    {
        return $this->one("SELECT * FROM empleado_documentos WHERE id = ?", [$id]);
    }

    public function addDocumento(array $d): int
    {
        return $this->insert('empleado_documentos', $d);
    }

    public function deleteDocumento(int $id): void
    {
        $this->exec("DELETE FROM empleado_documentos WHERE id = ?", [$id]);
    }

    public function documentosPorVencer(int $dias): array
    {
        return $this->all(
            "SELECT d.id, d.nombre, d.tipo, d.vence_en, DATEDIFF(d.vence_en, CURDATE()) AS dias,
                    e.id AS empleado_id, CONCAT(e.nombre,' ',e.apellido) AS empleado
               FROM empleado_documentos d JOIN empleados e ON e.id = d.empleado_id
              WHERE d.vence_en IS NOT NULL AND e.estado <> 'baja'
                AND d.vence_en <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
              ORDER BY d.vence_en",
            [$dias]
        );
    }

    public function cumpleanos(int $dias): array
    {
        // Próximo cumpleaños considerando el cambio de año
        return $this->all(
            "SELECT id, CONCAT(nombre,' ',apellido) AS empleado, foto, fecha_nacimiento,
                    DATEDIFF(
                      IF(DATE_FORMAT(fecha_nacimiento, CONCAT(YEAR(CURDATE()),'-%m-%d')) >= CURDATE(),
                         DATE_FORMAT(fecha_nacimiento, CONCAT(YEAR(CURDATE()),'-%m-%d')),
                         DATE_FORMAT(fecha_nacimiento, CONCAT(YEAR(CURDATE())+1,'-%m-%d'))),
                      CURDATE()) AS dias
               FROM empleados
              WHERE fecha_nacimiento IS NOT NULL AND estado <> 'baja'
             HAVING dias <= ?
              ORDER BY dias",
            [$dias]
        );
    }
}
