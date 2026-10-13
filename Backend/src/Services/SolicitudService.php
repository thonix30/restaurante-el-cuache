<?php
namespace App\Services;

use App\Core\HttpException;
use App\DTOs\SolicitudDTO;
use App\Repositories\EmpleadoRepository;
use App\Repositories\SolicitudRepository;
use App\Repositories\TurnoRepository;
use App\Utils\Audit;
use App\Utils\Auth;
use App\Utils\Database;
use App\Utils\Time;
use App\Utils\Url;

/** Vacaciones, permisos e intercambio de turno, con aprobación del admin. */
class SolicitudService
{
    private SolicitudRepository $repo;

    public function __construct()
    {
        $this->repo = new SolicitudRepository();
    }

    public function list(?string $estado = null, ?int $empleadoId = null): array
    {
        return array_map([$this, 'format'], $this->repo->list($estado, $empleadoId));
    }

    public function crear(int $empleadoId, SolicitudDTO $dto): array
    {
        $emp = (new EmpleadoRepository())->find($empleadoId);
        if (!$emp) throw HttpException::notFound('El empleado no existe.');
        $data = ['empleado_id' => $empleadoId, 'tipo' => $dto->tipo, 'motivo' => $dto->motivo];

        if ($dto->tipo === 'cambio_turno') {
            $turnos = new TurnoRepository();
            $t = $turnos->find($dto->turno_id);
            if (!$t || (int)$t['empleado_id'] !== $empleadoId) throw HttpException::invalid('Ese turno no es tuyo.');
            if ($t['fecha'] < Time::today()) throw HttpException::invalid('Ese turno ya pasó.');
            if ($dto->companero_id === $empleadoId) throw HttpException::invalid('Elige a otro compañero.');
            $comp = (new EmpleadoRepository())->find($dto->companero_id);
            if (!$comp || $comp['estado'] !== 'activo') throw HttpException::invalid('El compañero no está disponible.');
            if ($dto->turno_companero_id) {
                $tc = $turnos->find($dto->turno_companero_id);
                if (!$tc || (int)$tc['empleado_id'] !== $dto->companero_id) throw HttpException::invalid('El turno a cambio no es de ese compañero.');
            }
            $data += ['turno_id' => $dto->turno_id, 'companero_id' => $dto->companero_id, 'turno_companero_id' => $dto->turno_companero_id, 'fecha_inicio' => $t['fecha'], 'fecha_fin' => $t['fecha']];
        } else {
            if ($dto->fecha_inicio < Time::today()) throw HttpException::invalid('La fecha ya pasó.', ['errors' => ['fecha_inicio' => 'Elige una fecha futura.']]);
            if ($dto->tipo === 'vacaciones') {
                $dias = Time::daysBetween($dto->fecha_inicio, $dto->fecha_fin);
                $saldo = (new EmpleadoService())->vacaciones($emp)['disponibles'];
                if ($dias > $saldo) throw HttpException::invalid("Pides $dias días y tu saldo disponible es de $saldo.", ['errors' => ['fecha_fin' => 'Supera tu saldo.']]);
            }
            $data += ['fecha_inicio' => $dto->fecha_inicio, 'fecha_fin' => $dto->fecha_fin];
        }
        $id = $this->repo->create($data);
        return $this->format($this->repo->find($id));
    }

    public function resolver(int $id, bool $aprobar, ?string $respuesta): array
    {
        Database::transaction(function () use ($id, $aprobar, $respuesta) {
            $s = $this->repo->find($id);
            if (!$s) throw HttpException::notFound('La solicitud no existe.');
            if ($s['estado'] !== 'pendiente') throw HttpException::conflict('La solicitud ya fue resuelta.');

            if ($aprobar) {
                $turnos = new TurnoRepository();
                if ($s['tipo'] === 'cambio_turno') {
                    $t = $turnos->find((int)$s['turno_id']);
                    if (!$t || (int)$t['empleado_id'] !== (int)$s['empleado_id']) throw HttpException::conflict('El turno ya no existe o cambió de dueño.');
                    $turnos->save((int)$t['id'], ['empleado_id' => (int)$s['companero_id'], 'publicado' => 1]);
                    if ($s['turno_companero_id']) {
                        $turnos->save((int)$s['turno_companero_id'], ['empleado_id' => (int)$s['empleado_id'], 'publicado' => 1]);
                    }
                } else {
                    // Vacaciones/permiso: sus turnos de esos días se liberan (la cobertura los mostrará en rojo)
                    $turnos->deleteRango($s['fecha_inicio'], $s['fecha_fin'], (int)$s['empleado_id']);
                    if ($s['tipo'] === 'vacaciones' && $s['fecha_inicio'] <= Time::today() && $s['fecha_fin'] >= Time::today()) {
                        (new EmpleadoRepository())->updateFields((int)$s['empleado_id'], ['estado' => 'vacaciones']);
                    }
                }
            }
            $this->repo->save($id, [
                'estado' => $aprobar ? 'aprobada' : 'rechazada',
                'respuesta' => $respuesta ? mb_substr(trim($respuesta), 0, 255) : null,
                'resuelto_por' => Auth::id(),
                'resuelto_en' => date('Y-m-d H:i:s'),
            ]);
            $tipos = ['vacaciones' => 'vacaciones', 'permiso' => 'permiso', 'cambio_turno' => 'cambio de turno'];
            Audit::log($aprobar ? 'solicitud_aprobada' : 'solicitud_rechazada', 'personal', 'solicitud', $id,
                ucfirst($tipos[$s['tipo']]) . " de {$s['empleado']} " . ($aprobar ? 'aprobada' : 'rechazada') .
                ($s['fecha_inicio'] ? " ({$s['fecha_inicio']}" . ($s['fecha_fin'] !== $s['fecha_inicio'] ? " a {$s['fecha_fin']}" : '') . ')' : ''));
        });
        return $this->format($this->repo->find($id));
    }

    public function cancelar(int $id, int $empleadoId): array
    {
        $s = $this->repo->find($id);
        if (!$s || (int)$s['empleado_id'] !== $empleadoId) throw HttpException::notFound('La solicitud no existe.');
        if ($s['estado'] !== 'pendiente') throw HttpException::conflict('Solo puedes cancelar solicitudes pendientes.');
        $this->repo->save($id, ['estado' => 'cancelada']);
        return $this->format($this->repo->find($id));
    }

    private function format(array $s): array
    {
        return [
            'id' => (int)$s['id'],
            'empleado_id' => (int)$s['empleado_id'],
            'empleado' => $s['empleado'],
            'foto' => Url::asset($s['foto']),
            'puesto' => $s['puesto'],
            'tipo' => $s['tipo'],
            'fecha_inicio' => $s['fecha_inicio'],
            'fecha_fin' => $s['fecha_fin'],
            'dias' => $s['fecha_inicio'] ? Time::daysBetween($s['fecha_inicio'], $s['fecha_fin'] ?? $s['fecha_inicio']) : null,
            'motivo' => $s['motivo'],
            'estado' => $s['estado'],
            'respuesta' => $s['respuesta'],
            'companero' => $s['companero'],
            'turno' => $s['turno_fecha'] ? ['fecha' => $s['turno_fecha'], 'inicio' => substr($s['turno_inicio'], 0, 5), 'fin' => substr($s['turno_fin'], 0, 5)] : null,
            'turno_companero' => $s['turno_comp_fecha'] ? ['fecha' => $s['turno_comp_fecha'], 'inicio' => substr($s['turno_comp_inicio'], 0, 5), 'fin' => substr($s['turno_comp_fin'], 0, 5)] : null,
            'resuelto_por' => $s['resuelto_por_nombre'],
            'resuelto_en' => $s['resuelto_en'],
            'creado_en' => $s['creado_en'],
        ];
    }
}
