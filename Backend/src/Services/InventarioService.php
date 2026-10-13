<?php
namespace App\Services;

use App\Core\HttpException;
use App\DTOs\CompraDTO;
use App\Repositories\CompraRepository;
use App\Repositories\ProductoRepository;
use App\Utils\Audit;
use App\Utils\Auth;
use App\Utils\Database;
use App\Utils\Time;

class InventarioService
{
    private ProductoRepository $productos;
    private CompraRepository $compras;

    public function __construct()
    {
        $this->productos = new ProductoRepository();
        $this->compras = new CompraRepository();
    }

    public function stock(): array
    {
        $items = array_map(fn($p) => $p->toArray(), $this->productos->list(['controla_stock' => true]));
        return [
            'productos' => $items,
            'resumen' => [
                'controlados' => count($items),
                'stock_bajo' => count(array_filter($items, fn($p) => $p['stock_bajo'] && $p['stock'] > 0)),
                'sin_stock' => count(array_filter($items, fn($p) => $p['stock'] <= 0)),
                'valor' => round(array_sum(array_map(fn($p) => $p['stock'] * $p['costo'], $items)), 2),
            ],
        ];
    }

    public function compras(?string $desde, ?string $hasta, ?string $q): array
    {
        [$desde, $hasta] = Time::period($desde, $hasta, date('Y-m-01'));
        $rows = $this->compras->list($desde, $hasta, $q);
        return [
            'desde' => $desde, 'hasta' => $hasta,
            'compras' => $rows,
            'total' => round(array_sum(array_column($rows, 'total')), 2),
            'proveedores' => $this->compras->proveedores(),
        ];
    }

    /** Registra la compra, suma el stock, actualiza el costo y reactiva si estaba agotado por stock. */
    public function registrarCompra(CompraDTO $dto): array
    {
        $id = Database::transaction(function () use ($dto) {
            $p = $this->productos->findRaw($dto->producto_id, true);
            if (!$p) throw HttpException::notFound('El producto no existe.');
            $id = $this->compras->create([
                'proveedor' => $dto->proveedor,
                'producto_id' => $dto->producto_id,
                'cantidad' => $dto->cantidad,
                'costo_unitario' => $dto->costo_unitario,
                'total' => round($dto->cantidad * $dto->costo_unitario, 2),
                'notas' => $dto->notas,
                'registrado_por' => Auth::id(),
            ]);
            $upd = ['costo' => $dto->costo_unitario];
            if (!(int)$p['controla_stock']) $upd['controla_stock'] = 1; // comprar implica controlar stock
            $this->productos->save($dto->producto_id, $upd);
            $this->productos->addStock($dto->producto_id, $dto->cantidad);
            (new ProductoService())->reactivarPorStockSiCorresponde($dto->producto_id);

            Audit::log('compra_registrada', 'inventario', 'producto', $dto->producto_id,
                "Compra de {$dto->cantidad} × {$p['nombre']} a {$dto->proveedor} (Q" . number_format($dto->cantidad * $dto->costo_unitario, 2) . ")",
                ['cantidad' => $dto->cantidad, 'costo_unitario' => $dto->costo_unitario, 'proveedor' => $dto->proveedor]);
            return $id;
        });
        return ['id' => $id, 'producto' => (new ProductoService())->get($dto->producto_id)];
    }

    /** Ajuste manual (conteo físico, merma). Queda en auditoría con motivo. */
    public function ajustar(int $productoId, int $nuevoStock, string $motivo): array
    {
        if (trim($motivo) === '') throw HttpException::invalid('Indica el motivo del ajuste.', ['errors' => ['motivo' => 'Obligatorio.']]);
        if ($nuevoStock < 0) throw HttpException::invalid('El stock no puede ser negativo.');
        Database::transaction(function () use ($productoId, $nuevoStock, $motivo) {
            $p = $this->productos->findRaw($productoId, true);
            if (!$p) throw HttpException::notFound('El producto no existe.');
            $this->productos->save($productoId, ['stock' => $nuevoStock]);
            $prod = new ProductoService();
            if ($nuevoStock === 0 && (int)$p['controla_stock'] && (int)$p['disponible']) {
                $prod->agotarPorStockSiCorresponde($productoId, 0, $p['nombre']);
            } else {
                $prod->reactivarPorStockSiCorresponde($productoId);
            }
            Audit::log('stock_ajustado', 'inventario', 'producto', $productoId,
                "Stock de «{$p['nombre']}»: {$p['stock']} → $nuevoStock · $motivo",
                ['antes' => (int)$p['stock'], 'despues' => $nuevoStock, 'motivo' => $motivo]);
        });
        return (new ProductoService())->get($productoId);
    }
}
