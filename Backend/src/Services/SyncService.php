<?php
namespace App\Services;

use App\Repositories\ComandaRepository;
use App\Repositories\ProductoRepository;
use App\Utils\Auth;

/**
 * Sondeo liviano del personal (cada pocos segundos): avisos de mesas,
 * comandas listas para servir, platillos agotados y hora del servidor
 * (para que los cronómetros de todas las pantallas coincidan).
 */
class SyncService
{
    public function estado(): array
    {
        $u = Auth::user();
        $comandas = new ComandaRepository();
        $productos = new ProductoRepository();
        $caja = (new \App\Repositories\CajaRepository())->abierto();

        $data = [
            'server_time' => date('Y-m-d H:i:s'),
            'caja_abierta' => (bool)$caja,
            'llamados' => (new LlamadoService())->pendientes(),
            'listas' => array_map(fn($c) => [
                'id' => (int)$c['id'], 'mesa_numero' => $c['mesa_numero'] !== null ? (int)$c['mesa_numero'] : null,
                'listo_en' => $c['listo_en'], 'mesero_id' => $c['mesero_id'] !== null ? (int)$c['mesero_id'] : null,
            ], $comandas->listas()),
            'comandas_pendientes' => $comandas->contarPendientes(),
            'agotados' => $productos->agotadosIds(),
        ];
        if (($u['rol'] ?? null) === 'Admin') {
            $data['stock_bajo'] = $productos->stockBajoCount();
            $data['solicitudes_pendientes'] = (new \App\Repositories\SolicitudRepository())->pendientesCount();
        }
        return $data;
    }
}
