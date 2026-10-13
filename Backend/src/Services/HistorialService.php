<?php
namespace App\Services;

use App\Repositories\AuditoriaRepository;
use App\Repositories\CuentaRepository;
use App\Utils\Time;

class HistorialService
{
    public function cuentas(array $q): array
    {
        [$desde, $hasta] = Time::period($q['desde'] ?? null, $q['hasta'] ?? null);
        $limit = max(10, min(200, (int)($q['limit'] ?? 50)));
        $page = max(1, (int)($q['page'] ?? 1));
        $f = [
            'desde' => $desde, 'hasta' => $hasta,
            'estado' => in_array($q['estado'] ?? '', ['pagada', 'cancelada'], true) ? $q['estado'] : null,
            'mesa' => $q['mesa'] ?? null,
            'mesero_id' => $q['mesero_id'] ?? null,
            'q' => $q['q'] ?? null,
        ];
        [$rows, $tot] = (new CuentaRepository())->historial($f, $limit, ($page - 1) * $limit);
        return [
            'desde' => $desde, 'hasta' => $hasta, 'page' => $page, 'limit' => $limit,
            'total' => (int)$tot['n'],
            'resumen' => [
                'ventas' => round((float)$tot['ventas'], 2),
                'propinas' => round((float)$tot['propinas'], 2),
                'canceladas' => (int)$tot['canceladas'],
            ],
            'cuentas' => array_map(fn($r) => [
                'id' => (int)$r['id'],
                'estado' => $r['estado'],
                'mesa_numero' => $r['mesa_numero'] !== null ? (int)$r['mesa_numero'] : null,
                'mesero' => $r['mesero'],
                'personas' => (int)$r['personas'],
                'platillos' => (int)$r['platillos'],
                'subtotal' => (float)$r['subtotal'],
                'descuento' => (float)$r['descuento'],
                'total' => round((float)$r['subtotal'] - (float)$r['descuento'], 2),
                'propina' => (float)$r['propina'],
                'metodos' => $r['metodos'] ? explode(',', $r['metodos']) : [],
                'dividida' => (int)$r['num_pagos'] > 1,
                'abierta_en' => $r['abierta_en'],
                'cerrada_en' => $r['cerrada_en'],
                'motivo_cancelacion' => $r['motivo_cancelacion'],
            ], $rows),
        ];
    }

    public function auditoria(array $q): array
    {
        [$desde, $hasta] = Time::period($q['desde'] ?? null, $q['hasta'] ?? null, Time::addDays(Time::today(), -6));
        $limit = max(10, min(200, (int)($q['limit'] ?? 50)));
        $page = max(1, (int)($q['page'] ?? 1));
        $repo = new AuditoriaRepository();
        [$rows, $total, $porCat] = $repo->list([
            'desde' => $desde, 'hasta' => $hasta,
            'categoria' => $q['categoria'] ?? null,
            'accion' => $q['accion'] ?? null,
            'usuario_id' => $q['usuario_id'] ?? null,
            'q' => $q['q'] ?? null,
        ], $limit, ($page - 1) * $limit);
        return [
            'desde' => $desde, 'hasta' => $hasta, 'page' => $page, 'limit' => $limit, 'total' => $total,
            'eventos' => $rows,
            'por_categoria' => array_column($porCat, 'n', 'categoria'),
            'acciones' => $repo->acciones(),
            'usuarios' => $repo->usuarios(),
        ];
    }
}
