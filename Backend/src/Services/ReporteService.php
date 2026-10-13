<?php
namespace App\Services;

use App\Repositories\AsistenciaRepository;
use App\Repositories\EmpleadoRepository;
use App\Repositories\PagoRepository;
use App\Utils\Time;
use App\Utils\Url;

/** Reporte de horas, puntualidad, propinas repartidas y pago estimado. */
class ReporteService
{
    public function horas(?string $desde, ?string $hasta): array
    {
        [$desde, $hasta] = Time::period($desde, $hasta, Time::weekStart(Time::today()));
        $asis = (new AsistenciaService())->calcular($desde, $hasta);
        $propinas = $this->propinasPorEmpleado($desde, $hasta);
        $diasPeriodo = Time::daysBetween($desde, $hasta);

        $filas = [];
        foreach ((new EmpleadoRepository())->list(['incluir_baja' => true]) as $e) {
            $eid = (int)$e['id'];
            $dias = $asis[$eid] ?? [];
            if (!$dias && $e['estado'] === 'baja') continue;
            $trab = 0; $extra = 0; $faltas = 0; $tard = 0; $aTiempo = 0; $conTurno = 0;
            foreach ($dias as $r) {
                $trab += $r['trabajado_min'];
                $extra += $r['extra_min'];
                $just = in_array('justificada', $r['estados'], true);
                if (in_array('falta', $r['estados'], true) && !$just) $faltas++;
                if (in_array('tarde', $r['estados'], true)) $tard++;
                if (in_array('a_tiempo', $r['estados'], true)) $aTiempo++;
                if ($r['turno'] && $r['entrada']) $conTurno++;
            }
            $normales = max(0, $trab - $extra);
            $prop = round($propinas[$eid] ?? 0, 2);

            if ($e['tipo_pago'] === 'Por hora') {
                $tarifa = (float)$e['tarifa_hora'];
                $base = round($normales / 60 * $tarifa, 2);
                $pagoExtra = round($extra / 60 * $tarifa * 1.5, 2);
            } else {
                $mensual = (float)$e['salario'];
                $base = round($mensual / 30 * $diasPeriodo, 2);
                $pagoExtra = round($extra / 60 * ($mensual / 240) * 1.5, 2); // 240 h/mes de referencia
            }
            $filas[] = [
                'empleado' => ['id' => $eid, 'nombre' => trim($e['nombre'] . ' ' . $e['apellido']), 'puesto' => $e['puesto'], 'foto' => Url::asset($e['foto']), 'tipo_pago' => $e['tipo_pago']],
                'dias_trabajados' => count(array_filter($dias, fn($r) => $r['entrada'])),
                'horas_trabajadas' => round($trab / 60, 2),
                'horas_normales' => round($normales / 60, 2),
                'horas_extra' => round($extra / 60, 2),
                'faltas' => $faltas,
                'tardanzas' => $tard,
                'puntualidad' => $conTurno ? round($aTiempo / $conTurno * 100) : null,
                'propinas' => $prop,
                'pago_base' => $base,
                'pago_extra' => $pagoExtra,
                'pago_estimado' => round($base + $pagoExtra + $prop, 2),
            ];
        }

        // Puntualidad del equipo por día
        $serie = [];
        foreach (Time::dates($desde, $hasta) as $d) {
            $a = 0; $t = 0; $f = 0;
            foreach ($asis as $dias) {
                if (!isset($dias[$d])) continue;
                $est = $dias[$d]['estados'];
                if (in_array('a_tiempo', $est, true)) $a++;
                if (in_array('tarde', $est, true)) $t++;
                if (in_array('falta', $est, true)) $f++;
            }
            $serie[] = ['fecha' => $d, 'a_tiempo' => $a, 'tarde' => $t, 'falta' => $f, 'puntualidad' => ($a + $t) ? round($a / ($a + $t) * 100) : null];
        }

        $sum = fn($k) => round(array_sum(array_column($filas, $k)), 2);
        return [
            'desde' => $desde, 'hasta' => $hasta,
            'filas' => $filas,
            'totales' => [
                'horas_trabajadas' => $sum('horas_trabajadas'),
                'horas_extra' => $sum('horas_extra'),
                'faltas' => (int)$sum('faltas'),
                'tardanzas' => (int)$sum('tardanzas'),
                'propinas' => $sum('propinas'),
                'pago_estimado' => $sum('pago_estimado'),
            ],
            'puntualidad' => $serie,
        ];
    }

    /**
     * Reparte cada propina entre quienes estaban marcados (en turno) en el
     * momento del cobro. Si nadie había marcado, va al mesero de la cuenta
     * o a quien cobró. Devuelve [empleado_id => monto].
     */
    public function propinasPorEmpleado(string $desde, string $hasta): array
    {
        $pagos = (new PagoRepository())->conPropina($desde, $hasta);
        if (!$pagos) return [];
        $marcas = (new AsistenciaRepository())->marcaciones(Time::addDays($desde, -1) . ' 00:00:00', Time::addDays($hasta, 1) . ' 23:59:59');

        // Intervalos de presencia por empleado
        $intervalos = [];
        $abiertos = [];
        foreach ($marcas as $m) {
            $eid = (int)$m['empleado_id'];
            $ts = strtotime($m['marcado_en']);
            if ($m['tipo'] === 'entrada') $abiertos[$eid] = $ts;
            elseif ($m['tipo'] === 'salida' && isset($abiertos[$eid])) { $intervalos[] = [$eid, $abiertos[$eid], $ts]; unset($abiertos[$eid]); }
        }
        foreach ($abiertos as $eid => $ts) $intervalos[] = [$eid, $ts, min(time(), $ts + 14 * 3600)];

        $res = [];
        foreach ($pagos as $p) {
            $t = strtotime($p['creado_en']);
            $presentes = array_unique(array_map(fn($i) => $i[0], array_filter($intervalos, fn($i) => $i[1] <= $t && $i[2] >= $t)));
            if (!$presentes) {
                $quien = $p['mesero_id'] ?: $p['cobrado_por'];
                if ($quien) $presentes = [(int)$quien];
            }
            if (!$presentes) continue;
            $parte = (float)$p['propina'] / count($presentes);
            foreach ($presentes as $eid) $res[$eid] = ($res[$eid] ?? 0) + $parte;
        }
        return $res;
    }
}
