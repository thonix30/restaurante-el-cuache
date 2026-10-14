<?php
namespace App\Services;

use App\Core\HttpException;
use App\Entities\MesaEntity;
use App\Repositories\CuentaRepository;
use App\Repositories\MesaRepository;
use App\Utils\Audit;
use App\Utils\Config;

class MesaService
{
    private MesaRepository $repo;

    public function __construct()
    {
        $this->repo = new MesaRepository();
    }

    public function list(bool $withToken = false, bool $soloActivas = true): array
    {
        return array_map(fn($r) => MesaEntity::toArray($r, $withToken), $this->repo->allWithEstado($soloActivas));
    }

    public function save(?int $id, array $d): array
    {
        $numero = (int)($d['numero'] ?? 0);
        if ($numero <= 0) throw HttpException::invalid('Indica el número de mesa.', ['errors' => ['numero' => 'Número inválido.']]);
        if ($this->repo->numeroEnUso($numero, $id)) {
            throw HttpException::invalid("Ya existe la mesa $numero.", ['errors' => ['numero' => 'Ese número ya está en uso.']]);
        }
        $row = [
            'numero' => $numero,
            'nombre' => trim((string)($d['nombre'] ?? '')) ?: null,
            'capacidad' => max(1, min(30, (int)($d['capacidad'] ?? 4))),
            'zona' => mb_substr(trim((string)($d['zona'] ?? 'Salón')) ?: 'Salón', 0, 40),
            'forma' => in_array($d['forma'] ?? '', ['redonda', 'cuadrada', 'larga'], true) ? $d['forma'] : 'cuadrada',
        ];
        if (isset($d['pos_x'])) $row['pos_x'] = max(0, min(95, (float)$d['pos_x']));
        if (isset($d['pos_y'])) $row['pos_y'] = max(0, min(92, (float)$d['pos_y']));
        if (isset($d['activa'])) $row['activa'] = (int)(bool)$d['activa'];

        if ($id) {
            if (!$this->repo->find($id)) throw HttpException::notFound('La mesa no existe.');
            $this->repo->save($id, $row);
        } else {
            $row['qr_token'] = bin2hex(random_bytes(12));
            if (!isset($d['pos_x'], $d['pos_y'])) [$row['pos_x'], $row['pos_y']] = $this->lugarLibre();
            $id = $this->repo->create($row);
        }
        return MesaEntity::toArray($this->repo->findWithEstado($id), true);
    }

    /** Primer lugar del plano (en %) que no choca con otra mesa, recorriendo una cuadrícula. */
    private function lugarLibre(): array
    {
        $ocupados = array_map(fn($m) => [(float)$m['pos_x'], (float)$m['pos_y']], $this->repo->allWithEstado(true));
        for ($y = 8; $y <= 80; $y += 22) {
            for ($x = 6; $x <= 86; $x += 14) {
                $libre = true;
                foreach ($ocupados as [$ox, $oy]) {
                    if (abs($ox - $x) < 13 && abs($oy - $y) < 20) { $libre = false; break; }
                }
                if ($libre) return [$x, $y];
            }
        }
        return [44, 44];
    }

    /** Guarda posiciones del plano (arrastre en modo edición). */
    public function savePositions(array $positions): void
    {
        foreach ($positions as $p) {
            $id = (int)($p['id'] ?? 0);
            if ($id <= 0) continue;
            $this->repo->save($id, [
                'pos_x' => max(0, min(95, (float)($p['pos_x'] ?? 0))),
                'pos_y' => max(0, min(92, (float)($p['pos_y'] ?? 0))),
            ]);
        }
    }

    public function delete(int $id): void
    {
        $m = $this->repo->find($id);
        if (!$m) throw HttpException::notFound('La mesa no existe.');
        if ((new CuentaRepository())->abiertaDeMesa($id)) {
            throw HttpException::conflict('La mesa tiene una cuenta abierta. Ciérrala antes de eliminarla.');
        }
        // Con historial de ventas se desactiva para no perder la referencia
        if ($this->repo->tieneHistorial($id)) {
            $this->repo->save($id, ['activa' => 0]);
        } else {
            $this->repo->delete($id);
        }
        Audit::log('mesa_eliminada', 'sistema', 'mesa', $id, "Se eliminó la mesa {$m['numero']}");
    }

    /** Genera un QR nuevo: el anterior deja de funcionar (ej. si alguien lo fotografió). */
    public function regenerarToken(int $id): array
    {
        $m = $this->repo->find($id);
        if (!$m) throw HttpException::notFound('La mesa no existe.');
        $this->repo->save($id, ['qr_token' => bin2hex(random_bytes(12))]);
        Audit::log('qr_regenerado', 'sistema', 'mesa', $id, "Nuevo código QR para la mesa {$m['numero']}");
        return MesaEntity::toArray($this->repo->findWithEstado($id), true);
    }

    public function qrConfig(): array
    {
        return [
            'url_base_menu' => Config::setting('url_base_menu', ''),
            'restaurante' => Config::setting('restaurante_nombre', 'El Cuache'),
            'lema' => Config::setting('restaurante_lema', 'Café y Restaurante'),
        ];
    }
}
