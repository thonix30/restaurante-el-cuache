<?php
namespace App\Repositories;

use App\Entities\ProductoEntity;

class ProductoRepository extends Repository
{
    private const SELECT = "SELECT p.*, c.nombre AS categoria, c.orden AS cat_orden
                              FROM productos p LEFT JOIN categorias c ON c.id = p.categoria_id";

    /** @return ProductoEntity[] */
    public function list(array $f = []): array
    {
        $where = ['p.activo = 1'];
        $params = [];
        if (!empty($f['publico'])) $where[] = '(c.id IS NULL OR c.activa = 1)';
        if (!empty($f['categoria_id'])) { $where[] = 'p.categoria_id = ?'; $params[] = (int)$f['categoria_id']; }
        if (!empty($f['solo_agotados'])) $where[] = 'p.disponible = 0';
        if (!empty($f['controla_stock'])) $where[] = 'p.controla_stock = 1';
        if (!empty($f['q'])) { $where[] = '(p.nombre LIKE ? OR p.descripcion LIKE ?)'; $params[] = "%{$f['q']}%"; $params[] = "%{$f['q']}%"; }
        $rows = $this->all(self::SELECT . " WHERE " . implode(' AND ', $where) . " ORDER BY COALESCE(c.orden, 999), p.nombre", $params);
        return array_map([ProductoEntity::class, 'fromRow'], $rows);
    }

    public function find(int $id, bool $lock = false): ?ProductoEntity
    {
        $r = $this->one(self::SELECT . " WHERE p.id = ?" . ($lock ? " FOR UPDATE" : ""), [$id]);
        return $r ? ProductoEntity::fromRow($r) : null;
    }

    public function findRaw(int $id, bool $lock = false): ?array
    {
        return $this->one(self::SELECT . " WHERE p.id = ? AND p.activo = 1" . ($lock ? " FOR UPDATE" : ""), [$id]);
    }

    public function create(array $d): int
    {
        return $this->insert('productos', $d);
    }

    public function save(int $id, array $d): void
    {
        $this->update('productos', $id, $d);
    }

    public function softDelete(int $id): void
    {
        $this->exec("UPDATE productos SET activo = 0, disponible = 0 WHERE id = ?", [$id]);
    }

    public function setDisponible(int $id, bool $disponible, ?string $motivo): void
    {
        $this->exec(
            "UPDATE productos SET disponible = ?, agotado_motivo = ?, agotado_en = IF(? = 1, NULL, NOW()) WHERE id = ?",
            [$disponible ? 1 : 0, $disponible ? null : $motivo, $disponible ? 1 : 0, $id]
        );
    }

    /** Descuenta stock solo si alcanza. Devuelve el stock restante o null si no alcanzó. */
    public function takeStock(int $id, int $cantidad): ?int
    {
        $n = $this->exec("UPDATE productos SET stock = stock - ? WHERE id = ? AND controla_stock = 1 AND stock >= ?", [$cantidad, $id, $cantidad]);
        if ($n === 0) return null;
        return (int)$this->value("SELECT stock FROM productos WHERE id = ?", [$id]);
    }

    public function addStock(int $id, int $cantidad): int
    {
        $this->exec("UPDATE productos SET stock = GREATEST(0, stock + ?) WHERE id = ?", [$cantidad, $id]);
        return (int)$this->value("SELECT stock FROM productos WHERE id = ?", [$id]);
    }

    /** Ids de platillos no disponibles (para actualizar el menú en vivo). */
    public function agotadosIds(): array
    {
        return array_map('intval', $this->pdo->query("SELECT id FROM productos WHERE activo = 1 AND disponible = 0")->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function stockBajoCount(): int
    {
        return (int)$this->value("SELECT COUNT(*) FROM productos WHERE activo = 1 AND controla_stock = 1 AND stock <= stock_minimo");
    }

    public function stockBajo(): array
    {
        return $this->all("SELECT id, nombre, stock, stock_minimo FROM productos WHERE activo = 1 AND controla_stock = 1 AND stock <= stock_minimo ORDER BY stock");
    }
}
