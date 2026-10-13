<?php
namespace App\Controllers;

use App\Attributes\Authorize;
use App\Attributes\Route;
use App\Core\Controller;
use App\DTOs\CompraDTO;
use App\Services\CajaService;
use App\Services\EstadisticaService;
use App\Services\HistorialService;
use App\Services\InventarioService;

/** Caja, inventario, compras, historial, auditoría y estadísticas (solo Admin). */
#[Authorize(['Admin'])]
class AdminController extends Controller
{
    #[Route('/caja', 'GET')]
    public function caja()
    {
        $this->ok((new CajaService())->actual());
    }

    #[Route('/caja/abrir', 'POST')]
    public function abrirCaja()
    {
        $this->ok((new CajaService())->abrir((float)$this->input('monto_inicial', 0), $this->input('notas')), 'Caja abierta', 201);
    }

    #[Route('/caja/cerrar', 'POST')]
    public function cerrarCaja()
    {
        $this->ok((new CajaService())->cerrar((float)$this->input('efectivo_contado', 0), $this->input('notas')), 'Turno de caja cerrado');
    }

    #[Route('/caja/historial', 'GET')]
    public function historialCaja()
    {
        $this->ok((new CajaService())->historial());
    }

    #[Route('/inventario', 'GET')]
    public function inventario()
    {
        $this->ok((new InventarioService())->stock());
    }

    #[Route('/inventario/{id}/ajuste', 'POST')]
    public function ajuste(string $id)
    {
        $this->ok((new InventarioService())->ajustar((int)$id, (int)$this->input('stock', 0), (string)$this->input('motivo', '')), 'Stock ajustado');
    }

    #[Route('/compras', 'GET')]
    public function compras()
    {
        $this->ok((new InventarioService())->compras($this->query('desde'), $this->query('hasta'), $this->query('q')));
    }

    #[Route('/compras', 'POST')]
    public function registrarCompra()
    {
        $this->ok((new InventarioService())->registrarCompra(CompraDTO::fromRequest($this->input())), 'Compra registrada', 201);
    }

    #[Route('/historial', 'GET')]
    public function historial()
    {
        $this->ok((new HistorialService())->cuentas($_GET));
    }

    #[Route('/auditoria', 'GET')]
    public function auditoria()
    {
        $this->ok((new HistorialService())->auditoria($_GET));
    }

    #[Route('/estadisticas', 'GET')]
    public function estadisticas()
    {
        $this->ok((new EstadisticaService())->dashboard($this->query('desde'), $this->query('hasta')));
    }
}
