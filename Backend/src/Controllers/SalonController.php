<?php
namespace App\Controllers;

use App\Attributes\Authorize;
use App\Attributes\Route;
use App\Core\Controller;
use App\DTOs\PagoDTO;
use App\DTOs\PedidoDTO;
use App\Services\CocinaService;
use App\Services\LlamadoService;
use App\Services\MesaService;
use App\Services\PagoService;
use App\Services\PedidoService;

/** Salón: plano de mesas, cuentas, toma de pedidos, cobros y avisos. Cocina: KDS. */
#[Authorize(['Admin', 'Mesero'])]
class SalonController extends Controller
{
    #[Route('/salon', 'GET')]
    public function salon()
    {
        $this->ok([
            'mesas' => (new MesaService())->list(),
            'llamados' => (new LlamadoService())->pendientes(),
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/mesas/{id}/abrir', 'POST')]
    public function abrir(string $id)
    {
        $this->ok((new PedidoService())->abrirMesa((int)$id, (int)$this->input('personas', 1)), 'Mesa abierta', 201);
    }

    #[Route('/mesas/{id}/cuenta', 'GET')]
    public function cuentaMesa(string $id)
    {
        $this->ok((new PedidoService())->cuentaDeMesa((int)$id));
    }

    #[Route('/mesas/{id}/pedido', 'POST')]
    public function pedido(string $id)
    {
        $s = new PedidoService();
        $res = $s->enviar((int)$id, PedidoDTO::fromRequest($this->input()), 'mesero');
        $this->ok($res + ['cuenta' => $s->cuenta($res['cuenta_id'])], 'Pedido enviado a cocina', 201);
    }

    #[Route('/cuentas/{id}', 'GET')]
    #[Authorize(['Admin', 'Mesero', 'Cocinero'])]
    public function cuenta(string $id)
    {
        $this->ok((new PedidoService())->cuenta((int)$id));
    }

    #[Route('/cuentas/{id}/pagos', 'POST')]
    public function cobrar(string $id)
    {
        $res = (new PagoService())->cobrar((int)$id, PagoDTO::fromRequest($this->input()));
        $this->ok($res, $res['completo'] ? 'Cuenta saldada' : 'Pago registrado', 201);
    }

    #[Route('/cuentas/{id}/descuento', 'PUT')]
    #[Authorize(['Admin'])]
    public function descuento(string $id)
    {
        $this->ok((new PedidoService())->aplicarDescuento((int)$id, (string)$this->input('tipo', 'monto'), (float)$this->input('valor', 0), $this->input('motivo')), 'Descuento aplicado');
    }

    #[Route('/cuentas/{id}/personas', 'PUT')]
    public function personas(string $id)
    {
        $this->ok((new PedidoService())->setPersonas((int)$id, (int)$this->input('personas', 1)));
    }

    #[Route('/cuentas/{id}/mesa', 'PUT')]
    public function mover(string $id)
    {
        $this->ok((new PedidoService())->moverMesa((int)$id, (int)$this->input('mesa_id')), 'Cuenta movida');
    }

    #[Route('/cuentas/{id}/cancelar', 'POST')]
    #[Authorize(['Admin'])]
    public function cancelarCuenta(string $id)
    {
        $this->ok((new PedidoService())->cancelarCuenta((int)$id, (string)$this->input('motivo', '')), 'Cuenta cancelada');
    }

    #[Route('/items/{id}/cancelar', 'POST')]
    public function cancelarItem(string $id)
    {
        $cant = $this->input('cantidad');
        $this->ok((new PedidoService())->cancelarItem((int)$id, $cant !== null ? (int)$cant : null, (string)$this->input('motivo', '')), 'Platillo cancelado');
    }

    #[Route('/pagos/{id}/anular', 'POST')]
    #[Authorize(['Admin'])]
    public function anularPago(string $id)
    {
        $this->ok((new PagoService())->anular((int)$id, (string)$this->input('motivo', '')), 'Pago anulado');
    }

    #[Route('/llamados', 'GET')]
    public function llamados()
    {
        $this->ok((new LlamadoService())->pendientes());
    }

    #[Route('/llamados/{id}/atender', 'POST')]
    public function atender(string $id)
    {
        (new LlamadoService())->atender((int)$id);
        $this->ok(null, 'Aviso atendido');
    }

    // ── Cocina (KDS) ────────────────────────────────────────────────────
    #[Route('/cocina/comandas', 'GET')]
    #[Authorize(['Admin', 'Cocinero', 'Mesero'])]
    public function comandas()
    {
        $this->ok(['comandas' => (new CocinaService())->tablero(), 'server_time' => date('Y-m-d H:i:s')]);
    }

    #[Route('/cocina/comandas/{id}/estado', 'PUT')]
    #[Authorize(['Admin', 'Cocinero', 'Mesero'])]
    public function estadoComanda(string $id)
    {
        $this->ok((new CocinaService())->cambiarEstado((int)$id, $this->input('estado')));
    }
}
