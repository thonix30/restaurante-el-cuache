<?php
namespace App\Services;

use App\Core\HttpException;
use App\Repositories\CategoriaRepository;

class CategoriaService
{
    private CategoriaRepository $repo;

    public function __construct()
    {
        $this->repo = new CategoriaRepository();
    }

    public function list(bool $soloActivas = false): array
    {
        return $this->repo->listar($soloActivas);
    }

    public function save(?int $id, array $data): array
    {
        $nombre = trim((string)($data['nombre'] ?? ''));
        if ($nombre === '') throw HttpException::invalid('La categoría necesita un nombre.', ['errors' => ['nombre' => 'Escribe un nombre.']]);
        $row = [
            'nombre' => mb_substr($nombre, 0, 80),
            'icono' => preg_replace('/[^A-Za-z]/', '', (string)($data['icono'] ?? 'ForkKnife')) ?: 'ForkKnife',
            'activa' => isset($data['activa']) ? (int)(bool)$data['activa'] : 1,
        ];
        if ($id) {
            if (!$this->repo->find($id)) throw HttpException::notFound('La categoría no existe.');
            $this->repo->save($id, $row);
        } else {
            $id = $this->repo->create($row);
        }
        return $this->repo->find($id);
    }

    public function delete(int $id): void
    {
        $cats = array_column($this->repo->listar(), null, 'id');
        if (!isset($cats[$id])) throw HttpException::notFound('La categoría no existe.');
        if ($cats[$id]['productos'] > 0) {
            throw HttpException::conflict("La categoría tiene {$cats[$id]['productos']} platillos. Muévelos a otra categoría antes de eliminarla.");
        }
        $this->repo->delete($id);
    }

    public function reorder(array $ids): void
    {
        $this->repo->reorder(array_map('intval', $ids));
    }
}
