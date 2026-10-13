<?php
namespace App\Services;

use App\Core\HttpException;
use App\Entities\EmpleadoEntity;
use App\Repositories\EmpleadoRepository;
use App\Repositories\TurnoRepository;
use App\Utils\Audit;
use App\Utils\Time;

/** "Mi perfil": cada empleado ve y gestiona únicamente su propia información. */
class PerfilService
{
    public function get(int $id): array
    {
        $repo = new EmpleadoRepository();
        $e = $repo->find($id);
        if (!$e) throw HttpException::notFound('No encontramos tu ficha.');
        $turnos = new TurnoRepository();
        $hoy = Time::today();
        $lunes = Time::weekStart($hoy);

        $asis = new AsistenciaService();
        $semana = $asis->calcular($lunes, $hoy, $id)[$id] ?? [];
        $proximo = $turnos->proximo($id);

        return [
            'empleado' => EmpleadoEntity::fromRow($e)->toSelf(),
            'proximo_turno' => $proximo ? [
                'id' => (int)$proximo['id'], 'fecha' => $proximo['fecha'],
                'hora_inicio' => substr($proximo['hora_inicio'], 0, 5), 'hora_fin' => substr($proximo['hora_fin'], 0, 5), 'tipo' => $proximo['tipo'],
            ] : null,
            // Solo turnos publicados: los borradores del planificador no se muestran
            'horario' => $turnos->rango($lunes, Time::addDays($lunes, 13), $id, true),
            'horas_semana' => round(array_sum(array_column($semana, 'trabajado_min')) / 60, 1),
            'marcacion' => $asis->estadoMarcacion($e),
            'vacaciones' => (new EmpleadoService())->vacaciones($e),
            'solicitudes' => (new SolicitudService())->list(null, $id),
            'companeros' => array_values(array_map(fn($c) => ['id' => (int)$c['id'], 'nombre' => trim($c['nombre'] . ' ' . $c['apellido']), 'puesto' => $c['puesto']],
                array_filter($repo->activos(), fn($c) => (int)$c['id'] !== $id && $c['estado'] === 'activo'))),
            'turnos_companeros' => array_values(array_filter($turnos->rango($hoy, Time::addDays($hoy, 21), null, true), fn($t) => $t['empleado_id'] !== $id)),
            'asistencia_mes' => $asis->mes($id, date('Y-m')),
        ];
    }

    public function cambiarPassword(int $id, string $actual, string $nueva): void
    {
        $repo = new EmpleadoRepository();
        $e = $repo->find($id);
        if (!$e || !$e['password'] || !password_verify($actual, $e['password'])) {
            throw HttpException::invalid('La contraseña actual no es correcta.', ['errors' => ['actual' => 'Incorrecta.']]);
        }
        if (mb_strlen($nueva) < 6) throw HttpException::invalid('La nueva contraseña debe tener al menos 6 caracteres.', ['errors' => ['nueva' => 'Mínimo 6.']]);
        $repo->updateFields($id, ['password' => password_hash($nueva, PASSWORD_DEFAULT)]);
        Audit::log('password_cambiado', 'acceso', 'empleado', $id, 'Cambió su contraseña');
    }
}
