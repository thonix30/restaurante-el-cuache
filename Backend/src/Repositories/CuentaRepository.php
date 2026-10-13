<?php
namespace App\Repositories;

class CuentaRepository extends Repository
{
    public function find(int $id, bool $lock = false): ?array
    {
        return $this->one("SELECT * FROM cuentas WHERE id = ?" . ($lock ? " FOR UPDATE" : ""), [$id]);
    }

    public function abiertaDeMesa(int $mesaId, bool $lock = false): ?array
    {
        return $this->one(
            "SELECT * FROM cuentas WHERE mesa_id = ? AND estado = 'abierta' ORDER BY id DESC LIMIT 1" . ($lock ? " FOR UPDATE" : ""),
            [$mesaId]
        );
    }

    public function create(array $d): int
    {
        return $this->insert('cuentas', $d);
    }

    public function save(int $id, array $d): void
    {
        $this->update('cuentas', $id, $d);
    }

    /** Recalcula el subtotal a partir de los items activos (fuente única de verdad). */
    public function recalcular(int $id): float
    {
        $this->exec(
            "UPDATE cuentas SET subtotal = (SELECT COALESCE(SUM(precio * cantidad), 0) FROM comanda_items WHERE cuenta_id = ? AND estado = 'activo')
              WHERE id = ?",
            [$id, $id]
        );
        return (float)$this->value("SELECT subtotal FROM cuentas WHERE id = ?", [$id]);
    }

    public function detalle(int $id): ?array
    {
        return $this->one(
            "SELECT c.*, m.numero AS mesa_numero, COALESCE(m.nombre, CONCAT('Mesa ', m.numero)) AS mesa,
                    CONCAT(e.nombre,' ',e.apellido) AS mesero
               FROM cuentas c
               LEFT JOIN mesas m ON m.id = c.mesa_id
               LEFT JOIN empleados e ON e.id = c.mesero_id
              WHERE c.id = ?",
            [$id]
        );
    }

    public function items(int $cuentaId): array
    {
        return $this->all(
            "SELECT ci.*, co.estado AS comanda_estado, co.creado_en AS comanda_en, co.origen
               FROM comanda_items ci JOIN comandas co ON co.id = ci.comanda_id
              WHERE ci.cuenta_id = ? ORDER BY ci.id",
            [$cuentaId]
        );
    }

    public function item(int $id, bool $lock = false): ?array
    {
        return $this->one("SELECT * FROM comanda_items WHERE id = ?" . ($lock ? " FOR UPDATE" : ""), [$id]);
    }

    public function insertItem(array $d): int
    {
        return $this->insert('comanda_items', $d);
    }

    public function saveItem(int $id, array $d): void
    {
        $this->update('comanda_items', $id, $d);
    }

    public function itemsActivos(int $cuentaId): array
    {
        return $this->all("SELECT * FROM comanda_items WHERE cuenta_id = ? AND estado = 'activo'", [$cuentaId]);
    }

    /** Historial de cuentas cerradas con filtros y paginación. */
    public function historial(array $f, int $limit, int $offset): array
    {
        [$where, $p] = $this->historialWhere($f);
        $rows = $this->all(
            "SELECT c.id, c.estado, c.subtotal, c.descuento, c.pagado, c.propina, c.personas, c.abierta_en, c.cerrada_en,
                    c.motivo_cancelacion, m.numero AS mesa_numero, CONCAT(e.nombre,' ',e.apellido) AS mesero,
                    (SELECT COALESCE(SUM(cantidad),0) FROM comanda_items WHERE cuenta_id = c.id AND estado = 'activo') AS platillos,
                    (SELECT GROUP_CONCAT(DISTINCT metodo) FROM pagos WHERE cuenta_id = c.id AND anulado = 0) AS metodos,
                    (SELECT COUNT(*) FROM pagos WHERE cuenta_id = c.id AND anulado = 0) AS num_pagos
               FROM cuentas c
               LEFT JOIN mesas m ON m.id = c.mesa_id
               LEFT JOIN empleados e ON e.id = c.mesero_id
              WHERE $where
              ORDER BY COALESCE(c.cerrada_en, c.abierta_en) DESC
              LIMIT $limit OFFSET $offset",
            $p
        );
        $tot = $this->one(
            "SELECT COUNT(*) AS n,
                    COALESCE(SUM(IF(c.estado='pagada', c.subtotal - c.descuento, 0)),0) AS ventas,
                    COALESCE(SUM(IF(c.estado='pagada', c.propina, 0)),0) AS propinas,
                    COALESCE(SUM(c.estado='cancelada'),0) AS canceladas
               FROM cuentas c LEFT JOIN mesas m ON m.id = c.mesa_id WHERE $where",
            $p
        );
        return [$rows, $tot];
    }

    private function historialWhere(array $f): array
    {
        $w = ["c.estado IN ('pagada','cancelada')", "DATE(COALESCE(c.cerrada_en, c.abierta_en)) BETWEEN ? AND ?"];
        $p = [$f['desde'], $f['hasta']];
        if (!empty($f['estado'])) { $w[] = 'c.estado = ?'; $p[] = $f['estado']; }
        if (!empty($f['mesa'])) { $w[] = 'm.numero = ?'; $p[] = (int)$f['mesa']; }
        if (!empty($f['mesero_id'])) { $w[] = 'c.mesero_id = ?'; $p[] = (int)$f['mesero_id']; }
        if (!empty($f['q'])) {
            $w[] = "(c.id = ? OR EXISTS (SELECT 1 FROM comanda_items x WHERE x.cuenta_id = c.id AND x.nombre LIKE ?))";
            $p[] = (int)$f['q'];
            $p[] = '%' . $f['q'] . '%';
        }
        return [implode(' AND ', $w), $p];
    }
}
