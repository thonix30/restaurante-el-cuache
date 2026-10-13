<?php
namespace App\Repositories;

class PagoRepository extends Repository
{
    public function create(array $d): int
    {
        return $this->insert('pagos', $d);
    }

    public function find(int $id, bool $lock = false): ?array
    {
        return $this->one("SELECT * FROM pagos WHERE id = ?" . ($lock ? " FOR UPDATE" : ""), [$id]);
    }

    public function save(int $id, array $d): void
    {
        $this->update('pagos', $id, $d);
    }

    public function porCuenta(int $cuentaId): array
    {
        $rows = $this->all(
            "SELECT p.*, CONCAT(e.nombre,' ',e.apellido) AS cobrado_por_nombre
               FROM pagos p LEFT JOIN empleados e ON e.id = p.cobrado_por
              WHERE p.cuenta_id = ? ORDER BY p.id",
            [$cuentaId]
        );
        foreach ($rows as &$r) {
            $r['items'] = $r['items'] ? json_decode($r['items'], true) : null;
            foreach (['monto', 'propina', 'recibido'] as $k) $r[$k] = $r[$k] !== null ? (float)$r[$k] : null;
            $r['anulado'] = (bool)$r['anulado'];
        }
        return $rows;
    }

    /** Desglose por método de los pagos vigentes de un turno de caja. */
    public function resumenTurno(int $turnoId): array
    {
        return $this->all(
            "SELECT metodo, COUNT(*) AS movimientos, COALESCE(SUM(monto),0) AS monto, COALESCE(SUM(propina),0) AS propina
               FROM pagos WHERE turno_caja_id = ? AND anulado = 0 GROUP BY metodo",
            [$turnoId]
        );
    }

    public function anuladosTurno(int $turnoId): array
    {
        return $this->one(
            "SELECT COUNT(*) AS n, COALESCE(SUM(monto),0) AS monto FROM pagos WHERE turno_caja_id = ? AND anulado = 1",
            [$turnoId]
        );
    }

    /** Pagos con propina en un rango (para repartir propinas por turno). */
    public function conPropina(string $desde, string $hasta): array
    {
        return $this->all(
            "SELECT p.id, p.propina, p.creado_en, p.cobrado_por, c.mesero_id
               FROM pagos p JOIN cuentas c ON c.id = p.cuenta_id
              WHERE p.anulado = 0 AND p.propina > 0 AND DATE(p.creado_en) BETWEEN ? AND ?",
            [$desde, $hasta]
        );
    }
}
