<?php
namespace App\Services;

use App\Core\HttpException;
use App\Repositories\LlamadoRepository;
use App\Utils\Auth;

/** "Llamar al mesero" y "Pedir la cuenta" desde la mesa. */
class LlamadoService
{
    private LlamadoRepository $repo;

    public function __construct()
    {
        $this->repo = new LlamadoRepository();
    }

    /** Crea el llamado o devuelve el que ya estaba pendiente (sin duplicar avisos). */
    public function llamar(int $mesaId, string $tipo, ?string $metodo = null): array
    {
        if (!in_array($tipo, ['mesero', 'cuenta'], true)) throw HttpException::invalid('Tipo de llamado inválido.');
        if ($metodo !== null && !in_array($metodo, ['efectivo', 'tarjeta', 'dividir'], true)) $metodo = null;
        if ($tipo === 'cuenta') PedidoService::assertCajaAbierta(true);

        $existente = $this->repo->pendiente($mesaId, $tipo);
        if ($existente) {
            if ($tipo === 'cuenta' && $metodo && $existente['metodo_pago'] !== $metodo) $this->repo->setMetodo((int)$existente['id'], $metodo);
            return $this->repo->find((int)$existente['id']);
        }
        $id = $this->repo->create($mesaId, $tipo, $tipo === 'cuenta' ? $metodo : null);
        return $this->repo->find($id);
    }

    public function cancelarDeMesa(int $mesaId, int $llamadoId): void
    {
        $l = $this->repo->find($llamadoId);
        if (!$l || (int)$l['mesa_id'] !== $mesaId) throw HttpException::notFound('El aviso no existe.');
        $this->repo->cancelar($llamadoId);
    }

    public function atender(int $id): void
    {
        $l = $this->repo->find($id);
        if (!$l) throw HttpException::notFound('El aviso no existe.');
        $this->repo->atender($id, Auth::id());
    }

    public function pendientes(): array
    {
        $this->repo->expirarViejos();
        return array_map(fn($l) => [
            'id' => (int)$l['id'],
            'tipo' => $l['tipo'],
            'metodo_pago' => $l['metodo_pago'],
            'mesa_id' => (int)$l['mesa_id'],
            'mesa_numero' => (int)$l['mesa_numero'],
            'creado_en' => $l['creado_en'],
            'espera_seg' => (int)$l['espera_seg'],
            'mesero_id' => $l['mesero_id'] !== null ? (int)$l['mesero_id'] : null,
        ], $this->repo->pendientes());
    }
}
