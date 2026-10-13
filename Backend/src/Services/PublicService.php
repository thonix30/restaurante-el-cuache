<?php
namespace App\Services;

use App\Core\HttpException;
use App\Repositories\CajaRepository;
use App\Repositories\CategoriaRepository;
use App\Repositories\CuentaRepository;
use App\Repositories\LlamadoRepository;
use App\Repositories\MesaRepository;
use App\Repositories\ProductoRepository;
use App\Utils\Config;

/** Menú digital del cliente (sin login). La mesa se identifica con el token de su QR. */
class PublicService
{
    public function menu(): array
    {
        $productos = array_map(fn($p) => $p->toPublic(), (new ProductoRepository())->list(['publico' => true]));
        return [
            'restaurante' => [
                'nombre' => Config::setting('restaurante_nombre', 'El Cuache'),
                'lema' => Config::setting('restaurante_lema', 'Café y Restaurante'),
                'moneda' => Config::setting('moneda', 'Q'),
                'propina_sugerida' => (int)Config::setting('propina_sugerida', 10),
            ],
            'abierto' => (bool)(new CajaRepository())->abierto(),
            'categorias' => (new CategoriaRepository())->listar(true),
            'productos' => $productos,
        ];
    }

    public function mesa(string $token): array
    {
        $m = (new MesaRepository())->findByToken($token);
        if (!$m) throw HttpException::notFound('Este código QR ya no es válido. Pide ayuda a tu mesero.');
        return $m;
    }

    /** Estado en vivo para la mesa: agotados, llamados y lo que lleva pedido. */
    public function estado(string $token): array
    {
        $m = $this->mesa($token);
        $llamados = (new LlamadoRepository())->pendientesDeMesa((int)$m['id']);
        $porTipo = [];
        foreach ($llamados as $l) $porTipo[$l['tipo']] = ['id' => (int)$l['id'], 'creado_en' => $l['creado_en'], 'metodo_pago' => $l['metodo_pago']];

        $cuenta = (new CuentaRepository())->abiertaDeMesa((int)$m['id']);
        $resumen = null;
        if ($cuenta) {
            $det = (new PedidoService())->cuenta((int)$cuenta['id']);
            $resumen = [
                'total' => $det['total'],
                'saldo' => $det['saldo'],
                'items' => array_values(array_map(fn($i) => [
                    'nombre' => $i['nombre'], 'cantidad' => $i['cantidad'], 'precio' => $i['precio'], 'notas' => $i['notas'], 'estado' => $i['comanda_estado'],
                ], array_filter($det['items'], fn($i) => $i['estado'] === 'activo'))),
            ];
        }
        return [
            'server_time' => date('Y-m-d H:i:s'),
            'mesa' => ['numero' => (int)$m['numero'], 'nombre' => $m['nombre'] ?: 'Mesa ' . $m['numero']],
            'abierto' => (bool)(new CajaRepository())->abierto(),
            'agotados' => (new ProductoRepository())->agotadosIds(),
            'llamados' => ['mesero' => $porTipo['mesero'] ?? null, 'cuenta' => $porTipo['cuenta'] ?? null],
            'cuenta' => $resumen,
        ];
    }
}
