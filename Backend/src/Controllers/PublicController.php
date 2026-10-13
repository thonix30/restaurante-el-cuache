<?php
namespace App\Controllers;

use App\Attributes\Route;
use App\Core\Controller;
use App\Core\HttpException;
use App\DTOs\PedidoDTO;
use App\Services\CajaService;
use App\Services\LlamadoService;
use App\Services\PedidoService;
use App\Services\PublicService;

/** Endpoints sin login para el menú digital del cliente (QR de la mesa). */
class PublicController extends Controller
{
    #[Route('/public/menu', 'GET')]
    public function menu()
    {
        $this->ok((new PublicService())->menu());
    }

    #[Route('/public/caja', 'GET')]
    public function caja()
    {
        $this->ok((new CajaService())->estadoPublico());
    }

    #[Route('/public/mesa/{token}/estado', 'GET')]
    public function estado(string $token)
    {
        $this->ok((new PublicService())->estado($token));
    }

    #[Route('/public/mesa/{token}/pedido', 'POST')]
    public function pedido(string $token)
    {
        $mesa = (new PublicService())->mesa($token);
        $res = (new PedidoService())->enviar((int)$mesa['id'], PedidoDTO::fromRequest($this->input()), 'cliente');
        $this->ok(['comanda_id' => $res['comanda_id'], 'total_enviado' => $res['total_enviado']], '¡Tu pedido va a la cocina!', 201);
    }

    #[Route('/public/mesa/{token}/llamado', 'POST')]
    public function llamar(string $token)
    {
        $mesa = (new PublicService())->mesa($token);
        $tipo = (string)$this->input('tipo', '');
        if (!in_array($tipo, ['mesero', 'cuenta'], true)) throw HttpException::invalid('Tipo de aviso inválido.');
        $l = (new LlamadoService())->llamar((int)$mesa['id'], $tipo, $this->input('metodo_pago'));
        $this->ok(['id' => (int)$l['id'], 'tipo' => $l['tipo'], 'creado_en' => $l['creado_en'], 'metodo_pago' => $l['metodo_pago']],
            $tipo === 'mesero' ? 'Tu mesero ya fue avisado' : 'Te llevamos la cuenta en un momento', 201);
    }

    #[Route('/public/mesa/{token}/llamado/{id}', 'DELETE')]
    public function cancelarLlamado(string $token, string $id)
    {
        $mesa = (new PublicService())->mesa($token);
        (new LlamadoService())->cancelarDeMesa((int)$mesa['id'], (int)$id);
        $this->ok(null, 'Aviso cancelado');
    }
}
