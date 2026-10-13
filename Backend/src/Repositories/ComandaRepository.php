<?php
namespace App\Repositories;

class ComandaRepository extends Repository
{
    public function create(array $d): int
    {
        return $this->insert('comandas', $d);
    }

    public function find(int $id, bool $lock = false): ?array
    {
        return $this->one("SELECT * FROM comandas WHERE id = ?" . ($lock ? " FOR UPDATE" : ""), [$id]);
    }

    public function save(int $id, array $d): void
    {
        $this->update('comandas', $id, $d);
    }

    /** Tablero de cocina: comandas pendientes, en preparación y listas (con sus items activos). */
    public function tablero(): array
    {
        $comandas = $this->all(
            "SELECT co.*, m.numero AS mesa_numero, CONCAT(e.nombre,' ',e.apellido) AS mesero
               FROM comandas co
               JOIN cuentas c ON c.id = co.cuenta_id
               LEFT JOIN mesas m ON m.id = c.mesa_id
               LEFT JOIN empleados e ON e.id = c.mesero_id
              WHERE co.estado IN ('pendiente','preparacion','listo')
              ORDER BY co.creado_en"
        );
        if (!$comandas) return [];
        $ids = array_column($comandas, 'id');
        $items = $this->all(
            "SELECT id, comanda_id, producto_id, nombre, categoria, cantidad, notas, estado
               FROM comanda_items WHERE comanda_id IN (" . $this->in($ids) . ") ORDER BY id",
            $ids
        );
        $byComanda = [];
        foreach ($items as $it) $byComanda[$it['comanda_id']][] = $it;
        foreach ($comandas as &$c) $c['items'] = $byComanda[$c['id']] ?? [];
        return $comandas;
    }

    public function porCuenta(int $cuentaId): array
    {
        return $this->all("SELECT * FROM comandas WHERE cuenta_id = ? ORDER BY id", [$cuentaId]);
    }

    public function listas(): array
    {
        return $this->all(
            "SELECT co.id, co.listo_en, m.numero AS mesa_numero, c.mesero_id
               FROM comandas co JOIN cuentas c ON c.id = co.cuenta_id LEFT JOIN mesas m ON m.id = c.mesa_id
              WHERE co.estado = 'listo' ORDER BY co.listo_en"
        );
    }

    public function contarPendientes(): int
    {
        return (int)$this->value("SELECT COUNT(*) FROM comandas WHERE estado IN ('pendiente','preparacion')");
    }

    /** Si todos los items de la comanda están cancelados, la comanda queda cancelada. */
    public function cancelarSiVacia(int $comandaId): void
    {
        $activos = (int)$this->value("SELECT COUNT(*) FROM comanda_items WHERE comanda_id = ? AND estado = 'activo'", [$comandaId]);
        if ($activos === 0) $this->exec("UPDATE comandas SET estado = 'cancelado' WHERE id = ?", [$comandaId]);
    }

    public function cancelarDeCuenta(int $cuentaId): void
    {
        $this->exec("UPDATE comandas SET estado = 'cancelado' WHERE cuenta_id = ? AND estado <> 'entregado'", [$cuentaId]);
    }
}
