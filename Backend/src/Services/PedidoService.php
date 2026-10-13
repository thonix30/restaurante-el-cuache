<?php
namespace App\Services;

use App\Core\HttpException;
use App\DTOs\PedidoDTO;
use App\Repositories\CajaRepository;
use App\Repositories\ComandaRepository;
use App\Repositories\CuentaRepository;
use App\Repositories\LlamadoRepository;
use App\Repositories\MesaRepository;
use App\Repositories\PagoRepository;
use App\Repositories\ProductoRepository;
use App\Utils\Audit;
use App\Utils\Auth;
use App\Utils\Database;

/**
 * Cuentas de mesa y envío de comandas a cocina.
 * Toda escritura corre en transacción y bloquea la fila de la mesa/cuenta
 * para que dos envíos simultáneos (cliente + mesero) no abran dos cuentas.
 */
class PedidoService
{
    private const EPS = 0.005;

    private CuentaRepository $cuentas;
    private ComandaRepository $comandas;
    private ProductoRepository $productos;
    private MesaRepository $mesas;

    public function __construct()
    {
        $this->cuentas = new CuentaRepository();
        $this->comandas = new ComandaRepository();
        $this->productos = new ProductoRepository();
        $this->mesas = new MesaRepository();
    }

    public static function assertCajaAbierta(bool $cliente = false): array
    {
        $t = (new CajaRepository())->abierto();
        if (!$t) {
            throw HttpException::conflict(
                $cliente ? 'Aún no abrimos. En cuanto abramos podrás hacer tu pedido.' : 'La caja está cerrada. Un administrador debe abrirla antes de tomar pedidos.',
                ['code' => 'CAJA_CERRADA']
            );
        }
        return $t;
    }

    /** Abre la mesa (cuenta vacía) sin enviar nada a cocina. */
    public function abrirMesa(int $mesaId, int $personas = 1): array
    {
        $turno = self::assertCajaAbierta();
        $id = Database::transaction(function () use ($mesaId, $personas, $turno) {
            $mesa = $this->mesas->find($mesaId, true);
            if (!$mesa || !$mesa['activa']) throw HttpException::notFound('La mesa no existe.');
            if ($c = $this->cuentas->abiertaDeMesa($mesaId)) return (int)$c['id'];
            $u = Auth::user();
            return $this->cuentas->create([
                'mesa_id' => $mesaId,
                'mesero_id' => ($u && $u['rol'] === 'Mesero') ? $u['id'] : null,
                'turno_caja_id' => $turno['id'],
                'personas' => max(1, min(40, $personas)),
            ]);
        });
        return $this->cuenta($id);
    }

    /**
     * Envía platillos a cocina para una mesa. Valida disponibilidad y stock
     * con las filas bloqueadas; si algo se agotó devuelve 409 con el detalle
     * para que la pantalla marque esos platillos en el carrito.
     */
    public function enviar(int $mesaId, PedidoDTO $dto, string $origen): array
    {
        $turno = self::assertCajaAbierta($origen === 'cliente');
        $user = Auth::user();
        $prodService = new ProductoService();

        return Database::transaction(function () use ($mesaId, $dto, $origen, $turno, $user, $prodService) {
            $mesa = $this->mesas->find($mesaId, true);
            if (!$mesa || !$mesa['activa']) throw HttpException::notFound('La mesa no existe.');

            // Agrupar cantidades por producto para validar stock total
            $porProducto = [];
            foreach ($dto->items as $it) $porProducto[$it['producto_id']] = ($porProducto[$it['producto_id']] ?? 0) + $it['cantidad'];
            ksort($porProducto); // orden fijo de bloqueo → sin deadlocks

            $prods = [];
            $problemas = [];
            foreach ($porProducto as $pid => $cant) {
                $p = $this->productos->findRaw($pid, true);
                if (!$p || !(int)$p['disponible']) {
                    $problemas[] = ['producto_id' => $pid, 'nombre' => $p['nombre'] ?? 'Platillo', 'motivo' => 'agotado', 'disponibles' => 0];
                    continue;
                }
                if ((int)$p['controla_stock'] && (int)$p['stock'] < $cant) {
                    $problemas[] = ['producto_id' => $pid, 'nombre' => $p['nombre'], 'motivo' => 'stock', 'disponibles' => max(0, (int)$p['stock'])];
                    continue;
                }
                $prods[$pid] = $p;
            }
            if ($problemas) {
                $nombres = implode(', ', array_column($problemas, 'nombre'));
                throw HttpException::conflict(
                    count($problemas) === 1 ? "Lo sentimos, «{$nombres}» ya no está disponible." : "Algunos platillos ya no están disponibles: $nombres.",
                    ['code' => 'NO_DISPONIBLE', 'no_disponibles' => $problemas]
                );
            }

            // Cuenta abierta de la mesa (o nueva)
            $cuenta = $this->cuentas->abiertaDeMesa($mesaId, true);
            $esMesero = $user && $user['rol'] === 'Mesero';
            if (!$cuenta) {
                $cuentaId = $this->cuentas->create([
                    'mesa_id' => $mesaId,
                    'mesero_id' => $esMesero ? $user['id'] : null,
                    'turno_caja_id' => $turno['id'],
                    'personas' => max(1, min(40, (int)($dto->personas ?? 1))),
                ]);
            } else {
                $cuentaId = (int)$cuenta['id'];
                $upd = [];
                if ($esMesero && !$cuenta['mesero_id']) $upd['mesero_id'] = $user['id'];
                if ($dto->personas) $upd['personas'] = max(1, min(40, $dto->personas));
                if ($upd) $this->cuentas->save($cuentaId, $upd);
            }

            $comandaId = $this->comandas->create([
                'cuenta_id' => $cuentaId,
                'origen' => $origen,
                'creado_por' => $user['id'] ?? null,
            ]);

            $total = 0.0;
            $agotados = [];
            foreach ($dto->items as $it) {
                $p = $prods[$it['producto_id']];
                $this->cuentas->insertItem([
                    'comanda_id' => $comandaId,
                    'cuenta_id' => $cuentaId,
                    'producto_id' => (int)$p['id'],
                    'nombre' => $p['nombre'],
                    'categoria' => $p['categoria'],
                    'precio' => round((float)$p['precio'], 2),
                    'costo' => round((float)$p['costo'], 2),
                    'cantidad' => $it['cantidad'],
                    'notas' => $it['notas'],
                ]);
                $total += (float)$p['precio'] * $it['cantidad'];
            }
            foreach ($porProducto as $pid => $cant) {
                $p = $prods[$pid];
                if (!(int)$p['controla_stock']) continue;
                $restante = $this->productos->takeStock($pid, $cant);
                if ($restante === null) throw HttpException::conflict("No hay stock suficiente de «{$p['nombre']}».");
                if ($prodService->agotarPorStockSiCorresponde($pid, $restante, $p['nombre'])) $agotados[] = $pid;
            }

            $this->cuentas->recalcular($cuentaId);
            return [
                'cuenta_id' => $cuentaId,
                'comanda_id' => $comandaId,
                'total_enviado' => round($total, 2),
                'agotados' => $agotados,
            ];
        });
    }

    public function cuentaDeMesa(int $mesaId): ?array
    {
        $c = $this->cuentas->abiertaDeMesa($mesaId);
        return $c ? $this->cuenta((int)$c['id']) : null;
    }

    /** Detalle completo de una cuenta: items, comandas, pagos y saldos. */
    public function cuenta(int $id): array
    {
        $c = $this->cuentas->detalle($id);
        if (!$c) throw HttpException::notFound('La cuenta no existe.');
        $items = $this->cuentas->items($id);
        $pagos = (new PagoRepository())->porCuenta($id);

        // Cuánto de cada item ya se pagó en cuentas divididas por platillo
        $pagadoPorItem = [];
        foreach ($pagos as $pg) {
            if ($pg['anulado'] || !$pg['items']) continue;
            foreach ($pg['items'] as $pi) $pagadoPorItem[$pi['item_id']] = ($pagadoPorItem[$pi['item_id']] ?? 0) + $pi['cantidad'];
        }

        $subtotal = round((float)$c['subtotal'], 2);
        $descuento = round((float)$c['descuento'], 2);
        $pagado = round((float)$c['pagado'], 2);
        return [
            'id' => (int)$c['id'],
            'mesa_id' => $c['mesa_id'] !== null ? (int)$c['mesa_id'] : null,
            'mesa_numero' => $c['mesa_numero'] !== null ? (int)$c['mesa_numero'] : null,
            'mesero_id' => $c['mesero_id'] !== null ? (int)$c['mesero_id'] : null,
            'mesero' => $c['mesero'],
            'estado' => $c['estado'],
            'personas' => (int)$c['personas'],
            'abierta_en' => $c['abierta_en'],
            'cerrada_en' => $c['cerrada_en'],
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'descuento_motivo' => $c['descuento_motivo'],
            'total' => round($subtotal - $descuento, 2),
            'pagado' => $pagado,
            'propina' => round((float)$c['propina'], 2),
            'saldo' => round($subtotal - $descuento - $pagado, 2),
            'motivo_cancelacion' => $c['motivo_cancelacion'],
            'items' => array_map(fn($i) => [
                'id' => (int)$i['id'],
                'comanda_id' => (int)$i['comanda_id'],
                'producto_id' => $i['producto_id'] !== null ? (int)$i['producto_id'] : null,
                'nombre' => $i['nombre'],
                'categoria' => $i['categoria'],
                'precio' => (float)$i['precio'],
                'cantidad' => (int)$i['cantidad'],
                'notas' => $i['notas'],
                'estado' => $i['estado'],
                'motivo_cancelacion' => $i['motivo_cancelacion'],
                'comanda_estado' => $i['comanda_estado'],
                'origen' => $i['origen'],
                'enviado_en' => $i['comanda_en'],
                'pagado_cantidad' => round($pagadoPorItem[$i['id']] ?? 0, 3),
            ], $items),
            'comandas' => array_map(fn($co) => [
                'id' => (int)$co['id'], 'estado' => $co['estado'], 'origen' => $co['origen'],
                'creado_en' => $co['creado_en'], 'listo_en' => $co['listo_en'], 'entregado_en' => $co['entregado_en'],
            ], $this->comandas->porCuenta($id)),
            'pagos' => $pagos,
        ];
    }

    /** Cancela un platillo (o parte de sus unidades) con motivo. Devuelve el stock. */
    public function cancelarItem(int $itemId, ?int $cantidad, string $motivo): array
    {
        $motivo = trim($motivo);
        if ($motivo === '') throw HttpException::invalid('Indica el motivo de la cancelación.', ['errors' => ['motivo' => 'Obligatorio.']]);

        $cuentaId = Database::transaction(function () use ($itemId, $cantidad, $motivo) {
            $item = $this->cuentas->item($itemId, true);
            if (!$item || $item['estado'] !== 'activo') throw HttpException::notFound('El platillo no existe o ya fue cancelado.');
            $cuenta = $this->cuentas->find((int)$item['cuenta_id'], true);
            if ($cuenta['estado'] !== 'abierta') throw HttpException::conflict('La cuenta ya está cerrada.');

            $cant = $cantidad && $cantidad > 0 ? min($cantidad, (int)$item['cantidad']) : (int)$item['cantidad'];
            $monto = $cant * (float)$item['precio'];
            $netoTras = (float)$cuenta['subtotal'] - $monto - (float)$cuenta['descuento'];
            if ($netoTras + self::EPS < (float)$cuenta['pagado']) {
                throw HttpException::conflict('Ese platillo ya fue cobrado. Anula el pago correspondiente antes de cancelarlo.');
            }

            if ($cant < (int)$item['cantidad']) {
                // Cancelación parcial: se separa la línea
                $this->cuentas->saveItem($itemId, ['cantidad' => (int)$item['cantidad'] - $cant]);
                $copy = $item;
                unset($copy['id']);
                $copy['cantidad'] = $cant;
                $copy['estado'] = 'cancelado';
                $copy['motivo_cancelacion'] = mb_substr($motivo, 0, 255);
                $this->cuentas->insertItem($copy);
            } else {
                $this->cuentas->saveItem($itemId, ['estado' => 'cancelado', 'motivo_cancelacion' => mb_substr($motivo, 0, 255)]);
            }

            $this->devolverStock((int)$item['producto_id'], $cant);
            $this->comandas->cancelarSiVacia((int)$item['comanda_id']);
            $this->cuentas->recalcular((int)$item['cuenta_id']);
            $this->ajustarDescuento((int)$item['cuenta_id']);

            Audit::log('platillo_cancelado', 'venta', 'cuenta', $item['cuenta_id'],
                "Cancelado {$cant}× {$item['nombre']} (Q" . number_format($monto, 2) . "): $motivo",
                ['item_id' => $itemId, 'cantidad' => $cant, 'monto' => $monto, 'motivo' => $motivo]);
            return (int)$item['cuenta_id'];
        });
        return $this->cuenta($cuentaId);
    }

    /** Cancela la cuenta completa (solo Admin). No debe tener pagos vigentes. */
    public function cancelarCuenta(int $cuentaId, string $motivo): array
    {
        $motivo = trim($motivo);
        if ($motivo === '') throw HttpException::invalid('Indica el motivo de la cancelación.', ['errors' => ['motivo' => 'Obligatorio.']]);

        Database::transaction(function () use ($cuentaId, $motivo) {
            $c = $this->cuentas->find($cuentaId, true);
            if (!$c) throw HttpException::notFound('La cuenta no existe.');
            if ($c['estado'] !== 'abierta') throw HttpException::conflict('La cuenta ya está cerrada.');
            if ((float)$c['pagado'] > self::EPS) throw HttpException::conflict('La cuenta tiene pagos registrados. Anúlalos antes de cancelarla.');

            foreach ($this->cuentas->itemsActivos($cuentaId) as $it) {
                $this->devolverStock((int)$it['producto_id'], (int)$it['cantidad']);
            }
            $this->comandas->cancelarDeCuenta($cuentaId);
            $this->cuentas->save($cuentaId, [
                'estado' => 'cancelada',
                'motivo_cancelacion' => mb_substr($motivo, 0, 255),
                'cerrada_en' => date('Y-m-d H:i:s'),
            ]);
            if ($c['mesa_id']) (new LlamadoRepository())->atenderDeMesa((int)$c['mesa_id'], Auth::id());
            Audit::log('cuenta_cancelada', 'venta', 'cuenta', $cuentaId,
                "Cuenta #$cuentaId cancelada (Q" . number_format((float)$c['subtotal'], 2) . "): $motivo",
                ['subtotal' => (float)$c['subtotal'], 'motivo' => $motivo]);
        });
        return $this->cuenta($cuentaId);
    }

    /** Descuento sobre la cuenta (solo Admin): monto fijo o porcentaje. */
    public function aplicarDescuento(int $cuentaId, string $tipo, float $valor, ?string $motivo): array
    {
        if (!$motivo || trim($motivo) === '') throw HttpException::invalid('Indica el motivo del descuento.', ['errors' => ['motivo' => 'Obligatorio.']]);
        Database::transaction(function () use ($cuentaId, $tipo, $valor, $motivo) {
            $c = $this->cuentas->find($cuentaId, true);
            if (!$c || $c['estado'] !== 'abierta') throw HttpException::conflict('La cuenta no está abierta.');
            $subtotal = (float)$c['subtotal'];
            $desc = $tipo === 'porcentaje' ? round($subtotal * max(0, min(100, $valor)) / 100, 2) : round(max(0, $valor), 2);
            $max = round($subtotal - (float)$c['pagado'], 2);
            if ($desc > $max + self::EPS) throw HttpException::invalid('El descuento supera lo que falta por pagar (Q' . number_format($max, 2) . ').');
            $antes = (float)$c['descuento'];
            $this->cuentas->save($cuentaId, ['descuento' => $desc, 'descuento_motivo' => mb_substr(trim($motivo), 0, 255)]);
            Audit::log('descuento_aplicado', 'venta', 'cuenta', $cuentaId,
                "Descuento de Q" . number_format($desc, 2) . ($tipo === 'porcentaje' ? " ({$valor}%)" : '') . " a la cuenta #$cuentaId: $motivo",
                ['antes' => $antes, 'despues' => $desc, 'tipo' => $tipo, 'valor' => $valor]);
        });
        return $this->cuenta($cuentaId);
    }

    public function setPersonas(int $cuentaId, int $personas): array
    {
        $c = $this->cuentas->find($cuentaId);
        if (!$c || $c['estado'] !== 'abierta') throw HttpException::conflict('La cuenta no está abierta.');
        $this->cuentas->save($cuentaId, ['personas' => max(1, min(40, $personas))]);
        return $this->cuenta($cuentaId);
    }

    /** Pasa la cuenta a otra mesa libre (los clientes se cambiaron de lugar). */
    public function moverMesa(int $cuentaId, int $mesaDestino): array
    {
        Database::transaction(function () use ($cuentaId, $mesaDestino) {
            $c = $this->cuentas->find($cuentaId, true);
            if (!$c || $c['estado'] !== 'abierta') throw HttpException::conflict('La cuenta no está abierta.');
            $m = $this->mesas->find($mesaDestino, true);
            if (!$m || !$m['activa']) throw HttpException::notFound('La mesa destino no existe.');
            if ($this->cuentas->abiertaDeMesa($mesaDestino)) throw HttpException::conflict("La mesa {$m['numero']} está ocupada.");
            $this->cuentas->save($cuentaId, ['mesa_id' => $mesaDestino]);
            if ($c['mesa_id']) (new LlamadoRepository())->atenderDeMesa((int)$c['mesa_id'], Auth::id());
            Audit::log('cuenta_movida', 'venta', 'cuenta', $cuentaId, "Cuenta #$cuentaId pasó a la mesa {$m['numero']}");
        });
        return $this->cuenta($cuentaId);
    }

    /** Si al cancelar platillos el descuento queda mayor que el subtotal, se ajusta. */
    private function ajustarDescuento(int $cuentaId): void
    {
        $c = $this->cuentas->find($cuentaId);
        $max = max(0, (float)$c['subtotal'] - (float)$c['pagado']);
        if ((float)$c['descuento'] > $max) $this->cuentas->save($cuentaId, ['descuento' => round($max, 2)]);
    }

    private function devolverStock(?int $productoId, int $cantidad): void
    {
        if (!$productoId) return;
        $p = $this->productos->findRaw($productoId, true);
        if (!$p || !(int)$p['controla_stock']) return;
        $this->productos->addStock($productoId, $cantidad);
        (new ProductoService())->reactivarPorStockSiCorresponde($productoId);
    }
}
