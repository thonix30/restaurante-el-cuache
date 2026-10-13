<?php
namespace App\Services;

use App\Core\HttpException;
use App\DTOs\TurnoDTO;
use App\Repositories\EmpleadoRepository;
use App\Repositories\SolicitudRepository;
use App\Repositories\TurnoRepository;
use App\Utils\Audit;
use App\Utils\Config;
use App\Utils\Time;
use App\Utils\Url;

class TurnoService
{
    public const PRESETS = [
        'manana' => ['07:00', '15:00'],
        'tarde' => ['15:00', '23:00'],
        'noche' => ['18:00', '02:00'],
    ];

    private TurnoRepository $repo;

    public function __construct()
    {
        $this->repo = new TurnoRepository();
    }

    /** Planificador semanal: empleados, turnos, horas, conflictos y cobertura. */
    public function semana(string $fecha): array
    {
        $desde = Time::weekStart($fecha);
        $hasta = Time::addDays($desde, 6);
        $empleados = array_values(array_filter((new EmpleadoRepository())->activos(), fn($e) => $e['estado'] !== 'baja'));
        $turnos = $this->repo->rango($desde, $hasta);

        [$conflictos, $horas, $porEmpleado] = $this->analizar($turnos, $empleados, $desde, $hasta);
        foreach ($turnos as &$t) $t['conflictos'] = $conflictos[$t['id']] ?? [];
        unset($t);

        $maxSemana = (int)Config::setting('horas_max_semana', 48);
        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'dias' => Time::dates($desde, $hasta),
            'empleados' => array_map(fn($e) => [
                'id' => (int)$e['id'],
                'nombre' => trim($e['nombre'] . ' ' . $e['apellido']),
                'puesto' => $e['puesto'],
                'estado' => $e['estado'],
                'foto' => Url::asset($e['foto']),
                'minutos' => $horas[(int)$e['id']] ?? 0,
                'excede' => ($horas[(int)$e['id']] ?? 0) > $maxSemana * 60,
                'avisos' => $porEmpleado[(int)$e['id']] ?? [],
            ], $empleados),
            'turnos' => $turnos,
            'cobertura' => $this->cobertura($turnos, $desde, $hasta),
            'sin_publicar' => count(array_filter($turnos, fn($t) => !$t['publicado'])),
            'presets' => self::PRESETS,
            'config' => [
                'horas_max_semana' => $maxSemana,
                'descanso_min_horas' => (int)Config::setting('descanso_min_horas', 10),
                'cobertura_minima' => (int)Config::setting('cobertura_minima', 2),
            ],
        ];
    }

    private function analizar(array $turnos, array $empleados, string $desde, string $hasta): array
    {
        $maxSemana = (int)Config::setting('horas_max_semana', 48) * 60;
        $descansoMin = (int)Config::setting('descanso_min_horas', 10) * 3600;
        $ausencias = (new SolicitudRepository())->ausencias($desde, $hasta);
        $estadoEmp = array_column($empleados, 'estado', 'id');

        // Para revisar el descanso también contra el último turno de la semana anterior
        $previos = $this->repo->rango(Time::addDays($desde, -1), Time::addDays($desde, -1));

        $conf = []; $horas = []; $porEmp = [];
        $grupos = [];
        foreach (array_merge($previos, $turnos) as $t) {
            [$a, $b] = Time::shiftRange($t['fecha'], $t['hora_inicio'], $t['hora_fin']);
            $grupos[$t['empleado_id']][] = $t + ['_a' => $a, '_b' => $b, '_prev' => $t['fecha'] < $desde];
        }
        foreach ($grupos as $eid => $lista) {
            usort($lista, fn($x, $y) => $x['_a'] <=> $y['_a']);
            $prev = null;
            foreach ($lista as $t) {
                if (!$t['_prev']) {
                    $horas[$eid] = ($horas[$eid] ?? 0) + intdiv($t['_b'] - $t['_a'], 60);
                    if (isset($ausencias[$eid][$t['fecha']])) {
                        $conf[$t['id']][] = $ausencias[$eid][$t['fecha']] === 'vacaciones' ? 'Tiene vacaciones aprobadas ese día' : 'Tiene un permiso aprobado ese día';
                    }
                    if (in_array($estadoEmp[$eid] ?? 'activo', ['vacaciones', 'suspendido'], true)) {
                        $conf[$t['id']][] = 'El empleado está ' . ($estadoEmp[$eid] === 'vacaciones' ? 'de vacaciones' : 'suspendido');
                    }
                }
                if ($prev) {
                    if ($t['_a'] < $prev['_b']) {
                        if (!$t['_prev']) $conf[$t['id']][] = 'Se cruza con otro turno';
                    } elseif ($t['_a'] - $prev['_b'] < $descansoMin && !$t['_prev']) {
                        $h = round(($t['_a'] - $prev['_b']) / 3600, 1);
                        $conf[$t['id']][] = "Solo {$h} h de descanso desde el turno anterior (mínimo " . ($descansoMin / 3600) . " h)";
                    }
                }
                $prev = $t;
            }
            if (($horas[$eid] ?? 0) > $maxSemana) {
                $porEmp[$eid][] = 'Excede ' . ($maxSemana / 60) . ' h semanales (' . round($horas[$eid] / 60, 1) . ' h)';
            }
        }
        return [$conf, $horas, $porEmp];
    }

    /** Personas programadas por hora en el horario de servicio de cada día. */
    private function cobertura(array $turnos, string $desde, string $hasta): array
    {
        $minimo = (int)Config::setting('cobertura_minima', 2);
        $abre = (int)substr(Config::setting('horario_apertura', '07:00'), 0, 2);
        $cierra = (int)substr(Config::setting('horario_cierre', '23:00'), 0, 2);
        if ($cierra <= $abre) $cierra += 24;

        $rangos = array_map(fn($t) => Time::shiftRange($t['fecha'], $t['hora_inicio'], $t['hora_fin']), $turnos);
        $out = [];
        foreach (Time::dates($desde, $hasta) as $d) {
            $horas = [];
            for ($h = $abre; $h < $cierra; $h++) {
                $a = strtotime("$d 00:00") + $h * 3600;
                $b = $a + 3600;
                $n = 0;
                foreach ($rangos as [$x, $y]) if (Time::overlap($a, $b, $x, $y) >= 1800) $n++;
                $horas[] = ['hora' => $h % 24, 'personas' => $n, 'falta' => $n < $minimo];
            }
            $out[] = ['fecha' => $d, 'horas' => $horas, 'deficit' => count(array_filter($horas, fn($x) => $x['falta']))];
        }
        return $out;
    }

    public function crear(TurnoDTO $dto): array
    {
        $this->assertEmpleado($dto->empleado_id);
        $id = $this->repo->create([
            'empleado_id' => $dto->empleado_id, 'fecha' => $dto->fecha, 'hora_inicio' => $dto->hora_inicio,
            'hora_fin' => $dto->hora_fin, 'tipo' => $dto->tipo, 'notas' => $dto->notas, 'publicado' => 0,
        ]);
        return $this->conConflictos($id, $dto->fecha);
    }

    public function actualizar(int $id, TurnoDTO $dto): array
    {
        if (!$this->repo->find($id)) throw HttpException::notFound('El turno no existe.');
        $this->assertEmpleado($dto->empleado_id);
        // Mover o cambiar un turno lo devuelve a "sin publicar" para que el equipo vea el cambio al publicar
        $this->repo->save($id, [
            'empleado_id' => $dto->empleado_id, 'fecha' => $dto->fecha, 'hora_inicio' => $dto->hora_inicio,
            'hora_fin' => $dto->hora_fin, 'tipo' => $dto->tipo, 'notas' => $dto->notas, 'publicado' => 0,
        ]);
        return $this->conConflictos($id, $dto->fecha);
    }

    public function eliminar(int $id): void
    {
        if (!$this->repo->find($id)) throw HttpException::notFound('El turno no existe.');
        $this->repo->delete($id);
    }

    private function conConflictos(int $id, string $fecha): array
    {
        $sem = $this->semana($fecha);
        foreach ($sem['turnos'] as $t) if ($t['id'] === $id) return $t;
        return [];
    }

    public function copiarSemanaAnterior(string $semana, bool $reemplazar): array
    {
        $destino = Time::weekStart($semana);
        $origen = Time::addDays($destino, -7);
        $turnos = $this->repo->rango($origen, Time::addDays($origen, 6));
        if (!$turnos) throw HttpException::conflict('La semana anterior no tiene turnos para copiar.');
        if ($reemplazar) $this->repo->deleteRango($destino, Time::addDays($destino, 6));
        $empleados = array_column((new EmpleadoRepository())->activos(), null, 'id');
        $n = 0;
        foreach ($turnos as $t) {
            if (!isset($empleados[$t['empleado_id']])) continue;
            $this->repo->create([
                'empleado_id' => $t['empleado_id'], 'fecha' => Time::addDays($t['fecha'], 7),
                'hora_inicio' => $t['hora_inicio'], 'hora_fin' => $t['hora_fin'], 'tipo' => $t['tipo'], 'notas' => $t['notas'], 'publicado' => 0,
            ]);
            $n++;
        }
        Audit::log('turnos_copiados', 'personal', 'turno', null, "Se copiaron $n turnos a la semana del $destino");
        return $this->semana($destino);
    }

    public function publicar(string $semana): array
    {
        $desde = Time::weekStart($semana);
        $n = $this->repo->publicar($desde, Time::addDays($desde, 6));
        Audit::log('horario_publicado', 'personal', 'turno', null, "Horario publicado para la semana del $desde ($n turnos actualizados)");
        return $this->semana($desde);
    }

    public function limpiarSemana(string $semana): array
    {
        $desde = Time::weekStart($semana);
        $n = $this->repo->deleteRango($desde, Time::addDays($desde, 6));
        Audit::log('turnos_eliminados', 'personal', 'turno', null, "Se eliminaron $n turnos de la semana del $desde");
        return $this->semana($desde);
    }

    // ── Plantillas ──────────────────────────────────────────────────────
    public function plantillas(): array
    {
        return $this->repo->plantillas();
    }

    public function guardarPlantilla(string $nombre, string $semana, bool $rotativa): array
    {
        $nombre = trim($nombre);
        if ($nombre === '') throw HttpException::invalid('Ponle un nombre a la plantilla.');
        $desde = Time::weekStart($semana);
        $turnos = $this->repo->rango($desde, Time::addDays($desde, 6));
        if (!$turnos) throw HttpException::conflict('La semana no tiene turnos para guardar como plantilla.');
        $patron = array_map(fn($t) => [
            'empleado_id' => $t['empleado_id'],
            'dia' => Time::daysBetween($desde, $t['fecha']) - 1,
            'hora_inicio' => $t['hora_inicio'], 'hora_fin' => $t['hora_fin'], 'tipo' => $t['tipo'],
        ], $turnos);
        $id = $this->repo->crearPlantilla(mb_substr($nombre, 0, 80), $patron, $rotativa);
        return $this->repo->plantilla($id);
    }

    /**
     * Aplica una plantilla a una semana. En las rotativas, los patrones se
     * rotan entre los empleados de la plantilla una posición por semana
     * transcurrida desde que se creó (ej. mañana ↔ tarde semana a semana).
     */
    public function aplicarPlantilla(int $id, string $semana, bool $reemplazar): array
    {
        $p = $this->repo->plantilla($id);
        if (!$p) throw HttpException::notFound('La plantilla no existe.');
        $desde = Time::weekStart($semana);
        $activos = array_column((new EmpleadoRepository())->activos(), null, 'id');

        $emps = array_values(array_unique(array_column($p['patron'], 'empleado_id')));
        $mapa = array_combine($emps, $emps);
        if ($p['rotativa'] && count($emps) > 1) {
            $semanas = intdiv(strtotime($desde) - strtotime(Time::weekStart(substr($p['creado_en'], 0, 10))), 7 * 86400);
            $k = (($semanas % count($emps)) + count($emps)) % count($emps);
            foreach ($emps as $i => $e) $mapa[$e] = $emps[($i + $k) % count($emps)];
        }

        if ($reemplazar) $this->repo->deleteRango($desde, Time::addDays($desde, 6));
        foreach ($p['patron'] as $t) {
            $emp = $mapa[$t['empleado_id']] ?? $t['empleado_id'];
            if (!isset($activos[$emp])) continue;
            $this->repo->create([
                'empleado_id' => $emp, 'fecha' => Time::addDays($desde, (int)$t['dia']),
                'hora_inicio' => $t['hora_inicio'], 'hora_fin' => $t['hora_fin'], 'tipo' => $t['tipo'], 'publicado' => 0,
            ]);
        }
        Audit::log('plantilla_aplicada', 'personal', 'plantilla', $id, "Plantilla «{$p['nombre']}» aplicada a la semana del $desde");
        return $this->semana($desde);
    }

    public function borrarPlantilla(int $id): void
    {
        $this->repo->borrarPlantilla($id);
    }

    private function assertEmpleado(int $id): void
    {
        $e = (new EmpleadoRepository())->find($id);
        if (!$e || $e['estado'] === 'baja') throw HttpException::invalid('El empleado no existe o está dado de baja.');
    }
}
