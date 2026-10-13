<?php
namespace App\Services;

use App\Repositories\CompraRepository;
use App\Repositories\EstadisticaRepository;
use App\Utils\Time;

class EstadisticaService
{
    private EstadisticaRepository $repo;

    public function __construct()
    {
        $this->repo = new EstadisticaRepository();
    }

    /** Dashboard del periodo con comparativa contra el periodo anterior de igual duración. */
    public function dashboard(?string $desde, ?string $hasta): array
    {
        [$desde, $hasta] = Time::period($desde, $hasta, Time::addDays(Time::today(), -6));
        $dias = Time::daysBetween($desde, $hasta);
        $prevHasta = Time::addDays($desde, -1);
        $prevDesde = Time::addDays($prevHasta, -($dias - 1));

        $kpis = $this->repo->kpis($desde, $hasta);
        $prev = $this->repo->kpis($prevDesde, $prevHasta);
        $cambio = [];
        foreach (['ventas', 'cuentas', 'ticket_promedio', 'propinas', 'utilidad'] as $k) {
            $cambio[$k] = $prev[$k] > 0 ? round(($kpis[$k] - $prev[$k]) / $prev[$k] * 100, 1) : null;
        }

        $porHora = $dias === 1;
        $serie = $this->rellenarSerie($this->repo->serie($desde, $hasta, $porHora), $desde, $hasta, $porHora);
        $seriePrev = $this->rellenarSerie($this->repo->serie($prevDesde, $prevHasta, $porHora), $prevDesde, $prevHasta, $porHora);

        $kpis['compras'] = round((new CompraRepository())->totalPeriodo($desde, $hasta), 2);

        return [
            'desde' => $desde, 'hasta' => $hasta,
            'anterior' => ['desde' => $prevDesde, 'hasta' => $prevHasta, 'kpis' => $prev],
            'kpis' => $kpis,
            'cambio' => $cambio,
            'agrupado' => $porHora ? 'hora' : 'dia',
            'serie' => $serie,
            'serie_anterior' => array_column($seriePrev, 'ventas'),
            'top_platillos' => array_map(fn($r) => [
                'nombre' => $r['nombre'], 'unidades' => (int)$r['unidades'],
                'ventas' => round((float)$r['ventas'], 2), 'utilidad' => round((float)$r['utilidad'], 2),
            ], $this->repo->topPlatillos($desde, $hasta)),
            'categorias' => array_map(fn($r) => [
                'categoria' => $r['categoria'], 'ventas' => round((float)$r['ventas'], 2), 'unidades' => (int)$r['unidades'],
            ], $this->repo->porCategoria($desde, $hasta)),
            'meseros' => array_map(fn($r) => [
                'mesero' => $r['mesero'], 'cuentas' => (int)$r['cuentas'],
                'ventas' => round((float)$r['ventas'], 2), 'propinas' => round((float)$r['propinas'], 2),
                'ticket' => $r['cuentas'] ? round((float)$r['ventas'] / (int)$r['cuentas'], 2) : 0,
            ], $this->repo->porMesero($desde, $hasta)),
            'metodos' => array_map(fn($r) => ['metodo' => $r['metodo'], 'n' => (int)$r['n'], 'monto' => round((float)$r['monto'], 2)], $this->repo->porMetodo($desde, $hasta)),
        ];
    }

    private function rellenarSerie(array $rows, string $desde, string $hasta, bool $porHora): array
    {
        $map = [];
        foreach ($rows as $r) $map[(string)$r['k']] = $r;
        $out = [];
        if ($porHora) {
            for ($h = 6; $h <= 23; $h++) {
                $r = $map[(string)$h] ?? null;
                $out[] = ['etiqueta' => sprintf('%02d:00', $h), 'clave' => $h, 'ventas' => round((float)($r['ventas'] ?? 0), 2), 'cuentas' => (int)($r['cuentas'] ?? 0)];
            }
        } else {
            foreach (Time::dates($desde, $hasta) as $d) {
                $r = $map[$d] ?? null;
                $out[] = ['etiqueta' => $d, 'clave' => $d, 'ventas' => round((float)($r['ventas'] ?? 0), 2), 'cuentas' => (int)($r['cuentas'] ?? 0)];
            }
        }
        return $out;
    }
}
