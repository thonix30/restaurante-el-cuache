<?php
namespace App\Services;

use App\Core\HttpException;
use App\DTOs\ProductoDTO;
use App\Repositories\CategoriaRepository;
use App\Repositories\ProductoRepository;
use App\Utils\Audit;
use App\Utils\Database;
use App\Utils\Storage;

class ProductoService
{
    private ProductoRepository $repo;

    public function __construct()
    {
        $this->repo = new ProductoRepository();
    }

    public function list(array $filters = []): array
    {
        return array_map(fn($p) => $p->toArray(), $this->repo->list($filters));
    }

    public function get(int $id): array
    {
        $p = $this->repo->find($id);
        if (!$p) throw HttpException::notFound('El platillo no existe.');
        return $p->toArray();
    }

    public function create(ProductoDTO $dto): array
    {
        $this->assertCategoria($dto->categoria_id);
        $agotadoPorStock = $dto->controla_stock && $dto->stock <= 0;
        $id = $this->repo->create([
            'categoria_id' => $dto->categoria_id,
            'nombre' => $dto->nombre,
            'descripcion' => $dto->descripcion,
            'precio' => $dto->precio,
            'costo' => $dto->costo,
            'imagen' => $dto->imagenRelativa(),
            'imagen_posicion' => $dto->imagen_posicion,
            'disponible' => ($dto->disponible && !$agotadoPorStock) ? 1 : 0,
            'agotado_motivo' => $agotadoPorStock ? 'stock' : (!$dto->disponible ? 'manual' : null),
            'controla_stock' => $dto->controla_stock ? 1 : 0,
            'stock' => $dto->stock,
            'stock_minimo' => $dto->stock_minimo,
            'destacado' => $dto->destacado ? 1 : 0,
        ]);
        Audit::log('producto_creado', 'producto', 'producto', $id, "Nuevo platillo «{$dto->nombre}» a Q" . number_format($dto->precio, 2),
            ['precio' => $dto->precio, 'costo' => $dto->costo]);
        return $this->get($id);
    }

    public function update(int $id, ProductoDTO $dto): array
    {
        $antes = $this->repo->find($id);
        if (!$antes) throw HttpException::notFound('El platillo no existe.');
        $this->assertCategoria($dto->categoria_id);

        $data = [
            'categoria_id' => $dto->categoria_id,
            'nombre' => $dto->nombre,
            'descripcion' => $dto->descripcion,
            'precio' => $dto->precio,
            'costo' => $dto->costo,
            'imagen' => $dto->imagenRelativa(),
            'imagen_posicion' => $dto->imagen_posicion,
            'controla_stock' => $dto->controla_stock ? 1 : 0,
            'stock' => $dto->stock,
            'stock_minimo' => $dto->stock_minimo,
            'destacado' => $dto->destacado ? 1 : 0,
        ];
        // Disponibilidad coherente con el stock
        if ($dto->controla_stock && $dto->stock <= 0) {
            $data += ['disponible' => 0, 'agotado_motivo' => 'stock'];
        } elseif ($antes->agotado_motivo === 'stock' && (!$dto->controla_stock || $dto->stock > 0)) {
            $data += ['disponible' => 1, 'agotado_motivo' => null];
        } elseif ($dto->has('disponible')) {
            $data += ['disponible' => $dto->disponible ? 1 : 0, 'agotado_motivo' => $dto->disponible ? null : 'manual'];
        }
        $this->repo->save($id, $data);

        if (abs($antes->precio - $dto->precio) >= 0.005) {
            Audit::log('precio_cambiado', 'producto', 'producto', $id,
                "«{$dto->nombre}»: Q" . number_format($antes->precio, 2) . " → Q" . number_format($dto->precio, 2),
                ['antes' => $antes->precio, 'despues' => $dto->precio]);
        } else {
            Audit::log('producto_editado', 'producto', 'producto', $id, "Se editó «{$dto->nombre}»");
        }
        if ($antes->imagen && $antes->imagen !== $dto->imagen && preg_match('@/(uploads/[\w.\-]+)$@', $antes->imagen, $m)) {
            Storage::delete($m[1]);
        }
        return $this->get($id);
    }

    public function delete(int $id): void
    {
        $p = $this->repo->find($id);
        if (!$p) throw HttpException::notFound('El platillo no existe.');
        // Borrado lógico: el historial de ventas conserva su nombre y costo
        $this->repo->softDelete($id);
        Audit::log('producto_eliminado', 'producto', 'producto', $id, "Se eliminó «{$p->nombre}» de la carta");
    }

    /**
     * Interruptor Disponible / Agotado. Afecta al instante el menú del cliente
     * y la toma de pedidos (ambos lo leen en su siguiente sondeo).
     * Reactivar un platillo agotado por stock exige indicar el nuevo stock.
     */
    public function setDisponibilidad(int $id, bool $disponible, ?int $stock = null): array
    {
        return Database::transaction(function () use ($id, $disponible, $stock) {
            $p = $this->repo->find($id, true);
            if (!$p) throw HttpException::notFound('El platillo no existe.');

            if ($disponible && $p->controla_stock && $p->stock <= 0) {
                if ($stock === null || $stock <= 0) {
                    throw HttpException::invalid("«{$p->nombre}» no tiene stock. Indica cuántas unidades hay para reactivarlo.", ['requiere_stock' => true]);
                }
                $this->repo->save($id, ['stock' => $stock]);
            }
            $this->repo->setDisponible($id, $disponible, 'manual');

            if ($disponible !== $p->disponible) {
                Audit::log($disponible ? 'producto_reactivado' : 'producto_agotado', 'producto', 'producto', $id,
                    $disponible ? "«{$p->nombre}» vuelve a estar disponible" : "«{$p->nombre}» marcado como agotado",
                    $stock ? ['stock' => $stock] : null);
            }
            return $this->get($id);
        });
    }

    /** Llamado tras vender: si el stock llegó a cero se agota solo. */
    public function agotarPorStockSiCorresponde(int $id, int $stockRestante, string $nombre): bool
    {
        if ($stockRestante > 0) return false;
        $this->repo->setDisponible($id, false, 'stock');
        Audit::log('producto_agotado', 'producto', 'producto', $id, "«$nombre» se agotó por stock (llegó a 0)", ['motivo' => 'stock']);
        return true;
    }

    /** Llamado al reponer stock: un platillo agotado por stock vuelve solo. */
    public function reactivarPorStockSiCorresponde(int $id): void
    {
        $p = $this->repo->find($id);
        if ($p && !$p->disponible && $p->agotado_motivo === 'stock' && $p->stock > 0) {
            $this->repo->setDisponible($id, true, null);
            Audit::log('producto_reactivado', 'producto', 'producto', $id, "«{$p->nombre}» volvió a estar disponible al reponer stock", ['stock' => $p->stock]);
        }
    }

    private function assertCategoria(?int $id): void
    {
        if ($id && !(new CategoriaRepository())->find($id)) {
            throw HttpException::invalid('La categoría no existe.', ['errors' => ['categoria_id' => 'La categoría no existe.']]);
        }
    }
}
