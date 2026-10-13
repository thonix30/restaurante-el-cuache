<?php
namespace App\Repositories;

class AuditoriaRepository extends Repository
{
    public function list(array $f, int $limit, int $offset): array
    {
        $w = ["DATE(creado_en) BETWEEN ? AND ?"];
        $p = [$f['desde'], $f['hasta']];
        if (!empty($f['categoria'])) { $w[] = 'categoria = ?'; $p[] = $f['categoria']; }
        if (!empty($f['accion'])) { $w[] = 'accion = ?'; $p[] = $f['accion']; }
        if (!empty($f['usuario_id'])) { $w[] = 'usuario_id = ?'; $p[] = (int)$f['usuario_id']; }
        if (!empty($f['q'])) { $w[] = '(descripcion LIKE ? OR detalle LIKE ? OR entidad_id = ?)'; $p[] = "%{$f['q']}%"; $p[] = "%{$f['q']}%"; $p[] = $f['q']; }
        $where = implode(' AND ', $w);
        $rows = $this->all("SELECT * FROM auditoria WHERE $where ORDER BY id DESC LIMIT $limit OFFSET $offset", $p);
        foreach ($rows as &$r) {
            $d = $r['detalle'];
            if ($d && ($d[0] === '{' || $d[0] === '[')) $r['detalle'] = json_decode($d, true);
        }
        $total = (int)$this->value("SELECT COUNT(*) FROM auditoria WHERE $where", $p);
        $porCategoria = $this->all("SELECT categoria, COUNT(*) AS n FROM auditoria WHERE DATE(creado_en) BETWEEN ? AND ? GROUP BY categoria", [$f['desde'], $f['hasta']]);
        return [$rows, $total, $porCategoria];
    }

    public function acciones(): array
    {
        return $this->pdo->query("SELECT DISTINCT accion FROM auditoria ORDER BY accion")->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function usuarios(): array
    {
        return $this->all("SELECT DISTINCT usuario_id AS id, usuario_nombre AS nombre FROM auditoria WHERE usuario_id IS NOT NULL ORDER BY usuario_nombre");
    }
}
