<?php
namespace App\Services;

use App\Core\HttpException;
use App\Repositories\ComandaRepository;
use App\Utils\Database;

/** Tablero de cocina (KDS): Pendiente → En preparación → Listo → Entregado. */
class CocinaService
{
    private const FLUJO = ['pendiente' => 'preparacion', 'preparacion' => 'listo', 'listo' => 'entregado'];
    private const SELLO = ['preparacion' => 'iniciado_en', 'listo' => 'listo_en', 'entregado' => 'entregado_en'];

    private ComandaRepository $repo;

    public function __construct()
    {
        $this->repo = new ComandaRepository();
    }

    public function tablero(): array
    {
        return array_map(function ($c) {
            $items = array_values(array_filter($c['items'], fn($i) => $i['estado'] === 'activo'));
            return [
                'id' => (int)$c['id'],
                'cuenta_id' => (int)$c['cuenta_id'],
                'mesa_numero' => $c['mesa_numero'] !== null ? (int)$c['mesa_numero'] : null,
                'mesero' => $c['mesero'],
                'origen' => $c['origen'],
                'estado' => $c['estado'],
                'creado_en' => $c['creado_en'],
                'iniciado_en' => $c['iniciado_en'],
                'listo_en' => $c['listo_en'],
                'items' => array_map(fn($i) => [
                    'id' => (int)$i['id'],
                    'producto_id' => $i['producto_id'] !== null ? (int)$i['producto_id'] : null,
                    'nombre' => $i['nombre'],
                    'categoria' => $i['categoria'],
                    'cantidad' => (int)$i['cantidad'],
                    'notas' => $i['notas'],
                ], $items),
                'cancelados' => count($c['items']) - count($items),
            ];
        }, $this->repo->tablero());
    }

    /** Avanza al siguiente estado (o al indicado, permitiendo retroceder un paso por error). */
    public function cambiarEstado(int $id, ?string $estado = null): array
    {
        return Database::transaction(function () use ($id, $estado) {
            $c = $this->repo->find($id, true);
            if (!$c) throw HttpException::notFound('La comanda no existe.');
            if (in_array($c['estado'], ['entregado', 'cancelado'], true) && $estado === null) {
                throw HttpException::conflict('La comanda ya fue entregada.');
            }
            if ($c['estado'] === 'cancelado') throw HttpException::conflict('La comanda fue cancelada.');
            $nuevo = $estado ?? (self::FLUJO[$c['estado']] ?? null);
            $validos = ['pendiente', 'preparacion', 'listo', 'entregado'];
            if (!$nuevo || !in_array($nuevo, $validos, true)) throw HttpException::invalid('Estado inválido.');
            // Un mesero solo entrega lo que cocina ya marcó como listo
            if ($nuevo === 'entregado' && $c['estado'] !== 'listo' && (\App\Utils\Auth::user()['rol'] ?? null) === 'Mesero') {
                throw HttpException::conflict('Esta comanda todavía no está lista en cocina.');
            }

            $data = ['estado' => $nuevo];
            if (isset(self::SELLO[$nuevo])) $data[self::SELLO[$nuevo]] = date('Y-m-d H:i:s');
            if ($nuevo === 'pendiente') $data += ['iniciado_en' => null, 'listo_en' => null];
            if ($nuevo === 'preparacion') $data['listo_en'] = null;
            $this->repo->save($id, $data);
            return ['id' => $id, 'estado' => $nuevo];
        });
    }
}
