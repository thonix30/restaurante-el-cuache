<?php
namespace App\Services;

use App\Core\HttpException;
use App\Repositories\AsistenciaRepository;
use App\Repositories\EmpleadoRepository;
use App\Repositories\SolicitudRepository;
use App\Repositories\TurnoRepository;
use App\Utils\Audit;
use App\Utils\Auth;
use App\Utils\Config;
use App\Utils\Storage;
use App\Utils\Time;
use App\Utils\Url;

/**
 * Asistencia: kiosco de marcación, cálculo de estados por día y correcciones.
 *
 * Estados de un día (puede haber varios a la vez):
 *  programado · sin_entrada · falta · a_tiempo · tarde · salida_anticipada ·
 *  horas_extra · en_turno · en_descanso · sin_salida · sin_turno ·
 *  vacaciones · permiso · justificada
 */
class AsistenciaService
{
    public const ACCIONES = [
        'entrada' => 'Entrada',
        'inicio_descanso' => 'Inicio de descanso',
        'fin_descanso' => 'Fin de descanso',
        'salida' => 'Salida',
    ];

    private AsistenciaRepository $repo;

    public function __construct()
    {
        $this->repo = new AsistenciaRepository();
    }

    // ═════════════════════════ Kiosco ═════════════════════════

    public function identificar(string $pin): array
    {
        $emp = $this->empleadoPorPin($pin);
        return $this->estadoMarcacion($emp);
    }

    public function marcarConPin(string $pin, string $tipo, ?string $foto): array
    {
        $emp = $this->empleadoPorPin($pin);
        return $this->marcar($emp, $tipo, $foto, 'kiosco');
    }

    private function empleadoPorPin(string $pin): array
    {
        $throttle = new ThrottleService('pin');
        $throttle->assertNotLocked();
        if (!preg_match('/^\d{4}$/', $pin)) throw HttpException::invalid('El PIN tiene 4 dígitos.');
        $emp = (new EmpleadoRepository())->findByPinHash(Auth::pinHash($pin));
        $throttle->hit((bool)$emp, 'PIN');
        if (!$emp) {
            $s = $throttle->retryAfter();
            if ($s > 0) throw new HttpException("Demasiados intentos. Espera $s segundos.", 429, ['retry_after' => $s]);
            throw new HttpException('PIN incorrecto.', 401, ['intentos_restantes' => $throttle->intentosRestantes()]);
        }
        return $emp;
    }

    /** Qué puede marcar ahora el empleado según su última marcación. */
    public function estadoMarcacion(array $emp): array
    {
        $ultima = $this->repo->ultima((int)$emp['id']);
        $permitidas = match ($ultima['tipo'] ?? null) {
            'entrada', 'fin_descanso' => ['inicio_descanso', 'salida'],
            'inicio_descanso' => ['fin_descanso', 'salida'],
            default => ['entrada'],
        };
        $hoy = Time::today();
        $turnos = (new TurnoRepository())->rango($hoy, $hoy, (int)$emp['id']);
        return [
            'empleado' => [
                'id' => (int)$emp['id'],
                'nombre' => $emp['nombre'],
                'nombre_completo' => trim($emp['nombre'] . ' ' . $emp['apellido']),
                'puesto' => $emp['puesto'],
                'foto' => Url::asset($emp['foto']),
            ],
            'turno_hoy' => $turnos ? ['hora_inicio' => $turnos[0]['hora_inicio'], 'hora_fin' => end($turnos)['hora_fin'], 'tipo' => $turnos[0]['tipo']] : null,
            'ultima' => $ultima ? ['tipo' => $ultima['tipo'], 'marcado_en' => $ultima['marcado_en']] : null,
            'permitidas' => $permitidas,
        ];
    }

    public function marcar(array $emp, string $tipo, ?string $foto, string $origen): array
    {
        if (!isset(self::ACCIONES[$tipo])) throw HttpException::invalid('Acción inválida.');
        $estado = $this->estadoMarcacion($emp);
        if (!in_array($tipo, $estado['permitidas'], true)) {
            $msg = match ($tipo) {
                'entrada' => 'Ya marcaste tu entrada.',
                'salida' => 'Primero marca tu entrada.',
                'inicio_descanso' => 'Primero marca tu entrada.',
                'fin_descanso' => 'No tienes un descanso abierto.',
            };
            throw HttpException::conflict($msg);
        }
        $fotoPath = $foto ? Storage::saveDataUrlImage($foto, 'storage/asistencia') : null;
        $ahora = Time::now();
        $id = $this->repo->create([
            'empleado_id' => (int)$emp['id'], 'tipo' => $tipo, 'marcado_en' => $ahora, 'origen' => $origen, 'foto' => $fotoPath,
        ]);

        $detalle = null;
        if ($tipo === 'entrada' && $estado['turno_hoy']) {
            $inicio = strtotime(Time::today() . ' ' . $estado['turno_hoy']['hora_inicio']);
            $tarde = intdiv(time() - $inicio, 60);
            $tol = (int)Config::setting('tolerancia_minutos', 5);
            $detalle = $tarde > $tol ? "Llegaste $tarde min tarde" : '¡A tiempo!';
        }
        return [
            'id' => $id,
            'tipo' => $tipo,
            'accion' => self::ACCIONES[$tipo],
            'marcado_en' => $ahora,
            'empleado' => $estado['empleado'],
            'turno_hoy' => $estado['turno_hoy'],
            'detalle' => $detalle,
        ];
    }

    // ═════════════════════════ Cálculo ═════════════════════════

    /**
     * Resumen por empleado y día en un rango: [empleado_id][fecha] => resumen.
     * Incluye días con turno programado y días con marcaciones.
     */
    public function calcular(string $desde, string $hasta, ?int $empleadoId = null): array
    {
        $turnos = (new TurnoRepository())->rango($desde, $hasta, $empleadoId);
        $marcas = $this->repo->marcaciones("$desde 00:00:00", Time::addDays($hasta, 1) . ' 12:00:00', $empleadoId);
        $ausencias = (new SolicitudRepository())->ausencias($desde, $hasta);
        $justif = [];
        foreach ($this->repo->justificaciones($desde, $hasta, $empleadoId) as $j) $justif[(int)$j['empleado_id']][$j['fecha']][$j['tipo']] = $j;

        $turnosPor = [];
        foreach ($turnos as $t) $turnosPor[$t['empleado_id']][$t['fecha']][] = $t;
        $marcasPor = [];
        foreach ($marcas as $m) $marcasPor[(int)$m['empleado_id']][] = $m;

        $empleados = array_unique(array_merge(array_keys($turnosPor), array_keys($marcasPor)));
        $out = [];
        foreach ($empleados as $eid) {
            $dias = [];
            foreach (array_keys($turnosPor[$eid] ?? []) as $d) $dias[$d] = true;
            foreach ($marcasPor[$eid] ?? [] as $m) {
                $d = substr($m['marcado_en'], 0, 10);
                if ($m['tipo'] === 'entrada' && $d >= $desde && $d <= $hasta) $dias[$d] = true;
            }
            foreach (array_keys($dias) as $fecha) {
                $out[$eid][$fecha] = $this->resumenDia(
                    $eid, $fecha, $turnosPor[$eid][$fecha] ?? [], $marcasPor[$eid] ?? [],
                    $ausencias[$eid][$fecha] ?? null, $justif[$eid][$fecha] ?? []
                );
            }
            ksort($out[$eid]);
        }
        return $out;
    }

    public function resumenDia(int $eid, string $fecha, array $turnos, array $marcas, ?string $ausencia, array $justif): array
    {
        $now = time();
        $hoy = Time::today();
        $tol = (int)Config::setting('tolerancia_minutos', 5);

        // Turno programado (puede haber más de uno en el día)
        $ini = null; $fin = null; $prog = 0;
        foreach ($turnos as $t) {
            [$a, $b] = Time::shiftRange($fecha, $t['hora_inicio'], $t['hora_fin']);
            $ini = $ini === null ? $a : min($ini, $a);
            $fin = $fin === null ? $b : max($fin, $b);
            $prog += intdiv($b - $a, 60);
        }

        // Secuencia de marcas de este día: desde la primera entrada del día hasta la siguiente entrada
        $seq = [];
        $iniciado = false;
        foreach ($marcas as $m) {
            $d = substr($m['marcado_en'], 0, 10);
            if (!$iniciado) {
                if ($m['tipo'] === 'entrada' && $d === $fecha) { $iniciado = true; $seq[] = $m; }
                continue;
            }
            if ($m['tipo'] === 'entrada') break;
            $seq[] = $m;
            if ($m['tipo'] === 'salida') break;
        }

        $entrada = $seq ? strtotime($seq[0]['marcado_en']) : null;
        $salida = null; $descansos = []; $abierto = null; $descansoMin = 0;
        foreach ($seq as $m) {
            $ts = strtotime($m['marcado_en']);
            if ($m['tipo'] === 'inicio_descanso') $abierto = $ts;
            if ($m['tipo'] === 'fin_descanso' && $abierto) { $descansos[] = [$abierto, $ts]; $descansoMin += intdiv($ts - $abierto, 60); $abierto = null; }
            if ($m['tipo'] === 'salida') $salida = $ts;
        }
        $ultimaTipo = $seq ? end($seq)['tipo'] : null;

        $estados = [];
        $tardeMin = 0; $antMin = 0; $extraMin = 0; $trab = 0;

        if (!$entrada) {
            if ($ausencia) $estados[] = $ausencia;
            elseif ($turnos) {
                if ($now < $ini) $estados[] = 'programado';
                elseif ($now <= $fin) $estados[] = 'sin_entrada';
                else $estados[] = 'falta';
            }
        } else {
            $hasta = $salida ?? ($abierto ?: null);
            $enCurso = !$salida && $fecha >= Time::addDays($hoy, -1) && ($fin ? $now < $fin + 3600 : $now - $entrada < 14 * 3600);
            if (!$salida) $hasta = $enCurso ? $now : ($fin ?? $entrada);
            if ($abierto && $enCurso) $descansoMin += intdiv($now - $abierto, 60);
            $trab = max(0, intdiv($hasta - $entrada, 60) - $descansoMin);

            if ($turnos) {
                $tardeMin = max(0, intdiv($entrada - $ini, 60));
                $estados[] = $tardeMin > $tol ? 'tarde' : 'a_tiempo';
                if ($salida && $salida < $fin - $tol * 60) { $antMin = intdiv($fin - $salida, 60); $estados[] = 'salida_anticipada'; }
                $extraMin = max(0, $trab - $prog);
                if ($extraMin >= 15 && ($salida || !$enCurso)) $estados[] = 'horas_extra'; else $extraMin = $extraMin >= 15 ? $extraMin : 0;
            } else {
                $estados[] = 'sin_turno';
                $extraMin = $trab;
            }
            if (!$salida) $estados[] = $enCurso ? ($ultimaTipo === 'inicio_descanso' ? 'en_descanso' : 'en_turno') : 'sin_salida';
        }

        $justificada = false;
        foreach (['falta' => 'falta', 'tarde' => 'tardanza', 'salida_anticipada' => 'salida_anticipada'] as $estado => $tipoJ) {
            if (in_array($estado, $estados, true) && isset($justif[$tipoJ])) $justificada = true;
        }
        if ($justificada) $estados[] = 'justificada';

        $fmt = fn($ts) => $ts ? date('Y-m-d H:i:s', $ts) : null;
        return [
            'empleado_id' => $eid,
            'fecha' => $fecha,
            'turno' => $turnos ? ['inicio' => $fmt($ini), 'fin' => $fmt($fin), 'minutos' => $prog, 'tipo' => $turnos[0]['tipo'], 'ids' => array_column($turnos, 'id')] : null,
            'entrada' => $fmt($entrada),
            'salida' => $fmt($salida),
            'descansos' => array_map(fn($d) => ['inicio' => $fmt($d[0]), 'fin' => $fmt($d[1])], $descansos),
            'descanso_min' => $descansoMin,
            'trabajado_min' => $trab,
            'programado_min' => $prog,
            'tarde_min' => $tardeMin,
            'anticipada_min' => $antMin,
            'extra_min' => $extraMin,
            'estados' => $estados,
            'estado' => $estados[0] ?? 'libre',
            'justificaciones' => array_values(array_map(fn($j) => ['id' => (int)$j['id'], 'tipo' => $j['tipo'], 'motivo' => $j['motivo'], 'tiene_archivo' => (bool)$j['tiene_archivo']], $justif)),
            'marcaciones' => array_map(fn($m) => [
                'id' => (int)$m['id'], 'tipo' => $m['tipo'], 'marcado_en' => $m['marcado_en'],
                'origen' => $m['origen'], 'tiene_foto' => (bool)$m['tiene_foto'], 'motivo' => $m['motivo'],
            ], $seq),
        ];
    }

    /** Tabla "Asistencia del día". */
    public function dia(string $fecha): array
    {
        $calc = $this->calcular($fecha, $fecha);
        $empleados = (new EmpleadoRepository())->activos();
        $filas = [];
        foreach ($empleados as $e) {
            $r = $calc[(int)$e['id']][$fecha] ?? null;
            if (!$r && $e['estado'] !== 'activo') continue;
            $filas[] = [
                'empleado' => ['id' => (int)$e['id'], 'nombre' => trim($e['nombre'] . ' ' . $e['apellido']), 'puesto' => $e['puesto'], 'foto' => Url::asset($e['foto']), 'estado' => $e['estado']],
                'resumen' => $r,
            ];
        }
        // Primero quienes tenían turno o marcaron
        usort($filas, fn($a, $b) => ($b['resumen'] ? 1 : 0) <=> ($a['resumen'] ? 1 : 0)
            ?: strcmp($a['resumen']['turno']['inicio'] ?? 'z', $b['resumen']['turno']['inicio'] ?? 'z'));

        $count = fn($estado) => count(array_filter($filas, fn($f) => $f['resumen'] && in_array($estado, $f['resumen']['estados'], true)));
        return [
            'fecha' => $fecha,
            'filas' => $filas,
            'resumen' => [
                'programados' => count(array_filter($filas, fn($f) => $f['resumen']['turno'] ?? null)),
                'a_tiempo' => $count('a_tiempo'),
                'tarde' => $count('tarde'),
                'falta' => $count('falta'),
                'en_turno' => $count('en_turno') + $count('en_descanso'),
                'horas_extra' => $count('horas_extra'),
                'sin_salida' => $count('sin_salida'),
            ],
        ];
    }

    /** Mini calendario mensual de un empleado. */
    public function mes(int $empleadoId, string $mes): array
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = date('Y-m');
        $desde = "$mes-01";
        $hasta = date('Y-m-t', strtotime($desde));
        $calc = $this->calcular($desde, $hasta, $empleadoId)[$empleadoId] ?? [];
        $dias = [];
        foreach (Time::dates($desde, $hasta) as $d) {
            $r = $calc[$d] ?? null;
            $dias[] = ['fecha' => $d, 'estado' => $r['estado'] ?? 'libre', 'estados' => $r['estados'] ?? [], 'trabajado_min' => $r['trabajado_min'] ?? 0];
        }
        $vals = array_filter($calc);
        return [
            'mes' => $mes,
            'dias' => $dias,
            'totales' => [
                'trabajado_min' => array_sum(array_column($vals, 'trabajado_min')),
                'extra_min' => array_sum(array_column($vals, 'extra_min')),
                'tardanzas' => count(array_filter($vals, fn($r) => in_array('tarde', $r['estados'], true))),
                'faltas' => count(array_filter($vals, fn($r) => in_array('falta', $r['estados'], true) && !in_array('justificada', $r['estados'], true))),
            ],
        ];
    }

    /**
     * Corrección manual del admin: reemplaza las marcaciones del día por las
     * horas indicadas. Motivo obligatorio y queda en la auditoría (antes/después).
     */
    public function corregir(int $empleadoId, string $fecha, ?string $entrada, ?string $salida, ?string $dIni, ?string $dFin, string $motivo): array
    {
        if (trim($motivo) === '') throw HttpException::invalid('El motivo de la corrección es obligatorio.', ['errors' => ['motivo' => 'Obligatorio.']]);
        $emp = (new EmpleadoRepository())->find($empleadoId);
        if (!$emp) throw HttpException::notFound('El empleado no existe.');
        $hm = fn($v) => $v && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : null;
        $entrada = $hm($entrada); $salida = $hm($salida); $dIni = $hm($dIni); $dFin = $hm($dFin);
        if (!$entrada) throw HttpException::invalid('Indica la hora de entrada.', ['errors' => ['entrada' => 'Obligatoria.']]);

        $antes = $this->calcular($fecha, $fecha, $empleadoId)[$empleadoId][$fecha] ?? null;
        $idsPrevios = array_column($antes['marcaciones'] ?? [], 'id');

        $base = strtotime("$fecha $entrada");
        $ts = function (?string $h) use ($fecha, $base) {
            if (!$h) return null;
            $t = strtotime("$fecha $h");
            return $t < $base ? $t + 86400 : $t; // después de medianoche
        };
        $nuevas = [['entrada', $base]];
        if ($dIni && $dFin) { $nuevas[] = ['inicio_descanso', $ts($dIni)]; $nuevas[] = ['fin_descanso', $ts($dFin)]; }
        if ($salida) $nuevas[] = ['salida', $ts($salida)];
        for ($i = 1; $i < count($nuevas); $i++) {
            if ($nuevas[$i][1] < $nuevas[$i - 1][1]) throw HttpException::invalid('Las horas no siguen un orden lógico (entrada → descanso → salida).');
        }

        $this->repo->deleteIds($idsPrevios);
        foreach ($nuevas as [$tipo, $t]) {
            $this->repo->create([
                'empleado_id' => $empleadoId, 'tipo' => $tipo, 'marcado_en' => date('Y-m-d H:i:s', $t),
                'origen' => 'correccion', 'corregido_por' => Auth::id(), 'motivo' => mb_substr($motivo, 0, 255),
            ]);
        }
        $nombre = trim($emp['nombre'] . ' ' . $emp['apellido']);
        $h = fn($dt) => $dt ? substr($dt, 11, 5) : '—';
        Audit::log('asistencia_corregida', 'personal', 'empleado', $empleadoId,
            "Asistencia de $nombre del $fecha: " . $h($antes['entrada'] ?? null) . '–' . $h($antes['salida'] ?? null) . " → $entrada–" . ($salida ?? '—') . " · $motivo",
            ['fecha' => $fecha, 'antes' => ['entrada' => $antes['entrada'] ?? null, 'salida' => $antes['salida'] ?? null], 'despues' => compact('entrada', 'salida', 'dIni', 'dFin'), 'motivo' => $motivo]);

        return $this->calcular($fecha, $fecha, $empleadoId)[$empleadoId][$fecha];
    }

    public function justificar(int $empleadoId, string $fecha, string $tipo, string $motivo, ?array $archivo): array
    {
        if (!in_array($tipo, ['falta', 'tardanza', 'salida_anticipada'], true)) throw HttpException::invalid('Tipo inválido.');
        if (trim($motivo) === '') throw HttpException::invalid('Explica la justificación.', ['errors' => ['motivo' => 'Obligatorio.']]);
        $emp = (new EmpleadoRepository())->find($empleadoId);
        if (!$emp) throw HttpException::notFound('El empleado no existe.');
        $path = $archivo ? Storage::saveUploadedDocument($archivo, 'documentos')['path'] : null;
        $this->repo->justificar([
            'empleado_id' => $empleadoId, 'fecha' => $fecha, 'tipo' => $tipo,
            'motivo' => mb_substr(trim($motivo), 0, 255), 'archivo' => $path, 'registrado_por' => Auth::id(),
        ]);
        Audit::log('asistencia_justificada', 'personal', 'empleado', $empleadoId,
            trim($emp['nombre'] . ' ' . $emp['apellido']) . ": $tipo del $fecha justificada · $motivo");
        return $this->calcular($fecha, $fecha, $empleadoId)[$empleadoId][$fecha] ?? [];
    }

    public function archivoJustificacion(int $id): array
    {
        $j = $this->repo->justificacion($id);
        if (!$j || !$j['archivo']) throw HttpException::notFound('No hay archivo adjunto.');
        return ['path' => Storage::absolute($j['archivo']), 'nombre' => 'justificacion-' . $j['fecha'] . '.' . pathinfo($j['archivo'], PATHINFO_EXTENSION)];
    }

    public function fotoMarcacion(int $id): array
    {
        $m = $this->repo->find($id);
        if (!$m || !$m['foto']) throw HttpException::notFound('La marcación no tiene foto.');
        return ['path' => Storage::absolute($m['foto']), 'nombre' => 'marcacion-' . $id . '.' . pathinfo($m['foto'], PATHINFO_EXTENSION)];
    }
}
