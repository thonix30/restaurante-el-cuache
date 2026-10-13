<?php
namespace App\Services;

use App\Core\HttpException;
use App\DTOs\PagoDTO;
use App\Repositories\CajaRepository;
use App\Repositories\CuentaRepository;
use App\Repositories\LlamadoRepository;
use App\Repositories\PagoRepository;
use App\Utils\Audit;
use App\Utils\Auth;
use App\Utils\Database;

/**
 * Cobros (completos, por persona o por platillo) y anulaciones.
 * Cada pago queda ligado al turno de caja abierto: así el arqueo cuadra.
 */
class PagoService
{
    private const EPS = 0.005;

    private CuentaRepository $cuentas;
    private PagoRepository $pagos;

    public function __construct()
    {
        $this->cuentas = new CuentaRepository();
        $this->pagos = new PagoRepository();
    }

    public function cobrar(int $cuentaId, PagoDTO $dto): array
    {
        $turno = PedidoService::assertCajaAbierta();
        $user = Auth::user();

        $res = Database::transaction(function () use ($cuentaId, $dto, $turno, $user) {
            $c = $this->cuentas->find($cuentaId, true);
            if (!$c) throw HttpException::notFound('La cuenta no existe.');
            if ($c['estado'] !== 'abierta') throw HttpException::conflict('La cuenta ya está cerrada.');

            $neto = round((float)$c['subtotal'] - (float)$c['descuento'], 2);
            $pagado = round((float)$c['pagado'], 2);
            $saldo = round($neto - $pagado, 2);
            if ($saldo <= self::EPS) throw HttpException::conflict('La cuenta ya está saldada.');
            if ($dto->monto > $saldo + self::EPS) {
                throw HttpException::invalid('El monto (Q' . number_format($dto->monto, 2) . ') supera el saldo pendiente (Q' . number_format($saldo, 2) . ').', ['saldo' => $saldo]);
            }

            $this->pagos->create([
                'cuenta_id' => $cuentaId,
                'turno_caja_id' => $turno['id'],
                'cobrado_por' => $user['id'] ?? null,
                'monto' => $dto->monto,
                'propina' => $dto->propina,
                'metodo' => $dto->metodo,
                'recibido' => $dto->metodo === 'Efectivo' ? $dto->recibido : null,
                'etiqueta' => $dto->etiqueta,
                'items' => $dto->items ? json_encode($dto->items) : null,
            ]);

            $nuevoPagado = round($pagado + $dto->monto, 2);
            $completo = ($neto - $nuevoPagado) <= self::EPS;
            $upd = ['pagado' => $completo ? $neto : $nuevoPagado, 'propina' => round((float)$c['propina'] + $dto->propina, 2)];
            if ($completo) {
                $upd['estado'] = 'pagada';
                $upd['cerrada_en'] = date('Y-m-d H:i:s');
                if (!$c['mesero_id'] && $user && $user['rol'] === 'Mesero') $upd['mesero_id'] = $user['id'];
            }
            $this->cuentas->save($cuentaId, $upd);
            if ($completo && $c['mesa_id']) (new LlamadoRepository())->atenderDeMesa((int)$c['mesa_id'], $user['id'] ?? null);

            Audit::log('pago_registrado', 'venta', 'cuenta', $cuentaId,
                "Cobro de Q" . number_format($dto->monto, 2) . " ({$dto->metodo})" . ($dto->propina > 0 ? " + propina Q" . number_format($dto->propina, 2) : '') .
                ($dto->etiqueta ? " · {$dto->etiqueta}" : '') . ($completo ? ' · cuenta saldada' : ''),
                ['monto' => $dto->monto, 'propina' => $dto->propina, 'metodo' => $dto->metodo, 'completo' => $completo]);

            return [
                'completo' => $completo,
                'cambio' => ($dto->metodo === 'Efectivo' && $dto->recibido !== null) ? round($dto->recibido - $dto->monto - $dto->propina, 2) : null,
            ];
        });

        return $res + ['cuenta' => (new PedidoService())->cuenta($cuentaId)];
    }

    /** Anula un pago (solo Admin). No se permite si su turno de caja ya cerró. */
    public function anular(int $pagoId, string $motivo): array
    {
        $motivo = trim($motivo);
        if ($motivo === '') throw HttpException::invalid('Indica el motivo de la anulación.', ['errors' => ['motivo' => 'Obligatorio.']]);

        $cuentaId = Database::transaction(function () use ($pagoId, $motivo) {
            $pg = $this->pagos->find($pagoId, true);
            if (!$pg) throw HttpException::notFound('El pago no existe.');
            if ((int)$pg['anulado']) throw HttpException::conflict('El pago ya estaba anulado.');
            if ($pg['turno_caja_id']) {
                $t = (new CajaRepository())->find((int)$pg['turno_caja_id']);
                if ($t && $t['estado'] === 'cerrado') {
                    throw HttpException::conflict('El turno de caja de este pago ya se cerró; no se puede anular sin descuadrar el arqueo.');
                }
            }
            $c = $this->cuentas->find((int)$pg['cuenta_id'], true);
            $this->pagos->save($pagoId, ['anulado' => 1, 'anulado_motivo' => mb_substr($motivo, 0, 255), 'anulado_por' => Auth::id()]);
            $upd = [
                'pagado' => max(0, round((float)$c['pagado'] - (float)$pg['monto'], 2)),
                'propina' => max(0, round((float)$c['propina'] - (float)$pg['propina'], 2)),
            ];
            if ($c['estado'] === 'pagada') { $upd['estado'] = 'abierta'; $upd['cerrada_en'] = null; }
            $this->cuentas->save((int)$c['id'], $upd);

            Audit::log('pago_anulado', 'venta', 'cuenta', $c['id'],
                "Pago de Q" . number_format((float)$pg['monto'], 2) . " ({$pg['metodo']}) anulado: $motivo",
                ['pago_id' => $pagoId, 'monto' => (float)$pg['monto'], 'propina' => (float)$pg['propina'], 'motivo' => $motivo]);
            return (int)$c['id'];
        });
        return (new PedidoService())->cuenta($cuentaId);
    }
}
