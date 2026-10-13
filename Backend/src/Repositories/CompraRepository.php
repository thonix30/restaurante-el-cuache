<?php
namespace App\Repositories;

class CompraRepository extends Repository
{
    public function create(array $d): int
    {
        return $this->insert('compras', $d);
    }

    public function list(string $desde, string $hasta, ?string $q = null): array
    {
        $p = [$desde, $hasta];
        $extra = '';
        if ($q) { $extra = " AND (c.proveedor LIKE ? OR p.nombre LIKE ?)"; $p[] = "%$q%"; $p[] = "%$q%"; }
        $rows = $this->all(
            "SELECT c.*, p.nombre AS producto, CONCAT(e.nombre,' ',e.apellido) AS registrado_por_nombre
               FROM compras c
               JOIN productos p ON p.id = c.producto_id
               LEFT JOIN empleados e ON e.id = c.registrado_por
              WHERE DATE(c.fecha) BETWEEN ? AND ? $extra
              ORDER BY c.fecha DESC LIMIT 300",
            $p
        );
        foreach ($rows as &$r) {
            $r['cantidad'] = (int)$r['cantidad'];
            $r['costo_unitario'] = (float)$r['costo_unitario'];
            $r['total'] = (float)$r['total'];
        }
        return $rows;
    }

    public function proveedores(): array
    {
        return $this->pdo->query("SELECT DISTINCT proveedor FROM compras ORDER BY proveedor")->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function totalPeriodo(string $desde, string $hasta): float
    {
        return (float)$this->value("SELECT COALESCE(SUM(total),0) FROM compras WHERE DATE(fecha) BETWEEN ? AND ?", [$desde, $hasta]);
    }
}
