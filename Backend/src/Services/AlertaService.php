<?php
namespace App\Services;

use App\Repositories\AsistenciaRepository;
use App\Repositories\EmpleadoRepository;
use App\Repositories\ProductoRepository;
use App\Repositories\SolicitudRepository;
use App\Utils\Config;
use App\Utils\Time;

/** Alertas del equipo y operación para el administrador. */
class AlertaService
{
    public function todas(): array
    {
        $out = [];
        $emp = new EmpleadoRepository();

        foreach ($emp->documentosPorVencer((int)Config::setting('alerta_doc_dias', 30)) as $d) {
            $dias = (int)$d['dias'];
            $out[] = [
                'tipo' => 'documento', 'nivel' => $dias < 0 ? 'alta' : ($dias <= 7 ? 'media' : 'baja'),
                'titulo' => $dias < 0 ? 'Documento vencido' : 'Documento por vencer',
                'mensaje' => "{$d['nombre']} de {$d['empleado']} " . ($dias < 0 ? 'venció hace ' . abs($dias) . ' días' : ($dias === 0 ? 'vence hoy' : "vence en $dias días")),
                'empleado_id' => (int)$d['empleado_id'], 'ruta' => "/personal/{$d['empleado_id']}",
            ];
        }
        foreach ($emp->cumpleanos(7) as $c) {
            $dias = (int)$c['dias'];
            $out[] = [
                'tipo' => 'cumpleanos', 'nivel' => 'info',
                'titulo' => $dias === 0 ? '¡Cumpleaños hoy!' : 'Cumpleaños próximo',
                'mensaje' => $c['empleado'] . ($dias === 0 ? ' cumple años hoy 🎂' : ($dias === 1 ? ' cumple mañana' : " cumple en $dias días")),
                'empleado_id' => (int)$c['id'], 'ruta' => "/personal/{$c['id']}",
            ];
        }
        foreach ((new AsistenciaRepository())->sinSalida() as $s) {
            $out[] = [
                'tipo' => 'sin_salida', 'nivel' => 'media',
                'titulo' => 'No marcó salida',
                'mensaje' => "{$s['empleado']} marcó entrada el " . date('d/m H:i', strtotime($s['marcado_en'])) . ' y no registró salida',
                'empleado_id' => (int)$s['empleado_id'], 'ruta' => '/asistencia?fecha=' . substr($s['marcado_en'], 0, 10),
            ];
        }
        // Falta de personal: franjas de hoy y mañana con menos gente de la mínima
        $sem = (new TurnoService())->semana(Time::today());
        $sig = (new TurnoService())->semana(Time::addDays(Time::today(), 7));
        foreach (array_merge($sem['cobertura'], $sig['cobertura']) as $dia) {
            if ($dia['fecha'] < Time::today() || $dia['fecha'] > Time::addDays(Time::today(), 1) || !$dia['deficit']) continue;
            $franjas = array_values(array_filter($dia['horas'], fn($h) => $h['falta']));
            $out[] = [
                'tipo' => 'falta_personal', 'nivel' => 'media',
                'titulo' => 'Falta personal ' . ($dia['fecha'] === Time::today() ? 'hoy' : 'mañana'),
                'mensaje' => count($franjas) . ' hora(s) con menos de ' . $sem['config']['cobertura_minima'] . ' personas (desde las ' . sprintf('%02d:00', $franjas[0]['hora']) . ')',
                'ruta' => '/turnos?semana=' . $dia['fecha'],
            ];
        }
        $n = (new SolicitudRepository())->pendientesCount();
        if ($n) $out[] = ['tipo' => 'solicitud', 'nivel' => 'baja', 'titulo' => 'Solicitudes pendientes', 'mensaje' => "$n solicitud(es) esperan tu aprobación", 'ruta' => '/solicitudes'];
        foreach ((new ProductoRepository())->stockBajo() as $p) {
            $out[] = [
                'tipo' => 'stock_bajo', 'nivel' => (int)$p['stock'] <= 0 ? 'alta' : 'media',
                'titulo' => (int)$p['stock'] <= 0 ? 'Sin stock' : 'Stock bajo',
                'mensaje' => "{$p['nombre']}: quedan {$p['stock']} (mínimo {$p['stock_minimo']})", 'ruta' => '/inventario',
            ];
        }
        $orden = ['alta' => 0, 'media' => 1, 'baja' => 2, 'info' => 3];
        usort($out, fn($a, $b) => $orden[$a['nivel']] <=> $orden[$b['nivel']]);
        return $out;
    }
}
