<?php
namespace App\Repositories;

class CategoriaRepository extends Repository
{
    public function listar(bool $soloActivas = false): array
    {
        $rows = $this->all(
            "SELECT c.*, (SELECT COUNT(*) FROM productos p WHERE p.categoria_id = c.id AND p.activo = 1) AS productos
               FROM categorias c " . ($soloActivas ? "WHERE c.activa = 1 " : "") . "ORDER BY c.orden, c.nombre"
        );
        return array_map(fn($r) => [
            'id' => (int)$r['id'], 'nombre' => $r['nombre'], 'icono' => $r['icono'],
            'orden' => (int)$r['orden'], 'activa' => (bool)$r['activa'], 'productos' => (int)$r['productos'],
        ], $rows);
    }

    public function find(int $id): ?array
    {
        return $this->one("SELECT * FROM categorias WHERE id = ?", [$id]);
    }

    public function create(array $d): int
    {
        $d['orden'] = $d['orden'] ?? ((int)$this->value("SELECT COALESCE(MAX(orden),0) FROM categorias") + 1);
        return $this->insert('categorias', $d);
    }

    public function save(int $id, array $d): void
    {
        $this->update('categorias', $id, $d);
    }

    public function delete(int $id): void
    {
        $this->exec("DELETE FROM categorias WHERE id = ?", [$id]);
    }

    public function reorder(array $ids): void
    {
        foreach (array_values($ids) as $i => $id) {
            $this->exec("UPDATE categorias SET orden = ? WHERE id = ?", [$i + 1, (int)$id]);
        }
    }
}
