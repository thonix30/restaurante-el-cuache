<?php
namespace App\Services;

use App\Core\HttpException;
use App\Repositories\CajaRepository;
use App\Repositories\PagoRepository;
use App\Utils\Audit;
use App\Utils\Auth;
use App\Utils\Database;

/**
 * Turno de caja: apertura, arqueo en vivo y cierre.
 * Cambio respecto a El Jardín: la caja se puede cerrar aunque no cuadre,
 * pero la diferencia exige una justificación y queda en la auditoría
 * (antes se bloqueaba el cierre y el turno quedaba abierto indefinidamente).
 */
class CajaService
{
    private CajaRepository $repo;

    public function __construct()
    {
        $this->repo = new CajaRepository();
    }

    public function estadoPublico(): array
    {
        return ['abierto' => (bool)$this->repo->abierto()];
    }

    public function actual(): array
    {
        $t = $this->repo->abierto();
        return ['abierto' => (bool)$t, 'turno' => $t ? $this->resumen($t) : null];
    }

    public function abrir(float $monto, ?string $notas): array
    {
        if ($monto < 0) throw HttpException::invalid('El fondo inicial no puede ser negativo.');
        $id = Database::transaction(function () use ($monto, $notas) {
            if ($this->repo->abierto(true)) throw HttpException::conflict('Ya hay un turno de caja abierto.');
            return $this->repo->abrir(Auth::id(), round($monto, 2), $notas ? mb_substr($notas, 0, 255) : null);
        });
        Audit::log('caja_abierta', 'caja', 'turno_caja', $id, 'Caja abierta con fondo de Q' . number_format($monto, 2), ['monto_inicial' => $monto]);
        return $this->actual();
    }

    public function cerrar(float $contado, ?string $notas): array
    {
        $t = $this->repo->abierto();
        if (!$t) throw HttpException::conflict('No hay un turno de caja abierto.');

        $abiertas = $this->repo->cuentasAbiertas();
        if ($abiertas) {
            $mesas = implode(', ', array_map(fn($c) => 'mesa ' . $c['mesa_numero'], $abiertas));
            throw HttpException::conflict('Hay ' . count($abiertas) . " cuenta(s) abiertas ($mesas). Cóbralas o cancélalas antes de cerrar la caja.", ['cuentas_abiertas' => $abiertas]);
        }

        $res = $this->resumen($t);
        $esperado = $res['efectivo_esperado'];
        $diferencia = round($contado - $esperado, 2);
        $notas = $notas ? trim($notas) : '';
        if (abs($diferencia) >= 0.01 && $notas === '') {
            throw HttpException::invalid(
                'La caja no cuadra (' . ($diferencia > 0 ? 'sobran' : 'faltan') . ' Q' . number_format(abs($diferencia), 2) . '). Explica la diferencia para poder cerrar.',
                ['diferencia' => $diferencia, 'esperado' => $esperado, 'errors' => ['notas' => 'Explica la diferencia.']]
            );
        }

        $this->repo->cerrar((int)$t['id'], [
            'cerrado_por' => Auth::id(),
            'efectivo_esperado' => $esperado,
            'efectivo_contado' => round($contado, 2),
            'diferencia' => $diferencia,
            'notas_cierre' => $notas ? mb_substr($notas, 0, 255) : null,
            'resumen' => json_encode($res, JSON_UNESCAPED_UNICODE),
        ]);
        Audit::log('caja_cerrada', 'caja', 'turno_caja', $t['id'],
            'Caja cerrada · esperado Q' . number_format($esperado, 2) . ' · contado Q' . number_format($contado, 2) .
            (abs($diferencia) >= 0.01 ? ' · diferencia Q' . number_format($diferencia, 2) . ": $notas" : ' · cuadrada'),
            ['esperado' => $esperado, 'contado' => $contado, 'diferencia' => $diferencia]);

        return $this->repo->find((int)$t['id']) + ['resumen_calc' => $res];
    }

    public function historial(): array
    {
        $rows = $this->repo->historial(40);
        foreach ($rows as &$r) {
            $r['resumen'] = $r['resumen'] ? json_decode($r['resumen'], true) : null;
            foreach (['monto_inicial', 'efectivo_esperado', 'efectivo_contado', 'diferencia'] as $k) $r[$k] = $r[$k] !== null ? (float)$r[$k] : null;
        }
        return $rows;
    }

    /** Arqueo calculado de un turno (en vivo si sigue abierto). */
    public function resumen(array $t): array
    {
        $pagos = new PagoRepository();
        $metodos = ['Efectivo' => null, 'Tarjeta' => null, 'Transferencia' => null];
        foreach ($pagos->resumenTurno((int)$t['id']) as $f) {
            $metodos[$f['metodo']] = ['metodo' => $f['metodo'], 'movimientos' => (int)$f['movimientos'], 'monto' => round((float)$f['monto'], 2), 'propina' => round((float)$f['propina'], 2)];
        }
        $cobrado = 0; $propinas = 0;
        foreach ($metodos as $k => $v) {
            if (!$v) $metodos[$k] = $v = ['metodo' => $k, 'movimientos' => 0, 'monto' => 0.0, 'propina' => 0.0];
            $cobrado += $v['monto'];
            $propinas += $v['propina'];
        }
        $hasta = $t['cerrado_en'] ?? date('Y-m-d H:i:s');
        $mov = $this->repo->movimientosTurno((int)$t['id'], $t['abierto_en'], $hasta);
        $anul = $pagos->anuladosTurno((int)$t['id']);
        $inicial = round((float)$t['monto_inicial'], 2);
        $efectivo = $metodos['Efectivo'];

        return [
            'id' => (int)$t['id'],
            'abierto_en' => $t['abierto_en'],
            'abierto_por' => $t['abierto_por_nombre'] ?? null,
            'monto_inicial' => $inicial,
            'metodos' => array_values($metodos),
            'cobrado' => round($cobrado, 2),
            'propinas' => round($propinas, 2),
            'propinas_no_efectivo' => round($propinas - $efectivo['propina'], 2),
            'efectivo_esperado' => round($inicial + $efectivo['monto'] + $efectivo['propina'], 2),
            'descuentos' => $mov['descuentos'],
            'cancelaciones' => $mov['cancelaciones'],
            'pagos_anulados' => ['n' => (int)$anul['n'], 'monto' => round((float)$anul['monto'], 2)],
            'cuentas_cobradas' => $mov['cuentas_cobradas'],
            'ventas' => $mov['ventas'],
            'cuentas_abiertas' => count($this->repo->cuentasAbiertas()),
        ];
    }
}
